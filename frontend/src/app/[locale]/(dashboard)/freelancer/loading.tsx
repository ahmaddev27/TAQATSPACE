import { DashSkeleton } from "@/components/ui/DashSkeleton";

/** Instant skeleton for the freelancer dashboard. */
export default function FreelancerLoading() {
  return <DashSkeleton stats={3} cards={2} />;
}
