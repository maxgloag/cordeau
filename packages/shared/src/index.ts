export type HealthResponse = {
  status: "ok" | "degraded";
  timestamp: string;
  version: string;
  services?: Record<string, string>;
};

export type { paths, components, operations } from "./api.generated";
