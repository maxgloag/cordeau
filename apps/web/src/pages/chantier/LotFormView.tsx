import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { estimationLotValide, nomLotValide } from "@/lib/regles-metier";
import type { ModeFacturation } from "@/lib/api";
import { MODES, uniteDuMode } from "./modes";

export type LotFormValues = {
  nom: string;
  mode: ModeFacturation;
  estimation: number | null;
};

type Props = {
  initial?: LotFormValues;
  submitLabel: string;
  onSubmit: (values: LotFormValues) => void;
  onCancel: () => void;
};

export function LotFormView({
  initial,
  submitLabel,
  onSubmit,
  onCancel,
}: Props) {
  const [nom, setNom] = useState(initial?.nom ?? "");
  const [mode, setMode] = useState<ModeFacturation>(initial?.mode ?? "temps");
  const [estimation, setEstimation] = useState(
    initial?.estimation != null ? String(initial.estimation) : "",
  );

  const estimationNum = estimation.trim() === "" ? null : Number(estimation);
  const estimationOk =
    estimationNum === null ||
    (Number.isFinite(estimationNum) && estimationLotValide(estimationNum));
  const valide = nomLotValide(nom) && estimationOk;
  // Changer le mode efface l'estimation et le réel côté API (ADR 0024).
  const modeChange = initial !== undefined && initial.mode !== mode;

  return (
    <form
      className="space-y-3 rounded-lg border p-4"
      style={{ borderColor: "var(--color-border)" }}
      onSubmit={(e) => {
        e.preventDefault();
        if (!valide) return;
        onSubmit({
          nom: nom.trim(),
          mode,
          estimation: modeChange ? null : estimationNum,
        });
      }}
    >
      <div className="space-y-1.5">
        <Label htmlFor="lot-nom">Nom du lot</Label>
        <Input
          id="lot-nom"
          autoFocus
          value={nom}
          onChange={(e) => setNom(e.target.value)}
          placeholder="Carrelage salle de bain"
        />
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="lot-mode">Mode de facturation</Label>
          <select
            id="lot-mode"
            value={mode}
            onChange={(e) => setMode(e.target.value as ModeFacturation)}
            className="h-9 w-full rounded-md border bg-transparent px-2 text-sm"
            style={{ borderColor: "var(--color-border)" }}
          >
            {MODES.map((m) => (
              <option key={m.value} value={m.value}>
                {m.label} ({m.unite})
              </option>
            ))}
          </select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="lot-estimation">
            Estimation ({uniteDuMode(mode)})
          </Label>
          <Input
            id="lot-estimation"
            type="number"
            inputMode="decimal"
            min={0}
            step="any"
            value={modeChange ? "" : estimation}
            disabled={modeChange}
            onChange={(e) => setEstimation(e.target.value)}
            aria-invalid={!estimationOk}
          />
        </div>
      </div>

      {modeChange && (
        <p className="text-xs" style={{ color: "var(--color-destructive)" }}>
          Changer le mode efface l'estimation et le réel déjà saisis.
        </p>
      )}
      {!estimationOk && (
        <p className="text-xs" style={{ color: "var(--color-destructive)" }}>
          Estimation invalide (entre 0 et 9 999 999 999).
        </p>
      )}

      <div className="flex justify-end gap-2">
        <Button type="button" variant="ghost" size="sm" onClick={onCancel}>
          Annuler
        </Button>
        <Button type="submit" size="sm" disabled={!valide}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
