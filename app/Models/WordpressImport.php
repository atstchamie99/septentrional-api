<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordpressImport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source', 'entity', 'wordpress_id', 'target_type', 'target_id',
        'original_url', 'original_path', 'metadata', 'imported_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'imported_at' => 'datetime'];
    }
}
