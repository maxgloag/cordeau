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
    dedupe: ["react", "react-dom", "@tanstack/react-router", "@tanstack/react-query"],
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
            provider: playwright(),
            headless: true,
            instances: [{ browser: "chromium" }],
            // En CI, PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH pointe sur Chrome for Testing
            // installé depuis le CDN Google (browser-actions/setup-chrome). On le passe
            // explicitement en executablePath car le headless-shell de Playwright ne
            // respecte pas cette env var dans les versions 1.50+.
            ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
              ? {
                  providerOptions: {
                    launch: {
                      executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH,
                    },
                  },
                }
              : {}),
          },
        },
      },
    ],
  },
});
