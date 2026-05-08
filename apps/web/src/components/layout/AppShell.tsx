import type { ReactNode } from "react";
import { Link, useNavigate } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { HardHat, LayoutDashboard, LogOut } from "lucide-react";
import { toast } from "sonner";
import { fetchMe, logout } from "@/lib/api";

type Props = {
  children: ReactNode;
};

export default function AppShell({ children }: Props) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const { data: user } = useQuery({ queryKey: ["auth", "me"], queryFn: fetchMe, staleTime: 60_000 });

  async function handleLogout() {
    try {
      await logout();
      queryClient.clear();
      void navigate({ to: "/login" });
    } catch {
      toast.error("Erreur lors de la déconnexion");
    }
  }

  return (
    <div className="flex h-screen" style={{ background: "var(--color-background)", fontFamily: "var(--font-body)" }}>
      <aside
        className="flex w-56 flex-col"
        style={{ background: "var(--color-sidebar)", color: "var(--color-sidebar-foreground)" }}
      >
        <div className="flex items-center gap-2 px-5 py-6 border-b" style={{ borderColor: "var(--color-sidebar-border)" }}>
          <HardHat size={20} style={{ color: "var(--color-primary)" }} />
          <span style={{ fontFamily: "var(--font-heading)", fontWeight: 700, fontSize: "1.1rem", letterSpacing: "-0.02em" }}>
            Cordeau
          </span>
        </div>

        <nav className="flex flex-col gap-1 flex-1 px-3 py-4">
          <Link
            to="/dashboard"
            className="flex items-center gap-3 rounded px-3 py-2 text-sm transition-colors"
            activeProps={{ style: { background: "var(--color-sidebar-active)", color: "var(--color-sidebar-active-foreground)", fontWeight: 600 } }}
            inactiveProps={{ style: { color: "var(--color-sidebar-muted)" } }}
          >
            <LayoutDashboard size={15} />
            Chantiers
          </Link>
        </nav>

        <div className="px-4 py-4 border-t" style={{ borderColor: "var(--color-sidebar-border)" }}>
          <div className="text-xs mb-3 truncate" style={{ color: "var(--color-sidebar-muted)" }}>
            {user?.email ?? "…"}
          </div>
          <button
            onClick={() => void handleLogout()}
            className="flex items-center gap-2 text-xs transition-colors w-full text-sidebar-muted hover:text-sidebar-foreground"
          >
            <LogOut size={13} />
            Déconnexion
          </button>
        </div>
      </aside>

      <main className="flex-1 overflow-auto p-8">
        {children}
      </main>
    </div>
  );
}
