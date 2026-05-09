import { TouchableOpacity, Text, ActivityIndicator } from "react-native";

type Props = {
  label: string;
  isPending: boolean;
  onPress: () => void;
  containerClassName?: string;
};

export function SubmitButton({ label, isPending, onPress, containerClassName = "flex-1" }: Props) {
  return (
    <TouchableOpacity
      className={`${containerClassName} rounded-xl py-4 items-center ${isPending ? "bg-primary/70" : "bg-primary"}`}
      onPress={onPress}
      disabled={isPending}
    >
      {isPending
        ? <ActivityIndicator color="#FFFFFF" />
        : <Text className="text-primary-foreground text-base font-medium">{label}</Text>}
    </TouchableOpacity>
  );
}
