import { eq } from "drizzle-orm";
// uploadAsync (upload HTTP PUT vers presigned URL) n'a pas d'équivalent dans la
// nouvelle API File/Directory de SDK 56 → import de l'API legacy maintenue par Expo.
import * as FileSystem from "expo-file-system/legacy";
import { randomUUID } from "expo-crypto";
import { db } from "./index";
import { outboxPhotos, photos } from "./schema";
import type { OutboxPhotoStatus } from "./schema";
import { prepareUpload, confirmUpload } from "@/lib/api";

const MAX_RETRY = 10;
const MAX_BACKOFF_MS = 30_000;

export function pushToPhotoOutbox(entry: {
  photoId: string;
  localUri: string;
  chantierId: string;
}): void {
  db.insert(outboxPhotos)
    .values({
      id: randomUUID(),
      photoId: entry.photoId,
      localUri: entry.localUri,
      chantierId: entry.chantierId,
      status: "pending" as OutboxPhotoStatus,
      retryCount: 0,
      createdAt: Date.now(),
    })
    .run();
}

/**
 * Relance manuelle d'une entrée d'upload en échec : repasse en "pending" et remet
 * le compteur de tentatives à 0 pour que `processPhotoOutbox` la retente sans backoff.
 */
export function retryFailedEntry(entryId: string): void {
  db.update(outboxPhotos)
    .set({
      status: "pending" as OutboxPhotoStatus,
      retryCount: 0,
      lastAttemptAt: null,
    })
    .where(eq(outboxPhotos.id, entryId))
    .run();
}

let isSyncing = false;
// Notifie les chantiers dont une photo vient d'être confirmée, pour que l'appelant
// rafraîchisse sa query ["photos", chantierId] (sinon la vignette reste en ⏳ "local").
// Variable de module sûre car processPhotoOutbox est single-flight (isSyncing).
let notifyConfirmed: ((chantierId: string) => void) | null = null;

export async function processPhotoOutbox(
  onConfirmed?: (chantierId: string) => void,
): Promise<void> {
  if (isSyncing) return;
  isSyncing = true;
  notifyConfirmed = onConfirmed ?? null;

  try {
    await processPendingEntries();
    await resumeStuckUploading();
  } finally {
    isSyncing = false;
    notifyConfirmed = null;
  }
}

async function processPendingEntries(): Promise<void> {
  const entries = db
    .select()
    .from(outboxPhotos)
    .where(eq(outboxPhotos.status, "pending" as OutboxPhotoStatus))
    .orderBy(outboxPhotos.createdAt)
    .all();

  for (const entry of entries) {
    if (entry.retryCount >= MAX_RETRY) {
      db.update(outboxPhotos)
        .set({ status: "failed" as OutboxPhotoStatus })
        .where(eq(outboxPhotos.id, entry.id))
        .run();
      continue;
    }

    const delay = Math.min(
      Math.pow(2, entry.retryCount) * 1000,
      MAX_BACKOFF_MS,
    );
    const lastAttempt = entry.lastAttemptAt ?? 0;
    if (Date.now() - lastAttempt < delay && entry.retryCount > 0) continue;

    try {
      await processEntry(entry);
    } catch (e) {
      console.log("[photoOutbox] processEntry error", entry.photoId, e);
      db.update(outboxPhotos)
        .set({
          status: "pending" as OutboxPhotoStatus,
          retryCount: entry.retryCount + 1,
          lastAttemptAt: Date.now(),
        })
        .where(eq(outboxPhotos.id, entry.id))
        .run();
    }
  }
}

async function resumeStuckUploading(): Promise<void> {
  const stuckEntries = db
    .select()
    .from(outboxPhotos)
    .where(eq(outboxPhotos.status, "uploading" as OutboxPhotoStatus))
    .all();

  for (const entry of stuckEntries) {
    if (entry.uploadUrl && entry.remoteKey) {
      try {
        await uploadStep({
          ...entry,
          uploadUrl: entry.uploadUrl,
          remoteKey: entry.remoteKey,
        });
      } catch {
        db.update(outboxPhotos)
          .set({
            status: "pending" as OutboxPhotoStatus,
            retryCount: entry.retryCount + 1,
            lastAttemptAt: Date.now(),
          })
          .where(eq(outboxPhotos.id, entry.id))
          .run();
      }
    }
  }
}

async function processEntry(
  entry: typeof outboxPhotos.$inferSelect,
): Promise<void> {
  const { uploadUrl, remoteKey } = await prepareUpload(
    entry.chantierId,
    "image/jpeg",
  );
  db.update(outboxPhotos)
    .set({
      status: "uploading" as OutboxPhotoStatus,
      uploadUrl,
      remoteKey,
      lastAttemptAt: Date.now(),
    })
    .where(eq(outboxPhotos.id, entry.id))
    .run();

  await uploadStep({
    ...entry,
    uploadUrl,
    remoteKey,
    status: "uploading" as OutboxPhotoStatus,
  });
}

async function uploadStep(
  entry: typeof outboxPhotos.$inferSelect & {
    uploadUrl: string;
    remoteKey: string;
  },
): Promise<void> {
  // uploadAsync ne rejette pas sur un statut HTTP d'erreur : sans ce check,
  // un PUT refusé par R2 serait quand même confirmé côté API.
  const uploadResult = await FileSystem.uploadAsync(
    entry.uploadUrl,
    entry.localUri,
    {
      httpMethod: "PUT",
      headers: { "Content-Type": "image/jpeg" },
    },
  );
  if (uploadResult.status < 200 || uploadResult.status >= 300) {
    throw new Error(`Upload R2 refusé (${uploadResult.status})`);
  }

  db.update(outboxPhotos)
    .set({
      status: "confirming" as OutboxPhotoStatus,
      lastAttemptAt: Date.now(),
    })
    .where(eq(outboxPhotos.id, entry.id))
    .run();

  const confirmedPhoto = await confirmUpload(entry.remoteKey, entry.chantierId);

  db.update(photos)
    .set({
      status: "confirmed",
      remoteKey: confirmedPhoto.remoteKey,
      photoUrl: confirmedPhoto.photoUrl,
      thumbnailUrl: confirmedPhoto.thumbnailUrl,
      legende: confirmedPhoto.legende,
      syncedAt: Date.now(),
    })
    .where(eq(photos.id, entry.photoId))
    .run();

  db.update(outboxPhotos)
    .set({ status: "confirmed" as OutboxPhotoStatus })
    .where(eq(outboxPhotos.id, entry.id))
    .run();

  // Réveille l'UI : la photo passe de "local" (⏳) à "confirmed".
  notifyConfirmed?.(entry.chantierId);
}
