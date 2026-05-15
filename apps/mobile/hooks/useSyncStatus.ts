import { useEffect, useState } from "react";
import { getPendingCount } from "@/db/outbox";
import { useNetworkStatus } from "./useNetworkStatus";

export type SyncStatus = "offline" | "pending" | "synced";

const POLL_MS = 2000;

export function useSyncStatus(): { status: SyncStatus; pendingCount: number } {
  const isConnected = useNetworkStatus();
  const [pendingCount, setPendingCount] = useState(0);

  useEffect(() => {
    const tick = () => {
      const next = getPendingCount();
      setPendingCount((prev) => (prev === next ? prev : next));
    };
    tick();
    const interval = setInterval(tick, POLL_MS);
    return () => clearInterval(interval);
  }, []);

  const status: SyncStatus = !isConnected ? "offline" : pendingCount > 0 ? "pending" : "synced";

  return { status, pendingCount };
}
