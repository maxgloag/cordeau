import { useQuery } from "@tanstack/react-query";
import type { HealthResponse } from "@cordeau/shared";

const API_URL = import.meta.env["VITE_API_URL"] ?? "http://localhost:8000";

async function fetchHealth(): Promise<HealthResponse> {
  const res = await fetch(`${API_URL}/health`);
  if (!res.ok) throw new Error("Health check failed");
  return res.json() as Promise<HealthResponse>;
}

export default function App() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ["health"],
    queryFn: fetchHealth,
    refetchInterval: 10_000,
  });

  return (
    <main className="flex min-h-screen items-center justify-center bg-gray-950 text-white">
      <div className="rounded-2xl border border-gray-800 bg-gray-900 p-10 text-center shadow-xl">
        <h1 className="mb-6 text-2xl font-bold tracking-tight">Cordeau API</h1>

        {isLoading && (
          <p className="text-gray-400">Vérification du statut…</p>
        )}

        {isError && (
          <div className="flex items-center gap-2 justify-center">
            <span className="h-3 w-3 rounded-full bg-red-500" />
            <span className="text-red-400 font-medium">API injoignable</span>
          </div>
        )}

        {data && (
          <div className="space-y-3">
            <div className="flex items-center gap-2 justify-center">
              <span
                className={`h-3 w-3 rounded-full ${
                  data.status === "ok" ? "bg-green-400" : "bg-yellow-400"
                }`}
              />
              <span className="font-semibold capitalize">{data.status}</span>
            </div>
            <p className="text-sm text-gray-400">
              v{data.version} ·{" "}
              {new Date(data.timestamp).toLocaleTimeString("fr-FR")}
            </p>
            {data.services && (
              <div className="mt-4 text-xs text-gray-500 space-y-1">
                {Object.entries(data.services).map(([name, status]) => (
                  <div key={name} className="flex justify-between gap-8">
                    <span>{name}</span>
                    <span
                      className={
                        status === "ok" ? "text-green-400" : "text-red-400"
                      }
                    >
                      {status as string}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </main>
  );
}
