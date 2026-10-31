import { describe, it, expect, vi, beforeEach } from "vitest";
import { ApiError } from "@/lib/api";

const cookieValues: Record<string, string> = {};
vi.mock("next/headers", () => ({
  cookies: async () => ({
    get: (k: string) => (k in cookieValues ? { value: cookieValues[k] } : undefined),
  }),
}));
vi.mock("next/navigation", () => ({
  redirect: vi.fn((url: string) => {
    throw new Error(`REDIRECT:${url}`);
  }),
  notFound: vi.fn(() => {
    throw new Error("NOT_FOUND");
  }),
}));
vi.mock("@/lib/api", () => ({
  ApiError: class ApiError extends Error {
    status: number;
    constructor(status: number) {
      super("api");
      this.status = status;
    }
  },
}));
const showWorkspaceBySlug = vi.fn();
vi.mock("@/lib/api/workspaces", () => ({
  showWorkspaceBySlug: (s: string) => showWorkspaceBySlug(s),
}));
const getOwnerWorkspace = vi.fn();
vi.mock("@/lib/actions/owner", () => ({
  getOwnerWorkspace: () => getOwnerWorkspace(),
}));

import WorkspaceSlugPage from "./page";

const run = () =>
  WorkspaceSlugPage({ params: Promise.resolve({ locale: "en", slug: "acme" }) });

describe("/w/[slug]", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    for (const k of Object.keys(cookieValues)) delete cookieValues[k];
  });

  it("sends the signed-in owner of that workspace to the dashboard", async () => {
    cookieValues.taqat_token = "t";
    cookieValues.taqat_role = "workspace_owner";
    getOwnerWorkspace.mockResolvedValue({ slug: "acme" });

    await expect(run()).rejects.toThrow("REDIRECT:/en/owner");
  });

  it("sends a visitor to the public workspace page", async () => {
    showWorkspaceBySlug.mockResolvedValue({ id: "ws-1" });

    await expect(run()).rejects.toThrow("REDIRECT:/en/workspaces/ws-1");
  });

  it("does not treat an owner of a different workspace as the owner", async () => {
    cookieValues.taqat_token = "t";
    cookieValues.taqat_role = "workspace_owner";
    getOwnerWorkspace.mockResolvedValue({ slug: "other" });
    showWorkspaceBySlug.mockResolvedValue({ id: "ws-1" });

    await expect(run()).rejects.toThrow("REDIRECT:/en/workspaces/ws-1");
  });

  it("returns 404 for an unknown slug", async () => {
    showWorkspaceBySlug.mockRejectedValue(new ApiError(404, "not found"));

    await expect(run()).rejects.toThrow("NOT_FOUND");
  });
});
