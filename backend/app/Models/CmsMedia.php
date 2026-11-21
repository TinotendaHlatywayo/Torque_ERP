<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsMedia extends Model
{
    use HasUlids;

    protected $fillable = ['id', 'original_name', 'original_path', 'mime_type', 'file_size', 'width', 'height', 'variants', 'preferred_variant', 'uploaded_by'];

    protected $casts = ['file_size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'variants' => 'array'];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
