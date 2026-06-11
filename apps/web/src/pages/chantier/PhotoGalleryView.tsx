import { useRef } from "react";
import type { Photo } from "@/lib/api";
import type { FileUploadState } from "@/hooks/usePhotoUpload";

type Props = {
  photos: Photo[];
  uploads: FileUploadState[];
  isLoading: boolean;
  onUpload: (files: File[]) => void;
  onDelete: (photoId: string) => void;
  onPhotoClick: (photo: Photo) => void;
};

export function PhotoGalleryView({
  photos,
  uploads,
  isLoading,
  onUpload,
  onDelete,
  onPhotoClick,
}: Props) {
  const inputRef = useRef<HTMLInputElement>(null);

  function handleDrop(e: React.DragEvent) {
    e.preventDefault();
    const files = Array.from(e.dataTransfer.files).filter((f) =>
      f.type.startsWith("image/"),
    );
    if (files.length > 0) onUpload(files);
  }

  function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files ?? []);
    if (files.length > 0) onUpload(files);
    e.target.value = "";
  }

  const isEmpty = photos.length === 0 && uploads.length === 0;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-base font-semibold text-gray-900">Photos</h3>
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          className="text-sm text-blue-600 hover:text-blue-700"
        >
          + Ajouter
        </button>
        <input
          ref={inputRef}
          type="file"
          accept="image/*"
          multiple
          className="hidden"
          onChange={handleFileChange}
        />
      </div>

      {isLoading && (
        <div className="py-8 text-center text-sm text-gray-400">
          Chargement des photos…
        </div>
      )}

      {!isLoading && isEmpty ? (
        <div
          onDrop={handleDrop}
          onDragOver={(e) => e.preventDefault()}
          onClick={() => inputRef.current?.click()}
          className="flex flex-col items-center justify-center h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-blue-400 transition-colors"
        >
          <p className="text-sm text-gray-400">
            Déposer des photos ici ou cliquer
          </p>
        </div>
      ) : (
        !isLoading && (
          <div
            className="grid grid-cols-3 lg:grid-cols-4 gap-1"
            onDrop={handleDrop}
            onDragOver={(e) => e.preventDefault()}
          >
            {photos.map((photo) => (
              <div key={photo.id} className="relative group aspect-square">
                {photo.thumbnailUrl ? (
                  <img
                    src={photo.thumbnailUrl}
                    alt=""
                    className="w-full h-full object-cover rounded cursor-pointer"
                    onClick={() => onPhotoClick(photo)}
                  />
                ) : (
                  <div
                    data-testid="thumbnail-spinner"
                    className="w-full h-full bg-gray-100 rounded flex items-center justify-center"
                    style={{ minHeight: "64px" }}
                  >
                    <div className="w-5 h-5 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin" />
                  </div>
                )}
                <button
                  type="button"
                  onClick={() => onDelete(photo.id)}
                  className="absolute top-1 right-1 hidden group-hover:flex bg-black/50 text-white rounded-full w-6 h-6 items-center justify-center text-xs"
                  aria-label="Supprimer"
                >
                  ✕
                </button>
              </div>
            ))}

            {uploads.map(({ file, status }) => (
              <div
                key={file.name}
                className="aspect-square bg-gray-100 rounded flex items-center justify-center"
              >
                {status === "uploading" && (
                  <div className="w-5 h-5 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin" />
                )}
              </div>
            ))}
          </div>
        )
      )}
    </div>
  );
}
