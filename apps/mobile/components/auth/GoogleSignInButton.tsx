import { useEffect, useState } from "react";
import { TouchableOpacity, Text, View, ActivityIndicator } from "react-native";
import * as WebBrowser from "expo-web-browser";
import * as Google from "expo-auth-session/providers/google";
import Svg, { Path } from "react-native-svg";
import { useAuth } from "@/context/AuthContext";

WebBrowser.maybeCompleteAuthSession();

const IOS_CLIENT_ID = process.env["EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID"];

type Props = {
  label?: string;
  onError?: (message: string) => void;
};

export function GoogleSignInButton({ label = "Continuer avec Google", onError }: Props) {
  const { loginWithGoogle } = useAuth();
  const [isPending, setIsPending] = useState(false);

  const [request, response, promptAsync] = Google.useAuthRequest({
    iosClientId: IOS_CLIENT_ID,
  });

  useEffect(() => {
    if (response?.type !== "success") return;
    const idToken = response.params["id_token"];
    if (typeof idToken !== "string" || idToken === "") {
      onError?.("Pas d'id_token retourné par Google.");
      return;
    }
    setIsPending(true);
    loginWithGoogle(idToken)
      .catch(() => onError?.("La connexion Google a échoué."))
      .finally(() => setIsPending(false));
  }, [response, loginWithGoogle, onError]);

  return (
    <TouchableOpacity
      className="flex-row items-center justify-center gap-2 rounded-xl border border-border bg-surface py-3.5 px-4"
      onPress={() => void promptAsync()}
      disabled={!request || isPending}
      activeOpacity={0.7}
    >
      {isPending ? (
        <ActivityIndicator color="#6B6259" />
      ) : (
        <>
          <Svg viewBox="0 0 24 24" width={18} height={18}>
            <Path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
            <Path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
            <Path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
            <Path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
          </Svg>
          <Text className="text-text font-medium text-base">{label}</Text>
        </>
      )}
    </TouchableOpacity>
  );
}

export function AuthDivider({ text = "ou" }: { text?: string }) {
  return (
    <View className="my-4 flex-row items-center gap-3">
      <View className="h-px flex-1 bg-border" />
      <Text className="text-xs uppercase text-muted">{text}</Text>
      <View className="h-px flex-1 bg-border" />
    </View>
  );
}
