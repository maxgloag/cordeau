import { describe, it, expect, vi } from "vitest";
import { render } from "vitest-browser-react";
import { TacheListView } from "./TacheListView";
import type { Tache } from "@/lib/api";

const makeTache = (overrides: Partial<Tache> = {}): Tache => ({
  id: "t1",
  lotId: "lot-1",
  libelle: "Poser la faïence",
  faite: false,
  faiteLe: null,
  ordre: 0,
  ...overrides,
});

function renderView(taches: Tache[], handlers = {}) {
  return render(
    <TacheListView
      lotNom="Carrelage"
      taches={taches}
      isLoading={false}
      onToggle={vi.fn()}
      onRename={vi.fn()}
      onDelete={vi.fn()}
      onAdd={vi.fn()}
      {...handlers}
    />,
  );
}

describe("TacheListView", () => {
  it("affiche la progression des tâches", async () => {
    const screen = await renderView([
      makeTache({ id: "a", faite: true }),
      makeTache({ id: "b", libelle: "Joints" }),
      makeTache({ id: "c", libelle: "Nettoyage" }),
    ]);
    await expect.element(screen.getByText("Faites : 1 / 3")).toBeVisible();
  });

  it("coche une tâche", async () => {
    const onToggle = vi.fn();
    const tache = makeTache();
    const screen = await renderView([tache], { onToggle });
    await screen.getByRole("checkbox", { name: /poser la faïence/i }).click();
    expect(onToggle).toHaveBeenCalledWith(tache);
  });

  it("ajoute une tâche avec un libellé nettoyé", async () => {
    const onAdd = vi.fn();
    const screen = await renderView([], { onAdd });
    await screen
      .getByLabelText(/nouvelle tâche du lot carrelage/i)
      .fill("  Joints ");
    await screen.getByRole("button", { name: /ajouter/i }).click();
    expect(onAdd).toHaveBeenCalledWith("Joints");
  });

  it("refuse l'ajout d'un libellé vide", async () => {
    const screen = await renderView([]);
    await expect
      .element(screen.getByRole("button", { name: /ajouter/i }))
      .toBeDisabled();
  });

  it("renomme une tâche", async () => {
    const onRename = vi.fn();
    const tache = makeTache();
    const screen = await renderView([tache], { onRename });
    await screen.getByRole("button", { name: /renommer/i }).click();
    await screen
      .getByLabelText("Libellé de la tâche")
      .fill("Poser le carrelage");
    await screen
      .getByRole("button", { name: /enregistrer le libellé/i })
      .click();
    expect(onRename).toHaveBeenCalledWith(tache, "Poser le carrelage");
  });

  it("supprime une tâche", async () => {
    const onDelete = vi.fn();
    const tache = makeTache();
    const screen = await renderView([tache], { onDelete });
    await screen.getByRole("button", { name: /supprimer/i }).click();
    expect(onDelete).toHaveBeenCalledWith(tache);
  });
});
