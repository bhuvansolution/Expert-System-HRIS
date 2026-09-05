<?php

namespace App\Services\Performance;

use App\Models\PerformanceIndicator;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PerformanceReviewItemService
{
    public function getByReview(
        PerformanceReview $review
    ): Collection {
        return $review->items()
            ->with('indicator')
            ->latest()
            ->get();
    }

    public function getById(
        PerformanceReviewItem $item
    ): PerformanceReviewItem {
        return $item->load('indicator');
    }

    public function create(
        PerformanceReview $review,
        array $data
    ): PerformanceReviewItem {
        if ($review->status === 'approved') {
            throw new InvalidArgumentException('Performance review yang sudah approved tidak dapat diubah.');
        }

        $indicator = PerformanceIndicator::findOrFail(
            $data['performance_indicator_id']
        );

        if (!$indicator->is_active) {
            throw new InvalidArgumentException('Performance indicator yang dipilih tidak aktif.');
        }

        $exists = $review->items()
            ->where(
                'performance_indicator_id',
                $indicator->id
            )
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Performance indicator tersebut sudah digunakan dalam review.');
        }

        return DB::transaction(function () use ($review, $data) {
            $item = $review->items()->create($data);

            return $item->load('indicator');
        });
    }

    public function update(
        PerformanceReviewItem $item,
        array $data
    ): PerformanceReviewItem {
        $item->loadMissing('review');

        if ($item->review->status === 'approved') {
            throw new InvalidArgumentException('Performance review yang sudah approved tidak dapat diubah.');
        }

        if (
            isset($data['performance_indicator_id']) &&
            $data['performance_indicator_id'] !==
            $item->performance_indicator_id
        ) {
            $indicator = PerformanceIndicator::findOrFail(
                $data['performance_indicator_id']
            );

            if (!$indicator->is_active) {
                throw new InvalidArgumentException('Performance indicator yang dipilih tidak aktif.');
            }

            $exists = $item->review
                ->items()
                ->where(
                    'performance_indicator_id',
                    $indicator->id
                )
                ->where(
                    'id',
                    '!=',
                    $item->id
                )
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException(
                    'Performance indicator tersebut sudah digunakan dalam review.'
                );
            }
        }

        $item->update($data);

        return $item->refresh()->load('indicator');
    }

    public function delete(
        PerformanceReviewItem $item
    ): void {
        $item->loadMissing('review');

        if ($item->review->status === 'approved') {
            throw new InvalidArgumentException('Performance review yang sudah approved tidak dapat diubah.');
        }

        $item->delete();
    }
}
