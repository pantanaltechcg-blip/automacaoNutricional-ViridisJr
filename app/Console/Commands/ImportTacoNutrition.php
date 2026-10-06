<?php

namespace App\Console\Commands;

use App\Services\NutritionBaseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('nutrition:import-taco')]
#[Description('Importa a base nutricional TACO a partir dos arquivos estruturados aprovados')]
class ImportTacoNutrition extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NutritionBaseService $nutritionBaseService): int
    {
        try {
            $foods = $this->readCsv(
                resource_path('data/taco/processed/foods.csv'),
                ['source', 'source_code', 'name', 'category'],
            );
            $nutrients = $this->readCsv(
                resource_path('data/taco/processed/nutrients.csv'),
                ['name', 'unit'],
            );
            $foodNutrients = $this->readCsv(
                resource_path('data/taco/processed/food_nutrients.csv'),
                ['source', 'source_code', 'nutrient_name', 'nutrient_unit', 'amount_value', 'status'],
            );

            $result = $nutritionBaseService->import(
                array_map(fn (array $food): array => [
                    ...$food,
                    'category' => $food['category'] === '' ? null : $food['category'],
                ], $foods),
                $nutrients,
                array_map(fn (array $foodNutrient): array => [
                    ...$foodNutrient,
                    'amount_value' => $foodNutrient['amount_value'] === ''
                        ? null
                        : $foodNutrient['amount_value'],
                ], $foodNutrients),
            );
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Importação da TACO concluída.');
        $this->table(
            ['Registro', 'Quantidade'],
            [
                ['Foods', $result['foods']],
                ['Nutrients', $result['nutrients']],
                ['FoodNutrients', $result['food_nutrients']],
                ['EXACT', $result['statuses']['EXACT'] ?? 0],
                ['TRACE', $result['statuses']['TRACE'] ?? 0],
                ['NA', $result['statuses']['NA'] ?? 0],
                ['ABSENT', $result['statuses']['ABSENT'] ?? 0],
                ['REEVALUATION', $result['statuses']['REEVALUATION'] ?? 0],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $expectedHeaders
     * @return list<array<string, string>>
     */
    private function readCsv(string $path, array $expectedHeaders): array
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Não foi possível abrir o arquivo {$path}.");
        }

        try {
            $headers = fgetcsv($stream, 0, ',', '"', '');
            if ($headers !== $expectedHeaders) {
                throw new RuntimeException("Cabeçalho inválido no arquivo {$path}.");
            }

            $records = [];
            $line = 1;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    throw new RuntimeException("Quantidade de colunas inválida em {$path}:{$line}.");
                }

                $record = array_combine($headers, $values);
                if ($record === false) {
                    throw new RuntimeException("Não foi possível interpretar {$path}:{$line}.");
                }

                $records[] = $record;
            }

            return $records;
        } finally {
            fclose($stream);
        }
    }
}
