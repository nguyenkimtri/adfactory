<?php

namespace App\Events;

use App\Models\VideoJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoJobUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $job;

    public function __construct(VideoJob $job)
    {
        $this->job = $job;
    }

    public function broadcastOn()
    {
        return new Channel('jobs');
    }

    public function broadcastAs()
    {
        return 'job.updated';
    }
}
