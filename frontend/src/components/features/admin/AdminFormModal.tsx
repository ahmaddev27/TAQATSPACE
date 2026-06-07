"use client";

import { useMemo, useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Modal } from "@/components/ui/Modal";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Checkbox } from "@/components/ui/Checkbox";
import { useToast } from "@/components/providers/ToastProvider";
import {
  createAdmin,
  updateAdmin,
  type CreateAdminInput,
  type UpdateAdminInput,
} from "@/lib/actions/admin";
import type { ManagedAdmin } from "@/lib/api/admin";
import type { AdminPermission, AdminRole } from "@/lib/types";

/** Every assignable permission, in display order. `manage_admins` leads. */
const ALL_PERMISSIONS: AdminPermission[] = [
  "manage_admins",
  "manage_workspaces",
  "manage_users",
  "manage_billing",
  "manage_content",
  "manage_messaging",
  "view_reports",
];

const ASSIGNABLE_ROLES: AdminRole[] = ["admin", "super_admin"];

/** Permissions implied by the super-admin role (the full set). */
const SUPER_ADMIN_PERMISSIONS = new Set(ALL_PERMISSIONS);

/**
 * Sensible default grant pre-checked for a brand-new standard admin: everything
 * except managing other admins. Mirrors the backend's `defaultsForAdmin()`.
 */
const DEFAULT_ADMIN_PERMISSIONS: AdminPermission[] = ALL_PERMISSIONS.filter(
  (perm) => perm !== "manage_admins",
);

export interface AdminFormModalProps {
  /** The admin being edited, or null when creating a new one. */
  admin: ManagedAdmin | null;
  /** True when the edited row is the signed-in super-admin (self-lockout guard). */
  isSelf: boolean;
  onClose: () => void;
}

/**
 * Create / edit modal for an admin account. On create it collects name, email,
 * password, role and a permission set; on edit it patches role, permissions,
 * status and an optional new password. Field-level (422) errors from the API
 * are surfaced under each input.
 */
export function AdminFormModal({ admin, isSelf, onClose }: AdminFormModalProps) {
  const t = useTranslations("admin.admins");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const isEdit = admin !== null;

  const [name, setName] = useState(admin?.name ?? "");
  const [email, setEmail] = useState(admin?.email ?? "");
  const [password, setPassword] = useState("");
  const [passwordConfirm, setPasswordConfirm] = useState("");
  const [role, setRole] = useState<AdminRole>(
    admin?.is_super_admin ? "super_admin" : "admin",
  );
  const [permissions, setPermissions] = useState<Set<AdminPermission>>(
    () => new Set(admin?.permissions ?? DEFAULT_ADMIN_PERMISSIONS),
  );
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  // A super-admin implicitly holds every permission; lock the checkboxes on so
  // the UI never implies a super-admin could be missing one.
  const superSelected = role === "super_admin";
  const effectivePermissions = useMemo(
    () => (superSelected ? SUPER_ADMIN_PERMISSIONS : permissions),
    [superSelected, permissions],
  );

  const togglePermission = (perm: AdminPermission) => {
    setPermissions((prev) => {
      const next = new Set(prev);
      if (next.has(perm)) next.delete(perm);
      else next.add(perm);
      return next;
    });
  };

  const fieldError = (key: string): string | undefined => errors[key]?.[0];

  const submit = () => {
    setErrors({});
    startTransition(async () => {
      const chosenPermissions = superSelected
        ? ALL_PERMISSIONS
        : Array.from(permissions);

      if (isEdit && admin) {
        const patch: UpdateAdminInput = {
          admin_role: role,
          permissions: chosenPermissions,
        };
        if (password) {
          patch.password = password;
          patch.password_confirmation = passwordConfirm;
        }
        const res = await updateAdmin(admin.id, patch);
        if (res.ok) {
          toast({ tone: "ok", title: t("toast.updated") });
          onClose();
        } else {
          setErrors(res.errors ?? {});
          toast({ tone: "err", title: t("toast.saveFailed"), body: res.message });
        }
        return;
      }

      const payload: CreateAdminInput = {
        name,
        email,
        password,
        password_confirmation: passwordConfirm,
        admin_role: role,
        permissions: chosenPermissions,
      };
      const res = await createAdmin(payload);
      if (res.ok) {
        toast({ tone: "ok", title: t("toast.created") });
        onClose();
      } else {
        setErrors(res.errors ?? {});
        toast({ tone: "err", title: t("toast.saveFailed"), body: res.message });
      }
    });
  };

  return (
    <Modal
      title={isEdit ? t("editTitle") : t("createTitle")}
      icon="shield"
      onClose={onClose}
      closeLabel={t("cancel")}
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={pending}>
            {t("cancel")}
          </Button>
          <Button variant="primary" loading={pending} onClick={submit}>
            {isEdit ? t("save") : t("create")}
          </Button>
        </>
      }
    >
      <div className="stack" style={{ gap: 14 }}>
        {!isEdit && (
          <>
            <Field label={t("form.name")} error={fieldError("name")}>
              <Input
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder={t("form.namePlaceholder")}
              />
            </Field>
            <Field label={t("form.email")} error={fieldError("email")}>
              <Input
                type="email"
                className="ltr"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder={t("form.emailPlaceholder")}
              />
            </Field>
          </>
        )}

        <Field
          label={isEdit ? t("form.newPassword") : t("form.password")}
          hint={isEdit ? t("form.newPasswordHint") : undefined}
          error={fieldError("password")}
        >
          <Input
            type="password"
            className="ltr"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="new-password"
          />
        </Field>

        {(password || !isEdit) && (
          <Field label={t("form.passwordConfirm")}>
            <Input
              type="password"
              className="ltr"
              value={passwordConfirm}
              onChange={(e) => setPasswordConfirm(e.target.value)}
              autoComplete="new-password"
            />
          </Field>
        )}

        <Field
          label={t("form.role")}
          error={fieldError("admin_role")}
          hint={isSelf ? t("form.selfHint") : undefined}
        >
          <Select
            value={role}
            onChange={(e) => setRole(e.target.value as AdminRole)}
          >
            {ASSIGNABLE_ROLES.map((r) => (
              <option key={r} value={r}>
                {t(`role.${r}`)}
              </option>
            ))}
          </Select>
        </Field>

        <Field label={t("form.permissions")} error={fieldError("permissions")}>
          <div className="stack" style={{ gap: 8 }}>
            {ALL_PERMISSIONS.map((perm) => (
              <Checkbox
                key={perm}
                checked={effectivePermissions.has(perm)}
                disabled={superSelected}
                onChange={() => togglePermission(perm)}
              >
                {t(`permission.${perm}`)}
              </Checkbox>
            ))}
          </div>
        </Field>
      </div>
    </Modal>
  );
}
