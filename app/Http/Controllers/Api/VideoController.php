<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\VideoJob;
use App\Jobs\ProcessVideoJob;

class VideoController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_name' => 'nullable|string',
            'audio_url' => 'required|string',
            'bg_music_url' => 'nullable|string',
            'video_sources' => 'required|array',
            'logo_url' => 'nullable|string',
            'subtitle_data' => 'nullable|string',
            'settings' => 'nullable|array',
            'webhook_url' => 'nullable|string',
        ]);

        $videoJob = VideoJob::create($validated);

        // Đẩy vào hàng đợi xử lý ngầm
        ProcessVideoJob::dispatch($videoJob);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Video job created and queued successfully.',
                'job_id' => $videoJob->id,
                'status' => $videoJob->status,
            ]);
        }

        return back()->with('success', 'Video job created and queued successfully.');
    }

    public function status()
    {
        $jobs = VideoJob::latest()->take(10)->get();
        return response()->json($jobs);
    }

    public function destroy($id)
    {
        $job = VideoJob::findOrFail($id);
        $job->delete();
        return response()->json(['message' => 'Job deleted.']);
    }
}
