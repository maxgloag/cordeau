import { Plus, Pencil, Trash2, User, Phone, Mail } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { ClientFormView } from "./ClientFormView";
import type { ClientFormValues } from "./ClientFormView";
import type { UseFormReturn } from "react-hook-form";
import type { Client } from "@/lib/api";

type Props = {
  clients: Client[];
  isLoading: boolean;
  isError: boolean;

  showCreate: boolean;
  onOpenCreate: () => void;
  onCloseCreate: () => void;
  createForm: UseFormReturn<ClientFormValues, unknown, ClientFormValues>;
  onSubmitCreate: (values: ClientFormValues) => Promise<unknown>;
  isCreating: boolean;

  editingClient: Client | null;
  onOpenEdit: (c: Client) => void;
  onCloseEdit: () => void;
  editForm: UseFormReturn<ClientFormValues, unknown, ClientFormValues>;
  onSubmitEdit: (values: ClientFormValues) => Promise<unknown>;
  isEditing: boolean;

  deletingId: string | null;
  onRequestDelete: (id: string) => void;
  onConfirmDelete: () => Promise<void>;
  onCancelDelete: () => void;
  isDeleting: boolean;
};

export function ClientsListView({
  clients,
  isLoading,
  isError,
  showCreate,
  onOpenCreate,
  onCloseCreate,
  createForm,
  onSubmitCreate,
  isCreating,
  editingClient,
  onOpenEdit,
  onCloseEdit,
  editForm,
  onSubmitEdit,
  isEditing,
  deletingId,
  onRequestDelete,
  onConfirmDelete,
  onCancelDelete,
  isDeleting,
}: Props) {
  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1
            style={{
              fontFamily: "var(--font-heading)",
              fontWeight: 700,
              fontSize: "1.5rem",
              letterSpacing: "-0.02em",
              color: "var(--color-text)",
            }}
          >
            Clients
          </h1>
          <p className="text-sm mt-0.5" style={{ color: "var(--color-muted)" }}>
            {clients.length} client{clients.length !== 1 ? "s" : ""}
          </p>
        </div>
        <Button
          onClick={onOpenCreate}
          style={{ background: "var(--color-primary)", color: "var(--color-primary-foreground)" }}
        >
          <Plus size={16} className="mr-1.5" />
          Nouveau client
        </Button>
      </div>

      {isLoading && (
        <div className="py-20 text-center text-sm" style={{ color: "var(--color-muted)" }}>
          Chargement…
        </div>
      )}

      {isError && !isLoading && (
        <div
          className="rounded-lg px-4 py-3 text-sm"
          style={{ background: "oklch(0.96 0.03 27)", color: "var(--color-destructive)", border: "1px solid oklch(0.88 0.06 27)" }}
        >
          Impossible de charger les clients. Vérifiez votre connexion.
        </div>
      )}

      {!isLoading && !isError && clients.length === 0 && (
        <div
          className="rounded-lg border py-16 text-center"
          style={{ borderColor: "var(--color-border)", borderStyle: "dashed" }}
        >
          <p className="text-sm font-medium" style={{ color: "var(--color-text)" }}>
            Aucun client pour l'instant
          </p>
          <p className="text-sm mt-1" style={{ color: "var(--color-muted)" }}>
            Ajoutez votre premier client pour le lier à vos chantiers.
          </p>
          <Button
            onClick={onOpenCreate}
            className="mt-4"
            size="sm"
            style={{ background: "var(--color-primary)", color: "var(--color-primary-foreground)" }}
          >
            <Plus size={14} className="mr-1.5" />
            Nouveau client
          </Button>
        </div>
      )}

      {!isLoading && !isError && clients.length > 0 && (
        <div
          className="rounded-lg border overflow-hidden"
          style={{ background: "var(--color-surface)", borderColor: "var(--color-border)" }}
        >
          <table className="w-full text-sm">
            <thead>
              <tr style={{ borderBottom: `1px solid var(--color-border)`, background: "var(--color-background)" }}>
                <th className="px-4 py-2.5 text-left font-medium" style={{ color: "var(--color-muted)", fontSize: "0.75rem", textTransform: "uppercase", letterSpacing: "0.05em" }}>
                  Client
                </th>
                <th className="px-4 py-2.5 text-left font-medium" style={{ color: "var(--color-muted)", fontSize: "0.75rem", textTransform: "uppercase", letterSpacing: "0.05em" }}>
                  Contact
                </th>
                <th className="px-4 py-2.5 text-left font-medium" style={{ color: "var(--color-muted)", fontSize: "0.75rem", textTransform: "uppercase", letterSpacing: "0.05em" }}>
                  Adresse
                </th>
                <th className="px-4 py-2.5 text-right font-medium" style={{ color: "var(--color-muted)", fontSize: "0.75rem", textTransform: "uppercase", letterSpacing: "0.05em" }}>
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {clients.map((c, i) => (
                <tr
                  key={c.id}
                  style={{ borderBottom: i < clients.length - 1 ? `1px solid var(--color-border)` : undefined }}
                >
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <User size={13} className="shrink-0" style={{ color: "var(--color-primary)" }} />
                      <span className="font-medium" style={{ color: "var(--color-text)" }}>{c.nom}</span>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex flex-col gap-0.5">
                      {c.email && (
                        <span className="flex items-center gap-1 text-xs" style={{ color: "var(--color-muted)" }}>
                          <Mail size={10} />
                          {c.email}
                        </span>
                      )}
                      {c.telephone && (
                        <span className="flex items-center gap-1 text-xs" style={{ color: "var(--color-muted)" }}>
                          <Phone size={10} />
                          {c.telephone}
                        </span>
                      )}
                      {!c.email && !c.telephone && (
                        <span style={{ color: "var(--color-muted)", fontSize: "0.75rem" }}>—</span>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <p className="text-xs" style={{ color: "var(--color-text)" }}>{c.adresseRue}</p>
                    <p className="text-xs" style={{ color: "var(--color-muted)" }}>
                      {c.adresseCodePostal} {c.adresseVille}
                    </p>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-1">
                      <button
                        onClick={() => onOpenEdit(c)}
                        className="rounded p-1.5 transition-colors hover:bg-slate-100"
                        title="Modifier"
                        aria-label="Modifier le client"
                      >
                        <Pencil size={13} style={{ color: "var(--color-muted)" }} />
                      </button>
                      <button
                        onClick={() => onRequestDelete(c.id)}
                        className="rounded p-1.5 transition-colors hover:bg-red-50"
                        title="Supprimer"
                        aria-label="Supprimer le client"
                      >
                        <Trash2 size={13} style={{ color: "var(--color-destructive)" }} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialog open={showCreate} onOpenChange={(open) => { if (!open) onCloseCreate(); }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle style={{ fontFamily: "var(--font-heading)", fontWeight: 600 }}>
              Nouveau client
            </DialogTitle>
          </DialogHeader>
          <ClientFormView
            form={createForm}
            onSubmit={onSubmitCreate}
            isPending={isCreating}
            onCancel={onCloseCreate}
            submitLabel="Créer le client"
          />
        </DialogContent>
      </Dialog>

      <Dialog open={editingClient !== null} onOpenChange={(open) => { if (!open) onCloseEdit(); }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle style={{ fontFamily: "var(--font-heading)", fontWeight: 600 }}>
              Modifier le client
            </DialogTitle>
          </DialogHeader>
          <ClientFormView
            form={editForm}
            onSubmit={onSubmitEdit}
            isPending={isEditing}
            onCancel={onCloseEdit}
            submitLabel="Enregistrer"
          />
        </DialogContent>
      </Dialog>

      <AlertDialog open={deletingId !== null} onOpenChange={(open) => { if (!open) onCancelDelete(); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Supprimer ce client ?</AlertDialogTitle>
            <AlertDialogDescription>
              Cette action est irréversible. Le client sera définitivement supprimé et dé-lié de tous ses chantiers.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={onCancelDelete}>Annuler</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => void onConfirmDelete()}
              disabled={isDeleting}
              style={{ background: "var(--color-destructive)", color: "white" }}
            >
              {isDeleting ? "Suppression…" : "Supprimer"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
