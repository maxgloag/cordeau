import { eq, and, lt } from "drizzle-orm";
import { db } from "./index";
import { outbox, chantiers, clients } from "./schema";
import type { OutboxEntityType, OutboxOperation, OutboxStatus } from "./schema";
import {
  creerChantier, modifierChantier, archiverChantier,
  creerClient, modifierClient, supprimerClient,
} from "@/lib/api";
import type { QueryClient } from "@tanstack/react-query";
import { upsertChantiers, upsertClients } from "./queries";
import { randomUUID } from "expo-crypto";

function rewriteLocalId(entityType: OutboxEntityType, oldId: string, newId: string) {
  if (oldId === newId) return;
  db.update(outbox)
    .set({ entityId: newId })
    .where(and(
      eq(outbox.entityId, oldId),
      eq(outbox.entityType, entityType),
      eq(outbox.status, "pending" as OutboxStatus),
    ))
    .run();
  const table = entityType === "chantier" ? chantiers : clients;
  db.delete(table).where(eq(table.id, oldId)).run();
}

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
  db.insert(outbox).values({
    id: randomUUID(),
    entityType: entry.entityType,
    entityId: entry.entityId,
    operation: entry.operation,
    payload: entry.payload,
    status: "pending",
    retryCount: 0,
    createdAt: Date.now(),
  }).run();
}

export function getPendingCount(): number {
  return db.select().from(outbox)
    .where(and(
      eq(outbox.status, "pending" as OutboxStatus),
    ))
    .all().length;
}

let isSyncing = false;

export async function processOutbox(queryClient: QueryClient): Promise<void> {
  if (isSyncing) return;
  isSyncing = true;
  try {
    const entries = db.select().from(outbox)
      .where(and(
        eq(outbox.status, "pending" as OutboxStatus),
      ))
      .all();

    for (const entry of entries) {
      const fresh = db.select().from(outbox).where(eq(outbox.id, entry.id)).get();
      if (!fresh || fresh.status !== "pending") continue;

      if (fresh.retryCount >= MAX_RETRY) {
        db.update(outbox).set({ status: "abandoned" as OutboxStatus }).where(eq(outbox.id, fresh.id)).run();
        continue;
      }

      const delay = Math.min(Math.pow(2, fresh.retryCount) * 1000, MAX_BACKOFF_MS);
      const lastAttempt = fresh.lastAttemptAt ?? 0;
      if (Date.now() - lastAttempt < delay && fresh.retryCount > 0) continue;

      db.update(outbox).set({ status: "syncing" as OutboxStatus, lastAttemptAt: Date.now() }).where(eq(outbox.id, fresh.id)).run();

      try {
        const payload = JSON.parse(fresh.payload) as Record<string, unknown>;
        await pushEntry(fresh.entityType, fresh.entityId, fresh.operation, payload, queryClient);
        db.update(outbox).set({ status: "synced" as OutboxStatus }).where(eq(outbox.id, fresh.id)).run();
      } catch (err) {
        const status = (err as { status?: number }).status ?? 0;
        const isClientError = status >= 400 && status < 500;
        db.update(outbox).set({
          status: (isClientError ? "abandoned" : "pending") as OutboxStatus,
          retryCount: fresh.retryCount + 1,
          lastAttemptAt: Date.now(),
        }).where(eq(outbox.id, fresh.id)).run();
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
      const created = await creerChantier(payload as Parameters<typeof creerChantier>[0]);
      rewriteLocalId("chantier", entityId, created.id);
      upsertChantiers([created]);
      queryClient.setQueryData(["chantiers"], (old: unknown[] | undefined) => {
        const list = (old ?? []) as { id: string }[];
        if (list.some((c) => c.id === created.id)) return list;
        if (list.some((c) => c.id === entityId)) return list.map((c) => (c.id === entityId ? created : c));
        return [...list, created];
      });
    } else if (operation === "update") {
      const updated = await modifierChantier(entityId, payload as Parameters<typeof modifierChantier>[1]);
      upsertChantiers([updated]);
      queryClient.setQueryData(["chantiers"], (old: unknown[]) =>
        (old ?? []).map((c) => ((c as { id: string }).id === entityId ? updated : c)),
      );
    } else if (operation === "delete") {
      await archiverChantier(entityId);
      queryClient.setQueryData(["chantiers"], (old: unknown[]) =>
        (old ?? []).filter((c) => (c as { id: string }).id !== entityId),
      );
    }
  } else if (entityType === "client") {
    if (operation === "create") {
      const created = await creerClient(payload as Parameters<typeof creerClient>[0]);
      rewriteLocalId("client", entityId, created.id);
      upsertClients([created]);
      queryClient.setQueryData(["clients"], (old: unknown[] | undefined) => {
        const list = (old ?? []) as { id: string }[];
        if (list.some((c) => c.id === created.id)) return list;
        if (list.some((c) => c.id === entityId)) return list.map((c) => (c.id === entityId ? created : c));
        return [...list, created];
      });
    } else if (operation === "update") {
      const updated = await modifierClient(entityId, payload as Parameters<typeof modifierClient>[1]);
      upsertClients([updated]);
      queryClient.setQueryData(["clients"], (old: unknown[]) =>
        (old ?? []).map((c) => ((c as { id: string }).id === entityId ? updated : c)),
      );
    } else if (operation === "delete") {
      await supprimerClient(entityId);
      queryClient.setQueryData(["clients"], (old: unknown[]) =>
        (old ?? []).filter((c) => (c as { id: string }).id !== entityId),
      );
    }
  }
}

function cleanupSynced() {
  const cutoff = Date.now() - MAX_RETENTION_MS;
  db.delete(outbox)
    .where(and(
      eq(outbox.status, "synced" as OutboxStatus),
      lt(outbox.createdAt, cutoff),
    ))
    .run();
}
