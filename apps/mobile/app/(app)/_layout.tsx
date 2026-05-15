import { useEffect } from "react";
import { Stack } from "expo-router";
import { useQueryClient } from "@tanstack/react-query";
import { getAllChantiers, getAllClients } from "@/db/queries";
import { refreshChantiers, refreshClients } from "@/lib/sync";
import { useNetworkStatus } from "@/hooks/useNetworkStatus";

export default function AppLayout() {
  const queryClient = useQueryClient();
  const isConnected = useNetworkStatus();

  useEffect(() => {
    if (!isConnected) return;
    const promises: Promise<void>[] = [];
    if (getAllChantiers().length === 0) promises.push(refreshChantiers(queryClient));
    if (getAllClients().length === 0) promises.push(refreshClients(queryClient));
    if (promises.length > 0) void Promise.all(promises);
  }, [isConnected, queryClient]);

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: "#1C1813" },
        headerTintColor: "#F2EDE4",
        headerTitleStyle: { fontFamily: "BricolageGrotesque_700Bold", fontSize: 17 },
        headerBackTitle: "Retour",
        headerShown: false,
      }}
    >
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen
        name="chantiers/new"
        options={{ headerShown: true, title: "Nouveau chantier", presentation: "modal" }}
      />
      <Stack.Screen
        name="chantiers/[id]"
        options={{ headerShown: true, title: "Chantier" }}
      />
      <Stack.Screen
        name="clients/index"
        options={{ headerShown: true, title: "Clients" }}
      />
      <Stack.Screen
        name="clients/new"
        options={{ headerShown: true, title: "Nouveau client", presentation: "modal" }}
      />
      <Stack.Screen
        name="clients/[id]"
        options={{ headerShown: true, title: "Client" }}
      />
    </Stack>
  );
}
