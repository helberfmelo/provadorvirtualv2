<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewImportRequest;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportJobResource;
use App\Models\ImportJob;
use App\Services\Audit\AuditLogger;
use App\Services\Imports\ImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportController extends Controller
{
    use ResolvesMerchant;

    public function __construct(private readonly ImportService $imports) {}

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        $jobs = ImportJob::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return ImportJobResource::collection($jobs);
    }

    public function preview(PreviewImportRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $activeCompany = $this->currentCompany($request, $merchant);
        $data = $request->validated();
        $company = array_key_exists('merchant_company_id', $data)
            ? $this->merchantCompany($merchant, $data['merchant_company_id'])
            : $activeCompany;

        try {
            return response()->json([
                'data' => $this->imports->preview($merchant, $company, $data),
            ]);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'content' => $exception->getMessage(),
            ]);
        }
    }

    public function store(StoreImportRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $activeCompany = $this->currentCompany($request, $merchant);
        $data = $request->validated();
        $company = array_key_exists('merchant_company_id', $data)
            ? $this->merchantCompany($merchant, $data['merchant_company_id'])
            : $activeCompany;
        try {
            $job = $this->imports->commit($merchant, $company, $data);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'content' => $exception->getMessage(),
            ]);
        }

        app(AuditLogger::class)->log($request, $merchant, 'imports.committed', 'imports', 'info', [
            'merchant_company_id' => $company?->id,
            'module' => 'imports',
            'action' => 'commit',
            'before' => null,
            'after' => [
                'import_job_id' => $job->id,
                'type' => $job->type,
                'source_format' => $job->source_format,
                'status' => $job->status,
                'total_rows' => $job->total_rows,
                'imported_rows' => $job->imported_rows,
                'failed_rows' => $job->failed_rows,
            ],
            'context_data' => [
                'filename' => $job->filename,
                'summary' => $job->summary,
            ],
        ], $job);

        return (new ImportJobResource($job))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ImportJob $importJob)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        $this->assertJobScope($merchant->id, $company?->id, $importJob);

        return new ImportJobResource($importJob);
    }

    public function rollback(Request $request, ImportJob $importJob)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        $this->assertJobScope($merchant->id, $company?->id, $importJob);

        try {
            $job = $this->imports->rollback($merchant, $company, $importJob);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'import_job' => $exception->getMessage(),
            ]);
        }

        app(AuditLogger::class)->log($request, $merchant, 'imports.rolled_back', 'imports', 'warning', [
            'merchant_company_id' => $company?->id,
            'module' => 'imports',
            'action' => 'rollback',
            'before' => [
                'import_job_id' => $importJob->id,
                'status' => $importJob->status,
            ],
            'after' => [
                'import_job_id' => $job->id,
                'status' => $job->status,
            ],
            'context_data' => [
                'type' => $job->type,
                'filename' => $job->filename,
                'batch_id' => $job->metadata['batch_id'] ?? null,
            ],
        ], $job);

        return new ImportJobResource($job);
    }

    private function assertJobScope(int $merchantId, ?int $merchantCompanyId, ImportJob $importJob): void
    {
        abort_unless((int) $importJob->merchant_id === $merchantId, 404);
        abort_if(
            $merchantCompanyId && $importJob->merchant_company_id && (int) $importJob->merchant_company_id !== $merchantCompanyId,
            404
        );
    }
}
