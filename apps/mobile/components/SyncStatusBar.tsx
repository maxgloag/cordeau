import { View, Text } from "react-native";
import { WifiOff, CloudUpload } from "lucide-react-native";
import { useSyncStatus } from "@/hooks/useSyncStatus";

export function SyncStatusBar() {
  const { status, pendingCount } = useSyncStatus();

  if (status === "synced") return null;

  if (status === "offline") {
    return (
      <View className="mx-5 mb-3 px-3 py-2 rounded-lg flex-row items-center gap-2" style={{ backgroundColor: "#9CA3AF20" }}>
        <WifiOff size={14} color="#6B6259" />
        <Text className="text-xs text-muted flex-1">
          Hors-ligne — vos changements seront synchronisés à la reconnexion
          {pendingCount > 0 ? ` (${pendingCount} en attente)` : ""}
        </Text>
      </View>
    );
  }

  return (
    <View className="mx-5 mb-3 px-3 py-2 rounded-lg flex-row items-center gap-2" style={{ backgroundColor: "#F59E0B20" }}>
      <CloudUpload size={14} color="#B85C2A" />
      <Text className="text-xs flex-1" style={{ color: "#B85C2A" }}>
        Synchronisation en cours — {pendingCount} en attente
      </Text>
    </View>
  );
}
