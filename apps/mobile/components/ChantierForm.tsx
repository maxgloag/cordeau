import { View, Text, TextInput } from "react-native";
import { Controller, type Control, type FieldErrors } from "react-hook-form";
import type { ChantierFormValues } from "@/lib/chantier";

type Props = {
  control: Control<ChantierFormValues>;
  errors: FieldErrors<ChantierFormValues>;
};

const FIELDS = [
  { name: "adresseRue" as const, label: "Adresse", placeholder: "12 rue de la Paix", keyboard: "default" as const },
  { name: "adresseCodePostal" as const, label: "Code postal", placeholder: "75002", keyboard: "numeric" as const },
  { name: "adresseVille" as const, label: "Ville", placeholder: "Paris", keyboard: "default" as const },
  { name: "surfaceM2" as const, label: "Surface (m²) — optionnel", placeholder: "ex. 85", keyboard: "decimal-pad" as const },
];

export function ChantierForm({ control, errors }: Props) {
  return (
    <>
      {FIELDS.map(({ name, label, placeholder, keyboard }) => (
        <View key={name} className="mb-4">
          <Text className="text-xs font-medium text-muted mb-1.5 uppercase tracking-wide">
            {label}
          </Text>
          <Controller
            control={control}
            name={name}
            render={({ field: { onChange, onBlur, value } }) => (
              <TextInput
                className={`bg-surface border rounded-xl px-4 py-3.5 text-base text-text ${errors[name] ? "border-destructive" : "border-border"}`}
                placeholder={placeholder}
                placeholderTextColor="#9B8F85"
                keyboardType={keyboard}
                autoCapitalize="none"
                onBlur={onBlur}
                onChangeText={onChange}
                value={value}
              />
            )}
          />
          {errors[name] && (
            <Text className="text-xs text-destructive mt-1">{errors[name]?.message}</Text>
          )}
        </View>
      ))}
    </>
  );
}
