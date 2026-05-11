<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessVideoJob implements ShouldQueue
{
    use Queueable;

    protected $videoJob;

    /**
     * Create a new job instance.
     */
    public function __construct($videoJob)
    {
        $this->videoJob = $videoJob;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $processor = new \App\Services\VideoProcessorService($this->videoJob);
        $processor->process();
    }
}
