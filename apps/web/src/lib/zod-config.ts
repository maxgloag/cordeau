import { z } from "zod";

// Zod 4 sonde `new Function("")` pour activer sa compilation JIT. Sous une CSP stricte (ADR 0026), cet appel est
// attrapé mais signalé comme violation à chaque chargement de page. `jitless` supprime la sonde : la validation
// reste identique, sans compilation à l'exécution.
z.config({ jitless: true });
