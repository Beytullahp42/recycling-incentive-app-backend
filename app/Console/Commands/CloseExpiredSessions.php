<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\RecyclingSession;
use App\Enums\SessionLifecycle;

class CloseExpiredSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:close-expired-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically closes sessions that have passed their expiration time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredSessions = RecyclingSession::where('lifecycle_status', SessionLifecycle::ACTIVE)
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($expiredSessions as $session) {
            // 2. Clear from Cache (just in case)
            Cache::forget("recycle_session_{$session->session_token}");

            // 3. Update Database
            $session->update([
                'lifecycle_status' => SessionLifecycle::CLOSED, // Distinct from 'closed' (manual)
                'ended_at'         => now(),
            ]);

            $count++;
        }

        $this->info("Successfully closed {$count} expired sessions."); //
    }
}
