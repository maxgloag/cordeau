import { describe, it, expect, vi } from "vitest";
import { render } from "vitest-browser-react";
import { LotListView } from "./LotListView";
import type { Lot } from "@/lib/api";

const makeLot = (overrides: Partial<Lot> = {}): Lot => ({
  id: "lot-1",
  chantierId: "chantier-1",
  nom: "Carrelage",
  mode: "surface",
  unite: "m²",
  estimation: 12.5,
  reel: null,
  ordre: 0,
  ...overrides,
});

function renderView(lots: Lot[], handlers = {}) {
  return render(
    <LotListView
      lots={lots}
      isLoading={false}
      isError={false}
      renderTaches={(lot) => <p>Tâches de {lot.nom}</p>}
      onCreate={vi.fn()}
      onUpdate={vi.fn()}
      onDelete={vi.fn()}
      {...handlers}
    />,
  );
}

describe("LotListView", () => {
  it("explique qu'un chantier sans lot reste valide", async () => {
    const screen = await renderView([]);
    await expect.element(screen.getByText(/aucun lot/i)).toBeVisible();
  });

  it("affiche l'estimation avec l'unité dérivée du mode", async () => {
    const screen = await renderView([makeLot()]);
    await expect
      .element(screen.getByText(/estimation : 12,5 m²/i))
      .toBeVisible();
    await expect.element(screen.getByText("Tâches de Carrelage")).toBeVisible();
  });

  it("crée un lot surface avec estimation", async () => {
    const onCreate = vi.fn();
    const screen = await renderView([], { onCreate });
    await screen.getByRole("button", { name: /nouveau lot/i }).click();
    await screen.getByLabelText("Nom du lot").fill("Peinture");
    await screen.getByLabelText("Mode de facturation").selectOptions("surface");
    await screen.getByLabelText(/estimation \(m²\)/i).fill("30");
    await screen.getByRole("button", { name: /créer le lot/i }).click();
    expect(onCreate).toHaveBeenCalledWith({
      nom: "Peinture",
      mode: "surface",
      estimation: 30,
    });
  });

  it("bloque la création d'un lot sans nom", async () => {
    const screen = await renderView([]);
    await screen.getByRole("button", { name: /nouveau lot/i }).click();
    await expect
      .element(screen.getByRole("button", { name: /créer le lot/i }))
      .toBeDisabled();
  });

  it("prévient que changer le mode efface l'estimation", async () => {
    const onUpdate = vi.fn();
    const lot = makeLot();
    const screen = await renderView([lot], { onUpdate });
    await screen.getByRole("button", { name: /modifier le lot/i }).click();
    await screen.getByLabelText("Mode de facturation").selectOptions("forfait");
    await expect
      .element(screen.getByText(/efface l'estimation/i))
      .toBeVisible();
    await screen.getByRole("button", { name: "Enregistrer" }).click();
    expect(onUpdate).toHaveBeenCalledWith(lot, {
      nom: "Carrelage",
      mode: "forfait",
      estimation: null,
    });
  });

  it("demande confirmation avant de supprimer un lot", async () => {
    const onDelete = vi.fn();
    const lot = makeLot();
    const screen = await renderView([lot], { onDelete });
    await screen.getByRole("button", { name: /supprimer le lot/i }).click();
    expect(onDelete).not.toHaveBeenCalled();
    await screen
      .getByRole("button", { name: "Supprimer", exact: true })
      .click();
    expect(onDelete).toHaveBeenCalledWith(lot);
  });
});
