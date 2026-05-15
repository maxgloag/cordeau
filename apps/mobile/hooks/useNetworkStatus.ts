// TODO: migrate console.log to Sentry breadcrumbs en Phase 4+
import { useEffect, useState } from "react";
import * as Network from "expo-network";

export function useNetworkStatus() {
  const [isConnected, setIsConnected] = useState(true);

  useEffect(() => {
    let cancelled = false;

    function updateIfChanged(next: boolean) {
      setIsConnected((prev) => {
        if (prev === next) return prev;
        console.log(`[network] isConnected ${prev} -> ${next}`);
        return next;
      });
    }

    async function check() {
      const state = await Network.getNetworkStateAsync();
      if (!cancelled) updateIfChanged(state.isConnected ?? false);
    }

    void check();

    const sub = Network.addNetworkStateListener((state) => {
      if (cancelled) return;
      updateIfChanged(state.isConnected ?? false);
    });

    return () => {
      cancelled = true;
      sub.remove();
    };
  }, []);

  return isConnected;
}
