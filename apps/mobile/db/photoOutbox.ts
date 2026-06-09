import { eq } from "drizzle-orm";
import * as FileSystem from "expo-file-system";
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

let isSyncing = false;

export async function processPhotoOutbox(): Promise<void> {
  if (isSyncing) return;
  isSyncing = true;

  try {
    await processPendingEntries();
    await resumeStuckUploading();
  } finally {
    isSyncing = false;
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
  const { uploadUrl, remoteKey } = await prepareUpload(entry.chantierId);
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
  await FileSystem.uploadAsync(entry.uploadUrl, entry.localUri, {
    httpMethod: "PUT",
    headers: { "Content-Type": "image/jpeg" },
  });

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
      syncedAt: Date.now(),
    })
    .where(eq(photos.id, entry.photoId))
    .run();

  db.update(outboxPhotos)
    .set({ status: "confirmed" as OutboxPhotoStatus })
    .where(eq(outboxPhotos.id, entry.id))
    .run();
}
