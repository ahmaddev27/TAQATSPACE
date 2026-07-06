"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Modal } from "@/components/ui/Modal";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Textarea } from "@/components/ui/Textarea";
import { Select } from "@/components/ui/Select";
import { Checkbox } from "@/components/ui/Checkbox";
import { Badge } from "@/components/ui/Badge";
import {
  createPosProduct,
  updatePosProduct,
  deletePosProduct,
  adjustPosStock,
} from "@/lib/actions/pos";
import {
  POS_LOW_STOCK_THRESHOLD,
  type AdjustStockInput,
  type PosProduct,
  type StockMovementType,
} from "@/lib/types/pos";
import { money } from "./format";

/** Fixed café product categories (stored as these keys; labels are localized). */
const PRODUCT_CATEGORIES = ["cold_drink", "hot_drink", "snacks", "other"] as const;

interface Props {
  products: PosProduct[];
}

type Editor =
  | { mode: "create" }
  | { mode: "edit"; product: PosProduct }
  | { mode: "stock"; product: PosProduct }
  | null;

/** True when a tracked product's inventory is at/below the low-stock threshold. */
function isLowStock(p: PosProduct): boolean {
  return p.track_stock && p.stock_qty <= POS_LOW_STOCK_THRESHOLD;
}

/** Owner POS catalogue + inventory: create/edit products, restock/adjust, delete. */
export function PosManager({ products }: Props) {
  const t = useTranslations("owner.pos");
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [editor, setEditor] = useState<Editor>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  const onDelete = async (product: PosProduct) => {
    const ok = await confirm({
      title: t("deleteTitle"),
      message: t("deleteConfirm", { name: product.name }),
      confirmLabel: t("delete"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;

    setBusyId(product.id);
    startTransition(async () => {
      const res = await deletePosProduct(product.id);
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t("deleted") });
      } else {
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });
  };

  return (
    <section className="card card-pad stack" style={{ gap: 16 }}>
      <div className="between row wrap" style={{ gap: 10 }}>
        <div className="stack" style={{ gap: 2 }}>
          <h3 className="h3" style={{ margin: 0 }}>{t("catalogueHeading")}</h3>
          <p className="muted-3" style={{ margin: 0, fontSize: "var(--fs-sm)" }}>
            {t("catalogueSubtitle")}
          </p>
        </div>
        <Button variant="primary" icon="plus" onClick={() => setEditor({ mode: "create" })}>
          {t("addProduct")}
        </Button>
      </div>

      {products.length === 0 ? (
        <div className="empty-state" style={{ minHeight: 200 }}>
          <div className="st-ico" style={{ width: 48, height: 48 }}>
            <Icon name="coffee" />
          </div>
          <div>
            <div className="h3">{t("emptyTitle")}</div>
            <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>{t("emptyBody")}</div>
          </div>
        </div>
      ) : (
        <div className="stack" style={{ gap: 10 }}>
          {products.map((p) => (
            <div key={p.id} className="between row wrap" style={{ gap: 10 }}>
              <div className="stack" style={{ gap: 4, minWidth: 0 }}>
                <div className="row wrap" style={{ gap: 8, alignItems: "center" }}>
                  <span style={{ fontWeight: 600 }}>{p.name}</span>
                  {p.category && (
                    <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                      {(PRODUCT_CATEGORIES as readonly string[]).includes(p.category)
                        ? t(`categories.${p.category}`)
                        : p.category}
                    </span>
                  )}
                </div>
                <div className="row wrap" style={{ gap: 6, alignItems: "center" }}>
                  <span className="tnum" style={{ fontWeight: 600 }}>{money(p.price)}</span>
                  {p.sku && (
                    <span className="muted-3 ltr" style={{ fontSize: "var(--fs-sm)" }}>
                      {p.sku}
                    </span>
                  )}
                  {p.track_stock ? (
                    <Badge tone={isLowStock(p) ? "danger" : "neutral"} dot={isLowStock(p)}>
                      {t("stockCount", { count: p.stock_qty })}
                    </Badge>
                  ) : (
                    <Badge tone="neutral">{t("untracked")}</Badge>
                  )}
                  {!p.is_active && <Badge tone="neutral">{t("inactive")}</Badge>}
                  {p.is_active && !p.is_sellable && (
                    <Badge tone="danger">{t("outOfStock")}</Badge>
                  )}
                </div>
              </div>
              <div className="row" style={{ gap: 8 }}>
                {p.track_stock && (
                  <Button
                    variant="secondary"
                    size="sm"
                    icon="layers"
                    onClick={() => setEditor({ mode: "stock", product: p })}
                  >
                    {t("adjustStock")}
                  </Button>
                )}
                <Button
                  variant="secondary"
                  size="sm"
                  icon="edit"
                  aria-label={t("edit")}
                  onClick={() => setEditor({ mode: "edit", product: p })}
                />
                <Button
                  variant="ghost"
                  size="sm"
                  icon="trash"
                  aria-label={t("delete")}
                  loading={pending && busyId === p.id}
                  onClick={() => onDelete(p)}
                />
              </div>
            </div>
          ))}
        </div>
      )}

      {(editor?.mode === "create" || editor?.mode === "edit") && (
        <ProductModal
          product={editor.mode === "edit" ? editor.product : null}
          onClose={() => setEditor(null)}
        />
      )}
      {editor?.mode === "stock" && (
        <StockModal product={editor.product} onClose={() => setEditor(null)} />
      )}
    </section>
  );
}

/** Create/edit product form. `product === null` means create. */
function ProductModal({
  product,
  onClose,
}: {
  product: PosProduct | null;
  onClose: () => void;
}) {
  const t = useTranslations("owner.pos");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const [name, setName] = useState(product?.name ?? "");
  const [category, setCategory] = useState(product?.category ?? "");
  const [sku, setSku] = useState(product?.sku ?? "");
  const [price, setPrice] = useState(product ? product.price : "");
  const [trackStock, setTrackStock] = useState(product?.track_stock ?? false);
  const [stockQty, setStockQty] = useState(
    product ? String(product.stock_qty) : "0",
  );
  const [isActive, setIsActive] = useState(product?.is_active ?? true);

  const submit = () =>
    startTransition(async () => {
      const base = {
        name,
        category: category.trim() || null,
        sku: sku.trim() || null,
        price: Number(price),
        track_stock: trackStock,
        is_active: isActive,
      };
      // stock_qty is only meaningful on create; edits use the stock adjust flow.
      const payload = product
        ? base
        : { ...base, stock_qty: trackStock ? Number(stockQty) : 0 };

      const res = product
        ? await updatePosProduct(product.id, payload)
        : await createPosProduct(payload);

      if (res.ok) {
        toast({ tone: "ok", title: product ? t("updated") : t("created") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <Modal
      title={product ? t("editTitle") : t("createTitle")}
      icon="coffee"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="primary" loading={pending} onClick={submit}>{t("save")}</Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <Field label={t("name")} error={errors.name?.[0]}>
          <Input value={name} onChange={(e) => setName(e.target.value)} />
        </Field>
        <div className="row wrap" style={{ gap: 12 }}>
          <Field label={t("category")} optional error={errors.category?.[0]} className="grow">
            <Select value={category} onChange={(e) => setCategory(e.target.value)}>
              <option value="">{t("categories.none")}</option>
              {PRODUCT_CATEGORIES.map((c) => (
                <option key={c} value={c}>{t(`categories.${c}`)}</option>
              ))}
            </Select>
          </Field>
          <Field label={t("sku")} optional error={errors.sku?.[0]} className="grow">
            <Input className="ltr" value={sku} onChange={(e) => setSku(e.target.value)} />
          </Field>
        </div>
        <Field label={t("price")} error={errors.price?.[0]}>
          <Input
            type="number"
            min={0}
            step="0.01"
            className="ltr"
            value={price}
            onChange={(e) => setPrice(e.target.value)}
          />
        </Field>
        <Checkbox checked={trackStock} onChange={(e) => setTrackStock(e.target.checked)}>
          {t("trackStock")}
        </Checkbox>
        {trackStock && !product && (
          <Field label={t("initialStock")} error={errors.stock_qty?.[0]}>
            <Input
              type="number"
              min={0}
              step="1"
              className="ltr"
              value={stockQty}
              onChange={(e) => setStockQty(e.target.value)}
            />
          </Field>
        )}
        <Checkbox checked={isActive} onChange={(e) => setIsActive(e.target.checked)}>
          {t("isActive")}
        </Checkbox>
      </div>
    </Modal>
  );
}

/** Restock / adjust inventory for a single tracked product. */
function StockModal({
  product,
  onClose,
}: {
  product: PosProduct;
  onClose: () => void;
}) {
  const t = useTranslations("owner.pos");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const [type, setType] = useState<StockMovementType>("restock");
  const [qty, setQty] = useState("");
  const [note, setNote] = useState("");

  // Restock ADDS an amount (start empty); adjustment SETS the corrected count
  // (start from the current stock so the owner edits the true total).
  const onTypeChange = (next: StockMovementType) => {
    setType(next);
    setQty(next === "adjustment" ? String(product.stock_qty) : "");
  };

  const submit = () =>
    startTransition(async () => {
      const input: AdjustStockInput = {
        type,
        qty: Number(qty),
        note: note.trim() || null,
      };
      const res = await adjustPosStock(product.id, input);
      if (res.ok) {
        toast({ tone: "ok", title: t("stockUpdated") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <Modal
      title={t("stockTitle", { name: product.name })}
      icon="layers"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="primary" loading={pending} onClick={submit}>{t("apply")}</Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <p className="muted" style={{ margin: 0, fontSize: "var(--fs-sm)" }}>
          {t("currentStock", { count: product.stock_qty })}
        </p>
        <Field label={t("movementType")}>
          <Select
            value={type}
            onChange={(e) => onTypeChange(e.target.value as StockMovementType)}
          >
            <option value="restock">{t("typeRestock")}</option>
            <option value="adjustment">{t("typeAdjustment")}</option>
          </Select>
        </Field>
        <Field
          label={type === "restock" ? t("addAmount") : t("correctedCount")}
          hint={type === "restock" ? t("addAmountHint") : t("correctedCountHint")}
          error={errors.qty?.[0]}
        >
          <Input
            type="number"
            step="1"
            min="0"
            className="ltr"
            value={qty}
            onChange={(e) => setQty(e.target.value)}
          />
        </Field>
        <Field label={t("note")} optional error={errors.note?.[0]}>
          <Textarea rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
        </Field>
      </div>
    </Modal>
  );
}
