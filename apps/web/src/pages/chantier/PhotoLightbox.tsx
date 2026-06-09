import type { Photo } from "@/lib/api";

type Props = {
  photo: Photo | null;
  onClose: () => void;
};

export function PhotoLightbox({ photo, onClose }: Props) {
  if (!photo) return null;

  return (
    <div
      className="fixed inset-0 bg-black/90 z-50 flex items-center justify-center"
      onClick={onClose}
    >
      <button
        type="button"
        onClick={onClose}
        className="absolute top-4 right-4 text-white text-2xl hover:text-gray-300"
        aria-label="Fermer"
      >
        ✕
      </button>
      <img
        src={photo.photoUrl}
        alt=""
        className="max-w-[90vw] max-h-[90vh] object-contain"
        onClick={(e) => e.stopPropagation()}
      />
    </div>
  );
}
