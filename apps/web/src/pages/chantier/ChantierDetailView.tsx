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
  onSaveLegende: (photoId: string, legende: string | null) => void;
};

export function ChantierDetailView({
  photos,
  uploads,
  isLoading,
  onUpload,
  onDelete,
  onSaveLegende,
}: Props) {
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Détail chantier</h1>

      <PhotoGalleryView
        photos={photos}
        uploads={uploads}
        isLoading={isLoading}
        onUpload={onUpload}
        onDelete={onDelete}
        onPhotoClick={(photo) =>
          setLightboxIndex(photos.findIndex((p) => p.id === photo.id))
        }
      />

      <PhotoLightbox
        photos={photos}
        index={lightboxIndex}
        onClose={() => setLightboxIndex(null)}
        onNavigate={setLightboxIndex}
        onDelete={(photoId) => {
          onDelete(photoId);
          setLightboxIndex(null);
        }}
        onSaveLegende={onSaveLegende}
      />
    </div>
  );
}
