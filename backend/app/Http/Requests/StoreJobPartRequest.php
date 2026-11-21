<?php

namespace App\Http\Requests;

use App\Models\JobPartLine;
use App\Models\ServiceJob;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobPartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $replay = $this->replayLine() !== null;

        return [
            'product_id' => [$replay ? 'nullable' : 'required', 'string', 'exists:products,id'],
            'unit_id' => [$replay ? 'nullable' : 'required', 'string', 'exists:units,id'],
            'quantity' => [$replay ? 'nullable' : 'required', 'numeric', 'gt:0'],
            'unit_price_minor' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function replayLine(): ?JobPartLine
    {
        $key = $this->header('Idempotency-Key');
        $job = $this->route('job');

        if (! $key || ! $job instanceof ServiceJob) {
            return null;
        }

        return JobPartLine::where('job_id', $job->id)
            ->where('idempotency_key', $key)
            ->first();
    }
}
