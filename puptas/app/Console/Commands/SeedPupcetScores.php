<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TestPasser;

class SeedPupcetScores extends Command
{
    protected $signature   = 'test-passers:seed-scores
                                {--force : Overwrite scores that are already set}';

    protected $description = 'Assign random sample PUPCET total scores to existing test_passers for testing.';

    public function handle(): int
    {
        $query = TestPasser::query();

        if (! $this->option('force')) {
            $query->whereNull('pupcet_total_score');
        }

        $passers = $query->get();

        if ($passers->isEmpty()) {
            $this->info('No passers found that need scores (use --force to overwrite existing scores).');
            return self::SUCCESS;
        }

        $this->info("Assigning sample PUPCET scores to {$passers->count()} passer(s)...");

        $bar = $this->output->createProgressBar($passers->count());
        $bar->start();

        foreach ($passers as $passer) {
            // Generate a realistic-looking score: 40.00 – 98.00
            // Scores cluster around 60-80 to mimic a real distribution.
            $passer->pupcet_total_score = $this->generateScore();
            $passer->saveQuietly();   // skip model events / timestamps change
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Show a quick ranked preview
        $top = TestPasser::whereNotNull('pupcet_total_score')
            ->orderByDesc('pupcet_total_score')
            ->take(5)
            ->get(['test_passer_id', 'first_name', 'surname', 'pupcet_total_score']);

        $this->newLine();
        $this->info('Top 5 ranked passers after seeding:');
        $this->table(
            ['Rank', 'ID', 'Name', 'Score'],
            $top->map(fn ($p, $i) => [
                $i + 1,
                $p->test_passer_id,
                "{$p->surname}, {$p->first_name}",
                number_format($p->pupcet_total_score, 2),
            ])->toArray()
        );

        $this->info("Done! {$passers->count()} passer(s) updated.");
        return self::SUCCESS;
    }

    /**
     * Generate a realistic sample PUPCET score.
     *
     * Uses a normal-ish distribution centred around 68 to simulate real results.
     * Scores are clamped to [40.00, 99.00].
     */
    private function generateScore(): float
    {
        // Box-Muller transform for a roughly normal distribution
        $u1 = lcg_value();
        $u2 = lcg_value();
        $z  = sqrt(-2 * log($u1 + 1e-10)) * cos(2 * M_PI * $u2);

        $mean  = 68.0;
        $stdev = 12.0;
        $score = $mean + $stdev * $z;

        // Clamp and round to 2 decimal places
        return round(max(40.0, min(99.0, $score)), 2);
    }
}
