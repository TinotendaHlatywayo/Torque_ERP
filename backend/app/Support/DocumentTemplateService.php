<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DocumentTemplateService
{
    /**
     * @return array<int, string>
     */
    public function allowedPlaceholders(string $documentType): array
    {
        return match ($documentType) {
            'receipt', 'invoice' => [
                'sale_number', 'receipt_number', 'customer_name', 'currency_code',
                'subtotal', 'discount', 'tax', 'total', 'paid', 'issued_at', 'items',
            ],
            'supplier_bill' => [
                'supplier_name', 'bill_number', 'amount', 'currency_code',
                'bill_date', 'due_date', 'paid', 'status',
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public function requiredPlaceholders(string $documentType): array
    {
        return match ($documentType) {
            'receipt', 'invoice' => ['sale_number', 'total', 'issued_at'],
            'supplier_bill' => ['supplier_name', 'bill_number', 'amount', 'due_date'],
            default => [],
        };
    }

    public function supportedTypes(): array
    {
        return ['receipt', 'invoice', 'supplier_bill'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function validateContent(string $documentType, string $content): void
    {
        if (! in_array($documentType, $this->supportedTypes(), true)) {
            throw new \InvalidArgumentException('Unsupported document type.');
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $content, $matches);

        $placeholders = array_unique($matches[1] ?? []);
        $allowed = $this->allowedPlaceholders($documentType);

        foreach ($placeholders as $placeholder) {
            if (! in_array($placeholder, $allowed, true)) {
                throw new \InvalidArgumentException("Unsupported placeholder: {$placeholder}.");
            }
        }

        foreach ($this->requiredPlaceholders($documentType) as $required) {
            if (! in_array($required, $placeholders, true)) {
                throw new \InvalidArgumentException("Missing required placeholder: {$required}.");
            }
        }
    }

    public function createVersion(Branch $branch, string $documentType, string $name, string $content, User $actor): DocumentTemplate
    {
        $this->validateContent($documentType, $content);

        return DB::transaction(function () use ($branch, $documentType, $name, $content, $actor): DocumentTemplate {
            $latest = DocumentTemplate::where('branch_id', $branch->id)
                ->where('document_type', $documentType)
                ->where('name', $name)
                ->lockForUpdate()
                ->orderByDesc('version')
                ->first();

            $version = $latest ? $latest->version + 1 : 1;

            if ($latest) {
                $latest->update(['is_active' => false]);
            }

            return DocumentTemplate::create([
                'branch_id' => $branch->id,
                'document_type' => $documentType,
                'name' => $name,
                'version' => $version,
                'content' => $content,
                'is_active' => true,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(DocumentTemplate $template, array $data): string
    {
        $this->validateContent($template->document_type, $template->content);

        $rendered = $template->content;

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $rendered = str_replace('{{'.$key.'}}', e((string) $value), $rendered);
            $rendered = str_replace('{{ '.$key.' }}', e((string) $value), $rendered);
        }

        if (isset($data['items']) && is_array($data['items'])) {
            $itemsHtml = '';

            foreach ($data['items'] as $item) {
                $itemsHtml .= '<tr><td>'.e((string) ($item['product_name'] ?? '')).'</td><td>'.e((string) ($item['quantity'] ?? '')).'</td><td>'.e((string) ($item['total_minor'] ?? '')).'</td></tr>';
            }

            $rendered = str_replace('{{items}}', $itemsHtml, $rendered);
            $rendered = str_replace('{{ items }}', $itemsHtml, $rendered);
        }

        return $rendered;
    }

    public function sampleData(string $documentType): array
    {
        return match ($documentType) {
            'receipt', 'invoice' => [
                'sale_number' => 'SALE-BTA-000001',
                'receipt_number' => 'RECEIPT-BTA-000001',
                'customer_name' => 'Sample Customer',
                'currency_code' => 'USD',
                'subtotal' => '1000',
                'discount' => '50',
                'tax' => '95',
                'total' => '1045',
                'paid' => '1045',
                'issued_at' => now()->toIso8601String(),
                'items' => [
                    ['product_name' => 'Sample Product', 'quantity' => '1', 'total_minor' => 1000],
                ],
            ],
            'supplier_bill' => [
                'supplier_name' => 'Sample Supplier',
                'bill_number' => 'INV-001',
                'amount' => '5000',
                'currency_code' => 'USD',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'paid' => '0',
                'status' => 'open',
            ],
            default => [],
        };
    }
}
