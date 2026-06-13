/**
 * Harness de test : vraie base SQLite en mémoire via sql.js (WASM pur, aucun module
 * natif), avec le schéma chargé depuis les migrations Drizzle réelles.
 *
 * `expo-sqlite` ne tourne pas sous Jest (module natif iOS/Android), mais sql.js +
 * l'adaptateur `drizzle-orm/sql-js` exposent la MÊME API de query que le `db` de prod,
 * donc le code de `db/queries.ts` / `db/photoOutbox.ts` s'exécute tel quel contre cette
 * base — on teste le comportement réel (lignes, jointures, ON CONFLICT), pas un mock.
 *
 * Usage dans un test :
 *   jest.mock("../db/index", () => ({
 *     get db() { return require("../test-utils/testDb").holder.db; },
 *   }));
 *   beforeAll(loadSqlJs);
 *   beforeEach(freshTestDb);
 */
import fs from "fs";
import path from "path";
import initSqlJs, { type SqlJsStatic } from "sql.js";
import { drizzle, type SQLJsDatabase } from "drizzle-orm/sql-js";

export const holder: { db: SQLJsDatabase | null } = { db: null };

let SQL: SqlJsStatic | null = null;

export async function loadSqlJs(): Promise<void> {
  if (SQL) return;
  // jest-expo polyfille `window`, donc sql.js prendrait le chemin de chargement
  // browser (fetch du .wasm) qui échoue sous Jest. On fournit le binaire wasm
  // directement (option documentée) pour court-circuiter la détection d'environnement.
  const distDir = path.dirname(require.resolve("sql.js"));
  const buf = fs.readFileSync(path.join(distDir, "sql-wasm.wasm"));
  const wasmBinary = buf.buffer.slice(
    buf.byteOffset,
    buf.byteOffset + buf.byteLength,
  );
  SQL = await initSqlJs({ wasmBinary });
}

export function freshTestDb(): void {
  if (!SQL) throw new Error("loadSqlJs() doit être appelé avant freshTestDb()");
  const sqlite = new SQL.Database();

  const migrationsDir = path.join(__dirname, "../db/migrations");
  const files = fs
    .readdirSync(migrationsDir)
    .filter((f) => f.endsWith(".sql"))
    .sort();
  for (const file of files) {
    sqlite.run(fs.readFileSync(path.join(migrationsDir, file), "utf8"));
  }

  holder.db = drizzle(sqlite);
}
