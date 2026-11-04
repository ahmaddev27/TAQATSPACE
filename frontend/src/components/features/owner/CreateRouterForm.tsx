"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Icon } from "@/components/ui/Icon";
import { Input } from "@/components/ui/Input";
import { useToast } from "@/components/providers/ToastProvider";
import { createRouter } from "@/lib/actions/owner";

/** Guided empty state: no router yet, so name one to start the setup. */
export function CreateRouterForm() {
  const t = useTranslations("integration.empty");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [name, setName] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const submit = () =>
    startTransition(async () => {
      const res = await createRouter(name);
      if (res.ok) {
        toast({ tone: "ok", title: t("created") });
        return;
      }
      setErrors(res.errors ?? {});
      toast({ tone: "err", title: t("failed"), body: res.message });
    });

  return (
    <div className="empty-state">
      <div className="st-ico" style={{ width: 48, height: 48 }}>
        <Icon name="wifi" />
      </div>
      <div>
        <div style={{ fontWeight: 600, color: "var(--text-2)" }}>{t("title")}</div>
        <div style={{ fontSize: "var(--fs-sm)" }}>{t("body")}</div>
      </div>
      <div className="stack" style={{ gap: 10, width: "100%", maxWidth: 360 }}>
        <Field label={t("nameLabel")} error={errors.name?.[0]}>
          <Input value={name} maxLength={120} onChange={(e) => setName(e.target.value)} />
        </Field>
        <Button variant="primary" icon="plus" loading={pending} disabled={name.trim() === ""} onClick={submit}>
          {t("create")}
        </Button>
      </div>
    </div>
  );
}
