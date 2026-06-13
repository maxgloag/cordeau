import {
  login,
  register,
  fetchChantiers,
  fetchChantier,
  creerChantier,
  archiverChantier,
  patchPhotoLegende,
  deletePhoto,
} from "../lib/api";

jest.mock("expo-secure-store", () => ({
  getItemAsync: jest.fn(),
  setItemAsync: jest.fn(),
  deleteItemAsync: jest.fn(),
}));

const mockFetch = jest.fn();
global.fetch = mockFetch;

const mockSecureStore = jest.requireMock("expo-secure-store") as {
  getItemAsync: jest.Mock;
  setItemAsync: jest.Mock;
  deleteItemAsync: jest.Mock;
};

function mockResponse(status: number, body: unknown) {
  return {
    ok: status >= 200 && status < 300,
    status,
    statusText: status === 200 ? "OK" : "Error",
    json: jest.fn().mockResolvedValue(body),
  };
}

beforeEach(() => {
  jest.clearAllMocks();
  mockSecureStore.getItemAsync.mockResolvedValue(null);
});

describe("auth API", () => {
  it("login envoie email + motDePasse avec X-Client-Type: mobile", async () => {
    mockFetch.mockResolvedValueOnce(
      mockResponse(200, {
        id: "1",
        email: "a@b.com",
        token: "tok",
        refreshToken: "ref",
      }),
    );

    const result = await login("a@b.com", "secret");

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/auth/login",
      expect.objectContaining({
        method: "POST",
        headers: expect.objectContaining({
          "X-Client-Type": "mobile",
          "Content-Type": "application/json",
        }),
        body: JSON.stringify({ email: "a@b.com", motDePasse: "secret" }),
      }),
    );
    expect(result.token).toBe("tok");
  });

  it("register envoie email + motDePasse", async () => {
    mockFetch.mockResolvedValueOnce(
      mockResponse(200, {
        id: "2",
        email: "b@b.com",
        token: "tok2",
        refreshToken: "ref2",
      }),
    );

    await register("b@b.com", "pass");

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/auth/register",
      expect.objectContaining({ method: "POST" }),
    );
  });
});

describe("chantiers API", () => {
  beforeEach(() => {
    mockSecureStore.getItemAsync.mockResolvedValue("my-token");
  });

  it("fetchChantiers envoie le token Bearer", async () => {
    mockFetch.mockResolvedValueOnce(mockResponse(200, []));

    await fetchChantiers();

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/chantiers",
      expect.objectContaining({
        headers: expect.objectContaining({ Authorization: "Bearer my-token" }),
      }),
    );
  });

  it("fetchChantier récupère un chantier par ID", async () => {
    const chantier = {
      id: "abc",
      adresseRue: "1 rue Test",
      statut: "en_preparation",
    };
    mockFetch.mockResolvedValueOnce(mockResponse(200, chantier));

    const result = await fetchChantier("abc");

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/chantiers/abc",
      expect.anything(),
    );
    expect(result.id).toBe("abc");
  });

  it("creerChantier envoie POST avec le payload", async () => {
    const payload = {
      adresseRue: "2 av. Main",
      adresseCodePostal: "75001",
      adresseVille: "Paris",
      adressePays: "FR",
    };
    mockFetch.mockResolvedValueOnce(
      mockResponse(200, { id: "new", ...payload, statut: "EN_PREPARATION" }),
    );

    await creerChantier(payload);

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/chantiers",
      expect.objectContaining({
        method: "POST",
        body: JSON.stringify(payload),
      }),
    );
  });

  it("archiverChantier envoie DELETE", async () => {
    mockFetch.mockResolvedValueOnce({ ok: true, status: 204, json: jest.fn() });

    await archiverChantier("xyz");

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/chantiers/xyz",
      expect.objectContaining({ method: "DELETE" }),
    );
  });

  it("lève une ApiError sur réponse non-ok", async () => {
    mockFetch.mockResolvedValueOnce(
      mockResponse(404, { detail: "Chantier introuvable" }),
    );

    await expect(fetchChantier("unknown")).rejects.toMatchObject({
      status: 404,
      message: "Chantier introuvable",
    });
  });
});

describe("photos API", () => {
  beforeEach(() => {
    mockSecureStore.getItemAsync.mockResolvedValue("my-token");
  });

  it("patchPhotoLegende envoie un PATCH merge-patch avec la légende", async () => {
    mockFetch.mockResolvedValueOnce(
      mockResponse(200, { id: "p1", legende: "Mur nord" }),
    );

    await patchPhotoLegende("p1", "Mur nord");

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/photos/p1",
      expect.objectContaining({
        method: "PATCH",
        headers: expect.objectContaining({
          "Content-Type": "application/merge-patch+json",
        }),
        body: JSON.stringify({ legende: "Mur nord" }),
      }),
    );
  });

  it("deletePhoto envoie DELETE sur la photo", async () => {
    mockFetch.mockResolvedValueOnce({ ok: true, status: 204, json: jest.fn() });

    await deletePhoto("p1");

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/photos/p1",
      expect.objectContaining({ method: "DELETE" }),
    );
  });
});

describe("auto-refresh sur 401", () => {
  it("tente le refresh et réessaie si le token est expiré", async () => {
    mockSecureStore.getItemAsync
      .mockResolvedValueOnce("expired-token") // premier appel → token principal
      .mockResolvedValueOnce("refresh-token") // getRefreshToken dans tryRefresh
      .mockResolvedValueOnce("new-token"); // deuxième tentative après refresh

    mockFetch
      .mockResolvedValueOnce(mockResponse(401, {})) // premier appel → 401
      .mockResolvedValueOnce(
        mockResponse(200, {
          token: "new-token",
          refreshToken: "new-ref",
          id: "u",
          email: "e@e.com",
        }),
      ) // refresh
      .mockResolvedValueOnce(mockResponse(200, [])); // retry

    const result = await fetchChantiers();

    expect(mockFetch).toHaveBeenCalledTimes(3);
    expect(result).toEqual([]);
  });
});
