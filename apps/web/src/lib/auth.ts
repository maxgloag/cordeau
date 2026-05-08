import { redirect } from "@tanstack/react-router";
import type { QueryClient } from "@tanstack/react-query";
import { fetchMe } from "./api";

const AUTH_STALE_MS = 60_000;

function isTanStackRedirect(err: unknown): boolean {
  return (
    err instanceof Response ||
    (typeof err === "object" && err !== null && "to" in err)
  );
}

export async function requireAuth(queryClient: QueryClient): Promise<void> {
  try {
    await queryClient.fetchQuery({
      queryKey: ["auth", "me"],
      queryFn: fetchMe,
      staleTime: AUTH_STALE_MS,
    });
  } catch (err) {
    if (isTanStackRedirect(err)) throw err;
    throw redirect({ to: "/login" });
  }
}

export async function requireGuest(queryClient: QueryClient): Promise<void> {
  try {
    await queryClient.fetchQuery({
      queryKey: ["auth", "me"],
      queryFn: fetchMe,
      staleTime: AUTH_STALE_MS,
    });
    throw redirect({ to: "/dashboard" });
  } catch (err) {
    if (isTanStackRedirect(err)) throw err;
  }
}
