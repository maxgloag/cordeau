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
    surfaceM2: r.surfaceM2 ?? null,
    statut: r.statut,
    clientId: r.clientId ?? null,
    clientNom: r.clientNom ?? null,
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
        adresseRue: chantiers.adresseRue,
        adresseCodePostal: chantiers.adresseCodePostal,
        adresseVille: chantiers.adresseVille,
        adressePays: chantiers.adressePays,
        surfaceM2: chantiers.surfaceM2,
        statut: chantiers.statut,
        clientId: chantiers.clientId,
        clientNom: chantiers.clientNom,
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
    email: r.email ?? null,
    telephone: r.telephone ?? null,
    adresseRue: r.adresseRue,
    adresseCodePostal: r.adresseCodePostal,
    adresseVille: r.adresseVille,
    adressePays: r.adressePays,
    notes: r.notes ?? null,
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
        nom: clients.nom,
        email: clients.email,
        telephone: clients.telephone,
        adresseRue: clients.adresseRue,
        adresseCodePostal: clients.adresseCodePostal,
        adresseVille: clients.adresseVille,
        adressePays: clients.adressePays,
        notes: clients.notes,
        syncedAt: now,
        updatedAt: now,
      },
    })
    .run();
}
