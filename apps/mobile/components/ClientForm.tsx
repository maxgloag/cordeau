import { View, Text, TextInput } from "react-native";
import { Controller, type Control, type FieldErrors } from "react-hook-form";
import type { ClientFormValues } from "@/lib/client";

type Props = {
  control: Control<ClientFormValues>;
  errors: FieldErrors<ClientFormValues>;
};

const FIELDS: {
  name: keyof ClientFormValues;
  label: string;
  placeholder: string;
  keyboard: "default" | "email-address" | "phone-pad" | "numeric";
  optional?: boolean;
  multiline?: boolean;
}[] = [
  { name: "nom", label: "Nom", placeholder: "ACME SARL", keyboard: "default" },
  {
    name: "email",
    label: "Email",
    placeholder: "contact@client.fr",
    keyboard: "email-address",
    optional: true,
  },
  {
    name: "telephone",
    label: "Téléphone",
    placeholder: "06 12 34 56 78",
    keyboard: "phone-pad",
    optional: true,
  },
  {
    name: "adresseRue",
    label: "Adresse",
    placeholder: "12 rue de la Paix",
    keyboard: "default",
  },
  {
    name: "adresseCodePostal",
    label: "Code postal",
    placeholder: "75002",
    keyboard: "numeric",
  },
  {
    name: "adresseVille",
    label: "Ville",
    placeholder: "Paris",
    keyboard: "default",
  },
  {
    name: "notes",
    label: "Notes",
    placeholder: "Informations complémentaires…",
    keyboard: "default",
    optional: true,
    multiline: true,
  },
];

export function ClientForm({ control, errors }: Props) {
  return (
    <>
      {FIELDS.map(
        ({ name, label, placeholder, keyboard, optional, multiline }) => (
          <View key={name} className="mb-4">
            <Text className="text-xs font-medium text-muted mb-1.5 uppercase tracking-wide">
              {label}
              {optional ? (
                <Text className="normal-case tracking-normal font-normal">
                  {" "}
                  — optionnel
                </Text>
              ) : null}
            </Text>
            <Controller
              control={control}
              name={name}
              render={({ field: { onChange, onBlur, value } }) => (
                <TextInput
                  className={`bg-surface border rounded-xl px-4 py-3.5 text-base text-text ${errors[name] ? "border-destructive" : "border-border"} ${multiline ? "min-h-[80px]" : ""}`}
                  placeholder={placeholder}
                  placeholderTextColor="#9B8F85"
                  keyboardType={keyboard}
                  autoCapitalize={
                    keyboard === "email-address" ? "none" : "sentences"
                  }
                  multiline={multiline}
                  numberOfLines={multiline ? 3 : 1}
                  textAlignVertical={multiline ? "top" : "center"}
                  onBlur={onBlur}
                  onChangeText={onChange}
                  value={value}
                />
              )}
            />
            {errors[name] && (
              <Text className="text-xs text-destructive mt-1">
                {errors[name]?.message}
              </Text>
            )}
          </View>
        ),
      )}
    </>
  );
}
