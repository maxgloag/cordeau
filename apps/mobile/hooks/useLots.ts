import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  deleteLotLocal,
  getLotsForChantier,
  insertLotLocal,
} from "@/db/lotQueries";
import { refreshLots } from "@/lib/sync";
import type { ModeFacturation } from "@/lib/api";
import { prochainOrdre } from "@/lib/lot";
import { useNetworkStatus } from "./useNetworkStatus";
import { useOfflineMutation } from "./useOfflineMutation";

type NouveauLot = {
  nom: string;
  mode: ModeFacturation;
  estimation: number | null;
};

type CreerPayload = NouveauLot & { chantierId: string; ordre: number };

/**
 * Lots d'un chantier : lecture SQLite immédiate, rafraîchissement API en arrière-plan
 * (ADR 0012), écritures optimistes via l'outbox. Un chantier sans lot est valide.
 */
export function useLots(chantierId: string) {
  const queryClient = useQueryClient();
  const isConnected = useNetworkStatus();
  const queryKey = ["lots", chantierId];

  const { data: lots = [] } = useQuery({
    queryKey,
    queryFn: () => {
      const local = getLotsForChantier(chantierId);
      if (isConnected) {
        void refreshLots(queryClient, chantierId).catch(() => undefined);
      }
      return local;
    },
  });

  const { mutate: creer } = useOfflineMutation<CreerPayload>({
    entityType: "lot",
    operation: "create",
    buildLocal: (id, p) => {
      insertLotLocal({
        id,
        chantierId: p.chantierId,
        nom: p.nom,
        mode: p.mode,
        estimation: p.estimation,
        reel: null,
        ordre: p.ordre,
      });
      queryClient.setQueryData(queryKey, getLotsForChantier(chantierId));
    },
  });

  const { mutate: supprimer } = useOfflineMutation<{ chantierId: string }>({
    entityType: "lot",
    operation: "delete",
    buildLocal: (id) => {
      deleteLotLocal(id);
      queryClient.removeQueries({ queryKey: ["taches", id] });
      queryClient.setQueryData(queryKey, getLotsForChantier(chantierId));
    },
  });

  return {
    lots,
    ajouterLot: (lot: NouveauLot): string | undefined =>
      creer({
        ...lot,
        chantierId,
        ordre: prochainOrdre(getLotsForChantier(chantierId)),
      }),
    supprimerLot: (lotId: string): void => {
      supprimer({ chantierId }, lotId);
    },
  };
}
