<?php

namespace Tests\Unit;

use App\Services\NutritionCalculationArithmetic;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServingDailyValueArithmeticTest extends TestCase
{
    #[DataProvider('portionProvider')]
    public function test_portion_arithmetic_is_exact(
        string $numerator,
        string $denominator,
        string $portion,
        string $yield,
        string $expectedNumerator,
        string $expectedDenominator,
    ): void {
        $arithmetic = app(NutritionCalculationArithmetic::class);
        $value = $arithmetic->multiplyRatioByDecimal($numerator, $denominator, $portion)
            ->dividedBy($arithmetic->fromDecimal($yield));

        $this->assertSame([
            'numerator' => $expectedNumerator,
            'denominator' => $expectedDenominator,
        ], $arithmetic->ratio($value));
    }

    public function test_energy_and_percentage_arithmetic_remain_exact(): void
    {
        $arithmetic = app(NutritionCalculationArithmetic::class);

        $energy = $arithmetic->multiplyRatios('1', '3', '4', '1');
        $percentage = $arithmetic->percentage('1', '3', '7', '1');

        $this->assertSame(['numerator' => '4', 'denominator' => '3'], $arithmetic->ratio($energy));
        $this->assertSame(['numerator' => '100', 'denominator' => '21'], $arithmetic->ratio($percentage));
    }

    /** @return array<string, array{string, string, string, string, string, string}> */
    public static function portionProvider(): array
    {
        return [
            'integer' => ['10', '1', '20', '100', '2', '1'],
            'fraction' => ['1', '3', '20', '100', '1', '15'],
            'precise decimal' => ['1234567890123456789', '1000000000000000000', '0.000000000000000001', '3', '411522630041152263', '1000000000000000000000000000000000000'],
            'zero' => ['0', '1', '20', '100', '0', '1'],
        ];
    }
}
