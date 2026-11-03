"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import {
  confirmTwoFactorAction,
  disableTwoFactorAction,
  enableTwoFactorAction,
  type TwoFactorEnrollment,
} from "@/lib/actions/twoFactor";

export interface TwoFactorStatus {
  eligible: boolean;
  enabled: boolean;
  pending_confirmation: boolean;
  recovery_codes_remaining: number;
}

type Dialog = "enroll" | "recovery" | "disable" | null;

/**
 * TOTP two-factor management: enroll (QR + confirm code), show the one-time
 * recovery codes, and disable (requires a valid code).
 */
export function SecuritySettings({ status }: { status: TwoFactorStatus }) {
  const t = useTranslations("auth.twoFactor");
  const router = useRouter();

  const [dialog, setDialog] = useState<Dialog>(null);
  const [enrollment, setEnrollment] = useState<TwoFactorEnrollment | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function close() {
    setDialog(null);
    setCode("");
    setError(null);
  }

  async function startEnrollment() {
    setBusy(true);
    setError(null);
    const result = await enableTwoFactorAction();
    setBusy(false);
    if (!result.ok) return setError(result.message);
    setEnrollment(result.data);
    setDialog("enroll");
  }

  async function confirmEnrollment() {
    setBusy(true);
    setError(null);
    const result = await confirmTwoFactorAction(code.trim());
    setBusy(false);
    if (!result.ok) return setError(result.status === 429 ? t("throttled") : t("invalidCode"));
    setRecoveryCodes(result.data.recovery_codes);
    setCode("");
    setDialog("recovery");
  }

  async function disable() {
    setBusy(true);
    setError(null);
    const result = await disableTwoFactorAction({ code: code.trim() });
    setBusy(false);
    if (!result.ok) return setError(result.status === 429 ? t("throttled") : t("invalidCode"));
    close();
    router.refresh();
  }

  function finishRecovery() {
    setRecoveryCodes([]);
    setEnrollment(null);
    close();
    router.refresh();
  }

  if (!status.eligible) {
    return (
      <div className="profile-screen">
        <h1 className="h1">{t("title")}</h1>
        <Alert tone="info" title={t("notEligible")} />
      </div>
    );
  }

  return (
    <div className="profile-screen">
      <div className="stack" style={{ gap: 4 }}>
        <h1 className="h1">{t("title")}</h1>
        <p className="muted">{t("subtitle")}</p>
      </div>

      {error && dialog === null && <Alert tone="danger" title={error} />}

      <div className="card" style={{ padding: "var(--s-5)" }}>
        <h2 className="h3">{status.enabled ? t("statusOn") : t("statusOff")}</h2>
        {status.enabled && (
          <p className="muted" style={{ margin: "var(--s-2) 0" }}>
            {t("recoveryRemaining", { count: status.recovery_codes_remaining })}
          </p>
        )}
        <div style={{ marginTop: "var(--s-4)" }}>
          {status.enabled ? (
            <Button variant="danger" onClick={() => setDialog("disable")}>
              {t("disable")}
            </Button>
          ) : (
            <Button variant="primary" loading={busy} onClick={startEnrollment}>
              {t("enable")}
            </Button>
          )}
        </div>
      </div>

      {dialog === "enroll" && enrollment && (
        <Modal
          title={t("enrollTitle")}
          icon="shield"
          onClose={close}
          footer={
            <>
              <Button variant="ghost" onClick={close}>
                {t("cancel")}
              </Button>
              <Button
                variant="primary"
                loading={busy}
                disabled={code.trim().length !== 6}
                onClick={confirmEnrollment}
              >
                {t("confirm")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted">{t("scanHint")}</p>
            {/* Our own backend renders this SVG; loading it as an image keeps it inert. */}
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              alt={t("qrAlt")}
              width={192}
              height={192}
              style={{ alignSelf: "center", background: "#fff" }}
              src={`data:image/svg+xml;utf8,${encodeURIComponent(enrollment.qr_svg)}`}
            />
            <p className="muted">
              {t("manualKey")} <code className="ltr">{enrollment.secret}</code>
            </p>
            {error && <Alert tone="danger" title={error} />}
            <Field label={t("codeLabel")}>
              <Input
                className="ltr"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                value={code}
                onChange={(e) => setCode(e.target.value)}
              />
            </Field>
          </div>
        </Modal>
      )}

      {dialog === "recovery" && (
        <Modal
          title={t("recoveryTitle")}
          icon="lock"
          onClose={finishRecovery}
          footer={
            <Button variant="primary" onClick={finishRecovery}>
              {t("done")}
            </Button>
          }
        >
          <div className="stack" style={{ gap: 12 }}>
            <Alert tone="warning" title={t("recoveryWarning")} />
            <ul className="ltr" style={{ columns: 2, fontFamily: "monospace" }}>
              {recoveryCodes.map((recoveryCode) => (
                <li key={recoveryCode}>{recoveryCode}</li>
              ))}
            </ul>
          </div>
        </Modal>
      )}

      {dialog === "disable" && (
        <Modal
          title={t("disableTitle")}
          icon="shield"
          onClose={close}
          footer={
            <>
              <Button variant="ghost" onClick={close}>
                {t("cancel")}
              </Button>
              <Button
                variant="danger"
                loading={busy}
                disabled={code.trim().length !== 6}
                onClick={disable}
              >
                {t("disable")}
              </Button>
            </>
          }
        >
          <div className="stack" style={{ gap: 16 }}>
            <p className="muted">{t("disableHint")}</p>
            {error && <Alert tone="danger" title={error} />}
            <Field label={t("codeLabel")}>
              <Input
                className="ltr"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                value={code}
                onChange={(e) => setCode(e.target.value)}
              />
            </Field>
          </div>
        </Modal>
      )}
    </div>
  );
}
