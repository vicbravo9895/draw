<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QualityAlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public int $companyId,
        public array $alertData
    ) {}

    public function broadcastAs(): string
    {
        return 'QualityAlertTriggered';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->companyId),
            new PrivateChannel('portal.company.'.$this->companyId),
        ];
    }

    public function broadcastWith(): array
    {
        return $this->alertData;
    }
}
