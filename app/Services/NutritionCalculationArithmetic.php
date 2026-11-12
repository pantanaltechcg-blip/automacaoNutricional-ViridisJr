<?php

declare(strict_types=1);

namespace App\Services;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class NutritionCalculationArithmetic
{
    public function fromDecimal(string $value): BigRational
    {
        return BigRational::of($value);
    }

    public function multiply(string $left, string $right): BigRational
    {
        return $this->fromDecimal($left)->multipliedBy($this->fromDecimal($right));
    }

    public function divide(string $dividend, string $divisor): BigRational
    {
        return $this->fromDecimal($dividend)->dividedBy($this->fromDecimal($divisor));
    }

    public function add(string $left, string $right): BigRational
    {
        return $this->fromDecimal($left)->plus($this->fromDecimal($right));
    }

    public function fromRatio(string $numerator, string $denominator): BigRational
    {
        return BigRational::ofFraction($numerator, $denominator);
    }

    public function addRationals(BigRational $left, BigRational $right): BigRational
    {
        return $left->plus($right);
    }

    public function multiplyAndDivide(string $left, string $right, string $divisor): BigRational
    {
        return $this->multiply($left, $right)->dividedBy($this->fromDecimal($divisor));
    }

    public function multiplyRatioByDecimal(
        string $numerator,
        string $denominator,
        string $multiplier,
    ): BigRational {
        return $this->fromRatio($numerator, $denominator)
            ->multipliedBy($this->fromDecimal($multiplier));
    }

    public function multiplyRatios(
        string $leftNumerator,
        string $leftDenominator,
        string $rightNumerator,
        string $rightDenominator,
    ): BigRational {
        return $this->fromRatio($leftNumerator, $leftDenominator)
            ->multipliedBy($this->fromRatio($rightNumerator, $rightDenominator));
    }

    public function percentage(
        string $numerator,
        string $denominator,
        string $dailyValueNumerator,
        string $dailyValueDenominator,
    ): BigRational {
        return $this->fromRatio($numerator, $denominator)
            ->multipliedBy(100)
            ->dividedBy($this->fromRatio($dailyValueNumerator, $dailyValueDenominator));
    }

    public function normalizeRatio(
        string $numerator,
        string $denominator,
        string $yieldValue,
    ): BigRational {
        return $this->fromRatio($numerator, $denominator)
            ->multipliedBy($this->fromDecimal('100'))
            ->dividedBy($this->fromDecimal($yieldValue));
    }

    /**
     * @return array{numerator: string, denominator: string}
     */
    public function ratio(BigRational $value): array
    {
        return [
            'numerator' => (string) $value->getNumerator(),
            'denominator' => (string) $value->getDenominator(),
        ];
    }

    public function compare(BigRational $left, BigRational $right): int
    {
        return $left->compareTo($right);
    }

    public function roundToStepHalfUp(BigRational $value, BigRational $step): BigRational
    {
        if ($step->getSign() <= 0) {
            throw new InvalidArgumentException('O incremento de arredondamento deve ser positivo.');
        }

        return $value->dividedBy($step)
            ->toScale(0, RoundingMode::HalfUp)
            ->toBigRational()
            ->multipliedBy($step);
    }

    public function formatDecimal(BigRational $value, int $decimalPlaces): string
    {
        return $value->toScale($decimalPlaces, RoundingMode::Unnecessary)->toString();
    }
}
