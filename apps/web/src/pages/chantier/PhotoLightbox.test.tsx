import { describe, it, expect, vi } from "vitest";
import { render } from "vitest-browser-react";
import { userEvent } from "vitest/browser";
import { PhotoLightbox } from "./PhotoLightbox";
import type { Photo } from "@/lib/api";

const makePhoto = (overrides: Partial<Photo> = {}): Photo => ({
  id: "p1",
  chantierId: "c1",
  lotId: null,
  tacheId: null,
  remoteKey: "photos/uuid",
  photoUrl: "https://example.com/photo.jpg",
  thumbnailUrl: "https://example.com/thumb.jpg",
  creeLe: "2026-06-13T10:00:00+00:00",
  legende: null,
  ...overrides,
});

const noop = {
  onClose: vi.fn(),
  onNavigate: vi.fn(),
  onDelete: vi.fn(),
  onSaveLegende: vi.fn(),
};

describe("PhotoLightbox", () => {
  it("ne rend rien quand index est null", async () => {
    const screen = await render(
      <PhotoLightbox photos={[makePhoto()]} index={null} {...noop} />,
    );
    expect(screen.container.querySelector("img")).toBeNull();
  });

  it("le bouton suivant appelle onNavigate avec l'index suivant", async () => {
    const onNavigate = vi.fn();
    const photos = [makePhoto({ id: "p1" }), makePhoto({ id: "p2" })];
    const screen = await render(
      <PhotoLightbox
        photos={photos}
        index={0}
        {...noop}
        onNavigate={onNavigate}
      />,
    );
    await screen.getByRole("button", { name: /photo suivante/i }).click();
    expect(onNavigate).toHaveBeenCalledWith(1);
  });

  it("Escape ferme la lightbox", async () => {
    const onClose = vi.fn();
    await render(
      <PhotoLightbox
        photos={[makePhoto()]}
        index={0}
        {...noop}
        onClose={onClose}
      />,
    );
    await userEvent.keyboard("{Escape}");
    expect(onClose).toHaveBeenCalled();
  });

  it("éditer la légende puis enregistrer appelle onSaveLegende", async () => {
    const onSaveLegende = vi.fn();
    const screen = await render(
      <PhotoLightbox
        photos={[makePhoto({ id: "p1", legende: null })]}
        index={0}
        {...noop}
        onSaveLegende={onSaveLegende}
      />,
    );
    await screen.getByPlaceholder(/légende/i).fill("Mur fissuré");
    await screen
      .getByRole("button", { name: /enregistrer la légende/i })
      .click();
    expect(onSaveLegende).toHaveBeenCalledWith("p1", "Mur fissuré");
  });

  it("supprimer demande confirmation avant d'appeler onDelete", async () => {
    const onDelete = vi.fn();
    const screen = await render(
      <PhotoLightbox
        photos={[makePhoto({ id: "p1" })]}
        index={0}
        {...noop}
        onDelete={onDelete}
      />,
    );
    await screen.getByRole("button", { name: /supprimer la photo/i }).click();
    // onDelete pas encore appelé tant que non confirmé
    expect(onDelete).not.toHaveBeenCalled();
    await screen.getByRole("button", { name: /^supprimer$/i }).click();
    expect(onDelete).toHaveBeenCalledWith("p1");
  });
});
