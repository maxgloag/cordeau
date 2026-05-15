import { useState, useEffect } from "react";
import {
  View,
  Text,
  TouchableOpacity,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
  Alert,
} from "react-native";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { MapPin, Pencil, Archive, Maximize2 } from "lucide-react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import type { Chantier } from "@/lib/api";
import { STATUT_LABELS, STATUT_COLORS, chantierSchema, type ChantierFormValues } from "@/lib/chantier";
import { ChantierForm } from "@/components/ChantierForm";
import { SubmitButton } from "@/components/SubmitButton";
import { getAllChantiers, getAllClients, upsertChantiers } from "@/db/queries";
import { useOfflineMutation } from "@/hooks/useOfflineMutation";

function toFormValues(c: Chantier): ChantierFormValues {
  return {
    adresseRue: c.adresseRue,
    adresseCodePostal: c.adresseCodePostal,
    adresseVille: c.adresseVille,
    surfaceM2: c.surfaceM2 != null ? String(c.surfaceM2) : "",
    clientId: c.clientId ?? "",
  };
}

export default function ChantierDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const queryClient = useQueryClient();
  const [isEditing, setIsEditing] = useState(false);

  const { data: chantier } = useQuery({
    queryKey: ["chantiers"],
    queryFn: getAllChantiers,
    staleTime: Infinity,
    select: (items) => items.find((c) => c.id === id),
  });

  const { data: clients = [] } = useQuery({
    queryKey: ["clients"],
    queryFn: getAllClients,
    staleTime: Infinity,
  });

  const { control, handleSubmit, reset, formState: { errors } } = useForm<ChantierFormValues>({
    resolver: zodResolver(chantierSchema),
    defaultValues: { adresseRue: "", adresseCodePostal: "", adresseVille: "", surfaceM2: "", clientId: "" },
  });

  useEffect(() => {
    if (chantier) reset(toFormValues(chantier));
  }, [chantier, reset]);

  const { mutate: submitUpdate } = useOfflineMutation<Omit<Chantier, "id">>({
    entityType: "chantier",
    operation: "update",
    buildLocal: (entityId, payload) => {
      const updated: Chantier = { id: entityId, ...payload };
      upsertChantiers([updated]);
      queryClient.setQueryData(["chantiers"], (old: Chantier[] | undefined) =>
        (old ?? []).map((c) => (c.id === entityId ? updated : c)),
      );
    },
  });

  const { mutate: submitArchive } = useOfflineMutation<Record<string, never>>({
    entityType: "chantier",
    operation: "delete",
    buildLocal: (entityId) => {
      queryClient.setQueryData(["chantiers"], (old: Chantier[] | undefined) =>
        (old ?? []).filter((c) => c.id !== entityId),
      );
    },
  });

  function onSubmitEdit(values: ChantierFormValues) {
    if (!id || !chantier) return;
    const clientNom = clients.find((c) => c.id === values.clientId)?.nom ?? null;
    submitUpdate(
      {
        adresseRue: values.adresseRue,
        adresseCodePostal: values.adresseCodePostal,
        adresseVille: values.adresseVille,
        adressePays: chantier.adressePays,
        surfaceM2: values.surfaceM2 !== "" ? parseFloat(values.surfaceM2) : null,
        statut: chantier.statut,
        clientId: values.clientId || null,
        clientNom,
      },
      id,
    );
    setIsEditing(false);
  }

  function confirmArchive() {
    Alert.alert(
      "Archiver ce chantier ?",
      "Il n'apparaîtra plus dans votre liste.",
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Archiver",
          style: "destructive",
          onPress: () => {
            if (id) submitArchive({}, id);
            router.back();
          },
        },
      ],
    );
  }

  if (!chantier) {
    return (
      <View className="flex-1 bg-background items-center justify-center">
        <ActivityIndicator color="#B85C2A" size="large" />
      </View>
    );
  }

  const statusColor = STATUT_COLORS[chantier.statut] ?? "#9CA3AF";

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["bottom"]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : "height"}
        className="flex-1"
      >
        <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
          {!isEditing && (
            <View className="bg-surface rounded-2xl border border-border overflow-hidden mb-6">
              <View style={{ height: 4, backgroundColor: statusColor }} />
              <View className="p-5">
                <Text
                  className="text-2xl text-text mb-1"
                  style={{ fontFamily: "BricolageGrotesque_700Bold" }}
                >
                  {chantier.adresseRue}
                </Text>
                <View className="flex-row items-center gap-1 mb-4">
                  <MapPin size={13} color="#6B6259" />
                  <Text className="text-base text-muted">
                    {chantier.adresseCodePostal} {chantier.adresseVille}
                  </Text>
                </View>

                <View className="flex-row gap-3">
                  <View
                    className="rounded-full px-3 py-1.5"
                    style={{ backgroundColor: `${statusColor}18` }}
                  >
                    <Text className="text-sm font-medium" style={{ color: statusColor }}>
                      {STATUT_LABELS[chantier.statut] ?? chantier.statut}
                    </Text>
                  </View>
                  {chantier.surfaceM2 != null && (
                    <View className="flex-row items-center gap-1 rounded-full px-3 py-1.5 bg-background border border-border">
                      <Maximize2 size={12} color="#6B6259" />
                      <Text className="text-sm text-muted">{chantier.surfaceM2} m²</Text>
                    </View>
                  )}
                </View>
              </View>
            </View>
          )}

          {isEditing && (
            <View className="mb-6">
              <Text
                className="text-xl text-text mb-4"
                style={{ fontFamily: "BricolageGrotesque_700Bold" }}
              >
                Modifier le chantier
              </Text>

              <ChantierForm control={control} errors={errors} clients={clients} />

              <View className="flex-row gap-3">
                <TouchableOpacity
                  className="flex-1 border border-border rounded-xl py-4 items-center"
                  onPress={() => setIsEditing(false)}
                >
                  <Text className="text-base text-muted font-medium">Annuler</Text>
                </TouchableOpacity>
                <SubmitButton
                  label="Enregistrer"
                  isPending={false}
                  onPress={() => void handleSubmit(onSubmitEdit)()}
                />
              </View>
            </View>
          )}

          {!isEditing && chantier.statut !== "archive" && (
            <View className="gap-3">
              <TouchableOpacity
                className="flex-row items-center gap-3 bg-surface border border-border rounded-xl px-5 py-4"
                onPress={() => { if (chantier) reset(toFormValues(chantier)); setIsEditing(true); }}
              >
                <Pencil size={18} color="#B85C2A" />
                <Text className="text-base text-text font-medium">Modifier le chantier</Text>
              </TouchableOpacity>

              <TouchableOpacity
                className="flex-row items-center gap-3 bg-surface border border-border rounded-xl px-5 py-4"
                onPress={confirmArchive}
              >
                <Archive size={18} color="#6B6259" />
                <Text className="text-base text-muted font-medium">Archiver le chantier</Text>
              </TouchableOpacity>
            </View>
          )}
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
