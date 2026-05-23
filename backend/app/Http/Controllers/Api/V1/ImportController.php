<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewImportRequest;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportJobResource;
use App\Models\ImportJob;
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

        return (new ImportJobResource($job))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ImportJob $importJob)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        abort_unless((int) $importJob->merchant_id === (int) $merchant->id, 404);
        abort_if($company && $importJob->merchant_company_id && (int) $importJob->merchant_company_id !== (int) $company->id, 404);

        return new ImportJobResource($importJob);
    }
}
