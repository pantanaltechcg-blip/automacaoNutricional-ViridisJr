<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AddedSugarsFormulationValidation;
use App\Models\AddedSugarsFormulationVersion;

class AddedSugarsRegulatoryGateService
{
    /**
     * @return array{
     *   authorized: bool,
     *   status: string,
     *   validation: ?AddedSugarsFormulationValidation,
     *   causes: list<array{cause_type: string, cause_code: string, detail: ?string}>
     * }
     */
    public function evaluateVersionValidation(AddedSugarsFormulationVersion $version): array
    {
        $version->loadMissing('validations');
        /** @var ?AddedSugarsFormulationValidation $latest */
        $latest = $version->validations->last();

        if (! $latest instanceof AddedSugarsFormulationValidation) {
            return [
                'authorized' => false,
                'status' => 'PENDING',
                'validation' => null,
                'causes' => [[
                    'cause_type' => 'VALIDATION_BLOCK',
                    'cause_code' => 'FORMULATION_VALIDATION_PENDING',
                    'detail' => 'A versão da formulação ainda não possui validação técnica registrada.',
                ]],
            ];
        }

        $status = $latest->decision_status;
        $causes = [];

        if ($status === AddedSugarsFormulationValidation::STATUS_REJECTED) {
            $causes[] = [
                'cause_type' => 'VALIDATION_BLOCK',
                'cause_code' => 'FORMULATION_VALIDATION_REJECTED',
                'detail' => $latest->notes,
            ];

            return [
                'authorized' => false,
                'status' => $status,
                'validation' => $latest,
                'causes' => $causes,
            ];
        }

        if ($status === AddedSugarsFormulationValidation::STATUS_REVOKED) {
            $causes[] = [
                'cause_type' => 'VALIDATION_BLOCK',
                'cause_code' => 'FORMULATION_VALIDATION_REVOKED',
                'detail' => $latest->notes,
            ];

            return [
                'authorized' => false,
                'status' => $status,
                'validation' => $latest,
                'causes' => $causes,
            ];
        }

        if ($status !== AddedSugarsFormulationValidation::STATUS_AUTHORIZED) {
            $causes[] = [
                'cause_type' => 'VALIDATION_BLOCK',
                'cause_code' => 'FORMULATION_VALIDATION_NOT_AUTHORIZED',
                'detail' => 'Estado de validação não autorizado.',
            ];

            return [
                'authorized' => false,
                'status' => $status,
                'validation' => $latest,
                'causes' => $causes,
            ];
        }

        $expectedScope = $this->expectedScope($version);

        if ($latest->evidence_scope !== $expectedScope) {
            $causes[] = [
                'cause_type' => 'VALIDATION_BLOCK',
                'cause_code' => 'FORMULATION_VALIDATION_SCOPE_MISMATCH',
                'detail' => "Escopo esperado: {$expectedScope}.",
            ];
        }

        if (
            $latest->evidence_type === AddedSugarsFormulationValidation::EVIDENCE_TEST_FIXTURE
            && ! app()->environment('testing')
        ) {
            $causes[] = [
                'cause_type' => 'VALIDATION_BLOCK',
                'cause_code' => 'TEST_FIXTURE_NOT_ALLOWED',
                'detail' => 'Evidência TEST_FIXTURE é restrita exclusivamente ao ambiente testing.',
            ];
        }

        if (
            ! $this->nonEmpty($latest->normative_source)
            || ! $this->nonEmpty($latest->normative_version)
            || ! $this->nonEmpty($latest->evidence_reference)
            || ! $this->nonEmpty($latest->evidence_source)
            || ! $this->nonEmpty($latest->evidence_version)
            || ! $this->nonEmpty($latest->validated_by)
        ) {
            $causes[] = [
                'cause_type' => 'VALIDATION_BLOCK',
                'cause_code' => 'FORMULATION_VALIDATION_EVIDENCE_INCOMPLETE',
                'detail' => 'Metadados obrigatórios de evidência da validação técnica estão incompletos.',
            ];
        }

        return [
            'authorized' => $causes === [],
            'status' => $status,
            'validation' => $latest,
            'causes' => $causes,
        ];
    }

    public function expectedScope(AddedSugarsFormulationVersion $version): string
    {
        return "recipe:{$version->recipe_id}/added-sugars-formulation:v{$version->version_number}";
    }

    private function nonEmpty(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
