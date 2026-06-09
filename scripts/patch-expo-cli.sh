#!/bin/sh
# Fix: @expo/cli@56.x createFileMap-fork retourne { fileMap, ... } alors que
# metro@0.83.3 appelle setMaxListeners() directement sur le retour.
# hasteMap et dependencyPlugin sont déjà dans fileMap.plugins — retourner fileMap directement.
FILE="node_modules/@expo/cli/build/src/start/server/metro/createFileMap-fork.js"
if grep -q "return {" "$FILE" && grep -q "hasteMap," "$FILE"; then
  sed -i '' '/^    return {$/{
    N; N; N
    s/    return {\n        fileMap,\n        hasteMap,\n        dependencyPlugin\n    };/    \/\/ Fix: return fileMap directly (metro@0.83.3 calls setMaxListeners on return value)\n    return fileMap;/
  }' "$FILE" 2>/dev/null || true
  node -e "
    const fs = require('fs');
    const f = fs.readFileSync('$FILE', 'utf8');
    const patched = f.replace(
      /return \{\n        fileMap,\n        hasteMap,\n        dependencyPlugin\n    \};/,
      '\/\/ Fix: return fileMap directly (metro@0.83.3 calls setMaxListeners on return value)\n    return fileMap;'
    );
    if (f !== patched) { fs.writeFileSync('$FILE', patched); console.log('patch-expo-cli: applied'); }
    else { console.log('patch-expo-cli: already applied or not needed'); }
  "
fi
