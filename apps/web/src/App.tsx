import { useQuery } from "@tanstack/react-query";
import { fetchHealth } from "./lib/api";
import { AppView } from "./AppView";

export default function App() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ["health"],
    queryFn: fetchHealth,
    refetchInterval: 10_000,
  });

  return <AppView data={data} isLoading={isLoading} isError={isError} />;
}
