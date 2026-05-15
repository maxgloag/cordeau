import { useEffect, useState } from "react";
import * as Network from "expo-network";

export function useNetworkStatus() {
  const [isConnected, setIsConnected] = useState(true);

  useEffect(() => {
    let cancelled = false;

    async function check() {
      const state = await Network.getNetworkStateAsync();
      if (!cancelled) setIsConnected(state.isConnected ?? false);
    }

    void check();

    const interval = setInterval(() => void check(), 30_000);
    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, []);

  return isConnected;
}
