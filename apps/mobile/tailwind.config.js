/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./app/**/*.{js,jsx,ts,tsx}", "./components/**/*.{js,jsx,ts,tsx}"],
  presets: [require("nativewind/preset")],
  theme: {
    extend: {
      colors: {
        background: "#F2EDE4",
        surface: "#FFFFFF",
        border: "#E2DAD0",
        text: "#1C1813",
        muted: "#6B6259",
        primary: "#B85C2A",
        "primary-foreground": "#FFFFFF",
        destructive: "#C0392B",
        "sidebar-bg": "#1C1813",
        "status-preparation": "#3B82F6",
        "status-cours": "#22C55E",
        "status-termine": "#10B981",
        "status-archive": "#9CA3AF",
      },
      fontFamily: {
        heading: ["BricolageGrotesque_700Bold"],
        "heading-medium": ["BricolageGrotesque_500Medium"],
        body: ["System"],
      },
    },
  },
  plugins: [],
};
