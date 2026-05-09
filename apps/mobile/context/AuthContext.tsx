import {
  createContext,
  useContext,
  useState,
  useEffect,
  type ReactNode,
} from "react";
import { getToken, setTokens, clearTokens } from "@/lib/auth";
import { login as apiLogin, register as apiRegister, logout as apiLogout } from "@/lib/api";
import type { AuthResponse } from "@/lib/api";

type AuthState = {
  token: string | null;
  isLoading: boolean;
  login: (email: string, motDePasse: string) => Promise<void>;
  register: (email: string, motDePasse: string) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    getToken()
      .then((t) => setToken(t))
      .finally(() => setIsLoading(false));
  }, []);

  async function handleAuthResponse(res: AuthResponse) {
    if (res.token && res.refreshToken) {
      await setTokens(res.token, res.refreshToken);
      setToken(res.token);
    }
  }

  async function login(email: string, motDePasse: string) {
    await handleAuthResponse(await apiLogin(email, motDePasse));
  }

  async function register(email: string, motDePasse: string) {
    await handleAuthResponse(await apiRegister(email, motDePasse));
  }

  async function logout() {
    try {
      await apiLogout();
    } catch {
      // ignore si session déjà expirée
    }
    await clearTokens();
    setToken(null);
  }

  return (
    <AuthContext.Provider value={{ token, isLoading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth doit être utilisé dans AuthProvider");
  return ctx;
}
