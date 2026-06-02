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

export function workspaceRegisterSchema(t: Translate) {
  const seat = z.object({
    name: z.string().min(1, t("required")),
    price: z.number({ message: t("required") }).min(0, t("required")),
    unit: z.enum(["daily", "monthly"]),
  });

  return z
    .object({
      // Step 0 — space info
      name: z.string().min(2, t("required")),
      description: z.string().min(1, t("required")),
      capacity: z.number({ message: t("required") }).min(1, t("required")),
      hours: z.string().min(1, t("required")),
      // Step 1 — location
      city: z.string().min(1, t("required")),
      area: z.string().min(1, t("required")),
      address: z.string().min(1, t("required")),
      lat: z.number(),
      lng: z.number(),
      // Step 2 — seats + amenities
      seats: z.array(seat).min(1, t("minSeat")),
      amenities: z.array(z.string()).optional(),
      // Step 3 — documents
      license_file: fileSchema(t),
      id_document: fileSchema(t),
      terms: z.literal(true, { message: t("terms") }),
    });
}
export type WorkspaceRegisterValues = z.infer<
  ReturnType<typeof workspaceRegisterSchema>
>;

export const WORKSPACE_STEP_FIELDS: (keyof WorkspaceRegisterValues)[][] = [
  ["name", "description", "capacity", "hours"],
  ["city", "area", "address"],
  ["seats", "amenities"],
  ["license_file", "id_document", "terms"],
];

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
