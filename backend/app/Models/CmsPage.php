<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPage extends Model
{
    use HasUlids;

    protected $fillable = ['title', 'slug', 'current_version', 'published_version', 'published_slug', 'published_at', 'created_by'];

    protected $casts = [
        'current_version' => 'integer',
        'published_version' => 'integer',
        'published_at' => 'datetime',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(CmsPageVersion::class)->orderByDesc('version');
    }
}
