<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NutritionCalculationArithmetic;
use Brick\Math\Exception\RoundingNecessaryException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeclaredValuesArithmeticTest extends TestCase
{
    public function test_compare_compares_exact_rationals_without_floating_point(): void
    {
        $arithmetic = app(NutritionCalculationArithmetic::class);

        $oneThird = $arithmetic->fromRatio('1', '3');
        $threeTenths = $arithmetic->fromRatio('3', '10');
        $equivalent = $arithmetic->fromRatio('2', '6');

        $this->assertSame(1, $arithmetic->compare($oneThird, $threeTenths));
        $this->assertSame(-1, $arithmetic->compare($threeTenths, $oneThird));
        $this->assertSame(0, $arithmetic->compare($oneThird, $equivalent));
    }

    #[DataProvider('roundingProvider')]
    public function test_round_to_step_half_up_and_format_decimal_are_exact(
        string $numerator,
        string $denominator,
        string $stepNumerator,
        string $stepDenominator,
        int $decimalPlaces,
        string $expectedNumerator,
        string $expectedDenominator,
        string $expectedFormatted,
    ): void {
        $arithmetic = app(NutritionCalculationArithmetic::class);
        $value = $arithmetic->fromRatio($numerator, $denominator);
        $step = $arithmetic->fromRatio($stepNumerator, $stepDenominator);

        $rounded = $arithmetic->roundToStepHalfUp($value, $step);

        $this->assertSame([
            'numerator' => $expectedNumerator,
            'denominator' => $expectedDenominator,
        ], $arithmetic->ratio($rounded));
        $this->assertSame($expectedFormatted, $arithmetic->formatDecimal($rounded, $decimalPlaces));
    }

    public function test_format_decimal_throws_when_rounded_value_cannot_be_represented_exactly_in_scale(): void
    {
        $arithmetic = app(NutritionCalculationArithmetic::class);
        $rounded = $arithmetic->roundToStepHalfUp(
            $arithmetic->fromRatio('1', '3'),
            $arithmetic->fromRatio('1', '3'),
        );

        $this->expectException(RoundingNecessaryException::class);

        $arithmetic->formatDecimal($rounded, 2);
    }

    /** @return array<string, array{string, string, string, string, int, string, string, string}> */
    public static function roundingProvider(): array
    {
        return [
            'exact tie half_up integer' => ['5', '2', '1', '1', 0, '3', '1', '3'],
            'exact tie half_up one decimal' => ['25', '100', '1', '10', 1, '3', '10', '0.3'],
            'exact tie half_up 1.25' => ['125', '100', '1', '10', 1, '13', '10', '1.3'],
            'below half rounds down' => ['1249', '1000', '1', '10', 1, '6', '5', '1.2'],
            'repeating third rounds down at 0.1' => ['1', '3', '1', '10', 1, '3', '10', '0.3'],
            'repeating two thirds rounds up at 0.1' => ['2', '3', '1', '10', 1, '7', '10', '0.7'],
            'zero stays zero with scale' => ['0', '1', '1', '10', 1, '0', '1', '0.0'],
            'step 5 half up' => ['15', '2', '5', '1', 0, '10', '1', '10'],
            'beyond php_int_max' => [
                '9999999999999999999999999999999',
                '2',
                '1',
                '1',
                0,
                '5000000000000000000000000000000',
                '1',
                '5000000000000000000000000000000',
            ],
        ];
    }
}
