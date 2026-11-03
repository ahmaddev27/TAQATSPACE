"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { useToast } from "@/components/providers/ToastProvider";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { Modal } from "@/components/ui/Modal";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Checkbox } from "@/components/ui/Checkbox";
import { Badge } from "@/components/ui/Badge";
import {
  inviteStaff,
  updateStaffPermissions,
  deactivateStaff,
  deleteStaffAccount,
  resendStaffInvitation,
  deleteStaffInvitation,
} from "@/lib/actions/owner";
import {
  DEFAULT_STAFF_GROUP,
  POS_PERMISSIONS,
  STAFF_GROUPS,
  STAFF_GROUP_PERMISSIONS,
  type PosPermission,
  type Staff,
  type StaffGroup,
  type StaffInvitation,
} from "@/lib/types/staff";

interface Props {
  staff: Staff[];
  invitations: StaffInvitation[];
}

type Editor =
  | { mode: "invite" }
  | { mode: "permissions"; member: Staff }
  | null;

export function StaffManager({ staff, invitations }: Props) {
  const t = useTranslations("owner.staff");
  const { toast } = useToast();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [editor, setEditor] = useState<Editor>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  /** Run a server action for one row, toasting the outcome. */
  const run = (
    id: string,
    action: () => Promise<{ ok: boolean; message?: string }>,
    successKey: string,
  ) => {
    setBusyId(id);
    startTransition(async () => {
      const res = await action();
      setBusyId(null);
      if (res.ok) {
        toast({ tone: "ok", title: t(successKey) });
      } else {
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });
  };

  const onDeactivate = async (member: Staff) => {
    const ok = await confirm({
      title: t("confirmDeactivateTitle"),
      message: t("confirmDeactivateMsg", { name: member.name }),
      confirmLabel: t("deactivate"),
      tone: "danger",
      icon: "trash",
    });
    if (ok) run(member.id, () => deactivateStaff(member.id), "deactivatedSuccess");
  };

  const onDeleteStaff = async (member: Staff) => {
    const ok = await confirm({
      title: t("confirmDeleteTitle"),
      message: t("confirmDeleteMsg", { name: member.name }),
      confirmLabel: t("delete"),
      tone: "danger",
      icon: "trash",
    });
    if (ok) run(member.id, () => deleteStaffAccount(member.id), "deletedSuccess");
  };

  const onResend = (inv: StaffInvitation) =>
    run(inv.id, () => resendStaffInvitation(inv.id), "resentSuccess");

  const onDeleteInvite = async (inv: StaffInvitation) => {
    const ok = await confirm({
      title: t("confirmDeleteInviteTitle"),
      message: t("confirmDeleteInviteMsg", { email: inv.email }),
      confirmLabel: t("deleteInvite"),
      tone: "danger",
      icon: "trash",
    });
    if (ok) run(inv.id, () => deleteStaffInvitation(inv.id), "inviteDeletedSuccess");
  };

  return (
    <div className="stack" style={{ gap: 24 }}>
      <div className="row" style={{ justifyContent: "flex-end" }}>
        <Button variant="primary" icon="plus" onClick={() => setEditor({ mode: "invite" })}>
          {t("invite")}
        </Button>
      </div>

      <section className="card card-pad stack" style={{ gap: 12 }}>
        <h3 className="h3" style={{ margin: 0 }}>{t("staffHeading")}</h3>
        {staff.length === 0 ? (
          <p className="muted" style={{ margin: 0 }}>{t("noStaff")}</p>
        ) : (
          staff.map((m) => (
            <div key={m.id} className="between row wrap" style={{ gap: 10 }}>
              <div className="stack" style={{ gap: 4 }}>
                <span style={{ fontWeight: 600 }}>{m.name}</span>
                <span className="muted-3 ltr" style={{ fontSize: "var(--fs-sm)" }}>{m.email}</span>
                <div className="row wrap" style={{ gap: 6 }}>
                  <Badge tone="neutral">{t(`groups.${m.staff_group}`)}</Badge>
                  <Badge tone={m.status === "active" ? "success" : "neutral"} dot>
                    {t(`status.${m.status}`)}
                  </Badge>
                  {m.permissions.map((p) => (
                    <Badge key={p} tone="info">{t(`perms.${p}`)}</Badge>
                  ))}
                </div>
              </div>
              <div className="row" style={{ gap: 8 }}>
                <Button variant="secondary" size="sm" icon="shield" onClick={() => setEditor({ mode: "permissions", member: m })}>
                  {t("editPerms")}
                </Button>
                {m.status === "active" && (
                  <Button
                    variant="ghost"
                    size="sm"
                    icon="lock"
                    loading={pending && busyId === m.id}
                    onClick={() => onDeactivate(m)}
                  >
                    {t("deactivate")}
                  </Button>
                )}
                <Button
                  variant="danger"
                  size="sm"
                  icon="trash"
                  loading={pending && busyId === m.id}
                  onClick={() => onDeleteStaff(m)}
                >
                  {t("delete")}
                </Button>
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
              <div className="stack" style={{ gap: 2 }}>
                <span className="ltr">{inv.email}</span>
                <span className="muted-3" style={{ fontSize: "var(--fs-sm)" }}>
                  {t(`groups.${inv.staff_group}`)}
                  {inv.expires_at
                    ? ` · ${t("expiresAt", { date: new Date(inv.expires_at).toLocaleDateString() })}`
                    : null}
                </span>
              </div>
              <div className="row" style={{ gap: 8 }}>
                <Button
                  variant="secondary"
                  size="sm"
                  icon="mail"
                  loading={pending && busyId === inv.id}
                  onClick={() => onResend(inv)}
                >
                  {t("resend")}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  icon="trash"
                  loading={pending && busyId === inv.id}
                  onClick={() => onDeleteInvite(inv)}
                >
                  {t("deleteInvite")}
                </Button>
              </div>
            </div>
          ))
        )}
      </section>

      {editor?.mode === "invite" && (
        <InviteModal onClose={() => setEditor(null)} />
      )}
      {editor?.mode === "permissions" && (
        <PermissionsModal member={editor.member} onClose={() => setEditor(null)} />
      )}
    </div>
  );
}

/** Group dropdown; picking a group pre-fills its permissions, which stay editable. */
function GroupSelect({
  value,
  onChange,
}: {
  value: StaffGroup;
  onChange: (next: StaffGroup) => void;
}) {
  const t = useTranslations("owner.staff");

  return (
    <Field label={t("group")} hint={t("groupHint")}>
      <Select
        aria-label={t("group")}
        value={value}
        onChange={(e) => onChange(e.target.value as StaffGroup)}
      >
        {STAFF_GROUPS.map((g) => (
          <option key={g} value={g}>{t(`groups.${g}`)}</option>
        ))}
      </Select>
    </Field>
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
  const t = useTranslations("owner.staff");
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
  const t = useTranslations("owner.staff");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [email, setEmail] = useState("");
  const [name, setName] = useState("");
  const [group, setGroup] = useState<StaffGroup>(DEFAULT_STAFF_GROUP);
  const [permissions, setPermissions] = useState<PosPermission[]>(
    STAFF_GROUP_PERMISSIONS[DEFAULT_STAFF_GROUP],
  );
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const pickGroup = (next: StaffGroup) => {
    setGroup(next);
    setPermissions(STAFF_GROUP_PERMISSIONS[next]);
  };

  const submit = () =>
    startTransition(async () => {
      const res = await inviteStaff({
        email,
        name: name || undefined,
        staff_group: group,
        permissions,
      });
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
        <GroupSelect value={group} onChange={pickGroup} />
        <PermissionPicker value={permissions} onChange={setPermissions} />
      </div>
    </Modal>
  );
}

function PermissionsModal({ member, onClose }: { member: Staff; onClose: () => void }) {
  const t = useTranslations("owner.staff");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [group, setGroup] = useState<StaffGroup>(member.staff_group);
  const [permissions, setPermissions] = useState<PosPermission[]>(member.permissions);

  const pickGroup = (next: StaffGroup) => {
    setGroup(next);
    setPermissions(STAFF_GROUP_PERMISSIONS[next]);
  };

  const submit = () =>
    startTransition(async () => {
      const res = await updateStaffPermissions(member.id, permissions, group);
      if (res.ok) {
        toast({ tone: "ok", title: t("updatedSuccess") });
        onClose();
      } else {
        toast({ tone: "err", title: t("error"), body: res.message });
      }
    });

  return (
    <Modal
      title={t("editPermsTitle", { name: member.name })}
      icon="shield"
      onClose={onClose}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t("cancel")}</Button>
          <Button variant="primary" loading={pending} onClick={submit}>{t("save")}</Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        <GroupSelect value={group} onChange={pickGroup} />
        <PermissionPicker value={permissions} onChange={setPermissions} />
      </div>
    </Modal>
  );
}
