import { describe, it, expect } from "vitest";
import { render } from "vitest-browser-react";
import { useForm } from "react-hook-form";
import { LoginView } from "../pages/login/LoginView";
import type { LoginFormValues } from "../pages/login/LoginView";

function LoginViewWrapper({
  errorMessage,
}: {
  errorMessage?: string | undefined;
}) {
  const form = useForm<LoginFormValues>({
    defaultValues: { email: "", motDePasse: "" },
  });
  return (
    <LoginView
      form={form}
      onSubmit={async () => {}}
      isPending={false}
      errorMessage={errorMessage}
      registerHref="/register"
    />
  );
}

describe("LoginView", () => {
  it("affiche le titre de connexion", async () => {
    const screen = await render(<LoginViewWrapper />);
    await expect
      .element(screen.getByRole("heading", { name: /connexion/i }))
      .toBeVisible();
  });

  it("affiche les champs email et mot de passe", async () => {
    const screen = await render(<LoginViewWrapper />);
    await expect.element(screen.getByLabelText(/adresse email/i)).toBeVisible();
    await expect.element(screen.getByLabelText(/mot de passe/i)).toBeVisible();
  });

  it("affiche le message d'erreur si fourni", async () => {
    const screen = await render(
      <LoginViewWrapper errorMessage="Email ou mot de passe incorrect." />,
    );
    await expect
      .element(screen.getByText(/email ou mot de passe incorrect/i))
      .toBeVisible();
  });

  it("affiche le lien vers l'inscription", async () => {
    const screen = await render(<LoginViewWrapper />);
    await expect
      .element(screen.getByRole("link", { name: /créer un compte/i }))
      .toBeVisible();
  });
});
