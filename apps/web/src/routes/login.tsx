import { createFileRoute } from "@tanstack/react-router";
import { requireGuest } from "@/lib/auth";
import LoginPage from "@/pages/login/LoginPage";

export const Route = createFileRoute("/login")({
  beforeLoad: async ({ context }) => {
    await requireGuest(context.queryClient);
  },
  component: LoginPage,
});
