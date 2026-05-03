import React from "react";
import { render, screen, waitFor } from "@testing-library/react-native";
import App from "../App";

beforeEach(() => {
  global.fetch = jest.fn().mockResolvedValue({
    ok: true,
    json: async () => ({
      status: "ok",
      timestamp: new Date().toISOString(),
      version: "0.0.0",
      services: { database: "ok" },
    }),
  }) as jest.Mock;
});

describe("App — health check", () => {
  it("affiche le titre et le statut ok", async () => {
    render(<App />);
    expect(screen.getByText("Cordeau API")).toBeTruthy();
    await waitFor(() => {
      expect(screen.getByText("ok")).toBeTruthy();
    });
  });
});
