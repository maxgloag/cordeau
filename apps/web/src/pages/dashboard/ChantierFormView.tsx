import type { UseFormReturn } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { Client } from "@/lib/api";

export type ChantierFormValues = {
  adresseRue: string;
  adresseCodePostal: string;
  adresseVille: string;
  adressePays: string;
  surfaceM2: string;
  clientId: string;
};

type Props = {
  form: UseFormReturn<ChantierFormValues>;
  onSubmit: (values: ChantierFormValues) => Promise<unknown>;
  isPending: boolean;
  onCancel: () => void;
  submitLabel: string;
  clients?: Client[];
};

export function ChantierFormView({
  form,
  onSubmit,
  isPending,
  onCancel,
  submitLabel,
  clients = [],
}: Props) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = form;

  return (
    <form
      onSubmit={(e) => void handleSubmit(onSubmit)(e)}
      className="space-y-4"
    >
      <div className="space-y-1.5">
        <Label
          htmlFor="adresseRue"
          style={{
            fontSize: "0.8rem",
            fontWeight: 500,
            color: "var(--color-text)",
          }}
        >
          Adresse
        </Label>
        <Input
          id="adresseRue"
          placeholder="12 rue de la Paix"
          {...register("adresseRue")}
          aria-invalid={errors.adresseRue !== undefined}
        />
        {errors.adresseRue && (
          <p className="text-xs" style={{ color: "var(--color-destructive)" }}>
            {errors.adresseRue.message}
          </p>
        )}
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label
            htmlFor="adresseCodePostal"
            style={{
              fontSize: "0.8rem",
              fontWeight: 500,
              color: "var(--color-text)",
            }}
          >
            Code postal
          </Label>
          <Input
            id="adresseCodePostal"
            placeholder="75002"
            maxLength={5}
            {...register("adresseCodePostal")}
            aria-invalid={errors.adresseCodePostal !== undefined}
          />
          {errors.adresseCodePostal && (
            <p
              className="text-xs"
              style={{ color: "var(--color-destructive)" }}
            >
              {errors.adresseCodePostal.message}
            </p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label
            htmlFor="adresseVille"
            style={{
              fontSize: "0.8rem",
              fontWeight: 500,
              color: "var(--color-text)",
            }}
          >
            Ville
          </Label>
          <Input
            id="adresseVille"
            placeholder="Paris"
            {...register("adresseVille")}
            aria-invalid={errors.adresseVille !== undefined}
          />
          {errors.adresseVille && (
            <p
              className="text-xs"
              style={{ color: "var(--color-destructive)" }}
            >
              {errors.adresseVille.message}
            </p>
          )}
        </div>
      </div>

      <div className="space-y-1.5">
        <Label
          htmlFor="surfaceM2"
          style={{
            fontSize: "0.8rem",
            fontWeight: 500,
            color: "var(--color-text)",
          }}
        >
          Surface (m²){" "}
          <span style={{ color: "var(--color-muted)" }}>— optionnel</span>
        </Label>
        <Input
          id="surfaceM2"
          type="number"
          min="0"
          step="0.1"
          placeholder="ex. 85"
          {...register("surfaceM2")}
          aria-invalid={errors.surfaceM2 !== undefined}
        />
        {errors.surfaceM2 && (
          <p className="text-xs" style={{ color: "var(--color-destructive)" }}>
            {errors.surfaceM2.message}
          </p>
        )}
      </div>

      {clients.length > 0 && (
        <div className="space-y-1.5">
          <Label
            htmlFor="clientId"
            style={{
              fontSize: "0.8rem",
              fontWeight: 500,
              color: "var(--color-text)",
            }}
          >
            Client{" "}
            <span style={{ color: "var(--color-muted)" }}>— optionnel</span>
          </Label>
          <select
            id="clientId"
            className="flex h-9 w-full rounded-md border px-3 py-1 text-sm"
            style={{
              borderColor: "var(--color-border)",
              background: "var(--color-surface)",
              color: "var(--color-text)",
            }}
            {...register("clientId")}
          >
            <option value="">Aucun client</option>
            {clients.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nom}
              </option>
            ))}
          </select>
        </div>
      )}

      <div className="flex justify-end gap-2 pt-2">
        <Button
          type="button"
          variant="outline"
          onClick={onCancel}
          disabled={isPending}
        >
          Annuler
        </Button>
        <Button
          type="submit"
          disabled={isPending}
          style={{
            background: "var(--color-primary)",
            color: "var(--color-primary-foreground)",
          }}
        >
          {isPending ? "Enregistrement…" : submitLabel}
        </Button>
      </div>
    </form>
  );
}
