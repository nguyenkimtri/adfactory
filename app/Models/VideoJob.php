<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoJob extends Model
{
    protected $fillable = [
        'project_name',
        'audio_url',
        'bg_music_url',
        'video_sources',
        'logo_url',
        'subtitle_data',
        'settings',
        'output_path',
        'status',
        'progress',
        'status_message',
        'error_message',
        'webhook_url',
    ];

    protected $casts = [
        'audio_url' => 'array',
        'bg_music_url' => 'array',
        'video_sources' => 'array',
        'settings' => 'array',
    ];

    protected $attributes = [
        'video_sources' => '[]',
        'audio_url' => '[]',
        'bg_music_url' => '[]',
        'settings' => '{"format":"9:16","subtitles":true,"volume_audio":100,"volume_video":0,"volume_music":20}',
        'status_message' => 'Đang chờ xử lý...',
    ];
}
