"use client";

import { useState, useTransition } from "react";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Textarea } from "@/components/ui/Textarea";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import type { PublicDict } from "./i18n";
import { submitContactAction } from "./actions";

export interface ContactFormProps {
  dict: PublicDict;
}

const EMPTY = { name: "", email: "", subject: "", message: "" };

export function ContactForm({ dict }: ContactFormProps) {
  const t = dict.contact;
  const [values, setValues] = useState(EMPTY);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [sent, setSent] = useState(false);
  const [isPending, startTransition] = useTransition();

  function update(key: keyof typeof EMPTY, value: string) {
    setValues((v) => ({ ...v, [key]: value }));
  }

  function onSubmit(ev: React.FormEvent) {
    ev.preventDefault();
    setErrors({});
    startTransition(async () => {
      const result = await submitContactAction(values);
      if (result.ok) {
        setSent(true);
        setValues(EMPTY);
      } else if (result.errors) {
        setErrors(result.errors);
      }
    });
  }

  function fieldError(key: keyof typeof EMPTY): string | undefined {
    const code = errors[key]?.[0];
    if (!code) return undefined;
    return code === "email" ? dict.contact.email : undefined;
  }

  if (sent) {
    return (
      <div className="card card-pad" style={{ textAlign: "center" }}>
        <div className="confirm-art" style={{ background: "var(--success-bg)", color: "var(--success)" }}>
          <Icon name="checkCircle" size={32} />
        </div>
        <h3 className="h3" style={{ marginTop: 18 }}>
          {t.successTitle}
        </h3>
        <p className="muted" style={{ marginTop: 8 }}>
          {t.success}
        </p>
        <div style={{ marginTop: 18 }}>
          <Button variant="secondary" onClick={() => setSent(false)}>
            {t.another}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <form className="card card-pad stack" style={{ gap: 14 }} onSubmit={onSubmit} noValidate>
      <Field label={t.name} error={errors.name ? t.name : undefined}>
        <Input
          value={values.name}
          onChange={(e) => update("name", e.target.value)}
          placeholder={t.namePlaceholder}
        />
      </Field>
      <Field label={t.email} error={fieldError("email") ?? (errors.email ? t.email : undefined)}>
        <Input
          type="email"
          icon="mail"
          value={values.email}
          onChange={(e) => update("email", e.target.value)}
          placeholder={t.emailPlaceholder}
        />
      </Field>
      <Field label={t.subject} error={errors.subject ? t.subject : undefined}>
        <Input
          value={values.subject}
          onChange={(e) => update("subject", e.target.value)}
          placeholder={t.subjectPlaceholder}
        />
      </Field>
      <Field label={t.message} error={errors.message ? t.message : undefined}>
        <Textarea
          rows={5}
          value={values.message}
          onChange={(e) => update("message", e.target.value)}
          placeholder={t.messagePlaceholder}
        />
      </Field>
      <Button type="submit" variant="primary" size="lg" block loading={isPending} icon="send">
        {isPending ? t.submitting : t.submit}
      </Button>
    </form>
  );
}
