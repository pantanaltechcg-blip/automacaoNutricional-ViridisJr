<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NutritionCalculationArithmetic;
use Brick\Math\BigRational;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TypeError;

class NutritionCalculationArithmeticTest extends TestCase
{
    private NutritionCalculationArithmetic $arithmetic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->arithmetic = new NutritionCalculationArithmetic;
    }

    #[Test]
    public function it_builds_a_terminating_decimal_exactly_from_a_string(): void
    {
        $this->assertRatio('617', '50', $this->arithmetic->fromDecimal('12.34'));
    }

    #[Test]
    public function it_preserves_all_decimal_places_without_a_fixed_scale(): void
    {
        $this->assertRatio(
            '12345678901234567890123456789',
            '100000000000000000000000000000',
            $this->arithmetic->fromDecimal('0.12345678901234567890123456789'),
        );
    }

    #[Test]
    public function it_multiplies_decimal_strings_exactly(): void
    {
        $this->assertRatio(
            '774335617',
            '500000',
            $this->arithmetic->multiply('125.5001', '12.34'),
        );
    }

    #[Test]
    public function it_divides_without_materializing_a_repeating_decimal(): void
    {
        $this->assertRatio('1', '3', $this->arithmetic->divide('1', '3'));
    }

    #[Test]
    public function it_adds_decimal_strings_exactly(): void
    {
        $this->assertRatio('3', '10', $this->arithmetic->add('0.1', '0.2'));
    }

    #[Test]
    public function it_reduces_the_contribution_formula_to_canonical_terms(): void
    {
        $this->assertRatio(
            '774335617',
            '50000000',
            $this->arithmetic->multiplyAndDivide('125.5001', '12.34', '100'),
        );
        $this->assertRatio('3', '1', $this->arithmetic->multiply('1.5', '2'));
    }

    #[Test]
    public function it_normalizes_a_ratio_by_the_persisted_yield_without_rounding(): void
    {
        $this->assertRatio('25', '6', $this->arithmetic->normalizeRatio('25', '2', '300'));
        $this->assertRatio('1', '3', $this->arithmetic->normalizeRatio('1', '100', '3'));
    }

    #[Test]
    public function it_represents_numeric_zero_canonically_as_zero_over_one(): void
    {
        $this->assertRatio('0', '1', $this->arithmetic->multiplyAndDivide('125.5001', '0.00', '100'));
    }

    #[Test]
    public function its_public_decimal_inputs_are_strings_and_reject_floats(): void
    {
        foreach (['fromDecimal', 'multiply', 'divide', 'add', 'multiplyAndDivide', 'normalizeRatio'] as $methodName) {
            $method = new ReflectionMethod(NutritionCalculationArithmetic::class, $methodName);

            foreach ($method->getParameters() as $parameter) {
                $this->assertSame('string', (string) $parameter->getType());
            }
        }

        $this->expectException(TypeError::class);

        $this->arithmetic->fromDecimal(1.25);
    }

    private function assertRatio(string $numerator, string $denominator, BigRational $value): void
    {
        $ratio = $this->arithmetic->ratio($value);

        $this->assertSame($numerator, $ratio['numerator']);
        $this->assertSame($denominator, $ratio['denominator']);
    }
}
