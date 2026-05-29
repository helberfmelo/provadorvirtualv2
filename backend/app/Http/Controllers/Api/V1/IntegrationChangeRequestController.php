<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntegrationChangeRequest;
use App\Models\MerchantCompany;
use App\Services\Audit\AuditLogger;
use App\Support\ActiveTenant;
use App\Support\PlatformCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IntegrationChangeRequestController extends Controller
{
    public function store(Request $request)
    {
        $merchant = app(ActiveTenant::class)->merchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);

        abort_if(! $company, 404, 'Empresa ativa não encontrada.');

        $data = $request->validate([
            'to_platform' => ['required', 'string', Rule::in(PlatformCatalog::keys())],
            'accepted_terms' => ['accepted'],
        ]);

        if ($company->platform !== 'bigshop' || ! $company->bigshop_discount_active) {
            throw ValidationException::withMessages([
                'to_platform' => ['Esta empresa pode trocar a plataforma diretamente no painel.'],
            ]);
        }

        if ($data['to_platform'] === 'bigshop') {
            throw ValidationException::withMessages([
                'to_platform' => ['Escolha uma plataforma diferente da BigShop para solicitar a troca.'],
            ]);
        }

        $existing = IntegrationChangeRequest::query()
            ->where('merchant_company_id', $company->id)
            ->whereIn('status', [
                IntegrationChangeRequest::STATUS_PENDING,
                IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED,
                IntegrationChangeRequest::STATUS_APPROVED,
            ])
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'data' => $this->serialize($existing->load(['merchant', 'company', 'user'])),
                'message' => 'Já existe uma solicitação de troca em andamento para esta loja.',
            ], 200);
        }

        $changeRequest = IntegrationChangeRequest::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $request->user()?->id,
            'from_platform' => $company->platform,
            'to_platform' => $data['to_platform'],
            'status' => IntegrationChangeRequest::STATUS_PENDING,
            'terms_version' => 'bigshop-change-2026-05-29',
            'terms_accepted_at' => now(),
            'requested_at' => now(),
            'metadata' => [
                'bigshop_discount_active' => (bool) $company->bigshop_discount_active,
                'company_platform' => $company->platform,
            ],
        ]);

        app(AuditLogger::class)->log($request, $merchant, 'integration_change.requested', 'integrations', 'warning', [
            'merchant_company_id' => $company->id,
            'from_platform' => $company->platform,
            'to_platform' => $data['to_platform'],
        ], $changeRequest);

        return response()->json([
            'data' => $this->serialize($changeRequest->load(['merchant', 'company', 'user'])),
        ], 201);
    }

    public function index(Request $request): array
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                IntegrationChangeRequest::STATUS_PENDING,
                IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED,
                IntegrationChangeRequest::STATUS_APPROVED,
                IntegrationChangeRequest::STATUS_COMPLETED,
                IntegrationChangeRequest::STATUS_CANCELLED,
            ])],
            'company_id' => ['nullable', 'integer', 'exists:merchant_companies,id'],
        ]);

        $requests = IntegrationChangeRequest::query()
            ->with(['merchant', 'company', 'user'])
            ->when(filled($data['status'] ?? null), fn ($query) => $query->where('status', $data['status']))
            ->when(filled($data['company_id'] ?? null), fn ($query) => $query->where('merchant_company_id', $data['company_id']))
            ->orderByRaw("case when status in ('pending', 'payment_requested', 'approved') then 0 else 1 end")
            ->latest('id')
            ->limit(80)
            ->get();

        return [
            'data' => $requests->map(fn (IntegrationChangeRequest $changeRequest): array => $this->serialize($changeRequest))->values(),
        ];
    }

    public function update(Request $request, IntegrationChangeRequest $integrationChangeRequest)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                IntegrationChangeRequest::STATUS_PENDING,
                IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED,
                IntegrationChangeRequest::STATUS_APPROVED,
                IntegrationChangeRequest::STATUS_COMPLETED,
                IntegrationChangeRequest::STATUS_CANCELLED,
            ])],
            'payment_link' => ['nullable', 'url', 'max:255'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'apply_change' => ['nullable', 'boolean'],
        ]);

        $status = $data['status'] ?? $integrationChangeRequest->status;

        $integrationChangeRequest->forceFill([
            'status' => $status,
            'payment_link' => array_key_exists('payment_link', $data)
                ? ($data['payment_link'] ?: null)
                : $integrationChangeRequest->payment_link,
            'admin_notes' => array_key_exists('admin_notes', $data)
                ? ($data['admin_notes'] ?: null)
                : $integrationChangeRequest->admin_notes,
            'resolved_at' => in_array($status, [
                IntegrationChangeRequest::STATUS_COMPLETED,
                IntegrationChangeRequest::STATUS_CANCELLED,
            ], true) ? now() : null,
        ])->save();

        if (($data['apply_change'] ?? false) && $status === IntegrationChangeRequest::STATUS_COMPLETED) {
            $this->applyChange($integrationChangeRequest->refresh());
        }

        return response()->json([
            'data' => $this->serialize($integrationChangeRequest->refresh()->load(['merchant', 'company', 'user'])),
        ]);
    }

    private function applyChange(IntegrationChangeRequest $changeRequest): void
    {
        $company = MerchantCompany::query()->findOrFail($changeRequest->merchant_company_id);

        $company->forceFill([
            'platform' => $changeRequest->to_platform,
            'bigshop_discount_active' => false,
        ])->save();
    }

    private function serialize(IntegrationChangeRequest $changeRequest): array
    {
        return [
            'id' => $changeRequest->id,
            'from_platform' => $changeRequest->from_platform,
            'from_platform_label' => $this->platformLabel($changeRequest->from_platform),
            'to_platform' => $changeRequest->to_platform,
            'to_platform_label' => $this->platformLabel($changeRequest->to_platform),
            'status' => $changeRequest->status,
            'status_label' => $this->statusLabel($changeRequest->status),
            'terms_version' => $changeRequest->terms_version,
            'terms_accepted_at' => $changeRequest->terms_accepted_at?->toISOString(),
            'requested_at' => $changeRequest->requested_at?->toISOString(),
            'resolved_at' => $changeRequest->resolved_at?->toISOString(),
            'payment_link' => $changeRequest->payment_link,
            'admin_notes' => $changeRequest->admin_notes,
            'merchant' => [
                'id' => $changeRequest->merchant?->id,
                'name' => $changeRequest->merchant?->name,
                'slug' => $changeRequest->merchant?->slug,
            ],
            'company' => [
                'id' => $changeRequest->company?->id,
                'name' => $changeRequest->company?->name,
                'access_code' => $changeRequest->company?->access_code,
                'domain' => $changeRequest->company?->domain,
                'platform' => $changeRequest->company?->platform,
                'bigshop_discount_active' => (bool) $changeRequest->company?->bigshop_discount_active,
            ],
            'user' => [
                'id' => $changeRequest->user?->id,
                'name' => $changeRequest->user?->name,
                'email' => $changeRequest->user?->email,
            ],
        ];
    }

    private function platformLabel(string $platform): string
    {
        return PlatformCatalog::find($platform)['name'] ?? $platform;
    }

    private function statusLabel(string $status): string
    {
        return [
            IntegrationChangeRequest::STATUS_PENDING => 'Pendente',
            IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED => 'Link enviado',
            IntegrationChangeRequest::STATUS_APPROVED => 'Aprovada',
            IntegrationChangeRequest::STATUS_COMPLETED => 'Concluída',
            IntegrationChangeRequest::STATUS_CANCELLED => 'Cancelada',
        ][$status] ?? $status;
    }

    private function ensureAdmin(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'support'], true)) {
            throw new HttpException(403, 'Acesso restrito ao painel SaaS.');
        }
    }
}
