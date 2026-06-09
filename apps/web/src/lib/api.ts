import type { components } from "@cordeau/shared";

export const API_URL =
  import.meta.env["VITE_API_URL"] ?? "http://localhost:8000";

const TOKEN_KEY = "cordeau_token";
const REFRESH_TOKEN_KEY = "cordeau_refresh_token";

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}
export function setTokens(token: string, refreshToken: string): void {
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(REFRESH_TOKEN_KEY, refreshToken);
}
export function clearTokens(): void {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(REFRESH_TOKEN_KEY);
}

export type UserMe = {
  id: string;
  email: string;
};

export type ApiError = {
  status: number;
  message: string;
};

type AuthResponse = {
  id: string;
  email: string;
  token: string;
  refreshToken: string;
  expiresAt: string;
};

type RefreshResponse = {
  token: string;
  refreshToken: string;
  expiresAt: string;
};

async function tryRefresh(): Promise<boolean> {
  const refreshToken = localStorage.getItem(REFRESH_TOKEN_KEY);
  if (!refreshToken) return false;

  try {
    const res = await fetch(`${API_URL}/auth/refresh`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ refreshToken }),
    });
    if (!res.ok) {
      clearTokens();
      return false;
    }
    const data = (await res.json()) as RefreshResponse;
    setTokens(data.token, data.refreshToken);
    return true;
  } catch {
    clearTokens();
    return false;
  }
}

async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const defaultHeaders: Record<string, string> = { Accept: "application/json" };
  if (init?.body !== undefined)
    defaultHeaders["Content-Type"] = "application/json";

  const token = getToken();
  if (token) defaultHeaders["Authorization"] = `Bearer ${token}`;

  const res = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: { ...defaultHeaders, ...(init?.headers ?? {}) },
  });

  if (res.status === 401) {
    const refreshed = await tryRefresh();
    if (refreshed) {
      const retryHeaders = {
        ...defaultHeaders,
        Authorization: `Bearer ${getToken() ?? ""}`,
      };
      const retry = await fetch(`${API_URL}${path}`, {
        ...init,
        headers: { ...retryHeaders, ...(init?.headers ?? {}) },
      });
      if (!retry.ok) {
        const error: ApiError = {
          status: retry.status,
          message: retry.statusText,
        };
        try {
          const body = (await retry.json()) as {
            detail?: string;
            message?: string;
          };
          error.message = body.detail ?? body.message ?? retry.statusText;
        } catch {
          /* ignore */
        }
        throw error;
      }
      if (retry.status === 204) return undefined as T;
      return retry.json() as Promise<T>;
    }
    const error: ApiError = { status: 401, message: "Non authentifié" };
    throw error;
  }

  if (!res.ok) {
    const error: ApiError = { status: res.status, message: res.statusText };
    try {
      const body = (await res.json()) as { detail?: string; message?: string };
      error.message = body.detail ?? body.message ?? res.statusText;
    } catch {
      /* ignore */
    }
    throw error;
  }

  if (res.status === 204) return undefined as T;
  return res.json() as Promise<T>;
}

// Auth
export async function fetchMe(): Promise<UserMe> {
  return apiFetch<UserMe>("/auth/me");
}

export async function exchangeOAuthCode(code: string): Promise<UserMe> {
  const data = await apiFetch<AuthResponse>("/auth/oauth/code/exchange", {
    method: "POST",
    body: JSON.stringify({ code }),
  });
  setTokens(data.token, data.refreshToken);
  return { id: data.id, email: data.email };
}

export async function login(
  email: string,
  motDePasse: string,
): Promise<UserMe> {
  const data = await apiFetch<AuthResponse>("/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, motDePasse }),
  });
  setTokens(data.token, data.refreshToken);
  return { id: data.id, email: data.email };
}

export async function register(
  email: string,
  motDePasse: string,
): Promise<UserMe> {
  const data = await apiFetch<AuthResponse>("/auth/register", {
    method: "POST",
    body: JSON.stringify({ email, motDePasse }),
  });
  setTokens(data.token, data.refreshToken);
  return { id: data.id, email: data.email };
}

export async function logout(): Promise<void> {
  try {
    await apiFetch<void>("/auth/logout", { method: "POST" });
  } finally {
    clearTokens();
  }
}

// Chantiers
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

export async function fetchChantiers(): Promise<Chantier[]> {
  return apiFetch<Chantier[]>("/api/chantiers");
}

export type CreerChantierPayload = {
  adresseRue: string;
  adresseCodePostal: string;
  adresseVille: string;
  adressePays?: string;
  surfaceM2?: number | null;
  clientId?: string | null;
};

export async function creerChantier(
  payload: CreerChantierPayload,
): Promise<Chantier> {
  return apiFetch<Chantier>("/api/chantiers", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export type ModifierChantierPayload = Partial<CreerChantierPayload>;

export async function modifierChantier(
  id: string,
  payload: ModifierChantierPayload,
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

// Clients
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
};

export async function creerClient(
  payload: CreerClientPayload,
): Promise<Client> {
  return apiFetch<Client>("/api/clients", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export type ModifierClientPayload = Partial<CreerClientPayload>;

export async function modifierClient(
  id: string,
  payload: ModifierClientPayload,
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

// Photos
export type Photo = {
  id: string;
  chantierId: string;
  lotId: string | null;
  tacheId: string | null;
  remoteKey: string;
  photoUrl: string;
  thumbnailUrl: string | null;
  creeLe: string;
};

export async function prepareUpload(
  chantierId: string,
): Promise<{ uploadUrl: string; remoteKey: string }> {
  return apiFetch<{ uploadUrl: string; remoteKey: string }>(
    "/api/photos/prepare",
    {
      method: "POST",
      body: JSON.stringify({ chantierId }),
    },
  );
}

export async function confirmUpload(
  remoteKey: string,
  chantierId: string,
): Promise<Photo> {
  return apiFetch<Photo>("/api/photos/confirm", {
    method: "POST",
    body: JSON.stringify({ remoteKey, chantierId }),
  });
}

export async function fetchPhotos(chantierId: string): Promise<Photo[]> {
  return apiFetch<Photo[]>(`/api/chantiers/${chantierId}/photos`);
}

export async function deletePhoto(id: string): Promise<void> {
  return apiFetch<void>(`/api/photos/${id}`, { method: "DELETE" });
}
