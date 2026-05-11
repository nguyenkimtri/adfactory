<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoJob extends Model
{
    protected $guarded = [];

    protected $casts = [
        'video_sources' => 'array',
        'settings' => 'array',
    ];
}
