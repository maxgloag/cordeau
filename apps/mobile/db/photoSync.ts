import type { PhotoStatus } from "./schema";

export type LocalPhotoSummary = { id: string; status: PhotoStatus };
export type RemotePhotoSummary = { id: string };

/**
 * Décide quelles photos locales supprimer lors d'une réconciliation avec le serveur.
 *
 * Règle critique (perte de données terrain) : ne JAMAIS supprimer une photo non
 * encore confirmée par le serveur (status "local", upload en cours via l'outbox).
 * On ne supprime qu'une photo déjà "confirmed" localement mais absente de la liste
 * distante — c'est-à-dire supprimée depuis une autre surface (web, autre device).
 */
export function planPhotoDeletions(
  local: LocalPhotoSummary[],
  remote: RemotePhotoSummary[],
): string[] {
  const remoteIds = new Set(remote.map((p) => p.id));
  return local
    .filter((p) => p.status === "confirmed" && !remoteIds.has(p.id))
    .map((p) => p.id);
}
