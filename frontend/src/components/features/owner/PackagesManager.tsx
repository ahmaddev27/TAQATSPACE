"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { useConfirm } from "@/components/ui/ConfirmDialog";
import { useToast } from "@/components/providers/ToastProvider";
import { deletePackage } from "@/lib/actions/owner";
import type { Member, Package } from "@/lib/types";
import { money } from "./format";
import { PackageForm } from "./PackageForm";
import { AssignPackageModal } from "./AssignPackageModal";

export interface PackagesManagerProps {
  packages: Package[];
  members: Member[];
}

type Editing = { mode: "create" } | { mode: "edit"; pkg: Package } | null;

/** Internet-package cards with create/edit and assign-to-members flows. */
export function PackagesManager({ packages, members }: PackagesManagerProps) {
  const t = useTranslations("owner");
  const { toast } = useToast();
  const confirm = useConfirm();
  const [, startTransition] = useTransition();

  const [editing, setEditing] = useState<Editing>(null);
  const [assignFor, setAssignFor] = useState<Package | null>(null);

  const handleDelete = async (pkg: Package) => {
    const ok = await confirm({
      title: t("packages.delete"),
      message: t("packages.deleteConfirm"),
      confirmLabel: t("packages.delete"),
      tone: "danger",
      icon: "trash",
    });
    if (!ok) return;
    startTransition(async () => {
      const res = await deletePackage(pkg.id);
      if (res.ok) {
        toast({ tone: "ok", title: t("packages.deleted") });
      } else {
        toast({ tone: "err", title: t("packages.deleteFailed"), body: res.message });
      }
    });
  };

  return (
    <div className="stack" style={{ gap: 18 }}>
      {packages.length === 0 ? (
        <div className="empty-state" style={{ minHeight: 260 }}>
          <div className="st-ico" style={{ width: 48, height: 48 }}>
            <Icon name="wifi" />
          </div>
          <div>
            <div className="h3">{t("packages.emptyTitle")}</div>
            <div style={{ fontSize: "var(--fs-sm)", marginTop: 4 }}>
              {t("packages.emptyBody")}
            </div>
          </div>
          <Button
            variant="primary"
            icon="plus"
            onClick={() => setEditing({ mode: "create" })}
          >
            {t("packages.addPackage")}
          </Button>
        </div>
      ) : (
        <>
          <div className="pkg-grid">
            {packages.map((p) => (
              <div key={p.id} className="card card-pad stack" style={{ gap: 14 }}>
                <div className="between">
                  <div className="row" style={{ gap: 10 }}>
                    <span className="st-ico">
                      <Icon name="wifi" />
                    </span>
                    <h3 className="h3" style={{ fontSize: "var(--fs-lg)" }}>
                      {p.name}
                    </h3>
                  </div>
                  <span
                    className={`badge ${p.is_active ? "badge-success" : "badge-neutral"}`}
                  >
                    {p.is_active ? t("packages.active") : t("packages.inactive")}
                  </span>
                </div>

                <div className="row" style={{ gap: 6, alignItems: "baseline" }}>
                  <span className="tnum" style={{ fontSize: "1.8rem", fontWeight: 700 }}>
                    {p.speed_mbps}
                  </span>
                  <span className="muted">{t("packages.mbps")}</span>
                </div>

                <div className="stack" style={{ gap: 8 }}>
                  <Row
                    label={t("packages.dataCap")}
                    value={
                      p.is_unlimited
                        ? t("packages.unlimited")
                        : `${p.data_limit_gb ?? 0} ${t("packages.gb")}`
                    }
                  />
                  <Row
                    label={t("packages.price")}
                    value={
                      Number(p.price) > 0 ? money(p.price) : t("packages.included")
                    }
                  />
                  <Row
                    label={t("packages.members")}
                    value={String(p.assigned_members_count ?? p.members?.length ?? 0)}
                  />
                </div>

                <div className="row" style={{ gap: 8 }}>
                  <Button
                    variant="secondary"
                    size="sm"
                    icon="users"
                    onClick={() => setAssignFor(p)}
                    className="grow"
                  >
                    {t("packages.assignMembers")}
                  </Button>
                  <Button
                    variant="secondary"
                    size="sm"
                    icon="edit"
                    onClick={() => setEditing({ mode: "edit", pkg: p })}
                    aria-label={t("packages.edit")}
                  />
                  <Button
                    variant="ghost"
                    size="sm"
                    icon="trash"
                    onClick={() => handleDelete(p)}
                    aria-label={t("packages.delete")}
                  />
                </div>
              </div>
            ))}
          </div>

          <Button
            variant="primary"
            icon="plus"
            onClick={() => setEditing({ mode: "create" })}
            style={{ alignSelf: "flex-start" }}
          >
            {t("packages.addPackage")}
          </Button>
        </>
      )}

      {editing && (
        <PackageForm
          pkg={editing.mode === "edit" ? editing.pkg : null}
          onClose={() => setEditing(null)}
        />
      )}
      {assignFor && (
        <AssignPackageModal
          pkg={assignFor}
          members={members}
          onClose={() => setAssignFor(null)}
        />
      )}
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="between">
      <span className="muted" style={{ fontSize: "var(--fs-sm)" }}>
        {label}
      </span>
      <span style={{ fontSize: "var(--fs-sm)", fontWeight: 500 }}>{value}</span>
    </div>
  );
}
