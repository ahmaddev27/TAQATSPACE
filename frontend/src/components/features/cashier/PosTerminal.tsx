"use client";

import { useCallback, useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { Badge } from "@/components/ui/Badge";
import { StatTile } from "@/components/ui/StatTile";
import { Segmented } from "@/components/ui/Segmented";
import { useAuth } from "@/components/providers/AuthProvider";
import {
  createPosOrder,
  payPosOrder,
  cancelPosOrder,
  updateOrderStatus,
  refundOrder,
} from "@/lib/actions/pos";
import {
  POS_LOW_STOCK_THRESHOLD,
  isPosOrderOpen,
  type PosAdvanceStatus,
  type PosOrder,
  type PosOrderStatus,
  type PosPaymentMethod,
  type PosProduct,
  type PosSummary,
} from "@/lib/types/pos";
import { money } from "@/components/features/owner/format";

interface Props {
  products: PosProduct[];
  /** Open orders (new/preparing/ready) shown in the settle/fulfill queue. */
  openOrders: PosOrder[];
  /**
   * At-a-glance figures. Null when the actor lacks `pos_view_reports` (the
   * summary endpoint is gated) — the terminal still sells; it just hides the
   * stat tiles instead of failing to load.
   */
  summary: PosSummary | null;
}

/** A product plus the quantity currently in the cart. */
interface CartLine {
  product: PosProduct;
  qty: number;
}

const PAYMENT_METHODS: PosPaymentMethod[] = ["cash", "transfer"];

/** Next fulfillment status an open order advances to (null when terminal). */
const NEXT_STATUS: Partial<Record<PosOrderStatus, PosAdvanceStatus>> = {
  new: "preparing",
  preparing: "ready",
  ready: "completed",
};

/** i18n key (under cashier.terminal.queue) for the "advance to X" action. */
const ADVANCE_LABEL: Record<PosAdvanceStatus, string> = {
  preparing: "markPreparing",
  ready: "markReady",
  completed: "markCompleted",
};

/** Badge tone per fulfillment status. */
const STATUS_TONE: Record<
  PosOrderStatus,
  "neutral" | "info" | "warning" | "success" | "danger"
> = {
  new: "neutral",
  preparing: "warning",
  ready: "info",
  completed: "success",
  cancelled: "neutral",
  refunded: "danger",
};

/** True when a tracked product sits at/below the low-stock threshold. */
function isLowStock(p: PosProduct): boolean {
  return p.track_stock && p.stock_qty <= POS_LOW_STOCK_THRESHOLD;
}

/**
 * Cashier point-of-sale terminal: a tap-to-add product grid, an editable cart
 * that charges (create → pay) in one flow, and a queue of open orders (freelancer
 * tabs / unpaid tickets) each settleable or voidable inline. Cart state lives here
 * and flows down; every mutation runs through a Server Action that revalidates
 * the terminal, so summary + queue refresh on the next render.
 */
export function PosTerminal({ products, openOrders, summary }: Props) {
  const t = useTranslations("cashier.terminal");
  const { user } = useAuth();
  const [lines, setLines] = useState<CartLine[]>([]);

  // Refunds require `pos_refund`. Owners (any non-cashier actor here) always
  // pass; a cashier needs the grant. If the permission list is absent, show the
  // control and let the backend's 403 surface as a toast.
  const canRefund =
    user?.role !== "cashier" ||
    (user.permissions?.includes("pos_refund") ?? true);

  const addProduct = useCallback((product: PosProduct) => {
    setLines((prev) => {
      const existing = prev.find((l) => l.product.id === product.id);
      if (existing) {
        return prev.map((l) =>
          l.product.id === product.id ? { ...l, qty: l.qty + 1 } : l,
        );
      }
      return [...prev, { product, qty: 1 }];
    });
  }, []);

  return (
    <div className="stack" style={{ gap: 24 }}>
      {summary && (
        <div className="grid-stats">
          <StatTile
            icon="wallet"
            label={t("summary.todaySales")}
            value={money(summary.today_sales)}
          />
          <StatTile
            icon="receipt"
            label={t("summary.todayOrders")}
            value={summary.today_orders}
          />
          <StatTile
            icon="inbox"
            amber={summary.pending_orders > 0}
            label={t("summary.pendingOrders")}
            value={summary.pending_orders}
          />
          <StatTile
            icon="layers"
            amber={summary.low_stock > 0}
            label={t("summary.lowStock")}
            value={summary.low_stock}
          />
        </div>
      )}

      <div
        className="pos-grid"
        style={{
          display: "grid",
          gridTemplateColumns: "minmax(0, 1.6fr) minmax(280px, 1fr)",
          gap: 20,
          alignItems: "start",
        }}
      >
        <Catalogue products={products} onAdd={addProduct} />
        <Cart lines={lines} setLines={setLines} />
      </div>

      <Queue orders={openOrders} canRefund={canRefund} />
    </div>
  );
}

/* ------------------------------- Catalogue ---------------------------------- */

function Catalogue({
  products,
  onAdd,
}: {
  products: PosProduct[];
  onAdd: (product: PosProduct) => void;
}) {
  const t = useTranslations("cashier.terminal.catalogue");
  const [query, setQuery] = useState("");

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return products;
    return products.filter(
      (p) =>
        p.name.toLowerCase().includes(q) ||
        (p.category ?? "").toLowerCase().includes(q) ||
        (p.sku ?? "").toLowerCase().includes(q),
    );
  }, [products, query]);

  return (
    <section className="card card-pad stack" style={{ gap: 16 }}>
      <div className="between row wrap" style={{ gap: 10 }}>
        <h3 className="h3" style={{ margin: 0 }}>{t("heading")}</h3>
        <Input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={t("searchPlaceholder")}
          style={{ maxWidth: 220 }}
        />
      </div>

      {products.length === 0 ? (
        <p className="muted" style={{ margin: 0 }}>{t("empty")}</p>
      ) : filtered.length === 0 ? (
        <p className="muted" style={{ margin: 0 }}>{t("noResults")}</p>
      ) : (
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fill, minmax(140px, 1fr))",
            gap: 12,
          }}
        >
          {filtered.map((p) => (
            <ProductButton key={p.id} product={p} onAdd={onAdd} />
          ))}
        </div>
      )}
    </section>
  );
}

function ProductButton({
  product,
  onAdd,
}: {
  product: PosProduct;
  onAdd: (product: PosProduct) => void;
}) {
  const t = useTranslations("cashier.terminal.catalogue");
  const disabled = !product.is_sellable;

  return (
    <button
      type="button"
      className="card"
      disabled={disabled}
      onClick={() => onAdd(product)}
      style={{
        padding: 14,
        textAlign: "start",
        display: "flex",
        flexDirection: "column",
        gap: 8,
        cursor: disabled ? "not-allowed" : "pointer",
        opacity: disabled ? 0.55 : 1,
      }}
    >
      <span style={{ fontWeight: 600, lineHeight: 1.3 }}>{product.name}</span>
      <span className="tnum" style={{ fontWeight: 700 }}>{money(product.price)}</span>
      {disabled ? (
        <Badge tone="danger">{t("outOfStock")}</Badge>
      ) : product.track_stock ? (
        <Badge tone={isLowStock(product) ? "danger" : "neutral"} dot={isLowStock(product)}>
          {t("stockLeft", { count: product.stock_qty })}
        </Badge>
      ) : null}
    </button>
  );
}

/* ---------------------------------- Cart ------------------------------------ */

function Cart({
  lines,
  setLines,
}: {
  lines: CartLine[];
  setLines: React.Dispatch<React.SetStateAction<CartLine[]>>;
}) {
  const t = useTranslations("cashier.terminal.cart");
  const tToast = useTranslations("cashier.terminal.toast");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [customer, setCustomer] = useState("");
  const [discount, setDiscount] = useState("");
  const [method, setMethod] = useState<PosPaymentMethod>("cash");

  const setQty = (id: string, delta: number) =>
    setLines((prev) =>
      prev
        .map((l) => (l.product.id === id ? { ...l, qty: l.qty + delta } : l))
        .filter((l) => l.qty > 0),
    );

  const removeLine = (id: string) =>
    setLines((prev) => prev.filter((l) => l.product.id !== id));

  const reset = () => {
    setLines([]);
    setCustomer("");
    setDiscount("");
    setMethod("cash");
  };

  const subtotal = useMemo(
    () => lines.reduce((sum, l) => sum + Number(l.product.price) * l.qty, 0),
    [lines],
  );
  const discountValue = Math.max(0, Number(discount) || 0);
  const total = Math.max(0, subtotal - discountValue);

  const charge = () => {
    if (lines.length === 0) {
      toast({ tone: "info", title: tToast("emptyCart") });
      return;
    }
    startTransition(async () => {
      const created = await createPosOrder({
        items: lines.map((l) => ({ product_id: l.product.id, qty: l.qty })),
        discount: discountValue || undefined,
        customer_name: customer.trim() || null,
      });
      if (!created.ok) {
        toast({ tone: "err", title: tToast("error"), body: created.message });
        return;
      }
      const paid = await payPosOrder(created.data.id, { method });
      if (paid.ok) {
        toast({ tone: "ok", title: tToast("paid") });
        reset();
      } else {
        toast({ tone: "err", title: tToast("error"), body: paid.message });
      }
    });
  };

  const clearDisabled = lines.length === 0 && !customer && !discount;

  return (
    <section
      className="card card-pad stack"
      style={{ gap: 14, position: "sticky", top: 16 }}
    >
      <h3 className="h3" style={{ margin: 0 }}>{t("heading")}</h3>

      {lines.length === 0 ? (
        <p className="muted" style={{ margin: 0 }}>{t("empty")}</p>
      ) : (
        <div className="stack" style={{ gap: 10 }}>
          {lines.map((l) => (
            <div key={l.product.id} className="between row" style={{ gap: 8 }}>
              <div className="stack" style={{ gap: 2, minWidth: 0 }}>
                <span style={{ fontWeight: 600 }}>{l.product.name}</span>
                <span className="muted-3 tnum" style={{ fontSize: "var(--fs-sm)" }}>
                  {money(Number(l.product.price) * l.qty)}
                </span>
              </div>
              <div className="row" style={{ gap: 6, alignItems: "center" }}>
                <Button
                  variant="secondary"
                  size="sm"
                  aria-label={t("decrease")}
                  onClick={() => setQty(l.product.id, -1)}
                >
                  −
                </Button>
                <span className="tnum" style={{ minWidth: 20, textAlign: "center" }}>
                  {l.qty}
                </span>
                <Button
                  variant="secondary"
                  size="sm"
                  aria-label={t("increase")}
                  onClick={() => setQty(l.product.id, 1)}
                >
                  +
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  icon="x"
                  aria-label={t("remove")}
                  onClick={() => removeLine(l.product.id)}
                />
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="stack" style={{ gap: 10 }}>
        <Input
          value={customer}
          onChange={(e) => setCustomer(e.target.value)}
          placeholder={t("customerPlaceholder")}
          aria-label={t("customerName")}
        />
        <div className="between row" style={{ gap: 8, alignItems: "center" }}>
          <span className="muted">{t("discount")}</span>
          <Input
            type="number"
            min={0}
            step="0.01"
            className="ltr"
            value={discount}
            onChange={(e) => setDiscount(e.target.value)}
            style={{ maxWidth: 120 }}
          />
        </div>
      </div>

      <div className="stack" style={{ gap: 6 }}>
        <div className="between row">
          <span className="muted">{t("subtotal")}</span>
          <span className="tnum">{money(subtotal)}</span>
        </div>
        {discountValue > 0 && (
          <div className="between row">
            <span className="muted">{t("discountLabel")}</span>
            <span className="tnum">-{money(discountValue)}</span>
          </div>
        )}
        <div className="between row">
          <span style={{ fontWeight: 700 }}>{t("total")}</span>
          <span className="tnum" style={{ fontWeight: 700 }}>{money(total)}</span>
        </div>
      </div>

      <div className="stack" style={{ gap: 6 }}>
        <span className="muted">{t("method")}</span>
        <Segmented
          value={method}
          onChange={(id) => setMethod(id as PosPaymentMethod)}
          items={PAYMENT_METHODS.map((m) => ({ id: m, label: t(m) }))}
        />
      </div>

      <div className="row" style={{ gap: 8 }}>
        <Button variant="ghost" onClick={reset} disabled={clearDisabled}>
          {t("clear")}
        </Button>
        <Button
          variant="primary"
          icon="wallet"
          block
          loading={pending}
          disabled={lines.length === 0}
          onClick={charge}
        >
          {t("charge", { total: money(total) })}
        </Button>
      </div>
    </section>
  );
}

/* ---------------------------------- Queue ----------------------------------- */

function Queue({
  orders,
  canRefund,
}: {
  orders: PosOrder[];
  canRefund: boolean;
}) {
  const t = useTranslations("cashier.terminal.queue");

  return (
    <section className="card card-pad stack" style={{ gap: 12 }}>
      <div className="stack" style={{ gap: 2 }}>
        <h3 className="h3" style={{ margin: 0 }}>{t("heading")}</h3>
        <p className="muted-3" style={{ margin: 0, fontSize: "var(--fs-sm)" }}>
          {t("subtitle")}
        </p>
      </div>

      {orders.length === 0 ? (
        <p className="muted" style={{ margin: 0 }}>{t("empty")}</p>
      ) : (
        <div className="stack" style={{ gap: 16 }}>
          {orders.map((o) => (
            <QueueRow key={o.id} order={o} canRefund={canRefund} />
          ))}
        </div>
      )}
    </section>
  );
}

function QueueRow({
  order,
  canRefund,
}: {
  order: PosOrder;
  canRefund: boolean;
}) {
  const t = useTranslations("cashier.terminal.queue");
  const tStatus = useTranslations("cashier.terminal.status");
  const tCart = useTranslations("cashier.terminal.cart");
  const tToast = useTranslations("cashier.terminal.toast");
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [method, setMethod] = useState<PosPaymentMethod>("cash");

  const itemCount = order.items?.reduce((sum, i) => sum + i.qty, 0) ?? 0;
  const isPaid = order.paid_at != null;
  const isRefunded = order.status === "refunded" || order.refunded_at != null;
  const isOpen = isPosOrderOpen(order.status);
  const nextStatus = NEXT_STATUS[order.status];

  const pay = () =>
    startTransition(async () => {
      const res = await payPosOrder(order.id, { method });
      if (res.ok) {
        toast({ tone: "ok", title: tToast("paid") });
      } else {
        toast({ tone: "err", title: tToast("error"), body: res.message });
      }
    });

  const advance = () => {
    if (!nextStatus) return;
    startTransition(async () => {
      const res = await updateOrderStatus(order.id, nextStatus);
      if (res.ok) {
        toast({ tone: "ok", title: tToast(`status_${nextStatus}`) });
      } else {
        toast({ tone: "err", title: tToast("error"), body: res.message });
      }
    });
  };

  const refund = async () => {
    const ok = await confirm({
      title: t("refundTitle"),
      message: t("refundConfirm", { number: order.order_number }),
      confirmLabel: t("refund"),
      tone: "danger",
      icon: "refresh",
    });
    if (!ok) return;
    startTransition(async () => {
      const res = await refundOrder(order.id);
      if (res.ok) {
        toast({ tone: "ok", title: tToast("refunded") });
      } else {
        toast({ tone: "err", title: tToast("error"), body: res.message });
      }
    });
  };

  const cancel = async () => {
    const ok = await confirm({
      title: t("cancelTitle"),
      message: t("cancelConfirm", { number: order.order_number }),
      confirmLabel: t("cancel"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;
    startTransition(async () => {
      const res = await cancelPosOrder(order.id);
      if (res.ok) {
        toast({ tone: "ok", title: tToast("cancelled") });
      } else {
        toast({ tone: "err", title: tToast("error"), body: res.message });
      }
    });
  };

  return (
    <div className="stack" style={{ gap: 10 }}>
      <div className="between row wrap" style={{ gap: 12 }}>
        <div className="stack" style={{ gap: 4, minWidth: 0 }}>
          <div className="row wrap" style={{ gap: 8, alignItems: "center" }}>
            <span className="ltr" style={{ fontWeight: 600 }}>{order.order_number}</span>
            <Badge tone={order.source === "freelancer" ? "info" : "neutral"}>
              {order.customer_name ?? order.member?.name ?? t("walkIn")}
            </Badge>
            <Badge tone={STATUS_TONE[order.status]} dot>
              {tStatus(order.status)}
            </Badge>
            <Badge tone={isPaid ? "success" : "warning"} dot>
              {isPaid ? tStatus("paid") : tStatus("unpaid")}
            </Badge>
          </div>
          <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
            {t("items", { count: itemCount })} ·{" "}
            <span className="tnum">{money(order.total)}</span>
          </span>
          {order.note && (
            <span
              className="muted"
              style={{ fontSize: "var(--fs-sm)", fontStyle: "italic" }}
            >
              “{order.note}”
            </span>
          )}
        </div>
      </div>

      <div className="row wrap" style={{ gap: 8, alignItems: "center" }}>
        {/* Fulfillment: advance the order along new → preparing → ready → completed. */}
        {isOpen && nextStatus && (
          <Button
            variant="secondary"
            size="sm"
            icon={nextStatus === "completed" ? "checkCircle" : "clock"}
            loading={pending}
            onClick={advance}
          >
            {t(ADVANCE_LABEL[nextStatus])}
          </Button>
        )}

        {/* Payment: decoupled from fulfillment — settle any time while unpaid. */}
        {!isPaid && (
          <>
            <Segmented
              value={method}
              onChange={(id) => setMethod(id as PosPaymentMethod)}
              items={PAYMENT_METHODS.map((m) => ({ id: m, label: tCart(m) }))}
            />
            <Button
              variant="primary"
              size="sm"
              icon="wallet"
              loading={pending}
              onClick={pay}
            >
              {t("pay")}
            </Button>
          </>
        )}

        {/* Refund a settled order (gated by pos_refund; owners always pass). */}
        {isPaid && !isRefunded && canRefund && (
          <Button
            variant="danger"
            size="sm"
            icon="refresh"
            loading={pending}
            onClick={refund}
          >
            {t("refund")}
          </Button>
        )}

        {/* Cancel only while unpaid (no stock consumed yet). */}
        {!isPaid && (
          <Button
            variant="ghost"
            size="sm"
            icon="x"
            aria-label={t("cancel")}
            onClick={cancel}
          />
        )}
      </div>
    </div>
  );
}
