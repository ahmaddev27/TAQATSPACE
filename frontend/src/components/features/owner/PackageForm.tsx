"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import { useToast } from "@/components/providers/ToastProvider";
import { createPackage, updatePackage, type PackageInput } from "@/lib/actions/owner";
import type { Package } from "@/lib/types";

export interface PackageFormProps {
  /** When provided, the form edits this package; otherwise it creates one. */
  pkg: Package | null;
  onClose: () => void;
}

interface FormState {
  name: string;
  speed: string;
  price: string;
  dataLimit: string;
  unlimited: boolean;
  active: boolean;
}

function initialState(pkg: Package | null): FormState {
  return {
    name: pkg?.name ?? "",
    speed: pkg ? String(pkg.speed_mbps) : "",
    price: pkg ? String(pkg.price) : "0",
    dataLimit: pkg?.data_limit_gb != null ? String(pkg.data_limit_gb) : "",
    unlimited: pkg?.is_unlimited ?? true,
    active: pkg?.is_active ?? true,
  };
}

/** Create/edit modal for an internet package. */
export function PackageForm({ pkg, onClose }: PackageFormProps) {
  const t = useTranslations("owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const [form, setForm] = useState<FormState>(() => initialState(pkg));
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const isEdit = pkg !== null;

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  const submit = () => {
    const payload: PackageInput = {
      name: form.name.trim(),
      speed_mbps: Number(form.speed) || 0,
      price: Number(form.price) || 0,
      is_unlimited: form.unlimited,
      data_limit_gb: form.unlimited ? null : Number(form.dataLimit) || 0,
      is_active: form.active,
    };

    startTransition(async () => {
      const res = isEdit
        ? await updatePackage(pkg.id, payload)
        : await createPackage(payload);

      if (res.ok) {
        toast({
          tone: "ok",
          title: isEdit ? t("packages.updated") : t("packages.created"),
        });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("packages.saveFailed"), body: res.message });
      }
    });
  };

  return (
    <Modal
      title={isEdit ? t("packages.editTitle") : t("packages.newTitle")}
      icon="wifi"
      onClose={onClose}
      closeLabel={t("common.close")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            {t("packages.cancel")}
          </Button>
          <Button variant="primary" icon="check" loading={pending} onClick={submit}>
            {t("packages.save")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 16 }}>
        <Field label={t("packages.fName")} error={errors.name?.[0]}>
          <Input
            value={form.name}
            onChange={(e) => set("name", e.target.value)}
          />
        </Field>

        <div className="grid2">
          <Field label={t("packages.fSpeed")} error={errors.speed_mbps?.[0]}>
            <Input
              className="tnum"
              inputMode="numeric"
              value={form.speed}
              onChange={(e) => set("speed", e.target.value)}
            />
          </Field>
          <Field label={t("packages.fPrice")} error={errors.price?.[0]}>
            <Input
              className="tnum"
              inputMode="decimal"
              value={form.price}
              onChange={(e) => set("price", e.target.value)}
            />
          </Field>
        </div>

        <Checkbox
          checked={form.unlimited}
          onChange={(e) => set("unlimited", e.target.checked)}
        >
          {t("packages.fUnlimited")}
        </Checkbox>

        {!form.unlimited && (
          <Field label={t("packages.fDataLimit")} error={errors.data_limit_gb?.[0]}>
            <Input
              className="tnum"
              inputMode="numeric"
              value={form.dataLimit}
              onChange={(e) => set("dataLimit", e.target.value)}
            />
          </Field>
        )}

        <Checkbox
          checked={form.active}
          onChange={(e) => set("active", e.target.checked)}
        >
          {t("packages.fActive")}
        </Checkbox>
      </div>
    </Modal>
  );
}
