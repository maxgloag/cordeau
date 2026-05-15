CREATE TABLE `chantiers` (
	`id` text PRIMARY KEY NOT NULL,
	`adresse_rue` text NOT NULL,
	`adresse_code_postal` text NOT NULL,
	`adresse_ville` text NOT NULL,
	`adresse_pays` text DEFAULT 'FR' NOT NULL,
	`surface_m2` real,
	`statut` text DEFAULT 'en_preparation' NOT NULL,
	`client_id` text,
	`client_nom` text,
	`synced_at` integer,
	`updated_at` integer
);
--> statement-breakpoint
CREATE TABLE `clients` (
	`id` text PRIMARY KEY NOT NULL,
	`nom` text NOT NULL,
	`email` text,
	`telephone` text,
	`adresse_rue` text NOT NULL,
	`adresse_code_postal` text NOT NULL,
	`adresse_ville` text NOT NULL,
	`adresse_pays` text DEFAULT 'FR' NOT NULL,
	`notes` text,
	`synced_at` integer,
	`updated_at` integer
);
--> statement-breakpoint
CREATE TABLE `outbox` (
	`id` text PRIMARY KEY NOT NULL,
	`entity_type` text NOT NULL,
	`entity_id` text NOT NULL,
	`operation` text NOT NULL,
	`payload` text NOT NULL,
	`status` text DEFAULT 'pending' NOT NULL,
	`retry_count` integer DEFAULT 0 NOT NULL,
	`created_at` integer NOT NULL,
	`last_attempt_at` integer
);
