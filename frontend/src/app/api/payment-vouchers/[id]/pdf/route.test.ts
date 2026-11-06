import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";

vi.mock("next/headers", () => ({
  cookies: async () => ({ get: () => ({ value: "token" }) }),
}));

import { GET } from "./route";

const call = (id: string) => GET(new Request("http://localhost/api/payment-vouchers/x/pdf"), { params: Promise.resolve({ id }) });

describe("GET /api/payment-vouchers/[id]/pdf", () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
  });
  afterEach(() => vi.unstubAllGlobals());

  it("answers 404 for an id that is not a UUID, without calling the backend", async () => {
    const res = await call("../../admin/secrets");

    expect(res.status).toBe(404);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("forwards a valid UUID to the backend", async () => {
    fetchMock.mockResolvedValue(new Response("%PDF", { status: 200, headers: { "content-type": "application/pdf" } }));

    const res = await call("0b9d7a52-3a3e-4d6c-9a43-6f1f6f3c2a10");

    expect(res.status).toBe(200);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});
