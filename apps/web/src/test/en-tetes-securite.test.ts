import { describe, expect, it } from "vitest";
import contenu from "../../public/_headers?raw";

// Contrat de public/_headers (ADR 0026, temps 1) : un affaiblissement accidentel de la politique fait échouer ce test.
const csp =
  contenu.match(/^\s+Content-Security-Policy-Report-Only: (.+)$/m)?.[1] ?? "";
const directives = csp.split("; ");

describe("public/_headers", () => {
  it("pose les en-têtes de durcissement", () => {
    expect(contenu).toMatch(/^\s+X-Content-Type-Options: nosniff$/m);
    expect(contenu).toMatch(
      /^\s+Referrer-Policy: strict-origin-when-cross-origin$/m,
    );
    expect(contenu).toMatch(/^\s+X-Frame-Options: DENY$/m);
  });

  it.each([
    "default-src 'self'",
    "script-src 'self'",
    "object-src 'none'",
    "base-uri 'none'",
    "frame-ancestors 'none'",
  ])("la CSP contient la directive %s", (directive) => {
    expect(directives).toContain(directive);
  });

  it("n'autorise ni unsafe-eval ni script inline", () => {
    expect(csp).not.toContain("unsafe-eval");
    const scriptSrc = directives.find((d) => d.startsWith("script-src")) ?? "";
    expect(scriptSrc).not.toContain("unsafe-inline");
  });
});
