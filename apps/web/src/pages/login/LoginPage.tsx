import { useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { login } from "@/lib/api";
import type { ApiError } from "@/lib/api";
import { LoginView } from "./LoginView";
import type { LoginFormValues } from "./LoginView";

const schema = z.object({
  email: z.string().min(1, "Email requis").email("Email invalide"),
  motDePasse: z.string().min(1, "Mot de passe requis"),
});

export default function LoginPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [isPending, setIsPending] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | undefined>();

  const form = useForm<LoginFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", motDePasse: "" },
  });

  async function onSubmit(values: LoginFormValues) {
    setIsPending(true);
    setErrorMessage(undefined);
    try {
      const user = await login(values.email, values.motDePasse);
      queryClient.setQueryData(["auth", "me"], user);
      void navigate({ to: "/dashboard" });
    } catch (err) {
      const apiErr = err as ApiError;
      if (apiErr.status === 401) {
        setErrorMessage("Email ou mot de passe incorrect.");
      } else {
        setErrorMessage("Une erreur est survenue. Veuillez réessayer.");
      }
    } finally {
      setIsPending(false);
    }
  }

  return (
    <LoginView
      form={form}
      onSubmit={onSubmit}
      isPending={isPending}
      errorMessage={errorMessage}
      registerHref="/register"
    />
  );
}
