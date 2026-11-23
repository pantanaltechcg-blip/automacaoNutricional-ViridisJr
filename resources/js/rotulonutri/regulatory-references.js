/**
 * Resolvedor de referências regulatórias (CQ-002).
 * O backend atual não disponibiliza endpoint ou mecanismo para listar/obter
 * portion_authorization_id, regulatory_profile_id ou regulatory_declaration_profile_id.
 * Nenhum identificador deve ser criado, fixado, inferido ou mockado aqui.
 *
 * @returns {Promise<{ available: false, cause: string } | { available: true, portionAuthorizationId: number, regulatoryProfileId: number, regulatoryDeclarationProfileId: number }>}
 */
export async function resolveRegulatoryReferences() {
    return {
        available: false,
        cause: 'Etapa bloqueada: Feature 004 — referências regulatórias (autorização de porção e perfil regulatório) não disponibilizadas pelo sistema.',
    };
}
