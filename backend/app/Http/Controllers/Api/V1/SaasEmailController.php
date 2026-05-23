<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailSetting;
use App\Models\TransactionalEmail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaasEmailController extends Controller
{
    public function showSettings(Request $request): array
    {
        $this->ensureAdmin($request);

        return [
            'data' => $this->serializeSettings(EmailSetting::current()),
        ];
    }

    public function updateSettings(Request $request): array
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'mailer' => ['nullable', 'string', 'max:40'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'clear_password' => ['nullable', 'boolean'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $settings = EmailSetting::current();
        $payload = [
            'mailer' => $data['mailer'] ?? 'smtp',
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? null,
            'username' => $data['username'] ?? null,
            'encryption' => $data['encryption'] ?? null,
            'from_address' => $data['from_address'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];

        if (($data['clear_password'] ?? false) === true) {
            $payload['smtp_password'] = null;
        } elseif (filled($data['smtp_password'] ?? null)) {
            $payload['smtp_password'] = $data['smtp_password'];
        }

        $settings->forceFill($payload)->save();

        return [
            'data' => $this->serializeSettings($settings->fresh() ?? $settings),
        ];
    }

    public function templates(Request $request): array
    {
        $this->ensureAdmin($request);
        TransactionalEmail::ensureDefaults();

        return [
            'data' => TransactionalEmail::query()
                ->orderBy('name')
                ->get()
                ->map(fn (TransactionalEmail $template): array => $this->serializeTemplate($template)),
        ];
    }

    public function storeTemplate(Request $request)
    {
        $this->ensureAdmin($request);

        $data = $this->validateTemplate($request);
        $template = TransactionalEmail::query()->create($data);

        return response()->json([
            'data' => $this->serializeTemplate($template),
        ], 201);
    }

    public function updateTemplate(Request $request, TransactionalEmail $transactionalEmail): array
    {
        $this->ensureAdmin($request);

        $data = $this->validateTemplate($request, $transactionalEmail);
        $transactionalEmail->forceFill($data)->save();

        return [
            'data' => $this->serializeTemplate($transactionalEmail->fresh() ?? $transactionalEmail),
        ];
    }

    private function validateTemplate(Request $request, ?TransactionalEmail $template = null): array
    {
        if (! $template && ! $request->filled('code') && $request->filled('name')) {
            $request->merge([
                'code' => TransactionalEmail::normalizeCode((string) $request->input('name')),
            ]);
        }

        if ($request->has('code')) {
            $request->merge([
                'code' => TransactionalEmail::normalizeCode((string) $request->input('code')),
            ]);
        }

        $required = $template ? 'sometimes' : 'required';

        $data = $request->validate([
            'code' => [
                $required,
                'string',
                'max:80',
                Rule::unique('transactional_emails', 'code')->ignore($template?->id),
            ],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'subject' => [$required, 'string', 'max:255'],
            'body' => [$required, 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $data;
    }

    private function serializeSettings(EmailSetting $settings): array
    {
        return [
            'id' => $settings->id,
            'scope' => $settings->scope,
            'mailer' => $settings->mailer,
            'host' => $settings->host,
            'port' => $settings->port,
            'username' => $settings->username,
            'has_smtp_password' => $settings->hasSmtpPassword(),
            'encryption' => $settings->encryption,
            'from_address' => $settings->from_address,
            'from_name' => $settings->from_name,
            'is_active' => $settings->is_active,
            'updated_at' => $settings->updated_at?->toISOString(),
        ];
    }

    private function serializeTemplate(TransactionalEmail $template): array
    {
        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description,
            'subject' => $template->subject,
            'body' => $template->body,
            'variables' => $template->variables ?: [],
            'is_active' => $template->is_active,
            'updated_at' => $template->updated_at?->toISOString(),
        ];
    }

    private function ensureAdmin(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'support'], true)) {
            throw new HttpException(403, 'Acesso restrito ao painel SaaS.');
        }
    }
}
