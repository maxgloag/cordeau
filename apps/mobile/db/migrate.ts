import { migrate } from "drizzle-orm/expo-sqlite/migrator";
import { db } from "./index";
import migrations from "./migrations/migrations";

export function runMigrations() {
  migrate(db, migrations);
}
