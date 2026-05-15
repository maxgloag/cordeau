import { integer, real, sqliteTable, text } from "drizzle-orm/sqlite-core";

export const chantiers = sqliteTable("chantiers", {
  id: text("id").primaryKey(),
  adresseRue: text("adresse_rue").notNull(),
  adresseCodePostal: text("adresse_code_postal").notNull(),
  adresseVille: text("adresse_ville").notNull(),
  adressePays: text("adresse_pays").notNull().default("FR"),
  surfaceM2: real("surface_m2"),
  statut: text("statut").notNull().default("en_preparation"),
  clientId: text("client_id"),
  clientNom: text("client_nom"),
  syncedAt: integer("synced_at"),
  updatedAt: integer("updated_at"),
});

export const clients = sqliteTable("clients", {
  id: text("id").primaryKey(),
  nom: text("nom").notNull(),
  email: text("email"),
  telephone: text("telephone"),
  adresseRue: text("adresse_rue").notNull(),
  adresseCodePostal: text("adresse_code_postal").notNull(),
  adresseVille: text("adresse_ville").notNull(),
  adressePays: text("adresse_pays").notNull().default("FR"),
  notes: text("notes"),
  syncedAt: integer("synced_at"),
  updatedAt: integer("updated_at"),
});

export const outbox = sqliteTable("outbox", {
  id: text("id").primaryKey(),
  entityType: text("entity_type").notNull(),
  entityId: text("entity_id").notNull(),
  operation: text("operation").notNull(),
  payload: text("payload").notNull(),
  status: text("status").notNull().default("pending"),
  retryCount: integer("retry_count").notNull().default(0),
  createdAt: integer("created_at").notNull(),
  lastAttemptAt: integer("last_attempt_at"),
});
