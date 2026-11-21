<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'sku' => is_string($this->input('sku')) ? strtoupper(trim($this->input('sku'))) : $this->input('sku'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'barcode' => is_string($this->input('barcode')) && trim($this->input('barcode')) !== ''
                ? trim($this->input('barcode'))
                : null,
            'description' => is_string($this->input('description')) && trim($this->input('description')) !== ''
                ? trim($this->input('description'))
                : null,
            'category_id' => $this->input('category_id') === '' ? null : $this->input('category_id'),
            'reorder_level' => $this->input('reorder_level') === '' ? null : $this->input('reorder_level'),
            'public_slug' => is_string($this->input('public_slug')) && trim($this->input('public_slug')) !== '' ? trim($this->input('public_slug')) : null,
            'public_description' => is_string($this->input('public_description')) && trim($this->input('public_description')) !== '' ? trim($this->input('public_description')) : null,
            'public_image_id' => $this->input('public_image_id') === '' ? null : $this->input('public_image_id'),
            'public_image_url' => is_string($this->input('public_image_url')) && trim($this->input('public_image_url')) !== ''
                ? trim($this->input('public_image_url'))
                : null,
            'public_image_alt' => is_string($this->input('public_image_alt')) && trim($this->input('public_image_alt')) !== '' ? trim($this->input('public_image_alt')) : null,
        ];

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        $rules = [
            'name' => ['required', 'string', 'max:180'],
            'sku' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('products', 'sku')->where(fn ($query) => $query->where('branch_id', $branch->id)),
            ],
            'barcode' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'string'],
            'base_unit_id' => ['required', 'string'],
            'selling_price' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d+)?$/'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'public_slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'public_slug')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'public_description' => ['nullable', 'string', 'max:5000'],
            'public_image_id' => ['nullable', 'string', 'exists:cms_media,id'],
            'public_image_url' => ['nullable', 'string', 'max:500'],
            'public_image_alt' => ['nullable', 'string', 'max:250'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,4})?$/'],
        ];

        if ($this->filled('barcode')) {
            $rules['barcode'][] = Rule::unique('products', 'barcode')
                ->where(fn ($query) => $query->where('branch_id', $branch->id));
        }

        return $rules;
    }

    protected function after(): array
    {
        return [
            function ($validator): void {
                /** @var Branch $branch */
                $branch = $this->route('branch');

                if (! $validator->errors()->has('selling_price')) {
                    try {
                        Money::toMinor((string) $this->input('selling_price'), $branch->currency_code);
                    } catch (InvalidArgumentException $exception) {
                        $validator->errors()->add('selling_price', $exception->getMessage());
                    }
                }

                if (! $validator->errors()->has('category_id') && $this->filled('category_id')) {
                    $category = Category::where('branch_id', $branch->id)
                        ->whereKey($this->input('category_id'))
                        ->first();

                    if (! $category) {
                        $validator->errors()->add('category_id', 'The category must belong to this branch.');
                    } elseif (! $category->is_active) {
                        $validator->errors()->add('category_id', 'The selected category is archived.');
                    }
                }

                if (! $validator->errors()->has('base_unit_id')) {
                    $unit = Unit::where('branch_id', $branch->id)
                        ->whereKey($this->input('base_unit_id'))
                        ->first();

                    if (! $unit) {
                        $validator->errors()->add('base_unit_id', 'The unit must belong to this branch.');
                    } elseif (! $unit->is_active) {
                        $validator->errors()->add('base_unit_id', 'The selected unit is archived.');
                    }
                }

                if ($this->boolean('is_public') && (! $this->filled('public_slug') || ! $this->filled('public_description'))) {
                    $validator->errors()->add('is_public', 'A public slug and customer-facing description are required before publishing.');
                }
                if ($this->filled('public_image_url')) {
                    $imageUrl = (string) $this->input('public_image_url');
                    $isRelative = str_starts_with($imageUrl, '/') && ! str_starts_with($imageUrl, '//');
                    $isAbsolute = filter_var($imageUrl, FILTER_VALIDATE_URL) !== false
                        && in_array(parse_url($imageUrl, PHP_URL_SCHEME), ['http', 'https'], true);

                    if (! $isRelative && ! $isAbsolute) {
                        $validator->errors()->add('public_image_url', 'Enter an http(s) image URL or a path starting with /.');
                    }
                }
                if (($this->filled('public_image_id') || $this->filled('public_image_url')) && ! $this->filled('public_image_alt')) {
                    $validator->errors()->add('public_image_alt', 'Describe the product image for customers using assistive technology.');
                }
            },
        ];
    }
}
