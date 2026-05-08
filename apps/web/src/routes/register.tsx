import { createFileRoute } from "@tanstack/react-router";
import { requireGuest } from "@/lib/auth";
import RegisterPage from "@/pages/register/RegisterPage";

export const Route = createFileRoute("/register")({
  beforeLoad: async ({ context }) => {
    await requireGuest(context.queryClient);
  },
  component: RegisterPage,
});
