export type HealthResponse = {
  status: "ok" | "degraded";
  timestamp: string;
  version: string;
  services?: Record<string, string>;
};
