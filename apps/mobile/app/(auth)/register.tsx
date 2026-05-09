import { useState } from "react";
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
} from "react-native";
import { Link } from "expo-router";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useAuth } from "@/context/AuthContext";
import type { ApiError } from "@/lib/api";

const schema = z
  .object({
    email: z.string().min(1, "Email requis").email("Email invalide"),
    motDePasse: z
      .string()
      .min(8, "8 caractères minimum")
      .regex(/[A-Z]/, "Au moins une majuscule")
      .regex(/[0-9]/, "Au moins un chiffre"),
    confirmation: z.string().min(1, "Confirmation requise"),
  })
  .refine((d) => d.motDePasse === d.confirmation, {
    message: "Les mots de passe ne correspondent pas",
    path: ["confirmation"],
  });

type FormValues = z.infer<typeof schema>;

export default function RegisterScreen() {
  const { register } = useAuth();
  const [isPending, setIsPending] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | undefined>();

  const { control, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", motDePasse: "", confirmation: "" },
  });

  async function onSubmit(values: FormValues) {
    setIsPending(true);
    setErrorMessage(undefined);
    try {
      await register(values.email, values.motDePasse);
    } catch (err) {
      const apiErr = err as ApiError;
      if (apiErr.status === 409) setErrorMessage("Cette adresse email est déjà utilisée.");
      else if (apiErr.status === 422) setErrorMessage("Mot de passe invalide (8 car. min., 1 maj., 1 chiffre).");
      else setErrorMessage("Une erreur est survenue.");
    } finally {
      setIsPending(false);
    }
  }

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === "ios" ? "padding" : "height"}
      className="flex-1 bg-background"
    >
      <ScrollView contentContainerStyle={{ flexGrow: 1 }} keyboardShouldPersistTaps="handled">
        <View className="flex-1 justify-center px-6 py-12">
          <View className="mb-10">
            <Text
              className="text-4xl text-text mb-1"
              style={{ fontFamily: "BricolageGrotesque_700Bold" }}
            >
              Cordeau
            </Text>
            <Text className="text-base text-muted">Créez votre espace artisan</Text>
          </View>

          <View className="bg-surface rounded-2xl p-6 shadow-sm border border-border">
            <Text
              className="text-xl text-text mb-6"
              style={{ fontFamily: "BricolageGrotesque_700Bold" }}
            >
              Créer un compte
            </Text>

            {errorMessage && (
              <View className="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4">
                <Text className="text-sm text-destructive">{errorMessage}</Text>
              </View>
            )}

            <View className="space-y-4">
              {(
                [
                  { name: "email" as const, label: "Adresse email", placeholder: "artisan@exemple.fr", keyboardType: "email-address" as const, secure: false },
                  { name: "motDePasse" as const, label: "Mot de passe", placeholder: "8 car. min., 1 maj., 1 chiffre", keyboardType: "default" as const, secure: true },
                  { name: "confirmation" as const, label: "Confirmer le mot de passe", placeholder: "••••••••", keyboardType: "default" as const, secure: true },
                ] as const
              ).map(({ name, label, placeholder, keyboardType, secure }) => (
                <View key={name}>
                  <Text className="text-xs font-medium text-muted mb-1.5 uppercase tracking-wide">
                    {label}
                  </Text>
                  <Controller
                    control={control}
                    name={name}
                    render={({ field: { onChange, onBlur, value } }) => (
                      <TextInput
                        className={`bg-background border rounded-xl px-4 py-3.5 text-base text-text ${errors[name] ? "border-destructive" : "border-border"}`}
                        placeholder={placeholder}
                        placeholderTextColor="#9B8F85"
                        keyboardType={keyboardType}
                        autoCapitalize="none"
                        autoCorrect={false}
                        secureTextEntry={secure}
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
            </View>

            <TouchableOpacity
              className={`mt-6 rounded-xl py-4 items-center ${isPending ? "bg-primary/70" : "bg-primary"}`}
              onPress={() => void handleSubmit(onSubmit)()}
              disabled={isPending}
            >
              {isPending ? (
                <ActivityIndicator color="#FFFFFF" />
              ) : (
                <Text
                  className="text-primary-foreground text-base"
                  style={{ fontFamily: "BricolageGrotesque_700Bold" }}
                >
                  Créer mon compte
                </Text>
              )}
            </TouchableOpacity>
          </View>

          <Text className="text-center text-sm text-muted mt-6">
            Déjà un compte ?{" "}
            <Link href="/(auth)/login">
              <Text className="text-primary font-medium">Se connecter</Text>
            </Link>
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
