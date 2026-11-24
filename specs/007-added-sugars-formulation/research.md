# Research: Added Sugars Formulation Obtaining and Validation (007-added-sugars-formulation)

**Date**: 2026-11-23  
**Feature**: `007-added-sugars-formulation`  
**Spec**: [spec.md](./spec.md)

## 1. Anchor & Immutability Between Recipe, Formulation Version, and Feature 003 Execution

- **Decision**: Anchor `AddedSugarsFormulation` (versioned via immutable `AddedSugarsFormulationVersion` + `AddedSugarsFormulationIngredient` + `AddedSugarsFormulationEvidence`) to `recipes.id`, and anchor each immutable `AddedSugarsEvaluation` snapshot to both a specific `AddedSugarsFormulationVersion` and a single immutable `NutritionCalculation` (Feature 003) belonging to the same `recipe_id`.
- **Rationale**: `Recipe` is mutable (`PUT /api/recipes/{recipe}`), whereas `NutritionCalculation` is an immutable snapshot of recipe ingredients, quantities (`g`), and yield (`yield_value`, `yield_unit`). Evaluating added sugars against a `NutritionCalculation` guarantees that ingredient quantities and yield match the exact snapshot consumed by Feature 004 (`ServingDailyValueCalculation`), eliminating drift between nutrition calculations and added sugars evaluations.
- **Alternatives considered**:
  - Snapshotting `Recipe` directly during evaluation without linking to `NutritionCalculation`: rejected because it would require fuzzy matching of ingredient sets and yields when Feature 004 consumes the evaluation.
  - Anchoring formulations exclusively to `NutritionCalculation`: rejected because re-running a nutrition calculation would force duplicating formulation metadata and evidences unnecessarily.

## 2. Representing Ingredient Added Sugars (Simple & Compound/Industrialized)

- **Decision**: Support two ingredient types (`SIMPLE`, `COMPOUND_INDUSTRIALIZED`), four regulatory classifications (`INCLUDED`, `EXCLUDED_NORMATIVE`, `MISSING`, `CONFLICTING`), and two exact input modes for `INCLUDED` items:
  - `PER_100G`: exact decimal string `amount_value` representing grams of added sugars per `100 g` of the ingredient (`0 <= amount_value <= 100`), multiplied by `ingredient_quantity / 100` using `BigRational`.
  - `DIRECT_BATCH_GRAMS`: exact decimal string `amount_value` representing grams of added sugars contributed directly by that ingredient in the batch (`0 <= amount_value <= ingredient_quantity`), used directly as a `BigRational` ratio.
- **Rationale**: Covers both simple ingredients (e.g., sucrose at `100 g / 100 g`, honey, syrups, or normatively excluded whole fruit) and compound/industrialized ingredients with manufacturer technical sheets declaring added sugars per `100 g` or per batch quantity. Enforcing physical upper bounds (`<= 100` for `PER_100G` and `<= ingredient_quantity` for `DIRECT_BATCH_GRAMS`) prevents physically impossible values while preserving exact `0` when validated.
- **Alternatives considered**:
  - Supporting `PER_100ML` ingredient declarations: rejected for v1 because `recipe_ingredients.unit` is strictly `g` (`FR-005` prohibits implicit mass-to-volume density conversions).
  - Creating a synthetic `Nutrient` row in `nutrients` (TACO): strictly prohibited by `FR-001`, `specs/backlog/added-sugars-formulation-validation.md`, and Constitution Principle V.

## 3. Strict Deny-by-Default Validation & Append-Only Decision Trail

- **Decision**: Persist technical validation decisions as append-only immutable records in `added_sugars_formulation_validations` referencing `added_sugars_formulation_versions.id`, with statuses `AUTHORIZED`, `REJECTED`, and `REVOKED`. A formulation version starts with no validation decision (effective status `PENDING`). Eligibility for regulatory use requires:
  1. The latest append-only validation record for that formulation version has `decision_status = 'AUTHORIZED'`;
  2. Non-empty `normative_source`, `normative_version`, `validated_by`, `validated_on`, `evidence_type`, `evidence_reference`, `evidence_source`, `evidence_version`, and exact scope matching `recipe:{recipe_id}/added-sugars-formulation:v{version_number}`;
  3. If `evidence_type = 'TEST_FIXTURE'`, authorization is accepted ONLY when `app()->environment('testing')` is true (identical to `ServingRegulatoryGateService` and `DeclaredValuesRegulatoryGateService`);
  4. Every ingredient preserved in the target `NutritionCalculation` is mapped unambiguously in the formulation version, has at least one non-conflicting evidence record (excluding unvalidated operational-only forms when not backed by technical validation), and has classification `INCLUDED` or `EXCLUDED_NORMATIVE` (none `MISSING` or `CONFLICTING`).
- **Rationale**: Mirrors the proven `deny-by-default` gate architecture of Features 004 and 005 while keeping formulation versions immutable and avoiding duplicating all ingredient rows just to record a validation decision.
- **Alternatives considered**:
  - Mutating a status column on `added_sugars_formulation_versions`: rejected because it violates append-only immutability and erases decision chronology.
  - Allowing operational Google Forms input to auto-authorize: strictly prohibited by `FR-003`, `FR-007`, and Constitution 2.0.0.

## 4. Exact Aggregation and Strict Null on Pending/Incomplete/Conflicting Ingredients

- **Decision**: During `AddedSugarsEvaluation`, compute and store exact rational contributions (`contribution_numerator` / `contribution_denominator`) for each individual ingredient that has a valid numeric or normatively excluded classification (`EXCLUDED_NORMATIVE` contributes `0/1` with `is_excluded_normative = true`, distinct from `MISSING`/`CONFLICTING`). However, compute `total_numerator/denominator` and `reference_numerator/denominator` (`total × 100 ÷ yield_value`, in `g` per `100 g` or `100 ml` according to `source.yield_unit`) **ONLY** when:
  - All ingredients of the `NutritionCalculation` are covered without mismatch;
  - No ingredient is `MISSING`, `CONFLICTING`, or physically inconsistent;
  - The formulation version is technically `AUTHORIZED` (not `PENDING`, `REJECTED`, `REVOKED`, or `TEST_FIXTURE` outside `testing`).
  Whenever any blocking condition exists, `total_numerator`, `total_denominator`, `reference_numerator`, and `reference_denominator` remain strictly `null`, `is_complete = false`, `is_regulatory_eligible = false`, and all blocking causes are recorded in `added_sugars_evaluation_causes`.
- **Rationale**: Satisfies `CQ-005`, `FR-006`, and `FR-011`: auditors can inspect per-ingredient contributions and exact blocking causes on persisted evaluations, while the aggregate batch and `100 g/100 ml` values are never exposed as partial subtotals or converted to zero.
- **Alternatives considered**:
  - Returning a partial aggregate subtotal: explicitly rejected in `CQ-005` (`Q2: A`).
  - Blocking persistence of incomplete evaluations: rejected because auditors need persisted traceability of pending/conflicting evaluations.

## 5. Retrocompatible Integration with Feature 004 (`MAP-004`/`VDR-004`) and Feature 005

- **Decision**:
  - Add an optional nullable integer parameter `added_sugars_evaluation_id` to `POST /api/nutrition-calculations/{nutritionCalculation}/serving-daily-value-calculations` (`StoreServingDailyValueCalculationRequest`).
  - Add nullable snapshot columns to `serving_daily_value_calculations` (referencing `added_sugars_evaluations.id` with `ON DELETE RESTRICT` + snapshot of version, eligibility, validation status, reference `100 g/100 ml` ratio, and evidence) and nullable formulation reference ratio columns (`source_reference_numerator`, `source_reference_denominator`, `value_source_type`) to `serving_daily_value_regulatory_results`.
  - If `added_sugars_evaluation_id` is omitted, Feature 004 and Feature 005 behave 100% identically to existing tests.
  - If `added_sugars_evaluation_id` is provided:
    - Reject with HTTP `422` if `evaluation.nutrition_calculation_id !== source.id`;
    - For `MAP-004`: if the evaluation is `is_regulatory_eligible = true` and `profile` is authorized and `MAP-004` exists in the profile with `regulatory_unit = 'g'`, compute portion value `total × portion_value ÷ yield_value` via `NutritionCalculationArithmetic`, populate `MAP-004` as `AVAILABLE` (`is_complete = true`) with `value_source_type = 'ADDED_SUGARS_FORMULATION'` and snapshot `source_reference_numerator/denominator`, allowing `VDR-004` to calculate `%VD` if and only if `VDR-004` itself is technically authorized (and never adding any energy factor).
    - If the evaluation is not eligible, keep `MAP-004` and `VDR-004` as `UNAVAILABLE` (`null`) and copy inherited blocking causes into `serving_daily_value_result_causes`.
  - Update `DeclaredNutritionCalculationService` (Feature 005) and `DeclaredValuesRegulatoryGateService` so that when `MAP-004` has `value_source_type = 'ADDED_SUGARS_FORMULATION'` and eligible snapshot data on the Feature 004 source, Feature 005 consumes `source_reference_numerator/denominator` (for `REFERENCE_100`) and `value_numerator/denominator` (for `PORTION`) directly without requiring a `FoodNutrient`/`Nutrient` row, while enforcing all Feature 005 NSQ, rounding, `%VD` recalculation, and `is_label_ready` gates.
- **Rationale**: Satisfies `CQ-003`, `CQ-007`, `FR-009`, and `FR-010` with zero breaking changes to existing Features 001–006 and zero synthetic TACO rows.
- **Alternatives considered**:
  - Requiring a `nutrient_id` in `foods`/`food_nutrients`: violates `FR-001` and `specs/backlog/added-sugars-formulation-validation.md`.
  - Blocking Feature 004 creation when the referenced evaluation is incomplete: rejected in `CQ-007` (`Q4: A`).
