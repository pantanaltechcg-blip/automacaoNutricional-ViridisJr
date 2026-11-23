<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\Nutrient;
use App\Services\IngredientFormNutrientMapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IngredientFormNutrientMapperTest extends TestCase
{
    use DatabaseTransactions;

    public function test_maps_exact_unique_nutrients_and_marks_unsupported_fields(): void
    {
        $energyKcal = Nutrient::query()->create(['name' => 'Energia', 'unit' => 'kcal']);
        Nutrient::query()->create(['name' => 'Energia', 'unit' => 'kJ']);
        $carb = Nutrient::query()->create(['name' => 'Carboidrato', 'unit' => 'g']);

        $mapper = new IngredientFormNutrientMapper;
        $mapped = $mapper->mapFields();
        $byField = collect($mapped)->keyBy('field');

        $this->assertTrue($byField['energy_kcal']['available']);
        $this->assertSame((int) $energyKcal->id, $byField['energy_kcal']['nutrientId']);

        $this->assertTrue($byField['carbohydrates']['available']);
        $this->assertSame((int) $carb->id, $byField['carbohydrates']['nutrientId']);

        $this->assertFalse($byField['total_sugars']['available']);
        $this->assertSame('UNSUPPORTED', $byField['total_sugars']['reason']);
        $this->assertFalse($byField['added_sugars']['available']);
        $this->assertSame('UNSUPPORTED', $byField['added_sugars']['reason']);
        $this->assertFalse($byField['trans_fat']['available']);
        $this->assertSame('UNSUPPORTED', $byField['trans_fat']['reason']);
    }

    public function test_marks_missing_and_ambiguous_nutrients_as_unavailable_without_arbitrary_choice(): void
    {
        Nutrient::query()->where('name', 'Sódio')->where('unit', 'mg')->delete();
        Nutrient::query()->create(['name' => 'Proteína', 'unit' => 'g']);
        Nutrient::query()->create(['name' => 'Proteína', 'unit' => 'g']);

        $mapper = new IngredientFormNutrientMapper;
        $byField = collect($mapper->mapFields())->keyBy('field');

        $this->assertFalse($byField['sodium']['available']);
        $this->assertNull($byField['sodium']['nutrientId']);
        $this->assertSame('NOT_FOUND', $byField['sodium']['reason']);

        $this->assertFalse($byField['proteins']['available']);
        $this->assertNull($byField['proteins']['nutrientId']);
        $this->assertSame('AMBIGUOUS', $byField['proteins']['reason']);
    }
}
