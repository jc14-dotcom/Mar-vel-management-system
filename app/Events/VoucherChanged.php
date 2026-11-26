<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoucherChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $voucherId,
        public int $recordVersion,
        public string $changeType,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('vouchers')];
    }

    public function broadcastAs(): string
    {
        return 'voucher.changed';
    }

    /**
     * @return array{voucher_id: int, record_version: int, change_type: string}
     */
    public function broadcastWith(): array
    {
        return [
            'voucher_id' => $this->voucherId,
            'record_version' => $this->recordVersion,
            'change_type' => $this->changeType,
        ];
    }
}
