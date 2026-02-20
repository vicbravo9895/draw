<?php

namespace App\Events;

use App\Models\Inspection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InspectionClosed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Inspection $inspection
    ) {}

    public function broadcastAs(): string
    {
        return 'InspectionClosed';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.' . $this->inspection->company_id),
            new PrivateChannel('portal.company.' . $this->inspection->company_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->inspection->id,
            'reference_code' => $this->inspection->reference_code,
            'status' => 'closed',
            'closed_at' => now()->toISOString(),
        ];
    }
}
