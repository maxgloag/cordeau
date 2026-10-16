/**
 * Règles pures de synchronisation Lot / Tâche (ADR 0012) : dépendance d'ordre dans
 * l'outbox et réconciliation avec le serveur. Sans accès base ni réseau.
 */

type EntreeDependante = {
  entityType: string;
  entityId: string;
  payload: string;
};

/**
 * Identifiant du parent dont l'entrée dépend : le chantier pour un lot, le lot pour
 * une tâche. Le parent doit exister côté serveur avant l'enfant (URL imbriquée).
 */
export function parentDe(entry: EntreeDependante): string | null {
  const cle =
    entry.entityType === "lot"
      ? "chantierId"
      : entry.entityType === "tache"
        ? "lotId"
        : null;
  if (!cle) return null;
  try {
    const valeur = (JSON.parse(entry.payload) as Record<string, unknown>)[cle];
    return typeof valeur === "string" ? valeur : null;
  } catch {
    return null;
  }
}

/**
 * Une entrée est bloquée tant que son entité (create précédent) ou son parent n'est
 * pas synchronisé : l'envoyer maintenant donnerait un 404, abandonné définitivement.
 */
export function estBloquee(
  entry: EntreeDependante,
  nonSynchronises: ReadonlySet<string>,
): boolean {
  if (nonSynchronises.has(entry.entityId)) return true;
  const parent = parentDe(entry);
  return parent !== null && nonSynchronises.has(parent);
}

export type LigneLocale = { id: string; syncedAt: number | null };

/**
 * Lignes locales à supprimer après lecture de la liste serveur. On ne supprime que
 * ce que le serveur a déjà confirmé (syncedAt) et qui a disparu : une ligne créée
 * hors ligne (syncedAt nul) ou touchée par une entrée d'outbox en attente est conservée.
 */
export function planSuppressions(
  locales: LigneLocale[],
  distantes: { id: string }[],
  enAttente: ReadonlySet<string>,
): string[] {
  const idsDistants = new Set(distantes.map((d) => d.id));
  return locales
    .filter(
      (l) =>
        l.syncedAt !== null && !idsDistants.has(l.id) && !enAttente.has(l.id),
    )
    .map((l) => l.id);
}
