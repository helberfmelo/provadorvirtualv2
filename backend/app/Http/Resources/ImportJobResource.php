<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_company_id' => $this->merchant_company_id,
            'type' => $this->type,
            'source_format' => $this->source_format,
            'filename' => $this->filename,
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'imported_rows' => $this->imported_rows,
            'failed_rows' => $this->failed_rows,
            'summary' => $this->summary ?? [],
            'errors' => $this->errors ?? [],
            'metadata' => $this->metadata ?? [],
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
