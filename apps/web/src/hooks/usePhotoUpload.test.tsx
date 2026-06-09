import { describe, it, expect, vi, beforeEach } from "vitest";
import { renderHook } from "vitest-browser-react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createElement } from "react";
import { usePhotoUpload } from "./usePhotoUpload";
import * as api from "@/lib/api";

vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof api>();
  return {
    ...actual,
    prepareUpload: vi.fn(),
    confirmUpload: vi.fn(),
  };
});

beforeEach(() => {
  vi.resetAllMocks();
  vi.stubGlobal("fetch", vi.fn());
});

// In browser mode, globalThis === window
const g = globalThis;

const wrapper = ({ children }: { children: React.ReactNode }) =>
  createElement(QueryClientProvider, { client: new QueryClient() }, children);

describe("usePhotoUpload", () => {
  it("appelle prepare → upload R2 → confirm dans l'ordre", async () => {
    vi.mocked(api.prepareUpload).mockResolvedValue({
      uploadUrl: "https://r2.example.com/put?sig=x",
      remoteKey: "photos/test-key",
    });
    (g.fetch as ReturnType<typeof vi.fn>).mockResolvedValue({ ok: true });
    vi.mocked(api.confirmUpload).mockResolvedValue({
      id: "photo-id",
      chantierId: "chantier-id",
      lotId: null,
      tacheId: null,
      remoteKey: "photos/test-key",
      photoUrl: "https://photos.example.com/photos/test-key",
      thumbnailUrl: null,
      creeLe: new Date().toISOString(),
    });

    const { result } = await renderHook(() => usePhotoUpload("chantier-id"), {
      wrapper,
    });

    const file = new File(["img"], "photo.jpg", { type: "image/jpeg" });
    await result.current.upload([file]);

    expect(api.prepareUpload).toHaveBeenCalledWith("chantier-id");
    expect(g.fetch).toHaveBeenCalledWith(
      "https://r2.example.com/put?sig=x",
      expect.objectContaining({ method: "PUT" }),
    );
    expect(api.confirmUpload).toHaveBeenCalledWith(
      "photos/test-key",
      "chantier-id",
    );
  });
});
