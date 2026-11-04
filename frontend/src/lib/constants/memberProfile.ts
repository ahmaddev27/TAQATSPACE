/**
 * Donor-required subscriber option lists. The codes mirror the backend enums
 * (EducationLevel, Specialization, EmploymentStatus, Gender); labels live in
 * the `owner.members.profile` message namespace.
 */
export const GENDERS = ["male", "female"] as const;

export const EDUCATION_LEVELS = [
  "no_formal",
  "primary",
  "lower_secondary",
  "secondary",
  "vocational",
  "intermediate_diploma",
  "bachelor",
  "higher_diploma",
  "master",
  "doctorate",
] as const;

export const SPECIALIZATIONS = [
  "computer_science",
  "software_engineering",
  "information_systems",
  "cybersecurity",
  "data_science",
  "civil_engineering",
  "architectural_engineering",
  "electrical_engineering",
  "mechanical_engineering",
  "chemical_engineering",
  "medicine",
  "nursing",
  "pharmacy",
  "dentistry",
  "medical_laboratory",
  "business_administration",
  "accounting",
  "economics",
  "finance",
  "marketing",
  "law",
  "education",
  "psychology",
  "social_work",
  "arabic_language",
  "english_language",
  "media",
  "graphic_design",
  "agriculture",
  "sciences",
  "mathematics",
  "sharia",
  "political_science",
  "tourism",
  "other",
] as const;

export const EMPLOYMENT_STATUSES = [
  "employed",
  "self_employed",
  "freelancer",
  "unemployed",
  "student",
  "retired",
  "other",
] as const;
