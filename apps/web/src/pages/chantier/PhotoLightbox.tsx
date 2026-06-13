import { useEffect, useState } from "react";
import {
  ChevronLeft,
  ChevronRight,
  X,
  ExternalLink,
  Trash2,
} from "lucide-react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import type { Photo } from "@/lib/api";

type Props = {
  photos: Photo[];
  index: number | null;
  onClose: () => void;
  onNavigate: (index: number) => void;
  onDelete: (photoId: string) => void;
  onSaveLegende: (photoId: string, legende: string | null) => void;
};

const dateFormat = new Intl.DateTimeFormat("fr-FR", {
  dateStyle: "long",
  timeStyle: "short",
});

export function PhotoLightbox({
  photos,
  index,
  onClose,
  onNavigate,
  onDelete,
  onSaveLegende,
}: Props) {
  const photo = index !== null ? photos[index] : undefined;
  const [legendeDraft, setLegendeDraft] = useState(photo?.legende ?? "");
  const [shownPhotoId, setShownPhotoId] = useState(photo?.id);

  // Réinitialise le brouillon quand la photo affichée change — ajustement d'état
  // pendant le rendu (pattern React recommandé, pas d'effet de synchronisation).
  if (photo && photo.id !== shownPhotoId) {
    setShownPhotoId(photo.id);
    setLegendeDraft(photo.legende ?? "");
  }

  const hasPrev = index !== null && index > 0;
  const hasNext = index !== null && index < photos.length - 1;

  useEffect(() => {
    if (index === null) return;
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") onClose();
      else if (e.key === "ArrowLeft" && hasPrev) onNavigate(index! - 1);
      else if (e.key === "ArrowRight" && hasNext) onNavigate(index! + 1);
    }
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [index, hasPrev, hasNext, onClose, onNavigate]);

  if (!photo) return null;

  function saveLegende() {
    if (!photo) return;
    const trimmed = legendeDraft.trim();
    const value = trimmed === "" ? null : trimmed;
    if (value === (photo.legende ?? null)) return;
    onSaveLegende(photo.id, value);
  }

  return (
    <div className="fixed inset-0 bg-black/90 z-50 flex flex-col">
      {/* Barre haute : fermer */}
      <div className="flex justify-end p-4">
        <button
          type="button"
          onClick={onClose}
          className="text-white hover:text-gray-300"
          aria-label="Fermer"
        >
          <X size={28} />
        </button>
      </div>

      {/* Image + navigation */}
      <div className="flex-1 flex items-center justify-center min-h-0 px-2">
        <button
          type="button"
          onClick={() => hasPrev && onNavigate(index! - 1)}
          disabled={!hasPrev}
          className="text-white p-2 hover:text-gray-300 disabled:opacity-30"
          aria-label="Photo précédente"
        >
          <ChevronLeft size={36} />
        </button>

        <img
          src={photo.photoUrl}
          alt={photo.legende ?? ""}
          className="max-w-[80vw] max-h-[70vh] object-contain"
        />

        <button
          type="button"
          onClick={() => hasNext && onNavigate(index! + 1)}
          disabled={!hasNext}
          className="text-white p-2 hover:text-gray-300 disabled:opacity-30"
          aria-label="Photo suivante"
        >
          <ChevronRight size={36} />
        </button>
      </div>

      {/* Pied : légende éditable + métadonnées + actions */}
      <div className="bg-black/60 px-6 py-4 space-y-3">
        <div className="flex items-center gap-2 max-w-2xl mx-auto">
          <input
            type="text"
            value={legendeDraft}
            onChange={(e) => setLegendeDraft(e.target.value)}
            onBlur={saveLegende}
            placeholder="Ajouter une légende"
            maxLength={280}
            className="flex-1 bg-transparent border-b border-white/30 text-white placeholder:text-gray-400 py-1 focus:outline-none focus:border-white"
          />
          <button
            type="button"
            onClick={saveLegende}
            className="text-sm text-white/80 hover:text-white whitespace-nowrap"
            aria-label="Enregistrer la légende"
          >
            Enregistrer
          </button>
        </div>

        <div className="flex items-center justify-between max-w-2xl mx-auto text-sm text-gray-300">
          <time dateTime={photo.creeLe}>
            {dateFormat.format(new Date(photo.creeLe))}
          </time>
          <div className="flex items-center gap-4">
            <a
              href={photo.photoUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1 hover:text-white"
            >
              <ExternalLink size={14} />
              Ouvrir l'original
            </a>
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <button
                  type="button"
                  className="flex items-center gap-1 text-red-400 hover:text-red-300"
                  aria-label="Supprimer la photo"
                >
                  <Trash2 size={14} />
                  Supprimer
                </button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Supprimer cette photo ?</AlertDialogTitle>
                  <AlertDialogDescription>
                    Cette action est définitive et supprimera la photo du
                    chantier.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Annuler</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={() => onDelete(photo.id)}
                    style={{
                      background: "var(--color-destructive)",
                      color: "white",
                    }}
                  >
                    Supprimer
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </div>
        </div>
      </div>
    </div>
  );
}
