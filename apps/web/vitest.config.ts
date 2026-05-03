import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import { playwright } from "@vitest/browser-playwright";

export default defineConfig({
  plugins: [react(), tailwindcss()],
  test: {
    projects: [
      {
        // Tests de logique pure — node, ultra-rapides, pas de browser
        extends: true,
        test: {
          name: "unit",
          environment: "node",
          include: ["src/**/*.test.ts"],
          setupFiles: [],
        },
      },
      {
        // Tests de composants React — vrai Chromium via Playwright
        extends: true,
        plugins: [react(), tailwindcss()],
        test: {
          name: "browser",
          include: ["src/**/*.test.tsx"],
          setupFiles: ["./src/test/setup.browser.ts"],
          browser: {
            enabled: true,
            provider: playwright(),
            headless: true,
            instances: [{ browser: "chromium" }],
          },
        },
      },
    ],
  },
});
