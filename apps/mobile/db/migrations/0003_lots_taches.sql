CREATE TABLE `lots` (
	`id` text PRIMARY KEY NOT NULL,
	`chantier_id` text NOT NULL,
	`nom` text NOT NULL,
	`mode` text NOT NULL,
	`estimation` real,
	`reel` real,
	`ordre` integer DEFAULT 0 NOT NULL,
	`synced_at` integer
);
--> statement-breakpoint
CREATE TABLE `taches` (
	`id` text PRIMARY KEY NOT NULL,
	`lot_id` text NOT NULL,
	`libelle` text NOT NULL,
	`faite` integer DEFAULT false NOT NULL,
	`faite_le` text,
	`ordre` integer DEFAULT 0 NOT NULL,
	`synced_at` integer
);
