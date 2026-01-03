<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RotateSeasons extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:rotate-seasons {--force : Force a rotation even if not expired}';

    /**
     * The console command description.
     */
    protected $description = 'Checks if the current season has ended and starts a new one';

    public function handle()
    {
        $this->info("Checking Season Status...");

        $activeSeason = Season::where('is_active', true)->latest()->first();

        if (! $activeSeason) {
            $this->info("No active season found. Starting the first season!");
            $this->startNewSeason(now()->format('F Y'));
            return;
        }

        if ($activeSeason->ends_at->isFuture() && ! $this->option('force')) {
            $this->info("Current season '{$activeSeason->name}' is still active. Ends in: " . $activeSeason->ends_at->diffForHumans());
            return;
        }

        $this->warn("Season '{$activeSeason->name}' has ended. Rotating now...");

        DB::transaction(function () use ($activeSeason) {
            $activeSeason->update(['is_active' => false]);
            $newStartDate = $activeSeason->ends_at;
            $newName = $newStartDate->format('F Y');

            $this->startNewSeason($newName, $newStartDate);
        });
    }

    private function startNewSeason($name, $startDate = null)
    {
        $start = $startDate ? Carbon::instance($startDate) : now();
        $end = $start->copy()->addMonth();

        $season = Season::create([
            'name'      => $name,
            'starts_at' => $start,
            'ends_at'   => $end,
            'is_active' => true,
        ]);

        $this->info("Successfully started: $name");
        $this->info("Starts: $start");
        $this->info("Ends:   $end");
    }
}
