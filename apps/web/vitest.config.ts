import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import { playwright } from "@vitest/browser-playwright";
import path from "path";

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
    dedupe: [
      "react",
      "react-dom",
      "@tanstack/react-router",
      "@tanstack/react-query",
    ],
  },
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
            // En CI, PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH pointe sur Chrome for Testing
            // (browser-actions/setup-chrome, CDN Google). launchOptions est spreader dans
            // browserType.launch() par PlaywrightBrowserProvider — seul moyen de surcharger
            // le headless-shell de Playwright 1.50+ (l'env var seule est ignorée).
            provider: playwright(
              process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
                ? { launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } }
                : {},
            ),
            headless: true,
            instances: [{ browser: "chromium" }],
          },
        },
      },
    ],
  },
});
