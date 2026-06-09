import { Plus, Pencil, Archive, MapPin, Maximize2, User } from "lucide-react";
import { Link } from "@tanstack/react-router";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
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
import { ChantierFormView } from "./ChantierFormView";
import type { ChantierFormValues } from "./ChantierFormView";
import type { UseFormReturn } from "react-hook-form";
import type { Chantier, Client } from "@/lib/api";

const STATUT_LABELS: Record<string, string> = {
  en_preparation: "En préparation",
  en_cours: "En cours",
  termine: "Terminé",
  archive: "Archivé",
};

type Props = {
  chantiers: Chantier[];
  clients: Client[];
  isLoading: boolean;
  isError: boolean;

  // Création
  showCreate: boolean;
  onOpenCreate: () => void;
  onCloseCreate: () => void;
  createForm: UseFormReturn<ChantierFormValues, unknown, ChantierFormValues>;
  onSubmitCreate: (values: ChantierFormValues) => Promise<unknown>;
  isCreating: boolean;

  // Modification
  editingChantier: Chantier | null;
  onOpenEdit: (c: Chantier) => void;
  onCloseEdit: () => void;
  editForm: UseFormReturn<ChantierFormValues, unknown, ChantierFormValues>;
  onSubmitEdit: (values: ChantierFormValues) => Promise<unknown>;
  isEditing: boolean;

  // Archivage
  archivingId: string | null;
  onRequestArchive: (id: string) => void;
  onConfirmArchive: () => Promise<void>;
  onCancelArchive: () => void;
  isArchiving: boolean;
};

export function DashboardView({
  chantiers,
  clients,
  isLoading,
  isError,
  showCreate,
  onOpenCreate,
  onCloseCreate,
  createForm,
  onSubmitCreate,
  isCreating,
  editingChantier,
  onOpenEdit,
  onCloseEdit,
  editForm,
  onSubmitEdit,
  isEditing,
  archivingId,
  onRequestArchive,
  onConfirmArchive,
  onCancelArchive,
  isArchiving,
}: Props) {
  return (
    <div>
      {/* En-tête de page */}
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
            Chantiers
          </h1>
          <p className="text-sm mt-0.5" style={{ color: "var(--color-muted)" }}>
            {chantiers.length} chantier{chantiers.length !== 1 ? "s" : ""} actif
            {chantiers.length !== 1 ? "s" : ""}
          </p>
        </div>
        <Button
          onClick={onOpenCreate}
          style={{
            background: "var(--color-primary)",
            color: "var(--color-primary-foreground)",
          }}
        >
          <Plus size={16} className="mr-1.5" />
          Nouveau chantier
        </Button>
      </div>

      {/* États */}
      {isLoading && (
        <div
          className="py-20 text-center text-sm"
          style={{ color: "var(--color-muted)" }}
        >
          Chargement…
        </div>
      )}

      {isError && !isLoading && (
        <div
          className="rounded-lg px-4 py-3 text-sm"
          style={{
            background: "oklch(0.96 0.03 27)",
            color: "var(--color-destructive)",
            border: "1px solid oklch(0.88 0.06 27)",
          }}
        >
          Impossible de charger les chantiers. Vérifiez votre connexion.
        </div>
      )}

      {/* Table */}
      {!isLoading && !isError && chantiers.length === 0 && (
        <div
          className="rounded-lg border py-16 text-center"
          style={{ borderColor: "var(--color-border)", borderStyle: "dashed" }}
        >
          <p
            className="text-sm font-medium"
            style={{ color: "var(--color-text)" }}
          >
            Aucun chantier pour l'instant
          </p>
          <p className="text-sm mt-1" style={{ color: "var(--color-muted)" }}>
            Créez votre premier chantier pour commencer.
          </p>
          <Button
            onClick={onOpenCreate}
            className="mt-4"
            size="sm"
            style={{
              background: "var(--color-primary)",
              color: "var(--color-primary-foreground)",
            }}
          >
            <Plus size={14} className="mr-1.5" />
            Nouveau chantier
          </Button>
        </div>
      )}

      {!isLoading && !isError && chantiers.length > 0 && (
        <div
          className="rounded-lg border overflow-hidden"
          style={{
            background: "var(--color-surface)",
            borderColor: "var(--color-border)",
          }}
        >
          <table className="w-full text-sm">
            <thead>
              <tr
                style={{
                  borderBottom: `1px solid var(--color-border)`,
                  background: "var(--color-background)",
                }}
              >
                <th
                  className="px-4 py-2.5 text-left font-medium"
                  style={{
                    color: "var(--color-muted)",
                    fontSize: "0.75rem",
                    textTransform: "uppercase",
                    letterSpacing: "0.05em",
                  }}
                >
                  Adresse
                </th>
                <th
                  className="px-4 py-2.5 text-left font-medium"
                  style={{
                    color: "var(--color-muted)",
                    fontSize: "0.75rem",
                    textTransform: "uppercase",
                    letterSpacing: "0.05em",
                  }}
                >
                  Client
                </th>
                <th
                  className="px-4 py-2.5 text-left font-medium"
                  style={{
                    color: "var(--color-muted)",
                    fontSize: "0.75rem",
                    textTransform: "uppercase",
                    letterSpacing: "0.05em",
                  }}
                >
                  Surface
                </th>
                <th
                  className="px-4 py-2.5 text-left font-medium"
                  style={{
                    color: "var(--color-muted)",
                    fontSize: "0.75rem",
                    textTransform: "uppercase",
                    letterSpacing: "0.05em",
                  }}
                >
                  Statut
                </th>
                <th
                  className="px-4 py-2.5 text-right font-medium"
                  style={{
                    color: "var(--color-muted)",
                    fontSize: "0.75rem",
                    textTransform: "uppercase",
                    letterSpacing: "0.05em",
                  }}
                >
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {chantiers.map((c, i) => (
                <tr
                  key={c.id}
                  style={{
                    borderBottom:
                      i < chantiers.length - 1
                        ? `1px solid var(--color-border)`
                        : undefined,
                  }}
                >
                  <td className="px-4 py-3">
                    <div className="flex items-start gap-2">
                      <MapPin
                        size={13}
                        className="mt-0.5 shrink-0"
                        style={{ color: "var(--color-primary)" }}
                      />
                      <div>
                        <Link
                          to="/chantiers/$id"
                          params={{ id: c.id }}
                          className="font-medium hover:text-blue-600 hover:underline"
                          style={{ color: "var(--color-text)" }}
                        >
                          {c.adresseRue}
                        </Link>
                        <p
                          className="text-xs"
                          style={{ color: "var(--color-muted)" }}
                        >
                          {c.adresseCodePostal} {c.adresseVille}
                        </p>
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    {c.clientNom ? (
                      <span
                        className="flex items-center gap-1 text-xs"
                        style={{ color: "var(--color-text)" }}
                      >
                        <User
                          size={11}
                          style={{ color: "var(--color-primary)" }}
                        />
                        {c.clientNom}
                      </span>
                    ) : (
                      <span
                        style={{
                          color: "var(--color-muted)",
                          fontSize: "0.75rem",
                        }}
                      >
                        —
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    {c.surfaceM2 != null ? (
                      <span
                        className="flex items-center gap-1 text-xs"
                        style={{
                          color: "var(--color-text)",
                          fontFamily: "var(--font-mono)",
                        }}
                      >
                        <Maximize2
                          size={11}
                          style={{ color: "var(--color-muted)" }}
                        />
                        {c.surfaceM2} m²
                      </span>
                    ) : (
                      <span
                        style={{
                          color: "var(--color-muted)",
                          fontSize: "0.75rem",
                        }}
                      >
                        —
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium badge-${c.statut}`}
                    >
                      {STATUT_LABELS[c.statut] ?? c.statut}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-1">
                      <button
                        onClick={() => onOpenEdit(c)}
                        className="rounded p-1.5 transition-colors hover:bg-slate-100"
                        title="Modifier"
                        aria-label="Modifier le chantier"
                      >
                        <Pencil
                          size={13}
                          style={{ color: "var(--color-muted)" }}
                        />
                      </button>
                      <button
                        onClick={() => onRequestArchive(c.id)}
                        className="rounded p-1.5 transition-colors hover:bg-slate-100"
                        title="Archiver"
                        aria-label="Archiver le chantier"
                      >
                        <Archive
                          size={13}
                          style={{ color: "var(--color-muted)" }}
                        />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Dialog Création */}
      <Dialog
        open={showCreate}
        onOpenChange={(open) => {
          if (!open) onCloseCreate();
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle
              style={{ fontFamily: "var(--font-heading)", fontWeight: 600 }}
            >
              Nouveau chantier
            </DialogTitle>
          </DialogHeader>
          <ChantierFormView
            form={createForm}
            onSubmit={onSubmitCreate}
            isPending={isCreating}
            onCancel={onCloseCreate}
            submitLabel="Créer le chantier"
            clients={clients}
          />
        </DialogContent>
      </Dialog>

      {/* Dialog Modification */}
      <Dialog
        open={editingChantier !== null}
        onOpenChange={(open) => {
          if (!open) onCloseEdit();
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle
              style={{ fontFamily: "var(--font-heading)", fontWeight: 600 }}
            >
              Modifier le chantier
            </DialogTitle>
          </DialogHeader>
          <ChantierFormView
            form={editForm}
            onSubmit={onSubmitEdit}
            isPending={isEditing}
            onCancel={onCloseEdit}
            submitLabel="Enregistrer"
            clients={clients}
          />
        </DialogContent>
      </Dialog>

      {/* AlertDialog Archivage */}
      <AlertDialog
        open={archivingId !== null}
        onOpenChange={(open) => {
          if (!open) onCancelArchive();
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Archiver ce chantier ?</AlertDialogTitle>
            <AlertDialogDescription>
              Le chantier sera archivé et n'apparaîtra plus dans la liste. Cette
              action peut être inversée en contactant le support.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={onCancelArchive}>
              Annuler
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() => void onConfirmArchive()}
              disabled={isArchiving}
              style={{ background: "var(--color-destructive)", color: "white" }}
            >
              {isArchiving ? "Archivage…" : "Archiver"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
