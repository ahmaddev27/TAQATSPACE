import { serverFetch } from "@/lib/api";
import type { ApiEnvelope } from "@/lib/types";
import type { PosOrder, PosProduct } from "@/lib/types/pos";

/**
 * Server-only readers for the freelancer café-ordering surface. A freelancer may
 * browse the menu of, and place orders against, any workspace they hold an ACTIVE
 * subscription to. Imports `serverFetch` (next/headers), so Server Components only.
 */

/** The active café menu of a workspace the freelancer is subscribed to. */
export async function freelancerPosProducts(
  workspaceId: string,
): Promise<PosProduct[]> {
  const res = await serverFetch<ApiEnvelope<{ products: PosProduct[] }>>(
    `/freelancer/pos/products?workspace_id=${encodeURIComponent(workspaceId)}`,
  );
  return res.data.products;
}

/** The freelancer's own recent café orders (most recent first). */
export async function freelancerPosOrders(): Promise<PosOrder[]> {
  const res = await serverFetch<ApiEnvelope<{ orders: PosOrder[] }>>(
    "/freelancer/pos/orders",
  );
  return res.data.orders;
}
