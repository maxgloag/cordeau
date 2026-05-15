import { useEffect } from "react";
import { AppState } from "react-native";
import * as Network from "expo-network";
import { useQueryClient } from "@tanstack/react-query";
import { processOutbox } from "@/db/outbox";
import { refreshAll } from "@/lib/sync";
import { useNetworkStatus } from "./useNetworkStatus";

async function syncIfOnline(queryClient: ReturnType<typeof useQueryClient>) {
  const ns = await Network.getNetworkStateAsync().catch(() => null);
  if (!ns?.isConnected) return;
  await processOutbox(queryClient).catch(() => {});
  await refreshAll(queryClient).catch(() => {});
}

export function useSyncWorker() {
  const queryClient = useQueryClient();
  const isConnected = useNetworkStatus();

  useEffect(() => {
    if (!isConnected) return;
    void syncIfOnline(queryClient);
  }, [isConnected, queryClient]);

  useEffect(() => {
    const sub = AppState.addEventListener("change", (state) => {
      if (state !== "active") return;
      void syncIfOnline(queryClient);
    });
    return () => sub.remove();
  }, [queryClient]);
}
