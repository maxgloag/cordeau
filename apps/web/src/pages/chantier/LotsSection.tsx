import {
  useMutation,
  useQueries,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { toast } from "sonner";
import {
  creerLot,
  creerTache,
  fetchLots,
  fetchTaches,
  modifierLot,
  modifierTache,
  supprimerLot,
  supprimerTache,
} from "@/lib/api";
import type { Tache } from "@/lib/api";
import { LotListView } from "./LotListView";
import { TacheListView } from "./TacheListView";

const MESSAGE_ERREUR = "L'opération a échoué. Réessayez.";

export function LotsSection({ chantierId }: { chantierId: string }) {
  const queryClient = useQueryClient();
  const lotsKey = ["chantiers", chantierId, "lots"] as const;
  const tachesKey = (lotId: string) => [...lotsKey, lotId, "taches"] as const;

  const lotsQuery = useQuery({
    queryKey: lotsKey,
    queryFn: () => fetchLots(chantierId),
  });
  const lots = lotsQuery.data ?? [];

  const tachesQueries = useQueries({
    queries: lots.map((lot) => ({
      queryKey: tachesKey(lot.id),
      queryFn: () => fetchTaches(lot.id),
    })),
  });
  const tachesParLot = new Map(
    lots.map((lot, i) => [lot.id, tachesQueries[i]] as const),
  );

  const onError = () => toast.error(MESSAGE_ERREUR);
  const refreshLots = () =>
    queryClient.invalidateQueries({ queryKey: lotsKey });
  const refreshTaches = (lotId: string) =>
    queryClient.invalidateQueries({ queryKey: tachesKey(lotId) });

  const createLot = useMutation({
    mutationFn: (v: Parameters<typeof creerLot>[1]) => creerLot(chantierId, v),
    onSuccess: refreshLots,
    onError,
  });
  const updateLot = useMutation({
    mutationFn: ({
      id,
      ...payload
    }: { id: string } & Parameters<typeof modifierLot>[1]) =>
      modifierLot(id, payload),
    onSuccess: refreshLots,
    onError,
  });
  const deleteLot = useMutation({
    mutationFn: supprimerLot,
    onSuccess: refreshLots,
    onError,
  });

  const addTache = useMutation({
    mutationFn: ({
      lotId,
      libelle,
      ordre,
    }: {
      lotId: string;
      libelle: string;
      ordre: number;
    }) => creerTache(lotId, { libelle, ordre }),
    onSuccess: (_, { lotId }) => refreshTaches(lotId),
    onError,
  });
  const patchTache = useMutation({
    mutationFn: ({
      tache,
      ...payload
    }: {
      tache: Tache;
      faite?: boolean;
      libelle?: string;
    }) => modifierTache(tache.id, payload),
    onSuccess: (_, { tache }) => refreshTaches(tache.lotId),
    onError,
  });
  const removeTache = useMutation({
    mutationFn: (tache: Tache) => supprimerTache(tache.id),
    onSuccess: (_, tache) => refreshTaches(tache.lotId),
    onError,
  });

  return (
    <LotListView
      lots={lots}
      isLoading={lotsQuery.isLoading}
      isError={lotsQuery.isError}
      onCreate={(v) =>
        createLot.mutate({
          nom: v.nom,
          mode: v.mode,
          estimation: v.estimation,
          ordre: lots.length,
        })
      }
      onUpdate={(lot, v) =>
        updateLot.mutate({
          id: lot.id,
          nom: v.nom,
          mode: v.mode,
          // Changer le mode efface l'estimation côté API : ne l'envoyer que si le mode est inchangé.
          ...(v.mode === lot.mode ? { estimation: v.estimation } : {}),
        })
      }
      onDelete={(lot) => deleteLot.mutate(lot.id)}
      renderTaches={(lot) => {
        const query = tachesParLot.get(lot.id);
        const taches = query?.data ?? [];
        return (
          <TacheListView
            lotNom={lot.nom}
            taches={taches}
            isLoading={query?.isLoading ?? true}
            onToggle={(t) => patchTache.mutate({ tache: t, faite: !t.faite })}
            onRename={(t, libelle) => patchTache.mutate({ tache: t, libelle })}
            onDelete={(t) => removeTache.mutate(t)}
            onAdd={(libelle) =>
              addTache.mutate({ lotId: lot.id, libelle, ordre: taches.length })
            }
          />
        );
      }}
    />
  );
}
