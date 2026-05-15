import { useState } from "react";
import {
  View,
  Text,
  TouchableOpacity,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from "react-native";
import { useRouter } from "expo-router";
import { useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { SafeAreaView } from "react-native-safe-area-context";
import { creerClient } from "@/lib/api";
import type { ApiError } from "@/lib/api";
import { clientSchema, type ClientFormValues } from "@/lib/client";
import { ClientForm } from "@/components/ClientForm";
import { ErrorBanner } from "@/components/ErrorBanner";
import { SubmitButton } from "@/components/SubmitButton";

export default function NewClientScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [isPending, setIsPending] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | undefined>();

  const { control, handleSubmit, formState: { errors } } = useForm<ClientFormValues>({
    resolver: zodResolver(clientSchema),
    defaultValues: {
      nom: "", email: "", telephone: "",
      adresseRue: "", adresseCodePostal: "", adresseVille: "", adressePays: "FR", notes: "",
    },
  });

  async function onSubmit(values: ClientFormValues) {
    setIsPending(true);
    setErrorMessage(undefined);
    try {
      await creerClient({
        nom: values.nom,
        email: values.email || null,
        telephone: values.telephone || null,
        adresseRue: values.adresseRue,
        adresseCodePostal: values.adresseCodePostal,
        adresseVille: values.adresseVille,
        adressePays: values.adressePays,
        notes: values.notes || null,
      });
      void queryClient.invalidateQueries({ queryKey: ["clients"] });
      router.back();
    } catch (err) {
      const apiErr = err as ApiError;
      setErrorMessage(apiErr.status === 422 ? "Données invalides." : "Une erreur est survenue.");
    } finally {
      setIsPending(false);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["bottom"]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : "height"}
        className="flex-1"
      >
        <ScrollView contentContainerStyle={{ flexGrow: 1, padding: 16 }} keyboardShouldPersistTaps="handled">
          <Text
            className="text-2xl text-text mb-6"
            style={{ fontFamily: "BricolageGrotesque_700Bold" }}
          >
            Nouveau client
          </Text>

          <ErrorBanner message={errorMessage} />

          <ClientForm control={control} errors={errors} />

          <View className="flex-row gap-3 mt-6">
            <TouchableOpacity
              className="flex-1 border border-border rounded-xl py-4 items-center"
              onPress={() => router.back()}
              disabled={isPending}
            >
              <Text className="text-base text-muted font-medium">Annuler</Text>
            </TouchableOpacity>
            <SubmitButton
              label="Créer"
              isPending={isPending}
              onPress={() => void handleSubmit(onSubmit)()}
            />
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
