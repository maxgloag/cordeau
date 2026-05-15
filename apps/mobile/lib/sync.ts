import { fetchChantiers, listClients } from "./api";
import { getAllChantiers, upsertChantiers, getAllClients, upsertClients } from "@/db/queries";
import type { QueryClient } from "@tanstack/react-query";

export async function refreshChantiers(queryClient: QueryClient): Promise<void> {
  const items = await fetchChantiers();
  upsertChantiers(items);
  queryClient.setQueryData(["chantiers"], items);
}

export async function refreshClients(queryClient: QueryClient): Promise<void> {
  const items = await listClients();
  upsertClients(items);
  queryClient.setQueryData(["clients"], items);
}

export async function refreshAll(queryClient: QueryClient): Promise<void> {
  await Promise.all([refreshChantiers(queryClient), refreshClients(queryClient)]);
}
