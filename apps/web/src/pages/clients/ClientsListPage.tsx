import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import {
  listClients,
  creerClient,
  modifierClient,
  supprimerClient,
} from "@/lib/api";
import type { Client } from "@/lib/api";
import { ClientsListView } from "./ClientsListView";
import type { ClientFormValues } from "./ClientFormView";

const clientSchema = z.object({
  nom: z.string().min(1, "Nom requis"),
  email: z.string().refine(
    (v) => v === "" || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
    { message: "Email invalide" },
  ),
  telephone: z.string(),
  adresseRue: z.string().min(1, "Adresse requise"),
  adresseCodePostal: z
    .string()
    .min(1, "Code postal requis")
    .regex(/^\d{5}$/, "Format invalide (5 chiffres)"),
  adresseVille: z.string().min(1, "Ville requise"),
  adressePays: z.string().min(1),
  notes: z.string(),
});

function makeDefaultValues(c?: Client): ClientFormValues {
  return {
    nom: c?.nom ?? "",
    email: c?.email ?? "",
    telephone: c?.telephone ?? "",
    adresseRue: c?.adresseRue ?? "",
    adresseCodePostal: c?.adresseCodePostal ?? "",
    adresseVille: c?.adresseVille ?? "",
    adressePays: c?.adressePays ?? "FR",
    notes: c?.notes ?? "",
  };
}

export default function ClientsListPage() {
  const queryClient = useQueryClient();

  const { data: clients = [], isLoading, isError } = useQuery({
    queryKey: ["clients"],
    queryFn: listClients,
  });

  const [showCreate, setShowCreate] = useState(false);
  const createForm = useForm<ClientFormValues>({
    resolver: zodResolver(clientSchema),
    defaultValues: makeDefaultValues(),
  });

  const createMutation = useMutation({
    mutationFn: (values: ClientFormValues) =>
      creerClient({
        nom: values.nom,
        email: values.email || null,
        telephone: values.telephone || null,
        adresseRue: values.adresseRue,
        adresseCodePostal: values.adresseCodePostal,
        adresseVille: values.adresseVille,
        adressePays: values.adressePays,
        notes: values.notes || null,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["clients"] });
      setShowCreate(false);
      createForm.reset(makeDefaultValues());
      toast.success("Client créé avec succès");
    },
    onError: () => toast.error("Erreur lors de la création"),
  });

  const [editingClient, setEditingClient] = useState<Client | null>(null);
  const editForm = useForm<ClientFormValues>({
    resolver: zodResolver(clientSchema),
    defaultValues: makeDefaultValues(),
  });

  function openEdit(c: Client) {
    setEditingClient(c);
    editForm.reset(makeDefaultValues(c));
  }

  const editMutation = useMutation({
    mutationFn: (values: ClientFormValues) => {
      if (!editingClient) throw new Error("Aucun client sélectionné");
      return modifierClient(editingClient.id, {
        nom: values.nom,
        email: values.email || null,
        telephone: values.telephone || null,
        adresseRue: values.adresseRue,
        adresseCodePostal: values.adresseCodePostal,
        adresseVille: values.adresseVille,
        adressePays: values.adressePays,
        notes: values.notes || null,
      });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["clients"] });
      setEditingClient(null);
      toast.success("Client modifié");
    },
    onError: () => toast.error("Erreur lors de la modification"),
  });

  const [deletingId, setDeletingId] = useState<string | null>(null);

  const deleteMutation = useMutation({
    mutationFn: (id: string) => supprimerClient(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["clients"] });
      setDeletingId(null);
      toast.success("Client supprimé");
    },
    onError: () => toast.error("Erreur lors de la suppression"),
  });

  return (
    <ClientsListView
      clients={clients}
      isLoading={isLoading}
      isError={isError}
      showCreate={showCreate}
      onOpenCreate={() => { setShowCreate(true); createForm.reset(makeDefaultValues()); }}
      onCloseCreate={() => setShowCreate(false)}
      createForm={createForm}
      onSubmitCreate={(v) => createMutation.mutateAsync(v)}
      isCreating={createMutation.isPending}
      editingClient={editingClient}
      onOpenEdit={openEdit}
      onCloseEdit={() => setEditingClient(null)}
      editForm={editForm}
      onSubmitEdit={(v) => editMutation.mutateAsync(v)}
      isEditing={editMutation.isPending}
      deletingId={deletingId}
      onRequestDelete={(id) => setDeletingId(id)}
      onConfirmDelete={async () => { if (deletingId) await deleteMutation.mutateAsync(deletingId); }}
      onCancelDelete={() => setDeletingId(null)}
      isDeleting={deleteMutation.isPending}
    />
  );
}
