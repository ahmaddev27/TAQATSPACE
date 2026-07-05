"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { Modal } from "@/components/ui/Modal";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Checkbox } from "@/components/ui/Checkbox";
import { Badge } from "@/components/ui/Badge";
import {
  inviteCashier,
  updateCashierPermissions,
  deactivateCashier,
} from "@/lib/actions/owner";
import {
  DEFAULT_CASHIER_PERMISSIONS,
  POS_PERMISSIONS,
  type Cashier,
  type CashierInvitation,
  type PosPermission,
} from "@/lib/types/cashier";

interface Props {
  cashiers: Cashier[];
  invitations: CashierInvitation[];
}

type Editor =
  | { mode: "invite" }
  | { mode: "permissions"; cashier: Cashier }
  | null;

export function CashiersManager({ cashiers, invitations }: Props) {
  const t = useTranslations("owner.cashiers");
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [editor, setEditor] = useState<Editor>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  const onDeactivate = async (cashier: Cashier) => {
    const ok = await confirm({
      title: t("confirmDeactivateTitle"),
      message: t("confirmDeactivateMsg", { name: cashier.name }),
      confirmLabel: t("deactivate"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;

    setBusyId(cashier.id);
    startTransition(async () => {
      const res = await deactivateCashier(cashier.id);
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t("deactivatedSuccess") });
      } else {
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });
  };

  return (
    <div className="stack" style={{ gap: 24 }}>
      <div className="row" style={{ justifyContent: "flex-end" }}>
        <Button variant="primary" icon="plus" onClick={() => setEditor({ mode: "invite" })}>
          {t("invite")}
        </Button>
      </div>

      <section className="card card-pad stack" style={{ gap: 12 }}>
        <h3 className="h3" style={{ margin: 0 }}>{t("cashiersHeading")}</h3>
        {cashiers.length === 0 ? (
          <p className="muted" style={{ margin: 0 }}>{t("noCashiers")}</p>
        ) : (
          cashiers.map((c) => (
            <div key={c.id} className="between row wrap" style={{ gap: 10 }}>
              <div className="stack" style={{ gap: 4 }}>
                <span style={{ fontWeight: 600 }}>{c.name}</span>
                <span className="muted-3 ltr" style={{ fontSize: "var(--fs-sm)" }}>{c.email}</span>
                <div className="row wrap" style={{ gap: 6 }}>
                  <Badge tone={c.status === "active" ? "success" : "neutral"} dot>
                    {t(`status.${c.status}`)}
                  </Badge>
                  {c.permissions.map((p) => (
                    <Badge key={p} tone="info">{t(`perms.${p}`)}</Badge>
                  ))}
                </div>
              </div>
              <div className="row" style={{ gap: 8 }}>
                <Button variant="secondary" size="sm" icon="shield" onClick={() => setEditor({ mode: "permissions", cashier: c })}>
                  {t("editPerms")}
                </Button>
                {c.status === "active" && (
                  <Button
                    variant="ghost"
                    size="sm"
                    icon="trash"
                    loading={pending && busyId === c.id}
                    onClick={() => onDeactivate(c)}
                  >
                    {t("deactivate")}
                  </Button>
                )}
              </div>
            </div>
          ))
        )}
      </section>

      <section className="card card-pad stack" style={{ gap: 12 }}>
        <h3 className="h3" style={{ margin: 0 }}>{t("invitationsHeading")}</h3>
        {invitations.length === 0 ? (
          <p className="muted" style={{ margin: 0 }}>{t("noInvitations")}</p>
        ) : (
          invitations.map((inv) => (
            <div key={inv.id} className="between row wrap" style={{ gap: 10 }}>
              <span className="ltr">{inv.email}</span>
              <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                {inv.expires_at
                  ? t("expiresAt", { date: new Date(inv.expires_at).toLocaleDateString() })
                  : null}
              </span>
            </div>
          ))
        )}
      </section>

      {editor?.mode === "invite" && (
        <InviteModal onClose={() => setEditor(null)} />
      )}
      {editor?.mode === "permissions" && (
        <PermissionsModal cashier={editor.cashier} onClose={() => setEditor(null)} />
      )}
    </div>
  );
}

/** Shared permission-checkbox group. */
function PermissionPicker({
  value,
  onChange,
}: {
  value: PosPermission[];
  onChange: (next: PosPermission[]) => void;
}) {
  const t = useTranslations("owner.cashiers");
  const toggle = (perm: PosPermission) =>
    onChange(value.includes(perm) ? value.filter((p) => p !== perm) : [...value, perm]);

  return (
    <Field label={t("permissions")} hint={t("permsHint")}>
      <div className="stack" style={{ gap: 8 }}>
        {POS_PERMISSIONS.map((perm) => (
          <Checkbox key={perm} checked={value.includes(perm)} onChange={() => toggle(perm)}>
            {t(`perms.${perm}`)}
          </Checkbox>
        ))}
      </div>
    </Field>
  );
}

function InviteModal({ onClose }: { onClose: () => void }) {
  const t = useTranslations("owner.cashiers");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [email, setEmail] = useState("");
  const [name, setName] = useState("");
  const [permissions, setPermissions] = useState<PosPermission[]>(DEFAULT_CASHIER_PERMISSIONS);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const submit = () =>
    startTransition(async () => {
      const res = await inviteCashier({ email, name: name || undefined, permissions });
      if (res.ok) {
        toast({ tone: "ok", title: t("invitedSuccess") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <Modal
      title={t("inviteTitle")}
      icon="mail"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="primary" loading={pending} onClick={submit}>{t("send")}</Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <Field label={t("email")} error={errors.email?.[0]}>
          <Input type="email" className="ltr" value={email} onChange={(e) => setEmail(e.target.value)} />
        </Field>
        <Field label={t("name")} optional error={errors.name?.[0]}>
          <Input value={name} onChange={(e) => setName(e.target.value)} />
        </Field>
        <PermissionPicker value={permissions} onChange={setPermissions} />
      </div>
    </Modal>
  );
}

function PermissionsModal({ cashier, onClose }: { cashier: Cashier; onClose: () => void }) {
  const t = useTranslations("owner.cashiers");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [permissions, setPermissions] = useState<PosPermission[]>(cashier.permissions);

  const submit = () =>
    startTransition(async () => {
      const res = await updateCashierPermissions(cashier.id, permissions);
      if (res.ok) {
        toast({ tone: "ok", title: t("updatedSuccess") });
        onClose();
      } else {
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <Modal
      title={t("editPermsTitle", { name: cashier.name })}
      icon="shield"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="primary" loading={pending} onClick={submit}>{t("save")}</Button>
        </>
      }
    >
      <PermissionPicker value={permissions} onChange={setPermissions} />
    </Modal>
  );
}
