import { createFileRoute, Outlet } from "@tanstack/react-router";
import { requireAuth } from "@/lib/auth";
import AppShell from "@/components/layout/AppShell";

export const Route = createFileRoute("/_authed")({
  beforeLoad: async ({ context }) => {
    await requireAuth(context.queryClient);
  },
  component: () => (
    <AppShell>
      <Outlet />
    </AppShell>
  ),
});
