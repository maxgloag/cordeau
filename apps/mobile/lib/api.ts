import type { components } from "@cordeau/shared";
import { getToken, getRefreshToken, clearTokens, setTokens } from "./auth";

const API_URL = process.env["EXPO_PUBLIC_API_URL"] ?? "http://localhost:8000";

let _onSessionExpired: (() => void) | null = null;
export function setSessionExpiredCallback(cb: () => void): void {
  _onSessionExpired = cb;
}

export type Chantier = components["schemas"]["Chantier"] & {
  id: string;
  adresseRue: string;
  adresseCodePostal: string;
  adresseVille: string;
  adressePays: string;
  statut: string;
  clientId?: string | null;
  clientNom?: string | null;
};

export type AuthResponse = {
  id: string;
  email: string;
  token?: string;
  refreshToken?: string;
  expiresAt?: string;
};

export type ApiError = {
  status: number;
  message: string;
};

async function apiFetch<T>(
  path: string,
  init?: RequestInit,
  retry = true,
): Promise<T> {
  const token = await getToken();
  const hasBody = init?.body !== undefined;

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Client-Type": "mobile",
    ...(hasBody ? { "Content-Type": "application/json" } : {}),
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...((init?.headers as Record<string, string> | undefined) ?? {}),
  };

  const res = await fetch(`${API_URL}${path}`, { ...init, headers });

  if (res.status === 401 && retry) {
    const refreshed = await tryRefresh();
    if (refreshed) return apiFetch<T>(path, init, false);
    await clearTokens();
    _onSessionExpired?.();
    throw { status: 401, message: "Session expirée" } satisfies ApiError;
  }

  if (!res.ok) {
    const error: ApiError = { status: res.status, message: res.statusText };
    try {
      const body = (await res.json()) as { detail?: string };
      if (body.detail) error.message = body.detail;
    } catch {
      // ignore json parse error
    }
    throw error;
  }

  if (res.status === 204) return undefined as T;
  return res.json() as Promise<T>;
}

async function tryRefresh(): Promise<boolean> {
  const refreshToken = await getRefreshToken();
  if (!refreshToken) return false;

  try {
    const res = await fetch(`${API_URL}/auth/refresh`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Client-Type": "mobile",
      },
      body: JSON.stringify({ refreshToken }),
    });
    if (!res.ok) return false;
    const data = (await res.json()) as AuthResponse;
    if (data.token && data.refreshToken) {
      await setTokens(data.token, data.refreshToken);
      return true;
    }
    return false;
  } catch {
    return false;
  }
}

export async function login(
  email: string,
  motDePasse: string,
): Promise<AuthResponse> {
  return apiFetch<AuthResponse>("/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, motDePasse }),
  });
}

export async function register(
  email: string,
  motDePasse: string,
): Promise<AuthResponse> {
  return apiFetch<AuthResponse>("/auth/register", {
    method: "POST",
    body: JSON.stringify({ email, motDePasse }),
  });
}

export async function logout(): Promise<void> {
  await apiFetch<void>("/auth/logout", { method: "POST" });
}

export async function exchangeGoogleIdToken(
  idToken: string,
): Promise<AuthResponse> {
  return apiFetch<AuthResponse>("/auth/oauth/google/exchange", {
    method: "POST",
    body: JSON.stringify({ idToken }),
  });
}

export async function fetchChantiers(): Promise<Chantier[]> {
  return apiFetch<Chantier[]>("/api/chantiers");
}

export async function fetchChantier(id: string): Promise<Chantier> {
  return apiFetch<Chantier>(`/api/chantiers/${id}`);
}

export type CreerChantierPayload = {
  adresseRue: string;
  adresseCodePostal: string;
  adresseVille: string;
  adressePays?: string;
  surfaceM2?: number | null;
  clientId?: string | null;
  uuid?: string | null;
};

export async function creerChantier(
  payload: CreerChantierPayload,
): Promise<Chantier> {
  return apiFetch<Chantier>("/api/chantiers", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function modifierChantier(
  id: string,
  payload: Partial<CreerChantierPayload>,
): Promise<Chantier> {
  return apiFetch<Chantier>(`/api/chantiers/${id}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/merge-patch+json" },
    body: JSON.stringify(payload),
  });
}

export async function archiverChantier(id: string): Promise<void> {
  return apiFetch<void>(`/api/chantiers/${id}`, { method: "DELETE" });
}

export type Client = components["schemas"]["Client"] & {
  id: string;
  nom: string;
  adresseRue: string;
  adresseCodePostal: string;
  adresseVille: string;
  adressePays: string;
};

export async function listClients(): Promise<Client[]> {
  return apiFetch<Client[]>("/api/clients");
}

export type CreerClientPayload = {
  nom: string;
  email?: string | null;
  telephone?: string | null;
  adresseRue: string;
  adresseCodePostal: string;
  adresseVille: string;
  adressePays?: string;
  notes?: string | null;
  uuid?: string | null;
};

export async function creerClient(
  payload: CreerClientPayload,
): Promise<Client> {
  return apiFetch<Client>("/api/clients", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function modifierClient(
  id: string,
  payload: Partial<CreerClientPayload>,
): Promise<Client> {
  return apiFetch<Client>(`/api/clients/${id}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/merge-patch+json" },
    body: JSON.stringify(payload),
  });
}

export async function supprimerClient(id: string): Promise<void> {
  return apiFetch<void>(`/api/clients/${id}`, { method: "DELETE" });
}

export type PrepareUploadResponse = {
  uploadUrl: string;
  remoteKey: string;
};

export type PhotoApiResponse = {
  id: string;
  chantierId: string;
  lotId: string | null;
  tacheId: string | null;
  remoteKey: string;
  photoUrl: string;
  thumbnailUrl: string | null;
  creeLe: string;
  legende: string | null;
};

export async function prepareUpload(
  chantierId: string,
  contentType: string,
): Promise<PrepareUploadResponse> {
  return apiFetch<PrepareUploadResponse>("/api/photos/prepare", {
    method: "POST",
    body: JSON.stringify({ chantierId, contentType }),
  });
}

export async function confirmUpload(
  remoteKey: string,
  chantierId: string,
): Promise<PhotoApiResponse> {
  return apiFetch<PhotoApiResponse>("/api/photos/confirm", {
    method: "POST",
    body: JSON.stringify({ remoteKey, chantierId }),
  });
}

export async function fetchPhotos(
  chantierId: string,
): Promise<PhotoApiResponse[]> {
  return apiFetch<PhotoApiResponse[]>(`/api/chantiers/${chantierId}/photos`);
}

export async function patchPhotoLegende(
  id: string,
  legende: string | null,
): Promise<PhotoApiResponse> {
  return apiFetch<PhotoApiResponse>(`/api/photos/${id}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/merge-patch+json" },
    body: JSON.stringify({ legende }),
  });
}

export async function deletePhoto(id: string): Promise<void> {
  return apiFetch<void>(`/api/photos/${id}`, { method: "DELETE" });
}
