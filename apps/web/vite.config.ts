import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";

// Config Vite pure (build, dev server) — sans config de test
// Les tests sont configurés dans vitest.config.ts
export default defineConfig({
  plugins: [react(), tailwindcss()],
});
