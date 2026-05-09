import * as SecureStore from "expo-secure-store";

const KEY_TOKEN = "cordeau_token";
const KEY_REFRESH = "cordeau_refresh_token";

export async function getToken(): Promise<string | null> {
  return SecureStore.getItemAsync(KEY_TOKEN);
}

export async function getRefreshToken(): Promise<string | null> {
  return SecureStore.getItemAsync(KEY_REFRESH);
}

export async function setTokens(token: string, refreshToken: string): Promise<void> {
  await Promise.all([
    SecureStore.setItemAsync(KEY_TOKEN, token),
    SecureStore.setItemAsync(KEY_REFRESH, refreshToken),
  ]);
}

export async function clearTokens(): Promise<void> {
  await Promise.all([
    SecureStore.deleteItemAsync(KEY_TOKEN),
    SecureStore.deleteItemAsync(KEY_REFRESH),
  ]);
}
