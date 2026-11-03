"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { createPlan, updatePlan } from "@/lib/actions/financePlans";
import {
  DEDICATED_SEAT_METHODS,
  METERED_METHODS,
  PRICING_METHODS,
  type Plan,
  type PricingMethod,
} from "@/lib/types/financePlans";

interface Props {
  /** `null` means create; the code is fixed once created. */
  plan: Plan | null;
  onClose: () => void;
}

/** Create/edit plan form: the fields shown follow the chosen pricing method. */
export function PlanFormModal({ plan, onClose }: Props) {
  const t = useTranslations("finance.plans");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const [method, setMethod] = useState<PricingMethod>(plan?.pricing_method ?? "monthly");
  const [code, setCode] = useState(plan?.code ?? "");
  const [nameAr, setNameAr] = useState(plan?.name_ar ?? "");
  const [nameEn, setNameEn] = useState(plan?.name_en ?? "");
  const [price, setPrice] = useState(plan?.price ?? "");
  const [overage, setOverage] = useState(plan?.overage_price ?? "");
  const [quantity, setQuantity] = useState(plan?.included_quantity ?? "");
  const [validity, setValidity] = useState(plan?.validity_days?.toString() ?? "");
  const [dedicated, setDedicated] = useState(plan?.is_dedicated_seat ?? false);
  const [promoPrice, setPromoPrice] = useState(plan?.promo_price ?? "");
  const [promoUntil, setPromoUntil] = useState(plan?.promo_until?.slice(0, 10) ?? "");

  const metered = METERED_METHODS.includes(method);
  const bundle = method === "bundle";
  const canBeDedicated = DEDICATED_SEAT_METHODS.includes(method);

  const changeMethod = (next: PricingMethod) => {
    setMethod(next);
    if (!DEDICATED_SEAT_METHODS.includes(next)) setDedicated(false);
  };

  const submit = () =>
    startTransition(async () => {
      const base = {
        name_ar: nameAr,
        name_en: nameEn,
        pricing_method: method,
        price,
        overage_price: metered || bundle ? overage || null : null,
        included_quantity: metered || bundle ? quantity || null : null,
        validity_days: bundle && validity ? Number(validity) : null,
        is_dedicated_seat: dedicated,
        promo_price: promoPrice || null,
        promo_until: promoUntil || null,
      };
      const res = plan ? await updatePlan(plan.id, base) : await createPlan({ ...base, code });

      if (res.ok) {
        toast({ tone: "ok", title: plan ? t("updated") : t("created") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  const quantityLabel = method === "per_gb" ? t("fields.includedGb") : t("fields.includedHours");

  return (
    <Modal
      title={plan ? t("editTitle") : t("createTitle")}
      icon="receipt"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="primary" loading={pending} onClick={submit}>{t("save")}</Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <Field label={t("fields.method")} error={errors.pricing_method?.[0]}>
          <Select value={method} onChange={(e) => changeMethod(e.target.value as PricingMethod)}>
            {PRICING_METHODS.map((m) => (
              <option key={m} value={m}>{t(`methods.${m}`)}</option>
            ))}
          </Select>
        </Field>
        <Field label={t("fields.code")} hint={plan ? t("codeLocked") : undefined} error={errors.code?.[0]}>
          <Input className="ltr" value={code} disabled={plan !== null} onChange={(e) => setCode(e.target.value)} />
        </Field>
        <div className="row wrap" style={{ gap: 12 }}>
          <Field label={t("fields.nameAr")} error={errors.name_ar?.[0]} className="grow">
            <Input value={nameAr} onChange={(e) => setNameAr(e.target.value)} />
          </Field>
          <Field label={t("fields.nameEn")} error={errors.name_en?.[0]} className="grow">
            <Input className="ltr" value={nameEn} onChange={(e) => setNameEn(e.target.value)} />
          </Field>
        </div>
        <Field label={t(`priceLabels.${method}`)} error={errors.price?.[0]}>
          <Input type="number" min={0} step="0.01" className="ltr" value={price} onChange={(e) => setPrice(e.target.value)} />
        </Field>
        {(metered || bundle) && (
          <div className="row wrap" style={{ gap: 12 }}>
            <Field label={quantityLabel} error={errors.included_quantity?.[0]} className="grow">
              <Input type="number" min={0} step="0.01" className="ltr" value={quantity} onChange={(e) => setQuantity(e.target.value)} />
            </Field>
            <Field label={t("fields.overagePrice")} error={errors.overage_price?.[0]} className="grow">
              <Input type="number" min={0} step="0.01" className="ltr" value={overage} onChange={(e) => setOverage(e.target.value)} />
            </Field>
          </div>
        )}
        {bundle && (
          <Field label={t("fields.validityDays")} error={errors.validity_days?.[0]}>
            <Input type="number" min={1} step="1" className="ltr" value={validity} onChange={(e) => setValidity(e.target.value)} />
          </Field>
        )}
        <div className="row wrap" style={{ gap: 12 }}>
          <Field label={t("fields.promoPrice")} error={errors.promo_price?.[0]} className="grow">
            <Input type="number" min={0} step="0.01" className="ltr" value={promoPrice} onChange={(e) => setPromoPrice(e.target.value)} />
          </Field>
          <Field label={t("fields.promoUntil")} error={errors.promo_until?.[0]} className="grow">
            <Input type="date" className="ltr" value={promoUntil} onChange={(e) => setPromoUntil(e.target.value)} />
          </Field>
        </div>
        <Field
          label={t("fields.dedicatedSeat")}
          hint={canBeDedicated ? undefined : t("dedicatedNotAllowed")}
          error={errors.is_dedicated_seat?.[0]}
        >
          <Checkbox checked={dedicated} disabled={!canBeDedicated} onChange={(e) => setDedicated(e.target.checked)}>
            {t("dedicatedHint")}
          </Checkbox>
        </Field>
      </div>
    </Modal>
  );
}
