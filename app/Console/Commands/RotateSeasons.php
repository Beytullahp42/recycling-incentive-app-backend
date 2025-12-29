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

        // 1. Find the currently active season
        $activeSeason = Season::where('is_active', true)->latest()->first();

        // SCENARIO A: System is brand new (No seasons yet)
        if (! $activeSeason) {
            $this->info("No active season found. Starting the first season!");
            $this->startNewSeason(now()->format('F Y'));
            return;
        }

        // SCENARIO B: Season is still valid
        if ($activeSeason->ends_at->isFuture() && ! $this->option('force')) {
            $this->info("Current season '{$activeSeason->name}' is still active. Ends in: " . $activeSeason->ends_at->diffForHumans());
            return;
        }

        // SCENARIO C: Season has expired (or Forced) -> ROTATE!
        $this->warn("Season '{$activeSeason->name}' has ended. Rotating now...");

        // Wrap in transaction to prevent race conditions
        DB::transaction(function () use ($activeSeason) {
            // Close the old one
            $activeSeason->update(['is_active' => false]);

            // Calculate start date (should be the exact second the old one ended)
            $newStartDate = $activeSeason->ends_at;

            // Generate Name: "January 2026"
            // We use the start date of the NEW season to determine the name
            $newName = $newStartDate->format('F Y');

            $this->startNewSeason($newName, $newStartDate);
        });
    }

    private function startNewSeason($name, $startDate = null)
    {
        // Default to NOW if no start date provided (first run)
        $start = $startDate ? Carbon::instance($startDate) : now();

        // CONFIG: Monthly seasons
        $end = $start->copy()->addMonth();

        $season = Season::create([
            'name'      => $name,
            'starts_at' => $start,
            'ends_at'   => $end,
            'is_active' => true,
        ]);

        $this->info("✅ Successfully started: $name");
        $this->info("   Starts: $start");
        $this->info("   Ends:   $end");
    }
}
