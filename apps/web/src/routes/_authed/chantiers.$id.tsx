import { createFileRoute } from "@tanstack/react-router";
import { ChantierDetailPage } from "@/pages/chantier/ChantierDetailPage";

export const Route = createFileRoute("/_authed/chantiers/$id")({
  component: ChantierDetailPage,
});
