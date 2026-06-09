import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { prepareUpload, confirmUpload } from "@/lib/api";

type UploadStatus = "idle" | "uploading" | "done" | "error";

export type FileUploadState = {
  file: File;
  status: UploadStatus;
  progress: number;
};

export function usePhotoUpload(chantierId: string) {
  const queryClient = useQueryClient();
  const [uploads, setUploads] = useState<FileUploadState[]>([]);

  function setFileStatus(fileName: string, status: UploadStatus, progress = 0) {
    setUploads((prev) =>
      prev.map((u) =>
        u.file.name === fileName ? { ...u, status, progress } : u,
      ),
    );
  }

  async function upload(files: File[]): Promise<void> {
    const initial = files.map((f) => ({
      file: f,
      status: "uploading" as UploadStatus,
      progress: 0,
    }));
    setUploads(initial);

    await Promise.allSettled(
      files.map(async (file) => {
        try {
          const { uploadUrl, remoteKey } = await prepareUpload(chantierId);

          await fetch(uploadUrl, {
            method: "PUT",
            body: file,
            headers: { "Content-Type": file.type || "image/jpeg" },
          });

          await confirmUpload(remoteKey, chantierId);
          setFileStatus(file.name, "done", 100);
        } catch {
          setFileStatus(file.name, "error");
        }
      }),
    );

    await queryClient.invalidateQueries({ queryKey: ["photos", chantierId] });
    setUploads([]);
  }

  return { upload, uploads };
}
