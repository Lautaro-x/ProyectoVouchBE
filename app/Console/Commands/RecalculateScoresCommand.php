<?php

namespace App\Console\Commands;

use App\Models\Review;
use App\Services\ScoringService;
use Illuminate\Console\Command;

class RecalculateScoresCommand extends Command
{
    protected $signature   = 'scores:recalculate';
    protected $description = 'Recalcula weighted_score y letter_grade de todas las reseñas y actualiza ProductScores';

    public function handle(ScoringService $scoring): int
    {
        $reviews = Review::with(['product.genres.categories', 'scores'])->get();

        $this->info("Recalculando {$reviews->count()} reseña(s)...");
        $bar = $this->output->createProgressBar($reviews->count());
        $bar->start();

        $affectedProductIds = [];

        foreach ($reviews as $review) {
            $newScore = $scoring->calculateWeightedScore($review);
            $newGrade = $scoring->calculateLetterGrade($newScore);

            $review->update([
                'weighted_score' => $newScore,
                'letter_grade'   => $newGrade,
            ]);

            $affectedProductIds[$review->product_id] = true;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Actualizando ProductScores...');

        foreach (array_keys($affectedProductIds) as $productId) {
            $product = \App\Models\Product::find($productId);
            if ($product) {
                $scoring->recalculateProductScores($product);
            }
        }

        $this->info('Hecho.');

        return self::SUCCESS;
    }
}
