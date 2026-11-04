"use client";

import { useState, useTransition } from "react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Stepper } from "@/components/ui/Stepper";
import { useToast } from "@/components/providers/ToastProvider";
import { updateMemberProfile } from "@/lib/actions/owner";
import {
  EDUCATION_LEVELS,
  EMPLOYMENT_STATUSES,
  GENDERS,
  SPECIALIZATIONS,
} from "@/lib/constants/memberProfile";
import type { Member } from "@/lib/types";

export interface ProfileWizardProps {
  member: Member;
  onDone: () => void;
  onCancel: () => void;
}

interface Draft {
  gender: string;
  employment_status: string;
  education_level: string;
  specialization: string;
  mikrotik_username: string;
}

const LAST_STEP = 2;

/** Three-step form that completes the donor fields of one subscriber. */
export function ProfileWizard({ member, onDone, onCancel }: ProfileWizardProps) {
  const t = useTranslations("owner.members.profile");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();
  const [step, setStep] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [draft, setDraft] = useState<Draft>({
    gender: member.user.gender ?? "",
    employment_status: member.user.employment_status ?? "",
    education_level: member.user.education_level ?? "",
    specialization: member.user.specialization ?? "",
    mikrotik_username: member.mikrotik_username ?? "",
  });
  const hasAccount = Boolean(member.mikrotik_username);

  const set = (key: keyof Draft) => (value: string) =>
    setDraft((d) => ({ ...d, [key]: value }));

  const options = (codes: readonly string[], group: string) =>
    codes.map((code) => (
      <option key={code} value={code}>
        {t(`${group}.${code}` as never)}
      </option>
    ));

  const save = () => {
    startTransition(async () => {
      const body = {
        gender: draft.gender || null,
        employment_status: draft.employment_status || null,
        education_level: draft.education_level || null,
        specialization: draft.specialization || null,
        ...(hasAccount && draft.mikrotik_username
          ? { mikrotik_username: draft.mikrotik_username }
          : {}),
      };
      const res = await updateMemberProfile(String(member.user.id), body);
      if (res.ok) {
        toast({ tone: "ok", title: t("saved") });
        onDone();
        return;
      }
      setError(res.errors?.mikrotik_username?.[0] ?? res.message);
      if (res.errors?.mikrotik_username) setStep(LAST_STEP);
    });
  };

  return (
    <div className="stack" style={{ gap: 16 }}>
      <Stepper
        steps={[t("stepPersonal"), t("stepEducation"), t("stepInternet")]}
        current={step}
      />

      {step === 0 && (
        <div className="stack" style={{ gap: 12 }}>
          <Field label={t("gender")} htmlFor="pw-gender">
            <Select
              id="pw-gender"
              value={draft.gender}
              onChange={(e) => set("gender")(e.target.value)}
            >
              <option value="">{t("choose")}</option>
              {options(GENDERS, "genders")}
            </Select>
          </Field>
          <Field label={t("employmentStatus")} htmlFor="pw-employment">
            <Select
              id="pw-employment"
              value={draft.employment_status}
              onChange={(e) => set("employment_status")(e.target.value)}
            >
              <option value="">{t("choose")}</option>
              {options(EMPLOYMENT_STATUSES, "employment")}
            </Select>
          </Field>
        </div>
      )}

      {step === 1 && (
        <div className="stack" style={{ gap: 12 }}>
          <Field label={t("educationLevel")} htmlFor="pw-education">
            <Select
              id="pw-education"
              value={draft.education_level}
              onChange={(e) => set("education_level")(e.target.value)}
            >
              <option value="">{t("choose")}</option>
              {options(EDUCATION_LEVELS, "education")}
            </Select>
          </Field>
          <Field label={t("specialization")} htmlFor="pw-specialization">
            <Select
              id="pw-specialization"
              value={draft.specialization}
              onChange={(e) => set("specialization")(e.target.value)}
            >
              <option value="">{t("choose")}</option>
              {options(SPECIALIZATIONS, "specializations")}
            </Select>
          </Field>
        </div>
      )}

      {step === LAST_STEP && (
        <Field
          label={t("mikrotikUsername")}
          htmlFor="pw-mikrotik"
          hint={hasAccount ? t("mikrotikHint") : t("noInternetAccount")}
          error={error}
        >
          <Input
            id="pw-mikrotik"
            className="ltr"
            value={draft.mikrotik_username}
            disabled={!hasAccount}
            onChange={(e) => set("mikrotik_username")(e.target.value)}
          />
        </Field>
      )}

      <div className="between">
        <Button
          variant="ghost"
          onClick={step === 0 ? onCancel : () => setStep(step - 1)}
        >
          {step === 0 ? t("cancel") : t("back")}
        </Button>
        {step < LAST_STEP ? (
          <Button variant="primary" onClick={() => setStep(step + 1)}>
            {t("next")}
          </Button>
        ) : (
          <Button variant="primary" icon="check" loading={pending} onClick={save}>
            {t("save")}
          </Button>
        )}
      </div>
    </div>
  );
}
