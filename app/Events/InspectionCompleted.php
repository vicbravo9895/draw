<?php

namespace App\Events;

use App\Models\Inspection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InspectionCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public Inspection $inspection
    ) {}

    public function broadcastAs(): string
    {
        return 'InspectionCompleted';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->inspection->company_id),
            new PrivateChannel('portal.company.'.$this->inspection->company_id),
        ];
    }

    public function broadcastWith(): array
    {
        $ins = $this->inspection;
        $ins->load('parts.items');

        return [
            'id' => $ins->id,
            'reference_code' => $ins->reference_code,
            'status' => 'completed',
            'completed_at' => now()->toISOString(),
            'updated_at' => $ins->updated_at->toISOString(),
            'project' => $ins->project,
            'area_line' => $ins->area_line,
            'shift' => $ins->shift,
            'date' => $ins->date->format('Y-m-d'),
            'total_good' => $ins->total_good,
            'total_defects' => $ins->total_defects,
            'total' => $ins->total,
            'defect_rate' => $ins->defect_rate,
            'parts' => $ins->parts->map(fn ($p) => [
                'part_number' => $p->part_number,
                'total_good' => $p->part_total_good,
                'total_defects' => $p->part_total_defects,
                'total' => $p->part_total,
                'defect_rate' => $p->part_defect_rate,
                'items' => $p->items->map(fn ($i) => [
                    'lot_date' => $i->lot_date,
                    'good_qty' => $i->good_qty,
                    'defects_qty' => $i->defects_qty,
                ]),
            ]),
        ];
    }
}
