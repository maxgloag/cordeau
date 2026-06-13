import { useState, useEffect, type ReactElement } from "react";
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
  Alert,
  Image,
  FlatList,
} from "react-native";
import ImageView from "react-native-image-viewing";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  MapPin,
  Pencil,
  Archive,
  Maximize2,
  Trash2,
} from "lucide-react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import type { Chantier } from "@/lib/api";
import {
  STATUT_LABELS,
  STATUT_COLORS,
  chantierSchema,
  type ChantierFormValues,
} from "@/lib/chantier";
import { ChantierForm } from "@/components/ChantierForm";
import { SubmitButton } from "@/components/SubmitButton";
import {
  getAllChantiers,
  getAllClients,
  upsertChantiers,
  getPhotosForChantier,
  deleteLocalPhoto,
  setPhotoLegendeLocal,
} from "@/db/queries";
import { requiresRemoteDeletion } from "@/db/photoSync";
import { retryFailedEntry } from "@/db/photoOutbox";
import { refreshPhotos } from "@/lib/sync";
import { useNetworkStatus } from "@/hooks/useNetworkStatus";
import { useOfflineMutation } from "@/hooks/useOfflineMutation";
import { usePhotoCapture } from "@/hooks/usePhotoCapture";

type PhotoRow = ReturnType<typeof getPhotosForChantier>[number];

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
  const isConnected = useNetworkStatus();
  const [isEditing, setIsEditing] = useState(false);
  const [viewerIndex, setViewerIndex] = useState<number | null>(null);

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

  const { data: photosList = [] } = useQuery({
    queryKey: ["photos", id],
    queryFn: () => {
      const local = getPhotosForChantier(id ?? "");
      if (isConnected && id) void refreshPhotos(queryClient, id);
      return local;
    },
  });

  const { captureFromCamera, captureFromGallery } = usePhotoCapture(id ?? "");

  function onAjouterPhoto(): void {
    Alert.alert("Ajouter une photo", undefined, [
      { text: "Prendre une photo", onPress: captureFromCamera },
      { text: "Choisir dans la galerie", onPress: captureFromGallery },
      { text: "Annuler", style: "cancel" },
    ]);
  }

  const {
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ChantierFormValues>({
    resolver: zodResolver(chantierSchema),
    defaultValues: {
      adresseRue: "",
      adresseCodePostal: "",
      adresseVille: "",
      surfaceM2: "",
      clientId: "",
    },
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

  const { mutate: submitLegende } = useOfflineMutation<{
    legende: string | null;
    chantierId: string;
  }>({
    entityType: "photo",
    operation: "update",
    buildLocal: (photoId, payload) => {
      setPhotoLegendeLocal(photoId, payload.legende);
      queryClient.setQueryData(["photos", id], (old: PhotoRow[] | undefined) =>
        (old ?? []).map((p) =>
          p.id === photoId ? { ...p, legende: payload.legende } : p,
        ),
      );
    },
  });

  const { mutate: submitDeletePhoto } = useOfflineMutation<{
    chantierId: string;
  }>({
    entityType: "photo",
    operation: "delete",
    buildLocal: (photoId) => {
      deleteLocalPhoto(photoId);
      removePhotoFromCache(photoId);
    },
  });

  function removePhotoFromCache(photoId: string): void {
    queryClient.setQueryData(["photos", id], (old: PhotoRow[] | undefined) =>
      (old ?? []).filter((p) => p.id !== photoId),
    );
  }

  function handleSaveLegende(photoId: string, legende: string | null): void {
    if (!id) return;
    submitLegende({ legende, chantierId: id }, photoId);
  }

  function confirmDeletePhoto(photo: PhotoRow): void {
    if (!id) return;
    Alert.alert("Supprimer cette photo ?", "Cette action est définitive.", [
      { text: "Annuler", style: "cancel" },
      {
        text: "Supprimer",
        style: "destructive",
        onPress: () => {
          if (requiresRemoteDeletion(photo.status)) {
            submitDeletePhoto({ chantierId: id }, photo.id);
          } else {
            deleteLocalPhoto(photo.id);
            removePhotoFromCache(photo.id);
          }
          setViewerIndex(null);
        },
      },
    ]);
  }

  function onThumbnailPress(index: number): void {
    const photo = photosList[index];
    if (photo?.outboxStatus === "failed") {
      Alert.alert("Upload échoué", "Que veux-tu faire de cette photo ?", [
        { text: "Annuler", style: "cancel" },
        {
          text: "Réessayer",
          onPress: () => {
            if (photo.outboxId) retryFailedEntry(photo.outboxId);
          },
        },
        {
          text: "Supprimer",
          style: "destructive",
          onPress: () => {
            deleteLocalPhoto(photo.id);
            removePhotoFromCache(photo.id);
          },
        },
      ]);
      return;
    }
    setViewerIndex(index);
  }

  function onSubmitEdit(values: ChantierFormValues) {
    if (!id || !chantier) return;
    const clientNom =
      clients.find((c) => c.id === values.clientId)?.nom ?? null;
    submitUpdate(
      {
        adresseRue: values.adresseRue,
        adresseCodePostal: values.adresseCodePostal,
        adresseVille: values.adresseVille,
        adressePays: chantier.adressePays,
        surfaceM2:
          values.surfaceM2 !== "" ? parseFloat(values.surfaceM2) : null,
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
        <ScrollView
          contentContainerStyle={{ padding: 16 }}
          keyboardShouldPersistTaps="handled"
        >
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
                    <Text
                      className="text-sm font-medium"
                      style={{ color: statusColor }}
                    >
                      {STATUT_LABELS[chantier.statut] ?? chantier.statut}
                    </Text>
                  </View>
                  {chantier.surfaceM2 != null && (
                    <View className="flex-row items-center gap-1 rounded-full px-3 py-1.5 bg-background border border-border">
                      <Maximize2 size={12} color="#6B6259" />
                      <Text className="text-sm text-muted">
                        {chantier.surfaceM2} m²
                      </Text>
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

              <ChantierForm
                control={control}
                errors={errors}
                clients={clients}
              />

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

          {!isEditing && chantier.statut !== "archive" && (
            <View className="gap-3">
              <TouchableOpacity
                className="flex-row items-center gap-3 bg-surface border border-border rounded-xl px-5 py-4"
                onPress={() => {
                  if (chantier) reset(toFormValues(chantier));
                  setIsEditing(true);
                }}
              >
                <Pencil size={18} color="#B85C2A" />
                <Text className="text-base text-text font-medium">
                  Modifier le chantier
                </Text>
              </TouchableOpacity>

              <TouchableOpacity
                className="flex-row items-center gap-3 bg-surface border border-border rounded-xl px-5 py-4"
                onPress={confirmArchive}
              >
                <Archive size={18} color="#6B6259" />
                <Text className="text-base text-muted font-medium">
                  Archiver le chantier
                </Text>
              </TouchableOpacity>
            </View>
          )}

          {/* Section Photos */}
          <View className="mt-6 mb-4">
            <View className="flex-row items-center justify-between mb-3 px-4">
              <Text className="text-lg font-semibold text-gray-900">
                Photos
              </Text>
              <TouchableOpacity
                onPress={onAjouterPhoto}
                className="bg-blue-600 rounded-full w-8 h-8 items-center justify-center"
              >
                <Text className="text-white text-xl font-bold">+</Text>
              </TouchableOpacity>
            </View>

            {photosList.length === 0 ? (
              <TouchableOpacity
                onPress={onAjouterPhoto}
                className="mx-4 h-24 border-2 border-dashed border-gray-300 rounded-xl items-center justify-center"
              >
                <Text className="text-gray-400 text-sm">
                  Ajouter des photos
                </Text>
              </TouchableOpacity>
            ) : (
              <FlatList
                data={photosList}
                keyExtractor={(item) => item.id}
                numColumns={3}
                scrollEnabled={false}
                renderItem={({ item, index }) => (
                  <TouchableOpacity
                    className="flex-1 aspect-square m-0.5"
                    onPress={() => onThumbnailPress(index)}
                    activeOpacity={0.8}
                  >
                    <Image
                      source={{
                        uri:
                          item.thumbnailUrl ??
                          item.localUri ??
                          item.photoUrl ??
                          "",
                      }}
                      className="flex-1"
                      resizeMode="cover"
                    />
                    {item.outboxStatus === "failed" ? (
                      <View className="absolute bottom-1 right-1 bg-red-600/80 rounded px-1">
                        <Text className="text-white text-xs">⚠️</Text>
                      </View>
                    ) : (
                      item.status === "local" && (
                        <View className="absolute bottom-1 right-1 bg-black/50 rounded px-1">
                          <Text className="text-white text-xs">⏳</Text>
                        </View>
                      )
                    )}
                  </TouchableOpacity>
                )}
              />
            )}
          </View>
        </ScrollView>
      </KeyboardAvoidingView>

      <ImageView
        images={photosList.map((p) => ({
          uri: p.photoUrl ?? p.localUri ?? "",
        }))}
        imageIndex={viewerIndex ?? 0}
        visible={viewerIndex !== null}
        onRequestClose={() => setViewerIndex(null)}
        // react-native-image-viewing appelle onImageIndexChange(0) au montage (même
        // visible=false). On ignore donc tout changement tant que la visionneuse est
        // fermée, sinon elle s'ouvrirait toute seule à l'ouverture du chantier.
        onImageIndexChange={(i) =>
          setViewerIndex((current) => (current === null ? null : i))
        }
        FooterComponent={({ imageIndex }) => (
          <LightboxFooter
            photo={photosList[imageIndex]}
            onSave={handleSaveLegende}
            onDelete={confirmDeletePhoto}
          />
        )}
      />
    </SafeAreaView>
  );
}

/**
 * Pied de la visionneuse : légende éditable + suppression.
 * Le brouillon de légende est un état INTERNE — taper ne re-rend pas l'écran parent,
 * donc le footer n'est pas remonté à chaque frappe (sinon le clavier se fermerait).
 * Le brouillon se réinitialise quand on navigue vers une autre photo (changement d'id).
 */
function LightboxFooter({
  photo,
  onSave,
  onDelete,
}: {
  photo: PhotoRow | undefined;
  onSave: (photoId: string, legende: string | null) => void;
  onDelete: (photo: PhotoRow) => void;
}): ReactElement | null {
  const [draft, setDraft] = useState(photo?.legende ?? "");
  const [shownPhotoId, setShownPhotoId] = useState(photo?.id);

  if (photo && photo.id !== shownPhotoId) {
    setShownPhotoId(photo.id);
    setDraft(photo.legende ?? "");
  }

  if (!photo) return null;
  const current = photo;

  function save(): void {
    const trimmed = draft.trim();
    const value = trimmed === "" ? null : trimmed;
    if (value === (current.legende ?? null)) return;
    onSave(current.id, value);
  }

  return (
    <View className="px-5 pb-8 pt-3 bg-black/60">
      <TextInput
        value={draft}
        onChangeText={setDraft}
        onBlur={save}
        placeholder="Ajouter une légende"
        placeholderTextColor="#9CA3AF"
        maxLength={280}
        returnKeyType="done"
        onSubmitEditing={save}
        className="text-white text-base border-b border-white/30 pb-1 mb-3"
      />
      <TouchableOpacity
        onPress={() => onDelete(current)}
        className="flex-row items-center gap-2 self-start"
      >
        <Trash2 size={18} color="#F87171" />
        <Text className="text-red-400 text-base font-medium">
          Supprimer la photo
        </Text>
      </TouchableOpacity>
    </View>
  );
}
