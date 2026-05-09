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

const schema = z.object({
  email: z.string().min(1, "Email requis").email("Email invalide"),
  motDePasse: z.string().min(1, "Mot de passe requis"),
});

type FormValues = z.infer<typeof schema>;

export default function LoginScreen() {
  const { login } = useAuth();
  const [isPending, setIsPending] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | undefined>();

  const { control, handleSubmit, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", motDePasse: "" },
  });

  async function onSubmit(values: FormValues) {
    setIsPending(true);
    setErrorMessage(undefined);
    try {
      await login(values.email, values.motDePasse);
    } catch (err) {
      const apiErr = err as ApiError;
      setErrorMessage(
        apiErr.status === 401
          ? "Email ou mot de passe incorrect."
          : "Une erreur est survenue.",
      );
    } finally {
      setIsPending(false);
    }
  }

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === "ios" ? "padding" : "height"}
      className="flex-1 bg-background"
    >
      <ScrollView
        contentContainerStyle={{ flexGrow: 1 }}
        keyboardShouldPersistTaps="handled"
      >
        <View className="flex-1 justify-center px-6 py-12">
          {/* Logo */}
          <View className="mb-10">
            <Text
              className="text-4xl text-text mb-1"
              style={{ fontFamily: "BricolageGrotesque_700Bold" }}
            >
              Cordeau
            </Text>
            <Text className="text-base text-muted">
              Gérez vos chantiers simplement
            </Text>
          </View>

          {/* Formulaire */}
          <View className="bg-surface rounded-2xl p-6 shadow-sm border border-border">
            <Text
              className="text-xl text-text mb-6"
              style={{ fontFamily: "BricolageGrotesque_700Bold" }}
            >
              Connexion
            </Text>

            {errorMessage && (
              <View className="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4">
                <Text className="text-sm text-destructive">{errorMessage}</Text>
              </View>
            )}

            <View className="space-y-4">
              <View>
                <Text className="text-xs font-medium text-muted mb-1.5 uppercase tracking-wide">
                  Adresse email
                </Text>
                <Controller
                  control={control}
                  name="email"
                  render={({ field: { onChange, onBlur, value } }) => (
                    <TextInput
                      className={`bg-background border rounded-xl px-4 py-3.5 text-base text-text ${errors.email ? "border-destructive" : "border-border"}`}
                      placeholder="artisan@exemple.fr"
                      placeholderTextColor="#9B8F85"
                      keyboardType="email-address"
                      autoCapitalize="none"
                      autoCorrect={false}
                      onBlur={onBlur}
                      onChangeText={onChange}
                      value={value}
                    />
                  )}
                />
                {errors.email && (
                  <Text className="text-xs text-destructive mt-1">{errors.email.message}</Text>
                )}
              </View>

              <View>
                <Text className="text-xs font-medium text-muted mb-1.5 uppercase tracking-wide">
                  Mot de passe
                </Text>
                <Controller
                  control={control}
                  name="motDePasse"
                  render={({ field: { onChange, onBlur, value } }) => (
                    <TextInput
                      className={`bg-background border rounded-xl px-4 py-3.5 text-base text-text ${errors.motDePasse ? "border-destructive" : "border-border"}`}
                      placeholder="••••••••"
                      placeholderTextColor="#9B8F85"
                      secureTextEntry
                      onBlur={onBlur}
                      onChangeText={onChange}
                      value={value}
                    />
                  )}
                />
                {errors.motDePasse && (
                  <Text className="text-xs text-destructive mt-1">{errors.motDePasse.message}</Text>
                )}
              </View>
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
                  Se connecter
                </Text>
              )}
            </TouchableOpacity>
          </View>

          <Text className="text-center text-sm text-muted mt-6">
            Pas encore de compte ?{" "}
            <Link href="/(auth)/register">
              <Text className="text-primary font-medium">Créer un compte</Text>
            </Link>
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
