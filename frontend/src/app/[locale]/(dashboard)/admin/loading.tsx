import { DashSkeleton } from "@/components/ui/DashSkeleton";

/** Instant skeleton for the admin dashboard (KPI-heavy landing). */
export default function AdminLoading() {
  return <DashSkeleton stats={4} cards={2} />;
}
