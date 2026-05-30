import type { UseFormReturn } from "react-hook-form";
import { HardHat } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  AuthDivider,
  GoogleSignInButton,
} from "@/components/auth/GoogleSignInButton";

export type RegisterFormValues = {
  email: string;
  motDePasse: string;
  confirmation: string;
};

type Props = {
  form: UseFormReturn<RegisterFormValues>;
  onSubmit: (values: RegisterFormValues) => Promise<void>;
  isPending: boolean;
  errorMessage?: string | undefined;
  loginHref: string;
};

export function RegisterView({
  form,
  onSubmit,
  isPending,
  errorMessage,
  loginHref,
}: Props) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = form;

  return (
    <div
      className="flex min-h-screen items-center justify-center px-4"
      style={{
        background: "var(--color-background)",
        fontFamily: "var(--font-body)",
      }}
    >
      <div className="w-full max-w-sm">
        {/* En-tête */}
        <div className="mb-8 text-center">
          <div className="flex items-center justify-center gap-2 mb-4">
            <HardHat size={28} style={{ color: "var(--color-primary)" }} />
            <span
              style={{
                fontFamily: "var(--font-heading)",
                fontWeight: 700,
                fontSize: "1.5rem",
                letterSpacing: "-0.03em",
                color: "var(--color-text)",
              }}
            >
              Cordeau
            </span>
          </div>
          <h1
            style={{
              fontFamily: "var(--font-heading)",
              fontWeight: 600,
              fontSize: "1.25rem",
              color: "var(--color-text)",
            }}
          >
            Créer un compte
          </h1>
          <p className="mt-1 text-sm" style={{ color: "var(--color-muted)" }}>
            Gérez vos chantiers depuis votre espace artisan
          </p>
        </div>

        {/* Formulaire */}
        <form
          onSubmit={(e) => void handleSubmit(onSubmit)(e)}
          className="rounded-lg border p-6 shadow-sm"
          style={{
            background: "var(--color-surface)",
            borderColor: "var(--color-border)",
          }}
        >
          {errorMessage && (
            <div
              className="mb-4 rounded px-3 py-2 text-sm"
              style={{
                background: "oklch(0.96 0.03 27)",
                color: "var(--color-destructive)",
                border: "1px solid oklch(0.88 0.06 27)",
              }}
            >
              {errorMessage}
            </div>
          )}

          <GoogleSignInButton label="Créer mon compte avec Google" />
          <AuthDivider />

          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label
                htmlFor="email"
                style={{
                  color: "var(--color-text)",
                  fontSize: "0.8rem",
                  fontWeight: 500,
                }}
              >
                Adresse email
              </Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                placeholder="artisan@exemple.fr"
                {...register("email")}
                aria-invalid={errors.email !== undefined}
              />
              {errors.email && (
                <p
                  className="text-xs"
                  style={{ color: "var(--color-destructive)" }}
                >
                  {errors.email.message}
                </p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label
                htmlFor="motDePasse"
                style={{
                  color: "var(--color-text)",
                  fontSize: "0.8rem",
                  fontWeight: 500,
                }}
              >
                Mot de passe
              </Label>
              <Input
                id="motDePasse"
                type="password"
                autoComplete="new-password"
                placeholder="8 car. min., 1 majusc., 1 chiffre"
                {...register("motDePasse")}
                aria-invalid={errors.motDePasse !== undefined}
              />
              {errors.motDePasse && (
                <p
                  className="text-xs"
                  style={{ color: "var(--color-destructive)" }}
                >
                  {errors.motDePasse.message}
                </p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label
                htmlFor="confirmation"
                style={{
                  color: "var(--color-text)",
                  fontSize: "0.8rem",
                  fontWeight: 500,
                }}
              >
                Confirmer le mot de passe
              </Label>
              <Input
                id="confirmation"
                type="password"
                autoComplete="new-password"
                placeholder="••••••••"
                {...register("confirmation")}
                aria-invalid={errors.confirmation !== undefined}
              />
              {errors.confirmation && (
                <p
                  className="text-xs"
                  style={{ color: "var(--color-destructive)" }}
                >
                  {errors.confirmation.message}
                </p>
              )}
            </div>
          </div>

          <Button
            type="submit"
            disabled={isPending}
            className="mt-6 w-full"
            style={{
              background: "var(--color-primary)",
              color: "var(--color-primary-foreground)",
            }}
          >
            {isPending ? "Création…" : "Créer mon compte"}
          </Button>
        </form>

        <p
          className="mt-4 text-center text-sm"
          style={{ color: "var(--color-muted)" }}
        >
          Déjà un compte ?{" "}
          <a
            href={loginHref}
            className="font-medium hover:underline"
            style={{ color: "var(--color-primary)" }}
          >
            Se connecter
          </a>
        </p>
      </div>
    </div>
  );
}
