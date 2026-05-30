import type { UseFormReturn } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export type ClientFormValues = {
  nom: string;
  email: string;
  telephone: string;
  adresseRue: string;
  adresseCodePostal: string;
  adresseVille: string;
  adressePays: string;
  notes: string;
};

type Props = {
  form: UseFormReturn<ClientFormValues>;
  onSubmit: (values: ClientFormValues) => Promise<unknown>;
  isPending: boolean;
  onCancel: () => void;
  submitLabel: string;
};

export function ClientFormView({
  form,
  onSubmit,
  isPending,
  onCancel,
  submitLabel,
}: Props) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = form;

  const labelStyle = {
    fontSize: "0.8rem",
    fontWeight: 500,
    color: "var(--color-text)",
  } as const;
  const errorStyle = { color: "var(--color-destructive)" } as const;

  return (
    <form
      onSubmit={(e) => void handleSubmit(onSubmit)(e)}
      className="space-y-4"
    >
      <div className="space-y-1.5">
        <Label htmlFor="nom" style={labelStyle}>
          Nom
        </Label>
        <Input
          id="nom"
          placeholder="ACME SARL"
          {...register("nom")}
          aria-invalid={errors.nom !== undefined}
        />
        {errors.nom && (
          <p className="text-xs" style={errorStyle}>
            {errors.nom.message}
          </p>
        )}
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="email" style={labelStyle}>
            Email{" "}
            <span style={{ color: "var(--color-muted)" }}>— optionnel</span>
          </Label>
          <Input
            id="email"
            type="email"
            placeholder="contact@client.fr"
            {...register("email")}
            aria-invalid={errors.email !== undefined}
          />
          {errors.email && (
            <p className="text-xs" style={errorStyle}>
              {errors.email.message}
            </p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="telephone" style={labelStyle}>
            Téléphone{" "}
            <span style={{ color: "var(--color-muted)" }}>— optionnel</span>
          </Label>
          <Input
            id="telephone"
            type="tel"
            placeholder="06 12 34 56 78"
            {...register("telephone")}
            aria-invalid={errors.telephone !== undefined}
          />
          {errors.telephone && (
            <p className="text-xs" style={errorStyle}>
              {errors.telephone.message}
            </p>
          )}
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="adresseRue" style={labelStyle}>
          Adresse
        </Label>
        <Input
          id="adresseRue"
          placeholder="12 rue de la Paix"
          {...register("adresseRue")}
          aria-invalid={errors.adresseRue !== undefined}
        />
        {errors.adresseRue && (
          <p className="text-xs" style={errorStyle}>
            {errors.adresseRue.message}
          </p>
        )}
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="adresseCodePostal" style={labelStyle}>
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
            <p className="text-xs" style={errorStyle}>
              {errors.adresseCodePostal.message}
            </p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="adresseVille" style={labelStyle}>
            Ville
          </Label>
          <Input
            id="adresseVille"
            placeholder="Paris"
            {...register("adresseVille")}
            aria-invalid={errors.adresseVille !== undefined}
          />
          {errors.adresseVille && (
            <p className="text-xs" style={errorStyle}>
              {errors.adresseVille.message}
            </p>
          )}
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="notes" style={labelStyle}>
          Notes <span style={{ color: "var(--color-muted)" }}>— optionnel</span>
        </Label>
        <textarea
          id="notes"
          className="flex min-h-[72px] w-full rounded-md border px-3 py-2 text-sm outline-none resize-none"
          style={{
            borderColor: "var(--color-border)",
            background: "var(--color-surface)",
            color: "var(--color-text)",
          }}
          placeholder="Informations complémentaires…"
          {...register("notes")}
        />
      </div>

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
