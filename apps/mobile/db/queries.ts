import { sql } from "drizzle-orm";
import { db } from "./index";
import { chantiers, clients } from "./schema";
import type { Chantier, Client } from "@/lib/api";

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
