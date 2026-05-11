<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_jobs', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('project_name')->nullable();
            $table->text('audio_url');
            $table->text('bg_music_url')->nullable();
            $table->json('video_sources');
            $table->text('logo_url')->nullable();
            $table->text('subtitle_data')->nullable();
            $table->json('settings')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('output_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_jobs');
    }
};
