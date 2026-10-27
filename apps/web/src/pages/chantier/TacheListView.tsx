import { useState } from "react";
import { Check, Pencil, Plus, Trash2, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { libelleTacheValide } from "@/lib/regles-metier";
import type { Tache } from "@/lib/api";

type Props = {
  lotNom: string;
  taches: Tache[];
  isLoading: boolean;
  onToggle: (tache: Tache) => void;
  onRename: (tache: Tache, libelle: string) => void;
  onDelete: (tache: Tache) => void;
  onAdd: (libelle: string) => void;
};

export function TacheListView({
  lotNom,
  taches,
  isLoading,
  onToggle,
  onRename,
  onDelete,
  onAdd,
}: Props) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editValue, setEditValue] = useState("");
  const [newLibelle, setNewLibelle] = useState("");

  const faites = taches.filter((t) => t.faite).length;

  function startEdit(tache: Tache) {
    setEditingId(tache.id);
    setEditValue(tache.libelle);
  }

  function submitEdit(tache: Tache) {
    if (!libelleTacheValide(editValue)) return;
    if (editValue.trim() !== tache.libelle) onRename(tache, editValue.trim());
    setEditingId(null);
  }

  function submitAdd() {
    if (!libelleTacheValide(newLibelle)) return;
    onAdd(newLibelle.trim());
    setNewLibelle("");
  }

  return (
    <section aria-label={`Tâches du lot ${lotNom}`} className="space-y-2">
      <p className="text-xs" style={{ color: "var(--color-muted)" }}>
        {isLoading
          ? "Chargement des tâches…"
          : `Faites : ${faites} / ${taches.length}`}
      </p>

      <ul className="space-y-1">
        {taches.map((tache) => (
          <li key={tache.id} className="flex items-center gap-2 min-h-8">
            <input
              type="checkbox"
              checked={tache.faite}
              onChange={() => onToggle(tache)}
              aria-label={`Marquer « ${tache.libelle} » comme faite`}
              className="size-4 shrink-0"
            />
            {editingId === tache.id ? (
              <form
                className="flex flex-1 items-center gap-1"
                onSubmit={(e) => {
                  e.preventDefault();
                  submitEdit(tache);
                }}
              >
                <Input
                  autoFocus
                  value={editValue}
                  onChange={(e) => setEditValue(e.target.value)}
                  aria-label="Libellé de la tâche"
                  aria-invalid={!libelleTacheValide(editValue)}
                />
                <Button
                  type="submit"
                  size="icon"
                  variant="ghost"
                  aria-label="Enregistrer le libellé"
                  disabled={!libelleTacheValide(editValue)}
                >
                  <Check size={14} />
                </Button>
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  aria-label="Annuler la modification"
                  onClick={() => setEditingId(null)}
                >
                  <X size={14} />
                </Button>
              </form>
            ) : (
              <>
                <span
                  className={`flex-1 text-sm ${tache.faite ? "line-through" : ""}`}
                  style={{
                    color: tache.faite
                      ? "var(--color-muted)"
                      : "var(--color-text)",
                  }}
                >
                  {tache.libelle}
                </span>
                <Button
                  size="icon"
                  variant="ghost"
                  aria-label={`Renommer « ${tache.libelle} »`}
                  onClick={() => startEdit(tache)}
                >
                  <Pencil size={14} />
                </Button>
                <Button
                  size="icon"
                  variant="ghost"
                  aria-label={`Supprimer « ${tache.libelle} »`}
                  onClick={() => onDelete(tache)}
                >
                  <Trash2 size={14} />
                </Button>
              </>
            )}
          </li>
        ))}
      </ul>

      <form
        className="flex items-center gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          submitAdd();
        }}
      >
        <Input
          value={newLibelle}
          onChange={(e) => setNewLibelle(e.target.value)}
          placeholder="Nouvelle tâche"
          aria-label={`Nouvelle tâche du lot ${lotNom}`}
        />
        <Button
          type="submit"
          size="sm"
          variant="outline"
          disabled={!libelleTacheValide(newLibelle)}
        >
          <Plus size={14} className="mr-1" />
          Ajouter
        </Button>
      </form>
    </section>
  );
}
