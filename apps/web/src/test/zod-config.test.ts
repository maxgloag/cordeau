import { describe, expect, it } from "vitest";
import { z } from "zod";
import "../lib/zod-config";

describe("zod-config", () => {
  it("désactive la sonde de compilation JIT de Zod (qui déclenche une violation de CSP)", () => {
    expect(z.config().jitless).toBe(true);
  });
});
