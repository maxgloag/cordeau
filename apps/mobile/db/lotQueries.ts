import { asc, eq, inArray, sql } from "drizzle-orm";
import { db } from "./index";
import { lots, outbox, taches } from "./schema";
import { planSuppressions } from "./lotSync";
import type { Lot, Tache } from "@/lib/api";

export function getLotsForChantier(chantierId: string): Lot[] {
  return db
    .select()
    .from(lots)
    .where(eq(lots.chantierId, chantierId))
    .orderBy(asc(lots.ordre), asc(lots.id))
    .all()
    .map((r) => ({
      id: r.id,
      chantierId: r.chantierId,
      nom: r.nom,
      mode: r.mode as Lot["mode"],
      estimation: r.estimation,
      reel: r.reel,
      ordre: r.ordre,
    }));
}

export function getTachesForLot(lotId: string): Tache[] {
  return db
    .select()
    .from(taches)
    .where(eq(taches.lotId, lotId))
    .orderBy(asc(taches.ordre), asc(taches.id))
    .all()
    .map((r) => ({
      id: r.id,
      lotId: r.lotId,
      libelle: r.libelle,
      faite: r.faite,
      faiteLe: r.faiteLe,
      ordre: r.ordre,
    }));
}

/** Création locale : syncedAt reste nul tant que le serveur n'a pas confirmé. */
export function insertLotLocal(lot: Lot): void {
  db.insert(lots)
    .values({ ...lot, syncedAt: null })
    .onConflictDoNothing()
    .run();
}

export function insertTacheLocal(tache: Tache): void {
  db.insert(taches)
    .values({ ...tache, syncedAt: null })
    .onConflictDoNothing()
    .run();
}

/** Supprime le lot et ses tâches (le serveur supprime les tâches en cascade). */
export function deleteLotLocal(id: string): void {
  db.delete(taches).where(eq(taches.lotId, id)).run();
  db.delete(lots).where(eq(lots.id, id)).run();
}

export function deleteTacheLocal(id: string): void {
  db.delete(taches).where(eq(taches.id, id)).run();
}

export function setTacheFaiteLocal(id: string, faite: boolean): void {
  db.update(taches)
    .set({ faite, faiteLe: faite ? new Date().toISOString() : null })
    .where(eq(taches.id, id))
    .run();
}

export function setTacheLibelleLocal(id: string, libelle: string): void {
  db.update(taches).set({ libelle }).where(eq(taches.id, id)).run();
}

/** Entités touchées par une entrée d'outbox pas encore synchronisée. */
export function getIdsEnAttente(): Set<string> {
  return new Set(
    db
      .select({ entityId: outbox.entityId })
      .from(outbox)
      .where(inArray(outbox.status, ["pending", "syncing"]))
      .all()
      .map((r) => r.entityId),
  );
}

export function upsertLots(items: Lot[]): void {
  if (items.length === 0) return;
  const now = Date.now();
  db.insert(lots)
    .values(items.map((l) => ({ ...l, syncedAt: now })))
    .onConflictDoUpdate({
      target: lots.id,
      set: {
        nom: sql`excluded.nom`,
        mode: sql`excluded.mode`,
        estimation: sql`excluded.estimation`,
        reel: sql`excluded.reel`,
        ordre: sql`excluded.ordre`,
        syncedAt: now,
      },
    })
    .run();
}

export function upsertTaches(items: Tache[]): void {
  if (items.length === 0) return;
  const now = Date.now();
  db.insert(taches)
    .values(items.map((t) => ({ ...t, syncedAt: now })))
    .onConflictDoUpdate({
      target: taches.id,
      set: {
        libelle: sql`excluded.libelle`,
        faite: sql`excluded.faite`,
        faiteLe: sql`excluded.faite_le`,
        ordre: sql`excluded.ordre`,
        syncedAt: now,
      },
    })
    .run();
}

/**
 * Réconcilie les lots d'un chantier avec la liste serveur sans écraser ce que
 * l'outbox n'a pas encore poussé (création hors ligne, suppression en attente).
 */
export function reconcileLots(chantierId: string, remote: Lot[]): void {
  const enAttente = getIdsEnAttente();
  const locales = db
    .select({ id: lots.id, syncedAt: lots.syncedAt })
    .from(lots)
    .where(eq(lots.chantierId, chantierId))
    .all();
  upsertLots(remote.filter((l) => !enAttente.has(l.id)));
  for (const id of planSuppressions(locales, remote, enAttente)) {
    deleteLotLocal(id);
  }
}

export function reconcileTaches(lotId: string, remote: Tache[]): void {
  const enAttente = getIdsEnAttente();
  const locales = db
    .select({ id: taches.id, syncedAt: taches.syncedAt })
    .from(taches)
    .where(eq(taches.lotId, lotId))
    .all();
  upsertTaches(remote.filter((t) => !enAttente.has(t.id)));
  const aSupprimer = planSuppressions(locales, remote, enAttente);
  if (aSupprimer.length > 0) {
    db.delete(taches).where(inArray(taches.id, aSupprimer)).run();
  }
}
