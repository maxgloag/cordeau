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
import { Pencil, Trash2, User, Mail, Phone } from "lucide-react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import type { Client } from "@/lib/api";
import { clientSchema, type ClientFormValues } from "@/lib/client";
import { ClientForm } from "@/components/ClientForm";
import { SubmitButton } from "@/components/SubmitButton";
import { getAllClients, upsertClients, deleteClientLocal } from "@/db/queries";
import { useOfflineMutation } from "@/hooks/useOfflineMutation";

function toFormValues(c: Client): ClientFormValues {
  return {
    nom: c.nom,
    email: c.email ?? "",
    telephone: c.telephone ?? "",
    adresseRue: c.adresseRue,
    adresseCodePostal: c.adresseCodePostal,
    adresseVille: c.adresseVille,
    adressePays: c.adressePays,
    notes: c.notes ?? "",
  };
}

export default function ClientDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const queryClient = useQueryClient();
  const [isEditing, setIsEditing] = useState(false);

  const { data: client } = useQuery({
    queryKey: ["clients"],
    queryFn: getAllClients,
    staleTime: Infinity,
    select: (items) => items.find((c) => c.id === id),
  });

  const {
    control,
    handleSubmit,
    reset,
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

  useEffect(() => {
    if (client) reset(toFormValues(client));
  }, [client, reset]);

  const { mutate: submitUpdate } = useOfflineMutation<Omit<Client, "id">>({
    entityType: "client",
    operation: "update",
    buildLocal: (entityId, payload) => {
      const updated: Client = { id: entityId, ...payload };
      upsertClients([updated]);
      queryClient.setQueryData(["clients"], (old: Client[] | undefined) =>
        (old ?? []).map((c) => (c.id === entityId ? updated : c)),
      );
    },
  });

  const { mutate: submitDelete } = useOfflineMutation<Record<string, never>>({
    entityType: "client",
    operation: "delete",
    buildLocal: (entityId) => {
      deleteClientLocal(entityId);
      queryClient.setQueryData(["clients"], (old: Client[] | undefined) =>
        (old ?? []).filter((c) => c.id !== entityId),
      );
    },
  });

  function onSubmitEdit(values: ClientFormValues) {
    if (!id || !client) return;
    submitUpdate(
      {
        nom: values.nom,
        email: values.email || null,
        telephone: values.telephone || null,
        adresseRue: values.adresseRue,
        adresseCodePostal: values.adresseCodePostal,
        adresseVille: values.adresseVille,
        adressePays: values.adressePays,
        notes: values.notes || null,
      },
      id,
    );
    setIsEditing(false);
  }

  function confirmSupprimer() {
    Alert.alert(
      "Supprimer ce client ?",
      "Cette action est irréversible. Le client sera dé-lié de tous ses chantiers.",
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Supprimer",
          style: "destructive",
          onPress: () => {
            if (id) submitDelete({}, id);
            router.back();
          },
        },
      ],
    );
  }

  if (!client) {
    return (
      <View className="flex-1 bg-background items-center justify-center">
        <ActivityIndicator color="#B85C2A" size="large" />
      </View>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["bottom"]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : "height"}
        className="flex-1"
      >
        <ScrollView
          contentContainerStyle={{ padding: 16 }}
          keyboardShouldPersistTaps="handled"
        >
          {!isEditing && (
            <View className="bg-surface rounded-2xl border border-border overflow-hidden mb-6">
              <View style={{ height: 4, backgroundColor: "#B85C2A" }} />
              <View className="p-5">
                <View className="flex-row items-center gap-2 mb-1">
                  <User size={16} color="#B85C2A" />
                  <Text
                    className="text-2xl text-text"
                    style={{ fontFamily: "BricolageGrotesque_700Bold" }}
                  >
                    {client.nom}
                  </Text>
                </View>
                <Text className="text-base text-muted mb-3">
                  {client.adresseRue}, {client.adresseCodePostal}{" "}
                  {client.adresseVille}
                </Text>
                {client.email && (
                  <View className="flex-row items-center gap-2 mb-1">
                    <Mail size={13} color="#9B8F85" />
                    <Text className="text-sm text-muted">{client.email}</Text>
                  </View>
                )}
                {client.telephone && (
                  <View className="flex-row items-center gap-2">
                    <Phone size={13} color="#9B8F85" />
                    <Text className="text-sm text-muted">
                      {client.telephone}
                    </Text>
                  </View>
                )}
                {client.notes ? (
                  <Text className="text-sm text-muted mt-3 italic">
                    {client.notes}
                  </Text>
                ) : null}
              </View>
            </View>
          )}

          {isEditing && (
            <View className="mb-6">
              <Text
                className="text-xl text-text mb-4"
                style={{ fontFamily: "BricolageGrotesque_700Bold" }}
              >
                Modifier le client
              </Text>

              <ClientForm control={control} errors={errors} />

              <View className="flex-row gap-3">
                <TouchableOpacity
                  className="flex-1 border border-border rounded-xl py-4 items-center"
                  onPress={() => setIsEditing(false)}
                >
                  <Text className="text-base text-muted font-medium">
                    Annuler
                  </Text>
                </TouchableOpacity>
                <SubmitButton
                  label="Enregistrer"
                  isPending={false}
                  onPress={() => void handleSubmit(onSubmitEdit)()}
                />
              </View>
            </View>
          )}

          {!isEditing && (
            <View className="gap-3">
              <TouchableOpacity
                className="flex-row items-center gap-3 bg-surface border border-border rounded-xl px-5 py-4"
                onPress={() => {
                  if (client) reset(toFormValues(client));
                  setIsEditing(true);
                }}
              >
                <Pencil size={18} color="#B85C2A" />
                <Text className="text-base text-text font-medium">
                  Modifier le client
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                className="flex-row items-center gap-3 bg-surface border border-border rounded-xl px-5 py-4"
                onPress={confirmSupprimer}
              >
                <Trash2 size={18} color="#EF4444" />
                <Text
                  className="text-base font-medium"
                  style={{ color: "#EF4444" }}
                >
                  Supprimer le client
                </Text>
              </TouchableOpacity>
            </View>
          )}
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
