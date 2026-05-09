import { View, Text } from "react-native";

export function ErrorBanner({ message }: { message?: string }) {
  if (!message) return null;
  return (
    <View className="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4">
      <Text className="text-sm text-destructive">{message}</Text>
    </View>
  );
}
