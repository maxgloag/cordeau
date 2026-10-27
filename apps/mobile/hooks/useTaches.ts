import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  deleteTacheLocal,
  getTachesForLot,
  insertTacheLocal,
  setTacheFaiteLocal,
  setTacheLibelleLocal,
} from "@/db/lotQueries";
import { refreshTaches } from "@/lib/sync";
import type { Tache } from "@/lib/api";
import { prochainOrdre } from "@/lib/lot";
import { useNetworkStatus } from "./useNetworkStatus";
import { useOfflineMutation } from "./useOfflineMutation";

type CreerPayload = { lotId: string; libelle: string; ordre: number };
type ModifierPayload = { lotId: string; faite?: boolean; libelle?: string };

/**
 * Tâches d'un lot : mêmes principes que useLots. Les gestes (cocher, ajouter,
 * supprimer) s'enchaînent vite sur des lignes différentes, donc sans anti double-envoi.
 */
export function useTaches(lotId: string) {
  const queryClient = useQueryClient();
  const isConnected = useNetworkStatus();
  const queryKey = ["taches", lotId];

  const { data: taches = [] } = useQuery({
    queryKey,
    queryFn: () => {
      const local = getTachesForLot(lotId);
      if (isConnected) {
        void refreshTaches(queryClient, lotId).catch(() => undefined);
      }
      return local;
    },
  });

  const relire = (): void => {
    queryClient.setQueryData(queryKey, getTachesForLot(lotId));
  };

  const { mutate: creer } = useOfflineMutation<CreerPayload>({
    entityType: "tache",
    operation: "create",
    guard: false,
    buildLocal: (id, p) => {
      insertTacheLocal({
        id,
        lotId: p.lotId,
        libelle: p.libelle,
        faite: false,
        faiteLe: null,
        ordre: p.ordre,
      });
      relire();
    },
  });

  const { mutate: modifier } = useOfflineMutation<ModifierPayload>({
    entityType: "tache",
    operation: "update",
    guard: false,
    buildLocal: (id, p) => {
      if (p.faite !== undefined) setTacheFaiteLocal(id, p.faite);
      if (p.libelle !== undefined) setTacheLibelleLocal(id, p.libelle);
      relire();
    },
  });

  const { mutate: supprimer } = useOfflineMutation<{ lotId: string }>({
    entityType: "tache",
    operation: "delete",
    guard: false,
    buildLocal: (id) => {
      deleteTacheLocal(id);
      relire();
    },
  });

  return {
    taches,
    ajouterTache: (libelle: string): string | undefined =>
      creer({
        lotId,
        libelle,
        ordre: prochainOrdre(getTachesForLot(lotId)),
      }),
    basculerTache: (tache: Tache): void => {
      modifier({ lotId, faite: !tache.faite }, tache.id);
    },
    renommerTache: (tacheId: string, libelle: string): void => {
      modifier({ lotId, libelle }, tacheId);
    },
    supprimerTache: (tacheId: string): void => {
      supprimer({ lotId }, tacheId);
    },
  };
}
