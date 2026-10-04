<?php

declare(strict_types=1);

namespace CarMoneyLab\Domain;

/**
 * Решение по заявке: сначала LTV, затем правило пробега.
 *
 *   LTV <= approve_max              -> approve
 *   approve_max < LTV <= review_max -> review
 *   LTV > review_max                -> reject
 *
 * Пробег свыше review_mileage_km понижает только approve до review;
 * review и reject по LTV пробегом не смягчаются. Пробег не передан —
 * действует чистая логика LTV.
 */
final class DecisionEngine
{
    public const APPROVE = 'approve';
    public const REVIEW = 'review';
    public const REJECT = 'reject';

    private float $approveMax;
    private float $reviewMax;
    private int $reviewMileageKm;

    /**
     * @param array{approve_max:float,review_max:float} $thresholds
     */
    public function __construct(array $thresholds, int $reviewMileageKm = 400000)
    {
        $this->approveMax = $thresholds['approve_max'];
        $this->reviewMax = $thresholds['review_max'];
        $this->reviewMileageKm = $reviewMileageKm;
    }

    public function decide(float $ltv, ?int $mileage = null): string
    {
        $decision = $this->decideByLtv($ltv);

        if ($mileage !== null && $decision === self::APPROVE && $mileage > $this->reviewMileageKm) {
            return self::REVIEW;
        }

        return $decision;
    }

    private function decideByLtv(float $ltv): string
    {
        if ($ltv < $this->approveMax) {
            return self::APPROVE;
        }

        if ($ltv <= $this->reviewMax) {
            return self::REVIEW;
        }

        return self::REJECT;
    }
}
