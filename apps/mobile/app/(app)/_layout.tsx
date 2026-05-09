import { Stack } from "expo-router";

export default function AppLayout() {
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
    </Stack>
  );
}
