import { useParams } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { fetchPhotos, deletePhoto, patchLegende } from "@/lib/api";
import { usePhotoUpload } from "@/hooks/usePhotoUpload";
import { ChantierDetailView } from "./ChantierDetailView";

export function ChantierDetailPage() {
  const { id } = useParams({ from: "/_authed/chantiers/$id" });
  const queryClient = useQueryClient();

  const { data: photosList = [], isLoading } = useQuery({
    queryKey: ["photos", id],
    queryFn: () => fetchPhotos(id),
    staleTime: 30_000,
  });

  const { upload, uploads } = usePhotoUpload(id);

  async function handleDelete(photoId: string) {
    await deletePhoto(photoId);
    await queryClient.invalidateQueries({ queryKey: ["photos", id] });
  }

  async function handleSaveLegende(photoId: string, legende: string | null) {
    await patchLegende(photoId, legende);
    await queryClient.invalidateQueries({ queryKey: ["photos", id] });
  }

  return (
    <ChantierDetailView
      photos={photosList}
      uploads={uploads}
      isLoading={isLoading}
      onUpload={upload}
      onDelete={handleDelete}
      onSaveLegende={handleSaveLegende}
    />
  );
}
