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
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { SafeAreaView } from "react-native-safe-area-context";
import { creerChantier, listClients } from "@/lib/api";
import type { ApiError } from "@/lib/api";
import { chantierSchema, type ChantierFormValues } from "@/lib/chantier";
import { ChantierForm } from "@/components/ChantierForm";
import { ErrorBanner } from "@/components/ErrorBanner";
import { SubmitButton } from "@/components/SubmitButton";

export default function NewChantierScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [isPending, setIsPending] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | undefined>();

  const { data: clients = [] } = useQuery({ queryKey: ["clients"], queryFn: listClients });

  const { control, handleSubmit, formState: { errors } } = useForm<ChantierFormValues>({
    resolver: zodResolver(chantierSchema),
    defaultValues: { adresseRue: "", adresseCodePostal: "", adresseVille: "", surfaceM2: "", clientId: "" },
  });

  async function onSubmit(values: ChantierFormValues) {
    setIsPending(true);
    setErrorMessage(undefined);
    try {
      await creerChantier({
        adresseRue: values.adresseRue,
        adresseCodePostal: values.adresseCodePostal,
        adresseVille: values.adresseVille,
        adressePays: "FR",
        surfaceM2: values.surfaceM2 !== "" ? parseFloat(values.surfaceM2) : null,
        clientId: values.clientId || null,
      });
      void queryClient.invalidateQueries({ queryKey: ["chantiers"] });
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
            Nouveau chantier
          </Text>

          <ErrorBanner message={errorMessage} />

          <ChantierForm control={control} errors={errors} clients={clients} />

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
