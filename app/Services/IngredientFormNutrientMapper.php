<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Nutrient;

class IngredientFormNutrientMapper
{
    /**
     * Definição fixa dos campos da Tela 4 e suas correspondências nominais/dimensionais na tabela `nutrients`.
     *
     * @var list<array{field: string, label: string, unit: string, name: ?string}>
     */
    private const array FORM_FIELDS = [
        ['field' => 'energy_kcal', 'label' => 'Energia (kcal)', 'unit' => 'kcal', 'name' => 'Energia'],
        ['field' => 'carbohydrates', 'label' => 'Carboidratos (g)', 'unit' => 'g', 'name' => 'Carboidrato'],
        ['field' => 'total_sugars', 'label' => 'Açúcares totais (g)', 'unit' => 'g', 'name' => null],
        ['field' => 'added_sugars', 'label' => 'Açúcares adicionados (g)', 'unit' => 'g', 'name' => null],
        ['field' => 'proteins', 'label' => 'Proteínas (g)', 'unit' => 'g', 'name' => 'Proteína'],
        ['field' => 'total_fat', 'label' => 'Gorduras totais (g)', 'unit' => 'g', 'name' => 'Lipídeos'],
        ['field' => 'saturated_fat', 'label' => 'Gorduras saturadas (g)', 'unit' => 'g', 'name' => 'Ácidos graxos saturados'],
        ['field' => 'trans_fat', 'label' => 'Gorduras trans (g)', 'unit' => 'g', 'name' => null],
        ['field' => 'dietary_fiber', 'label' => 'Fibra alimentar (g)', 'unit' => 'g', 'name' => 'Fibra alimentar'],
        ['field' => 'sodium', 'label' => 'Sódio (mg)', 'unit' => 'mg', 'name' => 'Sódio'],
    ];

    /**
     * Resolve o mapeamento em modo somente leitura a partir da tabela `nutrients`.
     *
     * @return list<array{
     *     field: string,
     *     label: string,
     *     unit: string,
     *     nutrientId: ?int,
     *     available: bool,
     *     reason: ?string
     * }>
     */
    public function mapFields(): array
    {
        $nutrients = Nutrient::query()
            ->select(['id', 'name', 'unit'])
            ->get()
            ->groupBy(static fn (Nutrient $nutrient): string => "{$nutrient->name}|{$nutrient->unit}");

        $mapped = [];

        foreach (self::FORM_FIELDS as $definition) {
            if ($definition['name'] === null) {
                $mapped[] = [
                    'field' => $definition['field'],
                    'label' => $definition['label'],
                    'unit' => $definition['unit'],
                    'nutrientId' => null,
                    'available' => false,
                    'reason' => 'UNSUPPORTED',
                ];

                continue;
            }

            $key = "{$definition['name']}|{$definition['unit']}";
            $matches = $nutrients->get($key);

            if ($matches === null || $matches->isEmpty()) {
                $mapped[] = [
                    'field' => $definition['field'],
                    'label' => $definition['label'],
                    'unit' => $definition['unit'],
                    'nutrientId' => null,
                    'available' => false,
                    'reason' => 'NOT_FOUND',
                ];

                continue;
            }

            if ($matches->count() > 1) {
                $mapped[] = [
                    'field' => $definition['field'],
                    'label' => $definition['label'],
                    'unit' => $definition['unit'],
                    'nutrientId' => null,
                    'available' => false,
                    'reason' => 'AMBIGUOUS',
                ];

                continue;
            }

            /** @var Nutrient $nutrient */
            $nutrient = $matches->first();

            $mapped[] = [
                'field' => $definition['field'],
                'label' => $definition['label'],
                'unit' => $definition['unit'],
                'nutrientId' => (int) $nutrient->getKey(),
                'available' => true,
                'reason' => null,
            ];
        }

        return $mapped;
    }
}
