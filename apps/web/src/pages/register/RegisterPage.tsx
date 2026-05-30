import { useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { register } from "@/lib/api";
import type { ApiError } from "@/lib/api";
import { RegisterView } from "./RegisterView";
import type { RegisterFormValues } from "./RegisterView";

const schema = z
  .object({
    email: z.string().min(1, "Email requis").email("Email invalide"),
    motDePasse: z
      .string()
      .min(8, "8 caractères minimum")
      .regex(/[A-Z]/, "Au moins une majuscule")
      .regex(/[0-9]/, "Au moins un chiffre"),
    confirmation: z.string().min(1, "Confirmation requise"),
  })
  .refine((data) => data.motDePasse === data.confirmation, {
    message: "Les mots de passe ne correspondent pas",
    path: ["confirmation"],
  });

export default function RegisterPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [isPending, setIsPending] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | undefined>();

  const form = useForm<RegisterFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", motDePasse: "", confirmation: "" },
  });

  async function onSubmit(values: RegisterFormValues) {
    setIsPending(true);
    setErrorMessage(undefined);
    try {
      const user = await register(values.email, values.motDePasse);
      queryClient.setQueryData(["auth", "me"], user);
      void navigate({ to: "/dashboard" });
    } catch (err) {
      const apiErr = err as ApiError;
      if (apiErr.status === 409) {
        setErrorMessage("Cette adresse email est déjà utilisée.");
      } else if (apiErr.status === 422) {
        setErrorMessage(
          "Mot de passe invalide (8 car. min., 1 maj., 1 chiffre).",
        );
      } else {
        setErrorMessage("Une erreur est survenue. Veuillez réessayer.");
      }
    } finally {
      setIsPending(false);
    }
  }

  return (
    <RegisterView
      form={form}
      onSubmit={onSubmit}
      isPending={isPending}
      errorMessage={errorMessage}
      loginHref="/login"
    />
  );
}
