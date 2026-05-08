import { createFileRoute, redirect } from "@tanstack/react-router";
import { requireGuest } from "@/lib/auth";

export const Route = createFileRoute("/")({
  beforeLoad: async ({ context }) => {
    await requireGuest(context.queryClient);
    throw redirect({ to: "/login" });
  },
});
