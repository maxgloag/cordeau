import { Stack } from "expo-router";
import { useSyncWorker } from "@/hooks/useSyncWorker";

export default function AppLayout() {
  useSyncWorker();

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: "#1C1813" },
        headerTintColor: "#F2EDE4",
        headerTitleStyle: {
          fontFamily: "BricolageGrotesque_700Bold",
          fontSize: 17,
        },
        headerBackTitle: "Retour",
        headerShown: false,
      }}
    >
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen
        name="chantiers/new"
        options={{
          headerShown: true,
          title: "Nouveau chantier",
          presentation: "modal",
        }}
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
        options={{
          headerShown: true,
          title: "Nouveau client",
          presentation: "modal",
        }}
      />
      <Stack.Screen
        name="clients/[id]"
        options={{ headerShown: true, title: "Client" }}
      />
    </Stack>
  );
}
