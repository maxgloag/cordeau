import type { HealthResponse } from "@cordeau/shared";

const API_URL = import.meta.env["VITE_API_URL"] ?? "http://localhost:8000";

export async function fetchHealth(): Promise<HealthResponse> {
  const res = await fetch(`${API_URL}/health`);
  if (!res.ok) throw new Error(`Health check failed: ${res.status}`);
  return res.json() as Promise<HealthResponse>;
}
