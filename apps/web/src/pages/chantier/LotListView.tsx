import { useState } from "react";
import type { ReactNode } from "react";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
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
import type { Lot } from "@/lib/api";
import { LotFormView } from "./LotFormView";
import { MODES } from "./modes";
import type { LotFormValues } from "./LotFormView";

type Props = {
  lots: Lot[];
  isLoading: boolean;
  isError: boolean;
  renderTaches: (lot: Lot) => ReactNode;
  onCreate: (values: LotFormValues) => void;
  onUpdate: (lot: Lot, values: LotFormValues) => void;
  onDelete: (lot: Lot) => void;
};

function formatNombre(valeur: number): string {
  return valeur.toLocaleString("fr-FR", { maximumFractionDigits: 2 });
}

function formatValeur(valeur: number | null, unite: string): string {
  return valeur === null ? "—" : `${formatNombre(valeur)} ${unite}`;
}

export function LotListView({
  lots,
  isLoading,
  isError,
  renderTaches,
  onCreate,
  onUpdate,
  onDelete,
}: Props) {
  const [creating, setCreating] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [deletingLot, setDeletingLot] = useState<Lot | null>(null);

  return (
    <section aria-label="Lots" className="space-y-4">
      <div className="flex items-center justify-between">
        <h2
          className="text-lg font-semibold"
          style={{ fontFamily: "var(--font-heading)" }}
        >
          Lots
        </h2>
        {!creating && (
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus size={14} className="mr-1" />
            Nouveau lot
          </Button>
        )}
      </div>

      {creating && (
        <LotFormView
          submitLabel="Créer le lot"
          onCancel={() => setCreating(false)}
          onSubmit={(values) => {
            onCreate(values);
            setCreating(false);
          }}
        />
      )}

      {isLoading && (
        <p className="text-sm" style={{ color: "var(--color-muted)" }}>
          Chargement…
        </p>
      )}
      {isError && !isLoading && (
        <p className="text-sm" style={{ color: "var(--color-destructive)" }}>
          Impossible de charger les lots.
        </p>
      )}
      {!isLoading && !isError && lots.length === 0 && !creating && (
        <p className="text-sm" style={{ color: "var(--color-muted)" }}>
          Aucun lot : ce chantier reste valide sans lot. Ajoutez-en un pour
          suivre les tâches et les estimations.
        </p>
      )}

      <ul className="space-y-4">
        {lots.map((lot) => (
          <li
            key={lot.id}
            className="rounded-lg border p-4 space-y-3"
            style={{ borderColor: "var(--color-border)" }}
          >
            {editingId === lot.id ? (
              <LotFormView
                initial={{
                  nom: lot.nom,
                  mode: lot.mode,
                  estimation: lot.estimation,
                }}
                submitLabel="Enregistrer"
                onCancel={() => setEditingId(null)}
                onSubmit={(values) => {
                  onUpdate(lot, values);
                  setEditingId(null);
                }}
              />
            ) : (
              <div className="flex items-start justify-between gap-2">
                <div>
                  <h3 className="font-medium">{lot.nom}</h3>
                  <p
                    className="text-xs"
                    style={{ color: "var(--color-muted)" }}
                  >
                    {MODES.find((m) => m.value === lot.mode)?.label ?? lot.mode}
                    {" · Estimation : "}
                    {formatValeur(lot.estimation, lot.unite)}
                    {" · Réel : "}
                    {formatValeur(lot.reel, lot.unite)}
                  </p>
                </div>
                <div className="flex shrink-0">
                  <Button
                    size="icon"
                    variant="ghost"
                    aria-label={`Modifier le lot ${lot.nom}`}
                    onClick={() => setEditingId(lot.id)}
                  >
                    <Pencil size={14} />
                  </Button>
                  <Button
                    size="icon"
                    variant="ghost"
                    aria-label={`Supprimer le lot ${lot.nom}`}
                    onClick={() => setDeletingLot(lot)}
                  >
                    <Trash2 size={14} />
                  </Button>
                </div>
              </div>
            )}
            {renderTaches(lot)}
          </li>
        ))}
      </ul>

      <AlertDialog
        open={deletingLot !== null}
        onOpenChange={(open) => {
          if (!open) setDeletingLot(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Supprimer ce lot ?</AlertDialogTitle>
            <AlertDialogDescription>
              Le lot « {deletingLot?.nom} » et toutes ses tâches seront
              supprimés. Cette action est définitive.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Annuler</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (deletingLot) onDelete(deletingLot);
                setDeletingLot(null);
              }}
            >
              Supprimer
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </section>
  );
}
