import { setRequestLocale, getTranslations } from "next-intl/server";
import Link from "next/link";
import { Icon } from "@/components/ui/Icon";
import {
  FreelancerOrder,
  type OrderWorkspace,
} from "@/components/features/freelancer/FreelancerOrder";
import { listSubscriptions } from "@/lib/api/subscriptions";
import {
  freelancerPosProducts,
  freelancerPosOrders,
} from "@/lib/api/freelancer-pos";

export const dynamic = "force-dynamic";

interface PageProps {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ workspace_id?: string }>;
}

/**
 * Freelancer café ordering. Lists the menu of a workspace the freelancer is
 * actively subscribed to and lets them place a PENDING order settled at the
 * counter. The workspace is chosen via `?workspace_id=`, defaulting to the first
 * active subscription.
 */
export default async function FreelancerPosPage({
  params,
  searchParams,
}: PageProps) {
  const { locale } = await params;
  setRequestLocale(locale);
  const [{ workspace_id }, t] = await Promise.all([
    searchParams,
    getTranslations("freelancer.pos"),
  ]);

  const subscriptions = await listSubscriptions();

  // A freelancer may only order from workspaces they hold an ACTIVE subscription
  // to. De-duplicate by workspace (multiple subscriptions could share one).
  const workspaces: OrderWorkspace[] = [];
  const seen = new Set<string>();
  for (const sub of subscriptions) {
    if (sub.status !== "active" || seen.has(sub.workspace_id)) continue;
    seen.add(sub.workspace_id);
    workspaces.push({
      id: sub.workspace_id,
      name: sub.workspace?.name ?? sub.workspace_id,
    });
  }

  const header = (
    <div className="page-head">
      <h1 className="h1">{t("title")}</h1>
      <p className="muted">{t("subtitle")}</p>
    </div>
  );

  if (workspaces.length === 0) {
    return (
      <div className="page">
        {header}
        <section className="card card-pad">
          <div className="empty-state" style={{ minHeight: 240 }}>
            <div className="st-ico" style={{ width: 48, height: 48 }}>
              <Icon name="coffee" />
            </div>
            <div>
              <div className="h3">{t("noSubTitle")}</div>
              <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
                {t("noSubBody")}
              </div>
            </div>
            <Link href={`/${locale}/explore`} className="btn btn-primary">
              <Icon name="search" />
              {t("exploreCta")}
            </Link>
          </div>
        </section>
      </div>
    );
  }

  // Honour the requested workspace only when it is one the freelancer may order
  // from; otherwise fall back to the first active subscription.
  const activeWorkspaceId =
    workspace_id && workspaces.some((w) => w.id === workspace_id)
      ? workspace_id
      : workspaces[0].id;

  const [products, orders] = await Promise.all([
    freelancerPosProducts(activeWorkspaceId),
    freelancerPosOrders(),
  ]);

  return (
    <div className="page">
      {header}
      <FreelancerOrder
        workspaces={workspaces}
        activeWorkspaceId={activeWorkspaceId}
        products={products}
        orders={orders}
      />
    </div>
  );
}
