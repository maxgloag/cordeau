import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  RefreshControl,
} from "react-native";
import { useRouter } from "expo-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Plus, User, Mail, Phone } from "lucide-react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import type { Client } from "@/lib/api";
import { getAllClients } from "@/db/queries";
import { refreshClients } from "@/lib/sync";
import { useNetworkStatus } from "@/hooks/useNetworkStatus";
import { SyncStatusBar } from "@/components/SyncStatusBar";

function ClientCard({ item, onPress }: { item: Client; onPress: () => void }) {
  return (
    <TouchableOpacity
      className="bg-surface rounded-xl mb-3 overflow-hidden shadow-sm border border-border"
      onPress={onPress}
      activeOpacity={0.7}
    >
      <View className="px-4 py-3.5">
        <View className="flex-row items-center gap-2 mb-1">
          <User size={14} color="#B85C2A" />
          <Text
            className="text-base text-text flex-1"
            style={{ fontFamily: "BricolageGrotesque_700Bold" }}
            numberOfLines={1}
          >
            {item.nom}
          </Text>
        </View>
        <Text className="text-sm text-muted" numberOfLines={1}>
          {item.adresseRue}, {item.adresseCodePostal} {item.adresseVille}
        </Text>
        {(item.email ?? item.telephone) ? (
          <View className="flex-row gap-3 mt-2">
            {item.email && (
              <View className="flex-row items-center gap-1">
                <Mail size={11} color="#9B8F85" />
                <Text className="text-xs text-muted" numberOfLines={1}>
                  {item.email}
                </Text>
              </View>
            )}
            {item.telephone && (
              <View className="flex-row items-center gap-1">
                <Phone size={11} color="#9B8F85" />
                <Text className="text-xs text-muted">{item.telephone}</Text>
              </View>
            )}
          </View>
        ) : null}
      </View>
    </TouchableOpacity>
  );
}

export default function ClientsListScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const isConnected = useNetworkStatus();
  const [isSyncing, setIsSyncing] = useState(false);

  const { data: clients = [] } = useQuery({
    queryKey: ["clients"],
    queryFn: () => {
      const local = getAllClients();
      if (isConnected) void refreshClients(queryClient);
      return local;
    },
    staleTime: Infinity,
  });

  function handleRefresh() {
    if (!isConnected || isSyncing) return;
    setIsSyncing(true);
    void refreshClients(queryClient).finally(() => setIsSyncing(false));
  }

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["top"]}>
      <View className="px-5 pt-4 pb-3 flex-row items-center justify-between">
        <View>
          <Text
            className="text-2xl text-text"
            style={{ fontFamily: "BricolageGrotesque_700Bold" }}
          >
            Clients
          </Text>
          <Text className="text-sm text-muted mt-0.5">
            {clients.length} client{clients.length !== 1 ? "s" : ""}
          </Text>
        </View>
        <TouchableOpacity
          onPress={() => router.back()}
          className="px-3 py-1.5 rounded-lg border border-border"
        >
          <Text className="text-sm text-muted">Retour</Text>
        </TouchableOpacity>
      </View>

      <SyncStatusBar />

      {clients.length === 0 ? (
        <View className="flex-1 items-center justify-center px-6">
          <Text
            className="text-xl text-text mb-2"
            style={{ fontFamily: "BricolageGrotesque_700Bold" }}
          >
            Aucun client
          </Text>
          <Text className="text-base text-muted text-center">
            Appuyez sur + pour ajouter votre premier client.
          </Text>
        </View>
      ) : (
        <FlatList
          data={clients}
          keyExtractor={(item) => item.id}
          renderItem={({ item }) => (
            <ClientCard
              item={item}
              onPress={() => router.push(`/(app)/clients/${item.id}`)}
            />
          )}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 100 }}
          refreshControl={
            <RefreshControl
              refreshing={isSyncing}
              onRefresh={handleRefresh}
              tintColor="#B85C2A"
            />
          }
          showsVerticalScrollIndicator={false}
        />
      )}

      <TouchableOpacity
        className="absolute bottom-8 right-5 w-14 h-14 bg-primary rounded-full items-center justify-center shadow-lg"
        onPress={() => router.push("/(app)/clients/new")}
        activeOpacity={0.85}
        style={{
          shadowColor: "#B85C2A",
          shadowOffset: { width: 0, height: 4 },
          shadowOpacity: 0.35,
          shadowRadius: 8,
          elevation: 8,
        }}
      >
        <Plus size={26} color="#FFFFFF" strokeWidth={2.5} />
      </TouchableOpacity>
    </SafeAreaView>
  );
}
