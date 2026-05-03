import { useEffect, useState } from "react";
import { ActivityIndicator, StyleSheet, Text, View } from "react-native";
import { StatusBar } from "expo-status-bar";
import type { HealthResponse } from "@cordeau/shared";

const API_URL = process.env["EXPO_PUBLIC_API_URL"] ?? "http://localhost:8000";

type FetchState =
  | { status: "loading" }
  | { status: "ok"; data: HealthResponse }
  | { status: "error" };

export default function App() {
  const [state, setState] = useState<FetchState>({ status: "loading" });

  useEffect(() => {
    let cancelled = false;

    async function checkHealth() {
      try {
        const res = await fetch(`${API_URL}/health`);
        const data = (await res.json()) as HealthResponse;
        if (!cancelled) setState({ status: "ok", data });
      } catch {
        if (!cancelled) setState({ status: "error" });
      }
    }

    void checkHealth();
    const interval = setInterval(() => void checkHealth(), 10_000);

    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, []);

  return (
    <View style={styles.container}>
      <StatusBar style="light" />

      <Text style={styles.title}>Cordeau API</Text>

      {state.status === "loading" && (
        <ActivityIndicator size="large" color="#4ade80" />
      )}

      {state.status === "error" && (
        <Text style={styles.error}>API injoignable</Text>
      )}

      {state.status === "ok" && (
        <View style={styles.card}>
          <View style={styles.row}>
            <View
              style={[
                styles.dot,
                {
                  backgroundColor:
                    state.data.status === "ok" ? "#4ade80" : "#facc15",
                },
              ]}
            />
            <Text style={styles.status}>{state.data.status}</Text>
          </View>
          <Text style={styles.meta}>
            v{state.data.version}
          </Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#030712",
    alignItems: "center",
    justifyContent: "center",
    gap: 20,
  },
  title: {
    fontSize: 22,
    fontWeight: "700",
    color: "#f9fafb",
    letterSpacing: -0.5,
  },
  card: {
    alignItems: "center",
    gap: 8,
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  dot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  status: {
    fontSize: 16,
    fontWeight: "600",
    color: "#f9fafb",
    textTransform: "capitalize",
  },
  meta: {
    fontSize: 13,
    color: "#9ca3af",
  },
  error: {
    fontSize: 15,
    color: "#f87171",
    fontWeight: "500",
  },
});
