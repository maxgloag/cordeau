// TODO: migrate console.log to Sentry breadcrumbs en Phase 4+
import { useEffect } from "react";
import { AppState } from "react-native";
import * as Network from "expo-network";
import { useQueryClient } from "@tanstack/react-query";
import { processOutbox } from "@/db/outbox";
import { processPhotoOutbox } from "@/db/photoOutbox";
import { refreshAll } from "@/lib/sync";
import { useNetworkStatus } from "./useNetworkStatus";

const POLL_INTERVAL_MS = 5_000;

async function syncIfOnline(
  queryClient: ReturnType<typeof useQueryClient>,
  trigger: string,
) {
  const ns = await Network.getNetworkStateAsync().catch(() => null);
  console.log(
    `[sync] trigger=${trigger} connected=${ns?.isConnected ?? "unknown"}`,
  );
  if (!ns?.isConnected) return;
  await processOutbox(queryClient).catch((e) =>
    console.log("[sync] processOutbox error", e),
  );
  await processPhotoOutbox().catch((e) =>
    console.log("[sync] processPhotoOutbox error", e),
  );
  await refreshAll(queryClient).catch((e) =>
    console.log("[sync] refreshAll error", e),
  );
}

export function useSyncWorker() {
  const queryClient = useQueryClient();
  const isConnected = useNetworkStatus();

  useEffect(() => {
    if (!isConnected) return;
    void syncIfOnline(queryClient, "network-state-change");
  }, [isConnected, queryClient]);

  useEffect(() => {
    const sub = AppState.addEventListener("change", (state) => {
      if (state !== "active") return;
      void syncIfOnline(queryClient, "appstate-active");
    });
    return () => sub.remove();
  }, [queryClient]);

  useEffect(() => {
    const interval = setInterval(() => {
      void syncIfOnline(queryClient, "poll");
    }, POLL_INTERVAL_MS);
    return () => clearInterval(interval);
  }, [queryClient]);
}
