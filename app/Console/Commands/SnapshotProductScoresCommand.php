<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductScoreHistory;
use Illuminate\Console\Command;

class SnapshotProductScoresCommand extends Command
{
    protected $signature   = 'scores:snapshot';
    protected $description = 'Saves a daily snapshot of product scores, skipping products whose scores have not changed.';

    public function handle(): void
    {
        $products = Product::with('score')->whereHas('score')->get();
        $inserted = 0;
        $skipped  = 0;

        foreach ($products as $product) {
            $score = $product->score;

            if ($score->global_score === null && $score->pro_score === null) {
                $skipped++;
                continue;
            }

            $last = ProductScoreHistory::where('product_id', $product->id)
                ->latest('snapshot_date')
                ->first();

            if (
                $last &&
                $last->global_score === $score->global_score &&
                $last->pro_score    === $score->pro_score
            ) {
                $skipped++;
                continue;
            }

            ProductScoreHistory::create([
                'product_id'    => $product->id,
                'global_score'  => $score->global_score,
                'pro_score'     => $score->pro_score,
                'snapshot_date' => today(),
            ]);

            $inserted++;
        }

        $this->info("Snapshot completado: {$inserted} guardados, {$skipped} sin cambios.");
    }
}
