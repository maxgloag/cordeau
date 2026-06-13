import { fetchChantiers, fetchPhotos, listClients } from "./api";
import {
  getPhotosForChantier,
  reconcilePhotos,
  upsertChantiers,
  upsertClients,
} from "@/db/queries";
import type { QueryClient } from "@tanstack/react-query";

export async function refreshChantiers(
  queryClient: QueryClient,
): Promise<void> {
  const items = await fetchChantiers();
  upsertChantiers(items);
  queryClient.setQueryData(["chantiers"], items);
}

export async function refreshClients(queryClient: QueryClient): Promise<void> {
  const items = await listClients();
  upsertClients(items);
  queryClient.setQueryData(["clients"], items);
}

export async function refreshPhotos(
  queryClient: QueryClient,
  chantierId: string,
): Promise<void> {
  const remote = await fetchPhotos(chantierId);
  reconcilePhotos(chantierId, remote);
  // On renvoie les lignes locales post-réconciliation (et non `remote`) pour
  // conserver les photos "local" encore en cours d'upload via l'outbox.
  queryClient.setQueryData(
    ["photos", chantierId],
    getPhotosForChantier(chantierId),
  );
}

let isRefreshing = false;

export async function refreshAll(queryClient: QueryClient): Promise<void> {
  if (isRefreshing) return;
  isRefreshing = true;
  try {
    await Promise.all([
      refreshChantiers(queryClient),
      refreshClients(queryClient),
    ]);
  } finally {
    isRefreshing = false;
  }
}
