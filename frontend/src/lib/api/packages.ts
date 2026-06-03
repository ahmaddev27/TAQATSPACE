import { serverFetch } from "@/lib/api";
import type { ApiEnvelope, Package } from "@/lib/types";

/** Owner: list internet packages for their workspace. */
export async function listPackages(): Promise<Package[]> {
  const res = await serverFetch<ApiEnvelope<Package[]>>("/workspace/packages");
  return res.data;
}

export interface StorePackageInput {
  name: string;
  speed_mbps: number;
  price: number;
  data_limit_gb?: number | null;
  is_unlimited: boolean;
  is_active?: boolean;
}

/** Owner: create an internet package. */
export async function storePackage(input: StorePackageInput): Promise<Package> {
  const res = await serverFetch<ApiEnvelope<Package>>("/workspace/packages", {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data;
}

export type UpdatePackageInput = Partial<StorePackageInput>;

/** Owner: update an internet package. */
export async function updatePackage(
  packageId: string,
  input: UpdatePackageInput,
): Promise<Package> {
  const res = await serverFetch<ApiEnvelope<Package>>(
    `/workspace/packages/${packageId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data;
}

/** Owner: delete an internet package. */
export async function destroyPackage(packageId: string): Promise<void> {
  await serverFetch<void>(`/workspace/packages/${packageId}`, {
    method: "DELETE",
  });
}

/** Owner: assign a package to a member. */
export async function assignPackage(
  packageId: string,
  memberId: string,
): Promise<Package> {
  const res = await serverFetch<ApiEnvelope<Package>>(
    `/workspace/packages/${packageId}/assign`,
    { method: "PUT", body: JSON.stringify({ member_id: memberId }) },
  );
  return res.data;
}

/** Owner: unassign a package from a member. */
export async function unassignPackage(
  packageId: string,
  memberId: string,
): Promise<Package> {
  const res = await serverFetch<ApiEnvelope<Package>>(
    `/workspace/packages/${packageId}/unassign`,
    { method: "PUT", body: JSON.stringify({ member_id: memberId }) },
  );
  return res.data;
}
