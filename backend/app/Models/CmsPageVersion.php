<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPageVersion extends Model
{
    use HasUlids;

    protected $fillable = ['cms_page_id', 'version', 'title', 'slug', 'sections', 'media_ids', 'created_by'];

    protected $casts = ['version' => 'integer', 'sections' => 'array', 'media_ids' => 'array'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }
}
