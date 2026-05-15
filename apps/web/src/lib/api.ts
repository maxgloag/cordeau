import type { components } from "@cordeau/shared";

const API_URL = import.meta.env["VITE_API_URL"] ?? "http://localhost:8000";

export type Chantier = components["schemas"]["Chantier"] & {
  id: string;
  adresseRue: string;
  adresseCodePostal: string;
  adresseVille: string;
  adressePays: string;
  statut: string;
};

export type UserMe = {
  id: string;
  email: string;
};

export type ApiError = {
  status: number;
  message: string;
};

async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const defaultHeaders: Record<string, string> = { Accept: "application/json" };
  if (init?.body !== undefined) defaultHeaders["Content-Type"] = "application/json";

  const res = await fetch(`${API_URL}${path}`, {
    ...init,
    credentials: "include",
    headers: { ...defaultHeaders, ...(init?.headers ?? {}) },
  });

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

// Auth
export async function fetchMe(): Promise<UserMe> {
  return apiFetch<UserMe>("/auth/me");
}

export async function login(
  email: string,
  motDePasse: string,
): Promise<UserMe> {
  return apiFetch<UserMe>("/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, motDePasse }),
  });
}

export async function register(
  email: string,
  motDePasse: string,
): Promise<UserMe> {
  return apiFetch<UserMe>("/auth/register", {
    method: "POST",
    body: JSON.stringify({ email, motDePasse }),
  });
}

export async function logout(): Promise<void> {
  await apiFetch<void>("/auth/logout", { method: "POST" });
}

// Chantiers
export async function fetchChantiers(): Promise<Chantier[]> {
  return apiFetch<Chantier[]>("/api/chantiers");
}

export type CreerChantierPayload = {
  adresseRue: string;
  adresseCodePostal: string;
  adresseVille: string;
  adressePays?: string;
  surfaceM2?: number | null;
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

export async function creerClient(payload: CreerClientPayload): Promise<Client> {
  return apiFetch<Client>("/api/clients", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export type ModifierClientPayload = Partial<CreerClientPayload>;

export async function modifierClient(id: string, payload: ModifierClientPayload): Promise<Client> {
  return apiFetch<Client>(`/api/clients/${id}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/merge-patch+json" },
    body: JSON.stringify(payload),
  });
}

export async function supprimerClient(id: string): Promise<void> {
  return apiFetch<void>(`/api/clients/${id}`, { method: "DELETE" });
}
