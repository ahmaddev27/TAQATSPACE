"use client";

import { useState, useTransition } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { Select } from "@/components/ui/Select";
import { updateSubscriptionTerms } from "@/lib/actions/financeSubscriptions";
import type { OwnerSubscription } from "@/lib/api/ownerSubscriptions";
import type { BillingMode, Plan } from "@/lib/types/financePlans";

interface Props {
  subscription: OwnerSubscription;
  /** Active plans the owner can pick from. */
  plans: Plan[];
  onClose: () => void;
}

/** Owner form for a subscription's plan, billing mode, custom price and hours. */
export function SubscriptionTermsModal({ subscription, plans, onClose }: Props) {
  const t = useTranslations("finance.subscriptionTerms");
  const locale = useLocale();
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const [planId, setPlanId] = useState(subscription.plan_id ?? "");
  const [mode, setMode] = useState<BillingMode>(subscription.billing_mode);
  const [customPrice, setCustomPrice] = useState(subscription.custom_price ?? "");
  const [hours, setHours] = useState(subscription.contracted_hours ?? "");
  const [graceDays, setGraceDays] = useState(String(subscription.grace_days));
  const [autoRenew, setAutoRenew] = useState(subscription.auto_renew);

  const hourly = plans.find((p) => p.id === planId)?.pricing_method === "hourly";

  const submit = () =>
    startTransition(async () => {
      const res = await updateSubscriptionTerms(subscription.id, {
        plan_id: planId || null,
        billing_mode: mode,
        custom_price: customPrice || null,
        contracted_hours: hourly && hours ? hours : null,
        grace_days: Number(graceDays) || 0,
        auto_renew: autoRenew,
      });

      if (res.ok) {
        toast({ tone: "ok", title: t("saved") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <Modal
      title={t("title")}
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
        <Field label={t("fields.plan")} error={errors.plan_id?.[0]}>
          <Select value={planId} onChange={(e) => setPlanId(e.target.value)}>
            <option value="">{t("noPlan")}</option>
            {plans.map((p) => (
              <option key={p.id} value={p.id}>
                {locale === "ar" ? p.name_ar : p.name_en} ({p.code})
              </option>
            ))}
          </Select>
        </Field>
        <Field label={t("fields.billingMode")} error={errors.billing_mode?.[0]}>
          <Select value={mode} onChange={(e) => setMode(e.target.value as BillingMode)}>
            <option value="postpaid">{t("modes.postpaid")}</option>
            <option value="prepaid">{t("modes.prepaid")}</option>
          </Select>
        </Field>
        <Field label={t("fields.customPrice")} hint={t("fields.customPriceHint")} error={errors.custom_price?.[0]}>
          <Input type="number" min={0} step="0.01" className="ltr" value={customPrice} onChange={(e) => setCustomPrice(e.target.value)} />
        </Field>
        {hourly && (
          <Field label={t("fields.contractedHours")} hint={t("fields.contractedHoursHint")} error={errors.contracted_hours?.[0]}>
            <Input type="number" min={0} step="0.01" className="ltr" value={hours} onChange={(e) => setHours(e.target.value)} />
          </Field>
        )}
        <Field label={t("fields.graceDays")} error={errors.grace_days?.[0]}>
          <Input type="number" min={0} max={365} step="1" className="ltr" value={graceDays} onChange={(e) => setGraceDays(e.target.value)} />
        </Field>
        <Checkbox checked={autoRenew} onChange={(e) => setAutoRenew(e.target.checked)}>
          {t("fields.autoRenew")}
        </Checkbox>
      </div>
    </Modal>
  );
}
