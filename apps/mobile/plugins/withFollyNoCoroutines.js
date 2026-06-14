const { withDangerousMod } = require("expo/config-plugins");
const fs = require("fs");
const path = require("path");

/**
 * Config plugin Expo : désactive proprement les coroutines folly dans le build iOS.
 *
 * Contexte (issue #84) : sous Xcode 26.5, le clang récent active la macro
 * `__cpp_impl_coroutine`. `folly/Portability.h` en déduit `FOLLY_HAS_COROUTINES=1`
 * et inclut `folly/coro/Coroutine.h`, absent du prebuilt `ReactNativeDependencies`
 * livré par Expo SDK 56 / RN 0.85 → erreur `folly/coro/Coroutine.h file not found`.
 *
 * `FOLLY_CFG_NO_COROUTINES` est le flag sanctionné par folly pour désactiver les
 * coroutines (cf folly/Portability.h). On l'injecte dans
 * `GCC_PREPROCESSOR_DEFINITIONS` de toutes les cibles du projet Pods.
 *
 * `ios/` étant gitignored (regénéré à chaque `expo prebuild`), ce patch doit vivre
 * en config plugin committé plutôt qu'en édition manuelle du Podfile.
 *
 * @type {import('expo/config-plugins').ConfigPlugin}
 */
const withFollyNoCoroutines = (config) => {
  return withDangerousMod(config, [
    "ios",
    (config) => {
      const podfilePath = path.join(
        config.modRequest.platformProjectRoot,
        "Podfile",
      );
      const contents = fs.readFileSync(podfilePath, "utf8");

      const MARKER = "FOLLY_CFG_NO_COROUTINES";
      if (contents.includes(MARKER)) {
        return config;
      }

      const anchor = "post_install do |installer|\n";
      const anchorIndex = contents.indexOf(anchor);
      if (anchorIndex === -1) {
        throw new Error(
          "[withFollyNoCoroutines] Bloc `post_install do |installer|` introuvable dans le Podfile généré ; le template Expo a changé, mettre à jour le plugin.",
        );
      }

      const injection = [
        "    # >>> withFollyNoCoroutines (issue #84) : Xcode 26.5 active __cpp_impl_coroutine,",
        "    # folly inclut alors folly/coro/Coroutine.h absent du prebuilt. On force le flag",
        "    # folly sanctionné FOLLY_CFG_NO_COROUTINES sur toutes les cibles Pods.",
        "    installer.pods_project.targets.each do |target|",
        "      target.build_configurations.each do |build_config|",
        "        defs = build_config.build_settings['GCC_PREPROCESSOR_DEFINITIONS'] || ['$(inherited)']",
        "        defs = [defs] unless defs.is_a?(Array)",
        "        defs << 'FOLLY_CFG_NO_COROUTINES=1' unless defs.include?('FOLLY_CFG_NO_COROUTINES=1')",
        "        build_config.build_settings['GCC_PREPROCESSOR_DEFINITIONS'] = defs",
        "      end",
        "    end",
        "    # <<< withFollyNoCoroutines",
        "",
      ].join("\n");

      const insertAt = anchorIndex + anchor.length;
      const patched =
        contents.slice(0, insertAt) + injection + contents.slice(insertAt);

      fs.writeFileSync(podfilePath, patched);
      return config;
    },
  ]);
};

module.exports = withFollyNoCoroutines;
