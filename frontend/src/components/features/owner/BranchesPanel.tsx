"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { useToast } from "@/components/providers/ToastProvider";
import { createBranch, deleteBranch } from "@/lib/actions/owner";
import type { Branch } from "@/lib/types";

/** Branch list with add and delete; the default branch is never deletable. */
export function BranchesPanel({ branches }: { branches: Branch[] }) {
  const t = useTranslations("owner");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [name, setName] = useState("");
  const [address, setAddress] = useState("");

  const add = () => {
    startTransition(async () => {
      const res = await createBranch({ name: name.trim(), address: address.trim() || undefined });
      if (res.ok) {
        toast({ tone: "ok", title: t("seats.stations.branchCreated"), body: name.trim() });
        setName("");
        setAddress("");
      } else {
        toast({ tone: "err", title: t("seats.stations.branchFailed"), body: res.message });
      }
    });
  };

  const remove = (branch: Branch) => {
    startTransition(async () => {
      const res = await deleteBranch(branch.id);
      toast(
        res.ok
          ? { tone: "ok", title: t("seats.stations.branchDeleted"), body: branch.name }
          : { tone: "err", title: t("seats.stations.branchFailed"), body: res.message },
      );
    });
  };

  return (
    <div className="card card-pad stack" style={{ gap: 14 }}>
      <div className="row" style={{ gap: 8 }}>
        <Input
          value={name}
          maxLength={150}
          placeholder={t("seats.stations.branchName")}
          aria-label={t("seats.stations.branchName")}
          onChange={(e) => setName(e.target.value)}
        />
        <Input
          value={address}
          maxLength={255}
          placeholder={t("seats.stations.branchAddress")}
          aria-label={t("seats.stations.branchAddress")}
          onChange={(e) => setAddress(e.target.value)}
        />
        <Button icon="plus" loading={pending} disabled={name.trim() === ""} onClick={add}>
          {t("seats.stations.branchAdd")}
        </Button>
      </div>
      <div className="stack" style={{ gap: 8 }}>
        {branches.map((branch) => (
          <div key={branch.id} className="between">
            <div>
              <span style={{ fontWeight: 500 }}>{branch.name}</span>
              {branch.is_default && (
                <span className="badge badge-info" style={{ marginInlineStart: 8 }}>
                  {t("seats.stations.branchDefault")}
                </span>
              )}
              <div className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
                {branch.address} · {t("seats.stations.branchSeats", { count: branch.seats_count ?? 0 })}
              </div>
            </div>
            {!branch.is_default && (
              <Button variant="ghost" icon="x" disabled={pending} onClick={() => remove(branch)}>
                {t("seats.stations.branchDelete")}
              </Button>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
