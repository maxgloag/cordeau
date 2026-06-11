CREATE TABLE `outbox_photos` (
	`id` text PRIMARY KEY NOT NULL,
	`photo_id` text NOT NULL,
	`local_uri` text NOT NULL,
	`chantier_id` text NOT NULL,
	`status` text DEFAULT 'pending' NOT NULL,
	`upload_url` text,
	`remote_key` text,
	`retry_count` integer DEFAULT 0 NOT NULL,
	`created_at` integer NOT NULL,
	`last_attempt_at` integer
);
--> statement-breakpoint
CREATE TABLE `photos` (
	`id` text PRIMARY KEY NOT NULL,
	`chantier_id` text NOT NULL,
	`lot_id` text,
	`tache_id` text,
	`remote_key` text,
	`local_uri` text,
	`photo_url` text,
	`thumbnail_url` text,
	`status` text DEFAULT 'local' NOT NULL,
	`created_at` integer NOT NULL,
	`synced_at` integer
);
