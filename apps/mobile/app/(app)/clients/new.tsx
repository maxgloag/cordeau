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
import type { Client } from "@/lib/api";
import { clientSchema, type ClientFormValues } from "@/lib/client";
import { ClientForm } from "@/components/ClientForm";
import { SubmitButton } from "@/components/SubmitButton";
import { upsertClients } from "@/db/queries";
import { useOfflineMutation } from "@/hooks/useOfflineMutation";

export default function NewClientScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<ClientFormValues>({
    resolver: zodResolver(clientSchema),
    defaultValues: {
      nom: "",
      email: "",
      telephone: "",
      adresseRue: "",
      adresseCodePostal: "",
      adresseVille: "",
      adressePays: "FR",
      notes: "",
    },
  });

  const { mutate } = useOfflineMutation<Omit<Client, "id">>({
    entityType: "client",
    operation: "create",
    buildLocal: (entityId, payload) => {
      const localClient: Client = { id: entityId, ...payload };
      upsertClients([localClient]);
      queryClient.setQueryData(["clients"], (old: Client[] | undefined) => [
        ...(old ?? []),
        localClient,
      ]);
    },
  });

  function onSubmit(values: ClientFormValues) {
    mutate({
      nom: values.nom,
      email: values.email || null,
      telephone: values.telephone || null,
      adresseRue: values.adresseRue,
      adresseCodePostal: values.adresseCodePostal,
      adresseVille: values.adresseVille,
      adressePays: values.adressePays,
      notes: values.notes || null,
    });
    router.back();
  }

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["bottom"]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : "height"}
        className="flex-1"
      >
        <ScrollView
          contentContainerStyle={{ flexGrow: 1, padding: 16 }}
          keyboardShouldPersistTaps="handled"
        >
          <Text
            className="text-2xl text-text mb-6"
            style={{ fontFamily: "BricolageGrotesque_700Bold" }}
          >
            Nouveau client
          </Text>

          <ClientForm control={control} errors={errors} />

          <View className="flex-row gap-3 mt-6">
            <TouchableOpacity
              className="flex-1 border border-border rounded-xl py-4 items-center"
              onPress={() => router.back()}
            >
              <Text className="text-base text-muted font-medium">Annuler</Text>
            </TouchableOpacity>
            <SubmitButton
              label="Créer"
              isPending={false}
              onPress={() => void handleSubmit(onSubmit)()}
            />
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
