"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useRouter, usePathname } from "@/i18n/navigation";
import { useToast } from "@/components/providers/ToastProvider";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Select } from "@/components/ui/Select";
import { Field } from "@/components/ui/Field";
import { Textarea } from "@/components/ui/Textarea";
import { Badge } from "@/components/ui/Badge";
import { Alert } from "@/components/ui/Alert";
import { placeFreelancerOrder } from "@/lib/actions/freelancer";
import type { PosOrder, PosOrderStatus, PosProduct } from "@/lib/types/pos";
import { formatMoney, formatDate } from "./format";

/** A workspace the freelancer may order from (drawn from active subscriptions). */
export interface OrderWorkspace {
  id: string;
  name: string;
}

interface Props {
  workspaces: OrderWorkspace[];
  activeWorkspaceId: string;
  products: PosProduct[];
  orders: PosOrder[];
}

/** Badge tone per order fulfillment state. */
const ORDER_TONE: Record<
  PosOrderStatus,
  "success" | "warning" | "neutral" | "info" | "danger"
> = {
  new: "neutral",
  preparing: "warning",
  ready: "info",
  completed: "success",
  cancelled: "neutral",
  refunded: "danger",
};

/**
 * Freelancer café ordering: pick a subscribed workspace, build a cart from its
 * menu, and place a PENDING order that is settled at the counter. Also lists the
 * freelancer's recent orders with their payment state.
 */
export function FreelancerOrder({
  workspaces,
  activeWorkspaceId,
  products,
  orders,
}: Props) {
  const t = useTranslations("freelancer.pos");
  const { toast } = useToast();
  const router = useRouter();
  const pathname = usePathname();
  const [pending, startTransition] = useTransition();

  // Cart: product id -> quantity (> 0). Reset whenever the workspace changes.
  const [cart, setCart] = useState<Record<string, number>>({});
  const [note, setNote] = useState("");

  const sellable = useMemo(
    () => products.filter((p) => p.is_sellable),
    [products],
  );

  const lines = useMemo(
    () =>
      Object.entries(cart)
        .map(([id, qty]) => {
          const product = products.find((p) => p.id === id);
          return product ? { product, qty } : null;
        })
        .filter((line): line is { product: PosProduct; qty: number } => line !== null),
    [cart, products],
  );

  const total = useMemo(
    () => lines.reduce((sum, l) => sum + Number(l.product.price) * l.qty, 0),
    [lines],
  );

  const itemCount = lines.reduce((sum, l) => sum + l.qty, 0);

  const switchWorkspace = (id: string) => {
    if (id === activeWorkspaceId) return;
    setCart({});
    setNote("");
    router.push(`${pathname}?workspace_id=${id}`);
  };

  const setQty = (product: PosProduct, qty: number) => {
    setCart((prev) => {
      const next = { ...prev };
      if (qty <= 0) {
        delete next[product.id];
      } else {
        // Respect stock caps for tracked products.
        const cap = product.track_stock ? product.stock_qty : Infinity;
        next[product.id] = Math.min(qty, cap);
      }
      return next;
    });
  };

  const submit = () =>
    startTransition(async () => {
      const res = await placeFreelancerOrder({
        workspace_id: activeWorkspaceId,
        items: lines.map((l) => ({ product_id: l.product.id, qty: l.qty })),
        note: note.trim() || null,
      });
      if (res.ok) {
        toast({ tone: "ok", title: t("placed"), body: t("placedBody") });
        setCart({});
        setNote("");
      } else {
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <div className="stack" style={{ gap: 24 }}>
      {workspaces.length > 1 && (
        <Field label={t("chooseWorkspace")}>
          <Select
            value={activeWorkspaceId}
            onChange={(e) => switchWorkspace(e.target.value)}
          >
            {workspaces.map((w) => (
              <option key={w.id} value={w.id}>
                {w.name}
              </option>
            ))}
          </Select>
        </Field>
      )}

      <Alert tone="info" icon="bulb" title={t("counterTitle")}>
        {t("counterBody")}
      </Alert>

      <div
        style={{
          display: "grid",
          gap: 16,
          gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))",
          alignItems: "start",
        }}
      >
        {/* Menu */}
        <section className="card card-pad stack" style={{ gap: 16 }}>
          <div className="stack" style={{ gap: 2 }}>
            <h3 className="h3" style={{ margin: 0 }}>{t("menuHeading")}</h3>
            <p className="muted-3" style={{ margin: 0, fontSize: "var(--fs-sm)" }}>
              {t("menuSubtitle")}
            </p>
          </div>

          {sellable.length === 0 ? (
            <div className="empty-state" style={{ minHeight: 180 }}>
              <div className="st-ico" style={{ width: 48, height: 48 }}>
                <Icon name="coffee" />
              </div>
              <div>
                <div className="h3">{t("emptyMenuTitle")}</div>
                <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
                  {t("emptyMenuBody")}
                </div>
              </div>
            </div>
          ) : (
            <div className="stack" style={{ gap: 10 }}>
              {sellable.map((p) => {
                const qty = cart[p.id] ?? 0;
                const atCap = p.track_stock && qty >= p.stock_qty;
                return (
                  <div
                    key={p.id}
                    className="between row wrap"
                    style={{ gap: 10, alignItems: "center" }}
                  >
                    <div className="stack" style={{ gap: 4, minWidth: 0 }}>
                      <div className="row wrap" style={{ gap: 8, alignItems: "center" }}>
                        <span style={{ fontWeight: 600 }}>{p.name}</span>
                        {p.category && (
                          <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                            {p.category}
                          </span>
                        )}
                      </div>
                      <div className="row wrap" style={{ gap: 6, alignItems: "center" }}>
                        <span className="tnum" style={{ fontWeight: 600 }}>
                          {formatMoney(p.price)}
                        </span>
                        {p.track_stock && (
                          <Badge tone="neutral">
                            {t("inStock", { count: p.stock_qty })}
                          </Badge>
                        )}
                      </div>
                    </div>
                    {qty === 0 ? (
                      <Button
                        variant="secondary"
                        size="sm"
                        icon="plus"
                        onClick={() => setQty(p, 1)}
                      >
                        {t("add")}
                      </Button>
                    ) : (
                      <div className="row" style={{ gap: 6, alignItems: "center" }}>
                        <Button
                          variant="ghost"
                          size="sm"
                          icon="x"
                          aria-label={t("decrease")}
                          onClick={() => setQty(p, qty - 1)}
                        />
                        <span className="tnum" style={{ minWidth: 20, textAlign: "center", fontWeight: 600 }}>
                          {qty}
                        </span>
                        <Button
                          variant="ghost"
                          size="sm"
                          icon="plus"
                          aria-label={t("increase")}
                          disabled={atCap}
                          onClick={() => setQty(p, qty + 1)}
                        />
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </section>

        {/* Cart */}
        <section className="card card-pad stack" style={{ gap: 16 }}>
          <div className="stack" style={{ gap: 2 }}>
            <h3 className="h3" style={{ margin: 0 }}>{t("cartHeading")}</h3>
            <p className="muted-3" style={{ margin: 0, fontSize: "var(--fs-sm)" }}>
              {t("cartSubtitle")}
            </p>
          </div>

          {lines.length === 0 ? (
            <p className="muted" style={{ margin: 0 }}>{t("cartEmpty")}</p>
          ) : (
            <>
              <div className="stack" style={{ gap: 10 }}>
                {lines.map((l) => (
                  <div key={l.product.id} className="between row" style={{ gap: 10 }}>
                    <span style={{ minWidth: 0 }}>
                      <span className="muted-3 tnum">{l.qty}×</span> {l.product.name}
                    </span>
                    <span className="tnum" style={{ fontWeight: 600 }}>
                      {formatMoney(Number(l.product.price) * l.qty)}
                    </span>
                  </div>
                ))}
              </div>

              <div className="between row" style={{ gap: 10, borderTop: "1px solid var(--border)", paddingTop: 12 }}>
                <span style={{ fontWeight: 700 }}>{t("total")}</span>
                <span className="tnum" style={{ fontWeight: 700 }}>{formatMoney(total)}</span>
              </div>

              <Field label={t("note")} optional>
                <Textarea
                  rows={2}
                  value={note}
                  placeholder={t("notePlaceholder")}
                  onChange={(e) => setNote(e.target.value)}
                />
              </Field>

              <Button
                variant="primary"
                block
                icon="check"
                loading={pending}
                onClick={submit}
              >
                {t("placeOrder", { count: itemCount })}
              </Button>
            </>
          )}
        </section>
      </div>

      {/* Order history */}
      <section className="card card-pad stack" style={{ gap: 12 }}>
        <div className="stack" style={{ gap: 2 }}>
          <h3 className="h3" style={{ margin: 0 }}>{t("historyHeading")}</h3>
          <p className="muted-3" style={{ margin: 0, fontSize: "var(--fs-sm)" }}>
            {t("historySubtitle")}
          </p>
        </div>

        {orders.length === 0 ? (
          <p className="muted" style={{ margin: 0 }}>{t("historyEmpty")}</p>
        ) : (
          <div className="stack" style={{ gap: 10 }}>
            {orders.map((o) => (
              <div key={o.id} className="between row wrap" style={{ gap: 10 }}>
                <div className="stack" style={{ gap: 4 }}>
                  <div className="row wrap" style={{ gap: 8, alignItems: "center" }}>
                    <span className="ltr" style={{ fontWeight: 600 }}>{o.order_number}</span>
                    <Badge tone={ORDER_TONE[o.status]} dot>
                      {t(`orderStatus.${o.status}`)}
                    </Badge>
                  </div>
                  <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                    {formatDate(o.paid_at ?? o.created_at)}
                  </span>
                </div>
                <span className="tnum" style={{ fontWeight: 600 }}>{formatMoney(o.total)}</span>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
