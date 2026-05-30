import { fetchChantiers, listClients } from "./api";
import { upsertChantiers, upsertClients } from "@/db/queries";
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
