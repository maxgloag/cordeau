import { createFileRoute, redirect } from "@tanstack/react-router";
import { requireGuest } from "@/lib/auth";
import { exchangeOAuthCode } from "@/lib/api";

export const Route = createFileRoute("/")({
  beforeLoad: async ({ context }) => {
    const params = new URLSearchParams(window.location.search);
    const loginCode = params.get("login_code");
    if (loginCode) {
      window.history.replaceState({}, "", "/");
      try {
        const user = await exchangeOAuthCode(loginCode);
        context.queryClient.setQueryData(["auth", "me"], user);
        throw redirect({ to: "/dashboard" });
      } catch (err) {
        if (typeof err === "object" && err !== null && "to" in err) throw err;
        throw redirect({ to: "/login", search: { oauth_error: "provider" } });
      }
    }

    await requireGuest(context.queryClient);
    throw redirect({ to: "/login" });
  },
});
