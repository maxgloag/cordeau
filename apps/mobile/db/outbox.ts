// TODO: migrate console.log to Sentry breadcrumbs en Phase 4+
import { eq, and, lt, sql } from "drizzle-orm";
import { db } from "./index";
import { lots, outbox, photos, taches } from "./schema";
import type { OutboxEntityType, OutboxOperation, OutboxStatus } from "./schema";
import {
  creerChantier,
  modifierChantier,
  archiverChantier,
  creerClient,
  modifierClient,
  supprimerClient,
  patchPhotoLegende,
  deletePhoto,
  creerLot,
  supprimerLot,
  creerTache,
  modifierTache,
  supprimerTache,
} from "@/lib/api";
import type { QueryClient } from "@tanstack/react-query";
import {
  getPhotosForChantier,
  upsertChantiers,
  upsertClients,
} from "./queries";
import { getLotsForChantier, getTachesForLot } from "./lotQueries";
import { estBloquee } from "./lotSync";
import { randomUUID } from "expo-crypto";

const MAX_RETRY = 10;
const MAX_BACKOFF_MS = 30_000;
const MAX_RETENTION_MS = 7 * 24 * 60 * 60 * 1000;

export type OutboxEntry = {
  entityType: OutboxEntityType;
  entityId: string;
  operation: OutboxOperation;
  payload: string;
};

export function pushToOutbox(entry: OutboxEntry): void {
  db.insert(outbox)
    .values({
      id: randomUUID(),
      entityType: entry.entityType,
      entityId: entry.entityId,
      operation: entry.operation,
      payload: entry.payload,
      status: "pending",
      retryCount: 0,
      createdAt: Date.now(),
    })
    .run();
}

export function getPendingCount(): number {
  return db
    .select()
    .from(outbox)
    .where(and(eq(outbox.status, "pending" as OutboxStatus)))
    .all().length;
}

let isSyncing = false;

export async function processOutbox(queryClient: QueryClient): Promise<void> {
  if (isSyncing) {
    console.log("[outbox] processOutbox skip (already syncing)");
    return;
  }
  isSyncing = true;
  try {
    const entries = db
      .select()
      .from(outbox)
      .where(and(eq(outbox.status, "pending" as OutboxStatus)))
      // rowid départage deux entrées créées dans la même milliseconde : le lot
      // doit partir avant ses tâches (cf ADR 0012).
      .orderBy(outbox.createdAt, sql`rowid`)
      .all();

    if (entries.length === 0) {
      console.log("[outbox] processOutbox no pending");
      return;
    }
    console.log(`[outbox] processOutbox start (${entries.length} pending)`);

    // Entités dont l'entrée n'a pas abouti pendant ce passage : leurs enfants
    // (tâches d'un lot, lots d'un chantier) attendent, sinon l'API répond 404.
    const nonSynchronises = new Set<string>();

    for (const entry of entries) {
      if (estBloquee(entry, nonSynchronises)) {
        nonSynchronises.add(entry.entityId);
        continue;
      }

      if (entry.retryCount >= MAX_RETRY) {
        nonSynchronises.add(entry.entityId);
        console.log(
          `[outbox] abandoned ${entry.entityType}/${entry.operation}/${entry.entityId} (max retry)`,
        );
        db.update(outbox)
          .set({ status: "abandoned" as OutboxStatus })
          .where(eq(outbox.id, entry.id))
          .run();
        continue;
      }

      const delay = Math.min(
        Math.pow(2, entry.retryCount) * 1000,
        MAX_BACKOFF_MS,
      );
      const lastAttempt = entry.lastAttemptAt ?? 0;
      if (Date.now() - lastAttempt < delay && entry.retryCount > 0) {
        nonSynchronises.add(entry.entityId);
        continue;
      }

      db.update(outbox)
        .set({ status: "syncing" as OutboxStatus, lastAttemptAt: Date.now() })
        .where(eq(outbox.id, entry.id))
        .run();

      try {
        const payload = JSON.parse(entry.payload) as Record<string, unknown>;
        await pushEntry(
          entry.entityType,
          entry.entityId,
          entry.operation,
          payload,
          queryClient,
        );
        db.update(outbox)
          .set({ status: "synced" as OutboxStatus })
          .where(eq(outbox.id, entry.id))
          .run();
        console.log(
          `[outbox] synced ${entry.entityType}/${entry.operation}/${entry.entityId}`,
        );
      } catch (err) {
        const status = (err as { status?: number }).status ?? 0;
        // 409 Conflict = idempotence : l'entite existe deja cote serveur avec ce UUID -> on considere synced
        // 404 sur un delete : deja supprime cote serveur (ex. tache supprimee avec son lot)
        if (
          status === 409 ||
          (status === 404 && entry.operation === "delete")
        ) {
          console.log(
            `[outbox] 409 -> synced ${entry.entityType}/${entry.operation}/${entry.entityId}`,
          );
          db.update(outbox)
            .set({ status: "synced" as OutboxStatus })
            .where(eq(outbox.id, entry.id))
            .run();
          continue;
        }
        nonSynchronises.add(entry.entityId);
        const isClientError = status >= 400 && status < 500;
        const nextStatus: OutboxStatus = isClientError
          ? "abandoned"
          : "pending";
        console.log(
          `[outbox] error ${entry.entityType}/${entry.operation}/${entry.entityId} status=${status} -> ${nextStatus}`,
        );
        db.update(outbox)
          .set({
            status: nextStatus,
            retryCount: entry.retryCount + 1,
            lastAttemptAt: Date.now(),
          })
          .where(eq(outbox.id, entry.id))
          .run();
      }
    }

    cleanupSynced();
  } finally {
    isSyncing = false;
  }
}

async function pushEntry(
  entityType: OutboxEntityType,
  entityId: string,
  operation: OutboxOperation,
  payload: Record<string, unknown>,
  queryClient: QueryClient,
): Promise<void> {
  if (entityType === "chantier") {
    if (operation === "create") {
      const created = await creerChantier(
        payload as Parameters<typeof creerChantier>[0],
      );
      upsertChantiers([created]);
      queryClient.setQueryData(["chantiers"], (old: unknown[] | undefined) =>
        ((old ?? []) as { id: string }[]).map((c) =>
          c.id === entityId ? created : c,
        ),
      );
    } else if (operation === "update") {
      const updated = await modifierChantier(
        entityId,
        payload as Parameters<typeof modifierChantier>[1],
      );
      upsertChantiers([updated]);
      queryClient.setQueryData(["chantiers"], (old: unknown[] | undefined) =>
        ((old ?? []) as { id: string }[]).map((c) =>
          c.id === entityId ? updated : c,
        ),
      );
    } else if (operation === "delete") {
      await archiverChantier(entityId);
      queryClient.setQueryData(["chantiers"], (old: unknown[] | undefined) =>
        ((old ?? []) as { id: string }[]).filter((c) => c.id !== entityId),
      );
    }
  } else if (entityType === "client") {
    if (operation === "create") {
      const created = await creerClient(
        payload as Parameters<typeof creerClient>[0],
      );
      upsertClients([created]);
      queryClient.setQueryData(["clients"], (old: unknown[] | undefined) =>
        ((old ?? []) as { id: string }[]).map((c) =>
          c.id === entityId ? created : c,
        ),
      );
    } else if (operation === "update") {
      const updated = await modifierClient(
        entityId,
        payload as Parameters<typeof modifierClient>[1],
      );
      upsertClients([updated]);
      queryClient.setQueryData(["clients"], (old: unknown[] | undefined) =>
        ((old ?? []) as { id: string }[]).map((c) =>
          c.id === entityId ? updated : c,
        ),
      );
    } else if (operation === "delete") {
      await supprimerClient(entityId);
      queryClient.setQueryData(["clients"], (old: unknown[] | undefined) =>
        ((old ?? []) as { id: string }[]).filter((c) => c.id !== entityId),
      );
    }
  } else if (entityType === "lot") {
    const chantierId = payload["chantierId"] as string;
    if (operation === "create") {
      await creerLot(
        chantierId,
        sans(payload, "chantierId") as Parameters<typeof creerLot>[1],
      );
      db.update(lots)
        .set({ syncedAt: Date.now() })
        .where(eq(lots.id, entityId))
        .run();
    } else if (operation === "delete") {
      await supprimerLot(entityId);
    }
    queryClient.setQueryData(
      ["lots", chantierId],
      getLotsForChantier(chantierId),
    );
  } else if (entityType === "tache") {
    const lotId = payload["lotId"] as string;
    if (operation === "create") {
      await creerTache(
        lotId,
        sans(payload, "lotId") as Parameters<typeof creerTache>[1],
      );
      db.update(taches)
        .set({ syncedAt: Date.now() })
        .where(eq(taches.id, entityId))
        .run();
    } else if (operation === "update") {
      await modifierTache(
        entityId,
        sans(payload, "lotId") as Parameters<typeof modifierTache>[1],
      );
    } else if (operation === "delete") {
      await supprimerTache(entityId);
    }
    queryClient.setQueryData(["taches", lotId], getTachesForLot(lotId));
  } else if (entityType === "photo") {
    const chantierId = payload["chantierId"] as string;
    if (operation === "update") {
      const updated = await patchPhotoLegende(
        entityId,
        (payload["legende"] as string | null) ?? null,
      );
      db.update(photos)
        .set({ legende: updated.legende })
        .where(eq(photos.id, entityId))
        .run();
    } else if (operation === "delete") {
      await deletePhoto(entityId);
      db.delete(photos).where(eq(photos.id, entityId)).run();
    }
    queryClient.setQueryData(
      ["photos", chantierId],
      getPhotosForChantier(chantierId),
    );
  }
}

// Le parent ne sert qu'à l'URL imbriquée et à l'ordre de sync : il ne part pas dans le corps.
function sans(
  payload: Record<string, unknown>,
  cle: string,
): Record<string, unknown> {
  const { [cle]: _ignoree, ...reste } = payload;
  return reste;
}

function cleanupSynced() {
  const cutoff = Date.now() - MAX_RETENTION_MS;
  db.delete(outbox)
    .where(
      and(
        eq(outbox.status, "synced" as OutboxStatus),
        lt(outbox.createdAt, cutoff),
      ),
    )
    .run();
}
