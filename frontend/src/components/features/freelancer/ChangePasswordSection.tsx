"use client";

import { useTransition } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useTranslations } from "next-intl";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import { changePasswordAction } from "@/lib/actions/freelancer";

type Translate = (key: string) => string;

function passwordSchema(t: Translate) {
  return z
    .object({
      current_password: z.string().min(1, t("required")),
      new_password: z.string().min(8, t("passwordMin")),
      new_password_confirmation: z.string().min(8, t("passwordMin")),
    })
    .refine((d) => d.new_password === d.new_password_confirmation, {
      message: t("passwordMatch"),
      path: ["new_password_confirmation"],
    });
}
type PasswordValues = z.infer<ReturnType<typeof passwordSchema>>;

/** Separate "Change password" card with its own form + Server Action. */
export function ChangePasswordSection() {
  const t = useTranslations("freelancer.profile");
  const tErr = useTranslations("freelancer.errors");
  const { toast } = useToast();
  const [pending, startTransition] = useTransition();

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<PasswordValues>({
    resolver: zodResolver(passwordSchema((k) => tErr(k))),
    defaultValues: {
      current_password: "",
      new_password: "",
      new_password_confirmation: "",
    },
  });

  function onSubmit(values: PasswordValues) {
    startTransition(async () => {
      const result = await changePasswordAction(values);

      if (result.ok) {
        toast({ tone: "ok", title: t("passwordSuccess") });
        reset();
        return;
      }

      if (result.status === 422 && result.errors) {
        for (const [field, messages] of Object.entries(result.errors)) {
          if (field in values) {
            setError(field as keyof PasswordValues, { message: messages[0] });
          }
        }
        return;
      }

      toast({ tone: "err", title: t("passwordError"), body: result.message });
    });
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <div className="card card-pad stack" style={{ gap: 18 }}>
        <div className="stack" style={{ gap: 4 }}>
          <h3 className="h3">{t("passwordTitle")}</h3>
          <p className="muted">{t("passwordSubtitle")}</p>
        </div>

        <Field
          label={t("currentPassword")}
          error={errors.current_password?.message}
        >
          <Input
            icon="lock"
            type="password"
            autoComplete="current-password"
            {...register("current_password")}
          />
        </Field>

        <div className="grid2">
          <Field label={t("newPassword")} error={errors.new_password?.message}>
            <Input
              icon="lock"
              type="password"
              autoComplete="new-password"
              {...register("new_password")}
            />
          </Field>

          <Field
            label={t("confirmPassword")}
            error={errors.new_password_confirmation?.message}
          >
            <Input
              icon="lock"
              type="password"
              autoComplete="new-password"
              {...register("new_password_confirmation")}
            />
          </Field>
        </div>

        <div className="between">
          <span />
          <Button type="submit" variant="primary" icon="lock" loading={pending}>
            {t("changePassword")}
          </Button>
        </div>
      </div>
    </form>
  );
}
