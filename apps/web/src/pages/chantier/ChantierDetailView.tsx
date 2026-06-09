import { useState } from "react";
import type { Photo } from "@/lib/api";
import type { FileUploadState } from "@/hooks/usePhotoUpload";
import { PhotoGalleryView } from "./PhotoGalleryView";
import { PhotoLightbox } from "./PhotoLightbox";

type Props = {
  photos: Photo[];
  uploads: FileUploadState[];
  isLoading: boolean;
  onUpload: (files: File[]) => void;
  onDelete: (photoId: string) => void;
};

export function ChantierDetailView({
  photos,
  uploads,
  isLoading,
  onUpload,
  onDelete,
}: Props) {
  const [lightboxPhoto, setLightboxPhoto] = useState<Photo | null>(null);

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Détail chantier</h1>

      <PhotoGalleryView
        photos={photos}
        uploads={uploads}
        isLoading={isLoading}
        onUpload={onUpload}
        onDelete={onDelete}
        onPhotoClick={setLightboxPhoto}
      />

      <PhotoLightbox
        photo={lightboxPhoto}
        onClose={() => setLightboxPhoto(null)}
      />
    </div>
  );
}
