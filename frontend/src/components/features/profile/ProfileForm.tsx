"use client";

import { useState, useTransition } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";
import { Field } from "@/components/ui/Field";
import { Input } from "@/components/ui/Input";
import { Select } from "@/components/ui/Select";
import { Textarea } from "@/components/ui/Textarea";
import { Button } from "@/components/ui/Button";
import { useToast } from "@/components/providers/ToastProvider";
import { GENDER_OPTIONS, SPECIALTY_OPTIONS } from "@/lib/validations/auth";
import { updateProfileAction } from "@/lib/actions/profile";
import { AvatarUploader } from "./AvatarUploader";

type Translate = (key: string) => string;

function profileSchema(t: Translate) {
  return z.object({
    name: z.string().min(2, t("required")),
    phone: z.string(),
    gender: z.enum(["", ...GENDER_OPTIONS]),
    specialty: z.string(),
    bio: z.string(),
  });
}
type ProfileValues = z.infer<ReturnType<typeof profileSchema>>;

export interface ProfileFormProps {
  defaults: {
    name: string;
    email: string;
    phone: string;
    /** `""` (unspecified) or `"male"` / `"female"`. */
    gender: string;
    specialty: string;
    bio: string;
    avatar: string | null;
  };
  avatarInitial: string;
  /** Show the freelancer-only specialty + bio fields. */
  showFreelancerFields?: boolean;
  /** Dashboard paths to revalidate after a successful save. */
  revalidate?: string[];
}

/**
 * Personal-info form (name/phone + optional specialty/bio) with a circular
 * avatar uploader. Shared by admin and freelancer profile pages — the
 * role-specific fields are toggled via `showFreelancerFields`.
 */
export function ProfileForm({
  defaults,
  avatarInitial,
  showFreelancerFields = false,
  revalidate = [],
}: ProfileFormProps) {
  const t = useTranslations("profile");
  const tErr = useTranslations("profile.errors");
  const tg = useTranslations("common.gender");
  const { toast } = useToast();
  const router = useRouter();
  const [avatar, setAvatar] = useState<File | null>(null);
  const [pending, startTransition] = useTransition();

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema((k) => tErr(k))),
    defaultValues: {
      name: defaults.name,
      phone: defaults.phone,
      gender: defaults.gender as "" | (typeof GENDER_OPTIONS)[number],
      specialty: defaults.specialty,
      bio: defaults.bio,
    },
  });

  function onSubmit(values: ProfileValues) {
    startTransition(async () => {
      const result = await updateProfileAction(
        {
          name: values.name,
          phone: values.phone,
          gender: values.gender,
          specialty: showFreelancerFields ? values.specialty : "",
          bio: showFreelancerFields ? values.bio : "",
        },
        avatar,
        revalidate,
      );

      if (result.ok) {
        toast({ tone: "ok", title: t("saveSuccess") });
        setAvatar(null);
        router.refresh();
        return;
      }

      if (result.status === 422 && result.errors) {
        for (const [field, messages] of Object.entries(result.errors)) {
          if (field in values) {
            setError(field as keyof ProfileValues, { message: messages[0] });
          }
        }
        return;
      }

      toast({ tone: "err", title: t("saveError"), body: result.message });
    });
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <div className="card card-pad stack" style={{ gap: 22 }}>
        <h3 className="h3">{t("sectionInfo")}</h3>

        <AvatarUploader
          initialUrl={defaults.avatar}
          fallbackInitial={avatarInitial}
          onChange={setAvatar}
        />

        <div className="divider" />

        <div className="grid2">
          <Field label={t("name")} error={errors.name?.message}>
            <Input placeholder={t("namePlaceholder")} {...register("name")} />
          </Field>

          <Field label={t("email")} hint={t("emailHint")}>
            <Input className="ltr" value={defaults.email} readOnly disabled />
          </Field>

          <Field label={t("phone")} error={errors.phone?.message}>
            <Input
              icon="phone"
              className="ltr"
              placeholder={t("phonePlaceholder")}
              {...register("phone")}
            />
          </Field>

          <Field label={tg("label")} error={errors.gender?.message}>
            <Select defaultValue={defaults.gender} {...register("gender")}>
              <option value="">{tg("unspecified")}</option>
              {GENDER_OPTIONS.map((code) => (
                <option key={code} value={code}>
                  {tg(code)}
                </option>
              ))}
            </Select>
          </Field>

          {showFreelancerFields && (
            <Field label={t("specialty")} error={errors.specialty?.message}>
              <Select
                defaultValue={defaults.specialty}
                {...register("specialty")}
              >
                <option value="">{t("specialtyPlaceholder")}</option>
                {SPECIALTY_OPTIONS.map((code) => (
                  <option key={code} value={code}>
                    {t(`specialtyOptions.${code}`)}
                  </option>
                ))}
              </Select>
            </Field>
          )}
        </div>

        {showFreelancerFields && (
          <Field label={t("bio")} error={errors.bio?.message}>
            <Textarea
              placeholder={t("bioPlaceholder")}
              rows={4}
              {...register("bio")}
            />
          </Field>
        )}

        <div className="between">
          <span />
          <Button type="submit" variant="primary" icon="check" loading={pending}>
            {t("saveInfo")}
          </Button>
        </div>
      </div>
    </form>
  );
}
