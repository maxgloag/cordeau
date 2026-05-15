import { useEffect } from "react";
import { AppState } from "react-native";
import { useQueryClient } from "@tanstack/react-query";
import { processOutbox } from "@/db/outbox";
import { refreshAll } from "@/lib/sync";
import { useNetworkStatus } from "./useNetworkStatus";

export function useSyncWorker() {
  const queryClient = useQueryClient();
  const isConnected = useNetworkStatus();

  useEffect(() => {
    if (!isConnected) return;
    void (async () => {
      await processOutbox(queryClient).catch(() => {});
      await refreshAll(queryClient).catch(() => {});
    })();
  }, [isConnected, queryClient]);

  useEffect(() => {
    const sub = AppState.addEventListener("change", (state) => {
      if (state !== "active" || !isConnected) return;
      void (async () => {
        await processOutbox(queryClient).catch(() => {});
        await refreshAll(queryClient).catch(() => {});
      })();
    });
    return () => sub.remove();
  }, [isConnected, queryClient]);
}
