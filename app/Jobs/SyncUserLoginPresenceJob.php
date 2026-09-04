<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\UserPresenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SyncUserLoginPresenceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $userId,
        public readonly int $seenAt,
    ) {}

    public function handle(UserPresenceService $presence): void
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $presence->syncLoginHistory(
            $user,
            Carbon::createFromTimestamp($this->seenAt, (string) config('app.timezone')),
        );
    }
}
