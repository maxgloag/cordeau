import { createFileRoute } from "@tanstack/react-router";
import ClientsListPage from "@/pages/clients/ClientsListPage";

export const Route = createFileRoute("/_authed/clients")({
  component: ClientsListPage,
});
