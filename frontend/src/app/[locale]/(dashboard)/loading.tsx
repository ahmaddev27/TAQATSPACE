import { DashSkeleton } from "@/components/ui/DashSkeleton";

/**
 * Suspense fallback for any dashboard route without a more specific
 * `loading.tsx`. Renders inside the persistent shell (sidebar + topbar stay
 * mounted), so navigation shows an instant content skeleton instead of a
 * frozen screen.
 */
export default function DashboardLoading() {
  return <DashSkeleton stats={3} cards={2} />;
}
