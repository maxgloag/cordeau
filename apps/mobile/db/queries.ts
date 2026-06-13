import { eq, inArray, sql } from "drizzle-orm";
import { db } from "./index";
import { chantiers, clients, photos } from "./schema";
import { planPhotoDeletions } from "./photoSync";
import type { Chantier, Client, PhotoApiResponse } from "@/lib/api";

export function getAllChantiers(): Chantier[] {
  const rows = db.select().from(chantiers).all();
  return rows.map((r) => ({
    id: r.id,
    adresseRue: r.adresseRue,
    adresseCodePostal: r.adresseCodePostal,
    adresseVille: r.adresseVille,
    adressePays: r.adressePays,
    surfaceM2: r.surfaceM2,
    statut: r.statut,
    clientId: r.clientId,
    clientNom: r.clientNom,
  }));
}

export function upsertChantiers(items: Chantier[]) {
  if (items.length === 0) return;
  const now = Date.now();
  db.insert(chantiers)
    .values(
      items.map((c) => ({
        id: c.id,
        adresseRue: c.adresseRue,
        adresseCodePostal: c.adresseCodePostal,
        adresseVille: c.adresseVille,
        adressePays: c.adressePays,
        surfaceM2: c.surfaceM2 ?? null,
        statut: c.statut,
        clientId: c.clientId ?? null,
        clientNom: c.clientNom ?? null,
        syncedAt: now,
        updatedAt: now,
      })),
    )
    .onConflictDoUpdate({
      target: chantiers.id,
      set: {
        adresseRue: sql`excluded.adresse_rue`,
        adresseCodePostal: sql`excluded.adresse_code_postal`,
        adresseVille: sql`excluded.adresse_ville`,
        adressePays: sql`excluded.adresse_pays`,
        surfaceM2: sql`excluded.surface_m2`,
        statut: sql`excluded.statut`,
        clientId: sql`excluded.client_id`,
        clientNom: sql`excluded.client_nom`,
        syncedAt: now,
        updatedAt: now,
      },
    })
    .run();
}

export function getAllClients(): Client[] {
  const rows = db.select().from(clients).all();
  return rows.map((r) => ({
    id: r.id,
    nom: r.nom,
    email: r.email,
    telephone: r.telephone,
    adresseRue: r.adresseRue,
    adresseCodePostal: r.adresseCodePostal,
    adresseVille: r.adresseVille,
    adressePays: r.adressePays,
    notes: r.notes,
  }));
}

export function deleteClientLocal(id: string) {
  db.delete(clients).where(eq(clients.id, id)).run();
}

export function getPhotosForChantier(chantierId: string) {
  return db
    .select()
    .from(photos)
    .where(eq(photos.chantierId, chantierId))
    .orderBy(photos.createdAt)
    .all();
}

/**
 * Réconcilie les photos locales d'un chantier avec la liste serveur :
 * - upsert des photos distantes (status "confirmed"),
 * - suppression des photos locales "confirmed" absentes du serveur (supprimées ailleurs),
 * - préservation des photos "local" (upload en cours via l'outbox — jamais supprimées).
 */
export function reconcilePhotos(
  chantierId: string,
  remote: PhotoApiResponse[],
): void {
  const local = getPhotosForChantier(chantierId);
  const toDelete = planPhotoDeletions(local, remote);

  if (remote.length > 0) {
    const now = Date.now();
    db.insert(photos)
      .values(
        remote.map((p) => ({
          id: p.id,
          chantierId: p.chantierId,
          lotId: p.lotId,
          tacheId: p.tacheId,
          remoteKey: p.remoteKey,
          localUri: null,
          photoUrl: p.photoUrl,
          thumbnailUrl: p.thumbnailUrl,
          legende: p.legende,
          status: "confirmed" as const,
          createdAt: Date.parse(p.creeLe) || now,
          syncedAt: now,
        })),
      )
      .onConflictDoUpdate({
        target: photos.id,
        set: {
          remoteKey: sql`excluded.remote_key`,
          photoUrl: sql`excluded.photo_url`,
          thumbnailUrl: sql`excluded.thumbnail_url`,
          legende: sql`excluded.legende`,
          status: sql`excluded.status`,
          syncedAt: now,
        },
      })
      .run();
  }

  if (toDelete.length > 0) {
    db.delete(photos).where(inArray(photos.id, toDelete)).run();
  }
}

export function upsertClients(items: Client[]) {
  if (items.length === 0) return;
  const now = Date.now();
  db.insert(clients)
    .values(
      items.map((c) => ({
        id: c.id,
        nom: c.nom,
        email: c.email ?? null,
        telephone: c.telephone ?? null,
        adresseRue: c.adresseRue,
        adresseCodePostal: c.adresseCodePostal,
        adresseVille: c.adresseVille,
        adressePays: c.adressePays,
        notes: c.notes ?? null,
        syncedAt: now,
        updatedAt: now,
      })),
    )
    .onConflictDoUpdate({
      target: clients.id,
      set: {
        nom: sql`excluded.nom`,
        email: sql`excluded.email`,
        telephone: sql`excluded.telephone`,
        adresseRue: sql`excluded.adresse_rue`,
        adresseCodePostal: sql`excluded.adresse_code_postal`,
        adresseVille: sql`excluded.adresse_ville`,
        adressePays: sql`excluded.adresse_pays`,
        notes: sql`excluded.notes`,
        syncedAt: now,
        updatedAt: now,
      },
    })
    .run();
}
