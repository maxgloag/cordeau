import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
} from "react-native";
import { useRouter } from "expo-router";
import { useQuery } from "@tanstack/react-query";
import { MapPin, Plus, Users, User } from "lucide-react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { useAuth } from "@/context/AuthContext";
import { fetchChantiers } from "@/lib/api";
import type { Chantier } from "@/lib/api";
import { STATUT_LABELS, STATUT_COLORS } from "@/lib/chantier";

function ChantierCard({ item, onPress }: { item: Chantier; onPress: () => void }) {
  const color = STATUT_COLORS[item.statut] ?? "#9CA3AF";

  return (
    <TouchableOpacity
      className="bg-surface rounded-xl mb-3 flex-row overflow-hidden shadow-sm border border-border"
      onPress={onPress}
      activeOpacity={0.7}
    >
      <View style={{ width: 4, backgroundColor: color }} />

      <View className="flex-1 px-4 py-3.5">
        <View className="flex-row items-start justify-between gap-2">
          <View className="flex-1">
            <Text
              className="text-base text-text leading-tight"
              style={{ fontFamily: "BricolageGrotesque_700Bold" }}
              numberOfLines={1}
            >
              {item.adresseRue}
            </Text>
            <View className="flex-row items-center gap-1 mt-0.5">
              <MapPin size={11} color="#6B6259" />
              <Text className="text-sm text-muted" numberOfLines={1}>
                {item.adresseCodePostal} {item.adresseVille}
              </Text>
            </View>
            {item.clientNom ? (
              <View className="flex-row items-center gap-1 mt-1">
                <User size={11} color="#9B8F85" />
                <Text className="text-xs text-muted" numberOfLines={1}>{item.clientNom}</Text>
              </View>
            ) : null}
          </View>

          <View
            className="rounded-full px-2.5 py-1"
            style={{ backgroundColor: `${color}18` }}
          >
            <Text className="text-xs font-medium" style={{ color }}>
              {STATUT_LABELS[item.statut] ?? item.statut}
            </Text>
          </View>
        </View>

        {item.surfaceM2 != null && (
          <Text className="text-xs text-muted mt-2">
            {item.surfaceM2} m²
          </Text>
        )}
      </View>
    </TouchableOpacity>
  );
}

export default function DashboardScreen() {
  const router = useRouter();
  const { logout } = useAuth();

  const { data: chantiers = [], isLoading, isError, refetch, isRefetching } = useQuery({
    queryKey: ["chantiers"],
    queryFn: fetchChantiers,
  });

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["top"]}>
      <View className="px-5 pt-4 pb-3 flex-row items-center justify-between">
        <View>
          <Text
            className="text-2xl text-text"
            style={{ fontFamily: "BricolageGrotesque_700Bold" }}
          >
            Chantiers
          </Text>
          {!isLoading && (
            <Text className="text-sm text-muted mt-0.5">
              {chantiers.length} actif{chantiers.length !== 1 ? "s" : ""}
            </Text>
          )}
        </View>
        <View className="flex-row gap-2">
          <TouchableOpacity
            onPress={() => router.push("/(app)/clients")}
            className="px-3 py-1.5 rounded-lg border border-border flex-row items-center gap-1.5"
          >
            <Users size={14} color="#6B6259" />
            <Text className="text-sm text-muted">Clients</Text>
          </TouchableOpacity>
          <TouchableOpacity
            onPress={() => void logout()}
            className="px-3 py-1.5 rounded-lg border border-border"
          >
            <Text className="text-sm text-muted">Déconnexion</Text>
          </TouchableOpacity>
        </View>
      </View>

      {isLoading ? (
        <View className="flex-1 items-center justify-center">
          <ActivityIndicator color="#B85C2A" size="large" />
        </View>
      ) : isError ? (
        <View className="flex-1 items-center justify-center px-6">
          <Text className="text-base text-muted text-center">
            Impossible de charger les chantiers.{"\n"}Vérifiez votre connexion.
          </Text>
          <TouchableOpacity
            className="mt-4 bg-primary rounded-xl px-5 py-3"
            onPress={() => void refetch()}
          >
            <Text className="text-primary-foreground font-medium">Réessayer</Text>
          </TouchableOpacity>
        </View>
      ) : chantiers.length === 0 ? (
        <View className="flex-1 items-center justify-center px-6">
          <Text
            className="text-xl text-text mb-2"
            style={{ fontFamily: "BricolageGrotesque_700Bold" }}
          >
            Aucun chantier
          </Text>
          <Text className="text-base text-muted text-center">
            Appuyez sur + pour créer votre premier chantier.
          </Text>
        </View>
      ) : (
        <FlatList
          data={chantiers}
          keyExtractor={(item) => item.id}
          renderItem={({ item }) => (
            <ChantierCard
              item={item}
              onPress={() => router.push(`/(app)/chantiers/${item.id}`)}
            />
          )}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 100 }}
          refreshControl={
            <RefreshControl
              refreshing={isRefetching}
              onRefresh={() => void refetch()}
              tintColor="#B85C2A"
            />
          }
          showsVerticalScrollIndicator={false}
        />
      )}

      <TouchableOpacity
        className="absolute bottom-8 right-5 w-14 h-14 bg-primary rounded-full items-center justify-center shadow-lg"
        onPress={() => router.push("/(app)/chantiers/new")}
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
