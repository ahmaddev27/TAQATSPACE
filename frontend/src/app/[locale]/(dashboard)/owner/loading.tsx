import { DashSkeleton } from "@/components/ui/DashSkeleton";

/** Instant skeleton for the owner dashboard (KPI-heavy landing). */
export default function OwnerLoading() {
  return <DashSkeleton stats={5} cards={2} />;
}
