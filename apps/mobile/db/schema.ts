import { integer, real, sqliteTable, text } from "drizzle-orm/sqlite-core";

export type OutboxEntityType = "chantier" | "client" | "photo";
export type OutboxOperation = "create" | "update" | "delete";
export type OutboxStatus =
  | "pending"
  | "syncing"
  | "synced"
  | "error"
  | "abandoned";

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
  entityType: text("entity_type").$type<OutboxEntityType>().notNull(),
  entityId: text("entity_id").notNull(),
  operation: text("operation").$type<OutboxOperation>().notNull(),
  payload: text("payload").notNull(),
  status: text("status").$type<OutboxStatus>().notNull().default("pending"),
  retryCount: integer("retry_count").notNull().default(0),
  createdAt: integer("created_at").notNull(),
  lastAttemptAt: integer("last_attempt_at"),
});

export type PhotoStatus = "local" | "confirmed";
export type OutboxPhotoStatus =
  | "pending"
  | "uploading"
  | "confirming"
  | "confirmed"
  | "failed";

export const photos = sqliteTable("photos", {
  id: text("id").primaryKey(),
  chantierId: text("chantier_id").notNull(),
  lotId: text("lot_id"),
  tacheId: text("tache_id"),
  remoteKey: text("remote_key"),
  localUri: text("local_uri"),
  photoUrl: text("photo_url"),
  thumbnailUrl: text("thumbnail_url"),
  legende: text("legende"),
  status: text("status").$type<PhotoStatus>().notNull().default("local"),
  createdAt: integer("created_at").notNull(),
  syncedAt: integer("synced_at"),
});

export const outboxPhotos = sqliteTable("outbox_photos", {
  id: text("id").primaryKey(),
  photoId: text("photo_id").notNull(),
  localUri: text("local_uri").notNull(),
  chantierId: text("chantier_id").notNull(),
  status: text("status")
    .$type<OutboxPhotoStatus>()
    .notNull()
    .default("pending"),
  uploadUrl: text("upload_url"),
  remoteKey: text("remote_key"),
  retryCount: integer("retry_count").notNull().default(0),
  createdAt: integer("created_at").notNull(),
  lastAttemptAt: integer("last_attempt_at"),
});
