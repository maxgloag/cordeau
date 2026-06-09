import { describe, it, expect, vi } from "vitest";
import { render } from "vitest-browser-react";
import { PhotoGalleryView } from "./PhotoGalleryView";
import type { Photo } from "@/lib/api";

const makePhoto = (overrides: Partial<Photo> = {}): Photo => ({
  id: "photo-1",
  chantierId: "chantier-1",
  lotId: null,
  tacheId: null,
  remoteKey: "photos/uuid",
  photoUrl: "https://example.com/photo.jpg",
  thumbnailUrl: "https://example.com/thumb.jpg",
  creeLe: new Date().toISOString(),
  ...overrides,
});

describe("PhotoGalleryView", () => {
  it("affiche la zone de drop quand il n'y a pas de photos", async () => {
    const screen = await render(
      <PhotoGalleryView
        photos={[]}
        uploads={[]}
        isLoading={false}
        onUpload={vi.fn()}
        onDelete={vi.fn()}
        onPhotoClick={vi.fn()}
      />,
    );
    await expect.element(screen.getByText(/déposer/i)).toBeVisible();
  });

  it("affiche les vignettes quand des photos existent", async () => {
    const photos = [makePhoto({ id: "p1" }), makePhoto({ id: "p2" })];
    const screen = await render(
      <PhotoGalleryView
        photos={photos}
        uploads={[]}
        isLoading={false}
        onUpload={vi.fn()}
        onDelete={vi.fn()}
        onPhotoClick={vi.fn()}
      />,
    );
    // Two photos with thumbnailUrl → two img elements (alt="" → use container)
    const imgs = screen.container.querySelectorAll("img");
    expect(imgs).toHaveLength(2);
  });

  it("affiche un spinner quand thumbnailUrl est null", async () => {
    const screen = await render(
      <PhotoGalleryView
        photos={[makePhoto({ thumbnailUrl: null })]}
        uploads={[]}
        isLoading={false}
        onUpload={vi.fn()}
        onDelete={vi.fn()}
        onPhotoClick={vi.fn()}
      />,
    );
    await expect.element(screen.getByTestId("thumbnail-spinner")).toBeVisible();
  });
});
