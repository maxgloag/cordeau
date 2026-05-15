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
import type { Chantier } from "@/lib/api";
import { chantierSchema, type ChantierFormValues } from "@/lib/chantier";
import { ChantierForm } from "@/components/ChantierForm";
import { SubmitButton } from "@/components/SubmitButton";
import { getAllClients, upsertChantiers } from "@/db/queries";
import { useOfflineMutation } from "@/hooks/useOfflineMutation";

export default function NewChantierScreen() {
  const router = useRouter();
  const queryClient = useQueryClient();

  const { data: clients = [] } = useQuery({
    queryKey: ["clients"],
    queryFn: getAllClients,
    staleTime: Infinity,
  });

  const { control, handleSubmit, formState: { errors } } = useForm<ChantierFormValues>({
    resolver: zodResolver(chantierSchema),
    defaultValues: { adresseRue: "", adresseCodePostal: "", adresseVille: "", surfaceM2: "", clientId: "" },
  });

  const { mutate } = useOfflineMutation<Omit<Chantier, "id">>({
    entityType: "chantier",
    operation: "create",
    buildLocal: (entityId, payload) => {
      const localChantier: Chantier = { id: entityId, ...payload };
      upsertChantiers([localChantier]);
      queryClient.setQueryData(["chantiers"], (old: Chantier[] | undefined) =>
        [...(old ?? []), localChantier],
      );
    },
  });

  function onSubmit(values: ChantierFormValues) {
    const clientNom = clients.find((c) => c.id === values.clientId)?.nom ?? null;
    mutate({
      adresseRue: values.adresseRue,
      adresseCodePostal: values.adresseCodePostal,
      adresseVille: values.adresseVille,
      adressePays: "FR",
      surfaceM2: values.surfaceM2 !== "" ? parseFloat(values.surfaceM2) : null,
      statut: "en_preparation",
      clientId: values.clientId || null,
      clientNom,
    });
    router.back();
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

          <ChantierForm control={control} errors={errors} clients={clients} />

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
