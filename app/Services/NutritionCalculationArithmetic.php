<?php

declare(strict_types=1);

namespace App\Services;

use Brick\Math\BigRational;

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

    public function multiplyAndDivide(string $left, string $right, string $divisor): BigRational
    {
        return $this->multiply($left, $right)->dividedBy($this->fromDecimal($divisor));
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
}
