import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import {
  fetchChantiers,
  creerChantier,
  modifierChantier,
  archiverChantier,
  listClients,
} from "@/lib/api";
import type { Chantier } from "@/lib/api";
import { DashboardView } from "./DashboardView";
import type { ChantierFormValues } from "./ChantierFormView";

const chantierSchema = z.object({
  adresseRue: z.string().min(1, "Adresse requise"),
  adresseCodePostal: z
    .string()
    .min(1, "Code postal requis")
    .regex(/^\d{5}$/, "Format invalide (5 chiffres)"),
  adresseVille: z.string().min(1, "Ville requise"),
  adressePays: z.string().min(1),
  surfaceM2: z.string().refine(
    (v) => v === "" || (!isNaN(parseFloat(v)) && parseFloat(v) > 0),
    { message: "Surface invalide" },
  ),
  clientId: z.string(),
});

function makeDefaultValues(c?: Chantier): ChantierFormValues {
  return {
    adresseRue: c?.adresseRue ?? "",
    adresseCodePostal: c?.adresseCodePostal ?? "",
    adresseVille: c?.adresseVille ?? "",
    adressePays: c?.adressePays ?? "FR",
    surfaceM2: c?.surfaceM2 != null ? String(c.surfaceM2) : "",
    clientId: c?.clientId ?? "",
  };
}

export default function DashboardPage() {
  const queryClient = useQueryClient();

  const { data: clients = [] } = useQuery({ queryKey: ["clients"], queryFn: listClients });

  // Chantiers
  const { data: chantiers = [], isLoading, isError } = useQuery({
    queryKey: ["chantiers"],
    queryFn: fetchChantiers,
  });

  // Création
  const [showCreate, setShowCreate] = useState(false);
  const createForm = useForm<ChantierFormValues>({
    resolver: zodResolver(chantierSchema),
    defaultValues: makeDefaultValues(),
  });

  const createMutation = useMutation({
    mutationFn: (values: ChantierFormValues) =>
      creerChantier({
        adresseRue: values.adresseRue,
        adresseCodePostal: values.adresseCodePostal,
        adresseVille: values.adresseVille,
        adressePays: values.adressePays,
        surfaceM2: values.surfaceM2 !== "" ? parseFloat(values.surfaceM2) : null,
        clientId: values.clientId || null,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["chantiers"] });
      setShowCreate(false);
      createForm.reset(makeDefaultValues());
      toast.success("Chantier créé avec succès");
    },
    onError: () => toast.error("Erreur lors de la création"),
  });

  // Modification
  const [editingChantier, setEditingChantier] = useState<Chantier | null>(null);
  const editForm = useForm<ChantierFormValues>({
    resolver: zodResolver(chantierSchema),
    defaultValues: makeDefaultValues(),
  });

  function openEdit(c: Chantier) {
    setEditingChantier(c);
    editForm.reset(makeDefaultValues(c));
  }

  const editMutation = useMutation({
    mutationFn: (values: ChantierFormValues) => {
      if (!editingChantier) throw new Error("Aucun chantier sélectionné");
      return modifierChantier(editingChantier.id, {
        adresseRue: values.adresseRue,
        adresseCodePostal: values.adresseCodePostal,
        adresseVille: values.adresseVille,
        adressePays: values.adressePays,
        surfaceM2: values.surfaceM2 !== "" ? parseFloat(values.surfaceM2) : null,
        clientId: values.clientId || null,
      });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["chantiers"] });
      setEditingChantier(null);
      toast.success("Chantier modifié");
    },
    onError: () => toast.error("Erreur lors de la modification"),
  });

  // Archivage
  const [archivingId, setArchivingId] = useState<string | null>(null);

  const archiveMutation = useMutation({
    mutationFn: (id: string) => archiverChantier(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["chantiers"] });
      setArchivingId(null);
      toast.success("Chantier archivé");
    },
    onError: () => toast.error("Erreur lors de l'archivage"),
  });

  return (
    <DashboardView
      chantiers={chantiers}
      clients={clients}
      isLoading={isLoading}
      isError={isError}
      showCreate={showCreate}
      onOpenCreate={() => { setShowCreate(true); createForm.reset(makeDefaultValues()); }}
      onCloseCreate={() => setShowCreate(false)}
      createForm={createForm}
      onSubmitCreate={(v) => createMutation.mutateAsync(v)}
      isCreating={createMutation.isPending}
      editingChantier={editingChantier}
      onOpenEdit={openEdit}
      onCloseEdit={() => setEditingChantier(null)}
      editForm={editForm}
      onSubmitEdit={(v) => editMutation.mutateAsync(v)}
      isEditing={editMutation.isPending}
      archivingId={archivingId}
      onRequestArchive={(id) => setArchivingId(id)}
      onConfirmArchive={async () => { if (archivingId) await archiveMutation.mutateAsync(archivingId); }}
      onCancelArchive={() => setArchivingId(null)}
      isArchiving={archiveMutation.isPending}
    />
  );
}
