const { getDefaultConfig } = require("expo/metro-config");
const { withNativeWind } = require("nativewind/metro");

const config = getDefaultConfig(__dirname);
config.resolver.sourceExts.push("sql");

// En mode dev, Metro inclut les fichiers `.env*` du projet dans le graphe et
// tente de les transformer comme du JS (SyntaxError « Missing semicolon »).
// Ils sont chargés séparément par @expo/env, jamais importés comme modules :
// on les exclut du graphe. On étend le blockList par défaut d'Expo sans l'écraser.
config.resolver.blockList = [
  ...config.resolver.blockList,
  /(^|\/)\.env(\.[^/]+)?$/,
];

module.exports = withNativeWind(config, { input: "./global.css" });
