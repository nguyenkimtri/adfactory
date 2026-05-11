<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoJob;
use App\Jobs\ProcessVideoJob;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->all();
        
        // Tự động tách các dòng thành mảng cho mọi loại tài nguyên
        $fieldsToSplit = ['video_sources', 'audio_url', 'bg_music_url'];
        foreach ($fieldsToSplit as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = array_values(array_filter(array_map('trim', explode("\n", $data[$field]))));
            }
        }

        $validated = $request->validate([
            'project_name' => 'nullable|string',
            'audio_url' => 'required|array',
            'bg_music_url' => 'nullable|array',
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
        return response()->json(VideoJob::latest()->paginate(5));
    }

    public function destroy($id)
    {
        $job = VideoJob::findOrFail($id);
        $job->delete();
        return response()->json(['message' => 'Job deleted']);
    }
}
