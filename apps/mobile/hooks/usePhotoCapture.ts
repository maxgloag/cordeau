import * as ImagePicker from "expo-image-picker";
import { randomUUID } from "expo-crypto";
import { useQueryClient } from "@tanstack/react-query";
import { db } from "@/db";
import { photos } from "@/db/schema";
import { pushToPhotoOutbox, processPhotoOutbox } from "@/db/photoOutbox";

export function usePhotoCapture(chantierId: string) {
  const queryClient = useQueryClient();

  async function captureFromCamera(): Promise<void> {
    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: "images",
      quality: 0.8,
      allowsEditing: false,
    });
    if (result.canceled) return;
    insertPhotos(
      result.assets.map((a) => a.uri),
      chantierId,
      queryClient,
    );
  }

  async function captureFromGallery(): Promise<void> {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: "images",
      quality: 0.8,
      allowsMultipleSelection: true,
    });
    if (result.canceled) return;
    insertPhotos(
      result.assets.map((a) => a.uri),
      chantierId,
      queryClient,
    );
  }

  return { captureFromCamera, captureFromGallery };
}

function insertPhotos(
  uris: string[],
  chantierId: string,
  queryClient: ReturnType<typeof useQueryClient>,
): void {
  const now = Date.now();
  for (const localUri of uris) {
    const id = randomUUID();
    db.insert(photos)
      .values({
        id,
        chantierId,
        localUri,
        status: "local",
        createdAt: now,
      })
      .run();
    pushToPhotoOutbox({ photoId: id, localUri, chantierId });
  }
  queryClient.invalidateQueries({ queryKey: ["photos", chantierId] });
  void processPhotoOutbox();
}
