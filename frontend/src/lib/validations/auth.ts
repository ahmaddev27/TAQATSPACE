import { z } from "zod";

/** Translator signature compatible with next-intl's `useTranslations('validation')`. */
type Translate = (key: string, values?: Record<string, string | number>) => string;

const ACCEPTED_FILE_TYPES = [
  "image/jpeg",
  "image/png",
  "image/jpg",
  "application/pdf",
];
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

/** A File field that is validated client-side (type + size). */
function fileSchema(t: Translate, required = true) {
  const base = z
    .instanceof(File, { message: t("fileRequired") })
    .refine((f) => f.size <= MAX_FILE_SIZE, { message: t("fileSize") })
    .refine((f) => ACCEPTED_FILE_TYPES.includes(f.type), {
      message: t("fileType"),
    });
  return required ? base : base.optional();
}

export function loginSchema(t: Translate) {
  return z.object({
    email: z.string().email(t("email")),
    password: z.string().min(1, t("required")),
    remember: z.boolean().optional(),
  });
}
export type LoginValues = z.infer<ReturnType<typeof loginSchema>>;

export function forgotPasswordSchema(t: Translate) {
  return z.object({
    email: z.string().email(t("email")),
  });
}
export type ForgotPasswordValues = z.infer<ReturnType<typeof forgotPasswordSchema>>;

export function resetPasswordSchema(t: Translate) {
  return z
    .object({
      password: z.string().min(8, t("passwordMin")),
      password_confirmation: z.string().min(8, t("passwordMin")),
    })
    .refine((d) => d.password === d.password_confirmation, {
      message: t("passwordMatch"),
      path: ["password_confirmation"],
    });
}
export type ResetPasswordValues = z.infer<ReturnType<typeof resetPasswordSchema>>;

export function freelancerRegisterSchema(t: Translate) {
  return z
    .object({
      // Step 0
      name: z.string().min(2, t("required")),
      email: z.string().email(t("email")),
      phone: z.string().min(6, t("phone")),
      password: z.string().min(8, t("passwordMin")),
      password_confirmation: z.string().min(8, t("passwordMin")),
      // Step 1
      specialty: z.string().min(1, t("required")),
      bio: z.string().optional(),
      // Step 2
      id_document: fileSchema(t),
      terms: z.literal(true, { message: t("terms") }),
    })
    .refine((d) => d.password === d.password_confirmation, {
      message: t("passwordMatch"),
      path: ["password_confirmation"],
    });
}
export type FreelancerRegisterValues = z.infer<
  ReturnType<typeof freelancerRegisterSchema>
>;

/** Field groups per step — used to scope RHF `trigger()`. */
export const FREELANCER_STEP_FIELDS: (keyof FreelancerRegisterValues)[][] = [
  ["name", "email", "phone", "password", "password_confirmation"],
  ["specialty", "bio"],
  ["id_document", "terms"],
];

/** The three backend seat types, in display order. */
export const SEAT_TYPE_CODES = ["flexible", "fixed", "private_office"] as const;
export type SeatTypeCode = (typeof SEAT_TYPE_CODES)[number];

export function workspaceRegisterSchema(t: Translate) {
  // Numeric text fields stay strings (text inputs); coerced to numbers on submit.
  // Empty is allowed (price/capacity are optional); non-empty must be a ≥0 number.
  const numericText = z
    .string()
    .refine((v) => v.trim() === "" || Number(v) >= 0, t("required"));

  const seatType = z.object({
    type: z.enum(SEAT_TYPE_CODES),
    enabled: z.boolean(),
    price_monthly: numericText,
    price_daily: numericText,
    capacity: numericText,
  });

  return z
    .object({
      // Step 0 — owner account
      name: z.string().min(2, t("required")),
      email: z.string().email(t("email")),
      phone: z.string().min(6, t("phone")),
      password: z.string().min(8, t("passwordMin")),
      password_confirmation: z.string().min(8, t("passwordMin")),
      // Step 1 — space info
      workspace_name: z.string().min(2, t("required")),
      description: z.string().min(1, t("required")),
      capacity: z.number({ message: t("required") }).min(1, t("required")),
      hours: z.string().min(1, t("required")),
      // Step 2 — location
      city_id: z.string().min(1, t("required")),
      area: z.string().min(1, t("required")),
      address: z.string().min(1, t("required")),
      lat: z.number(),
      lng: z.number(),
      // Step 3 — seat types + amenities
      seat_types: z
        .array(seatType)
        .refine((rows) => rows.some((r) => r.enabled), { message: t("minSeat") }),
      amenities: z.array(z.string()).optional(),
      // Step 4 — documents
      license_file: fileSchema(t),
      id_document: fileSchema(t),
      terms: z.literal(true, { message: t("terms") }),
    })
    .refine((d) => d.password === d.password_confirmation, {
      message: t("passwordMatch"),
      path: ["password_confirmation"],
    });
}
export type WorkspaceRegisterValues = z.infer<
  ReturnType<typeof workspaceRegisterSchema>
>;

export const WORKSPACE_STEP_FIELDS: (keyof WorkspaceRegisterValues)[][] = [
  ["name", "email", "phone", "password", "password_confirmation"],
  ["workspace_name", "description", "capacity", "hours"],
  ["city_id", "area", "address"],
  ["seat_types", "amenities"],
  ["license_file", "id_document", "terms"],
];

/* ---------------------------------------------------------------------------
 * SSO onboarding — a freshly-provisioned SSO user picks a role and completes
 * the role-specific data. Account identity (name/email/password) already exists,
 * so these schemas omit it; documents are collected later, not at onboarding.
 * ------------------------------------------------------------------------- */

/** The selectable gender values; empty string means "prefer not to say". */
export const GENDER_OPTIONS = ["male", "female"] as const;
export type GenderCode = (typeof GENDER_OPTIONS)[number];

/** Optional gender field: empty string (unspecified) or one of the codes. */
const genderField = z.enum(["", ...GENDER_OPTIONS]);

export function freelancerOnboardingSchema(t: Translate) {
  return z.object({
    // Optional: the phone is provided by the Taqat SSO on provision, so we don't
    // re-require/confirm it here. Validated only for length when actually filled.
    phone: z.string().trim().max(30, t("phone")),
    gender: genderField,
    specialty: z.string().min(1, t("required")),
    bio: z.string().optional(),
  });
}
export type FreelancerOnboardingValues = z.infer<
  ReturnType<typeof freelancerOnboardingSchema>
>;

export function ownerOnboardingSchema(t: Translate) {
  const numericText = z
    .string()
    .refine((v) => v.trim() === "" || Number(v) >= 0, t("required"));

  const seatType = z.object({
    type: z.enum(SEAT_TYPE_CODES),
    enabled: z.boolean(),
    price_monthly: numericText,
    price_daily: numericText,
    capacity: numericText,
  });

  return z.object({
    // Optional at onboarding — the phone comes from the Taqat SSO on provision.
    phone: z.string().trim().max(30, t("phone")),
    gender: genderField,
    workspace_name: z.string().min(2, t("required")),
    description: z.string().min(1, t("required")),
    capacity: z.number({ message: t("required") }).min(1, t("required")),
    hours: z.string().min(1, t("required")),
    city_id: z.string().min(1, t("required")),
    area: z.string().min(1, t("required")),
    address: z.string().min(1, t("required")),
    lat: z.number(),
    lng: z.number(),
    seat_types: z
      .array(seatType)
      .refine((rows) => rows.some((r) => r.enabled), { message: t("minSeat") }),
    amenities: z.array(z.string()).optional(),
  });
}
export type OwnerOnboardingValues = z.infer<
  ReturnType<typeof ownerOnboardingSchema>
>;

export const AMENITY_CODES = [
  "wifi",
  "printer",
  "meeting_room",
  "parking",
  "coffee",
  "kitchen",
  "snow",
] as const;

export const SPECIALTY_OPTIONS = [
  "software",
  "design",
  "writing",
  "marketing",
  "other",
] as const;
