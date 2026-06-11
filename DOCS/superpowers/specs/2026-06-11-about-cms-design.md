# Full About-Page CMS — Design Spec

- **Date:** 2026-06-11
- **Branch:** `feat/about-cms`
- **Status:** Approved (design) — pending implementation plan

## 1. Context & problem

The public About page (`frontend/src/app/[locale]/(public)/about/page.tsx`) is
~90% hard-coded from the i18n dict (`dict.about.*`): eyebrow, title, lead, 3
stats, mission, vision, 4 values, CTA. Only `lead` + text `sections` come from
the CMS (`getContent("about")` → `cmsText()`), and the "images" are decorative
`<ImgPlaceholder>` boxes — there is **no image field at all**. So an admin
"editing About" can change two text blobs and never see an image change.

**Goal:** make the **entire existing About layout** editable from the admin
panel, including **real uploaded images** replacing the placeholders, while the
page still falls back to today's i18n defaults until content is entered.

This mirrors the existing **landing CMS** (`LandingEditor` + `LandingContentService`),
which already does bilingual text fields + image upload (cropper) + path→URL
resolution. We replicate that proven pattern for About.

## 2. Non-goals (YAGNI)

- No free-form block/section builder or section reordering (the About layout is
  fixed; only its content is editable). The existing optional text `sections[]`
  stay, now with an optional image each.
- Value/mission/vision **icons stay fixed** (shield/users/checkCircle/zap, flag,
  eye) — only their text is editable.
- No new analytics, no schema for unrelated pages.

## 3. Content schema (`AboutContent`, bilingual = `LocalizedText {ar?,en?}`)

```ts
interface AboutStat { value?: string; label?: LocalizedText }            // value is a locale-neutral number string ("+128")
interface AboutValue { title?: LocalizedText; body?: LocalizedText }     // icon is fixed by position
interface AboutSection {                                                 // existing, + optional image
  heading?: LocalizedText; body?: LocalizedText;
  image?: string; imageUrl?: string;
}
interface AboutContent {
  eyebrow?: LocalizedText;
  title1?: LocalizedText; title2?: LocalizedText;   // title2 = the highlighted span
  lead?: LocalizedText;
  stats?: AboutStat[];                              // up to 3
  mission?: { title?: LocalizedText; body?: LocalizedText };
  vision?:  { title?: LocalizedText; body?: LocalizedText };
  valuesTitle?: LocalizedText;
  values?: AboutValue[];                            // up to 4
  sections?: AboutSection[];
  cta?: { title?: LocalizedText; body?: LocalizedText };
  image?: string; imageUrl?: string;                // hero collage main
  imageSecondary?: string; imageSecondaryUrl?: string; // hero collage secondary
}
```
`*Url` fields are **read-only**, injected by the backend on read and stripped
before save (exactly like landing's image slots).

## 4. Architecture — mirror the landing CMS

### Backend (`backend/`)
- **`AboutContentService`** (mirror `LandingContentService`): `get()` (raw blob
  from the `about` `SiteSetting` row — the generic content store already persists
  it), `presented()` (inject resolved `imageUrl`/`imageSecondaryUrl`/section
  `imageUrl` via `App\Support\MediaUrl::resolve`), `update($blob)`, and
  `storeImage($file)` → `{path, url}` on the media disk. Its `IMAGE_PATHS` cover
  `image`, `imageSecondary`, and each `sections[].image`.
- **`AdminAboutController`** (mirror `AdminLandingController`): `show` (presented),
  `update`, `uploadImage`. **Public** `AboutController::show` → presented blob.
- **Routes** (mirror `admin-landing.php` + the public landing route):
  `GET /about-content` (public), `GET/PUT /admin/about`, `POST /admin/about/images`
  (gated by `manage_content`). Validation: reuse the generic permissive content
  validation for the blob (the store already accepts arbitrary JSON); a dedicated
  `UploadAboutImageRequest` mirrors `UploadLandingImageRequest`
  (`image|file|image|mimes:jpg,jpeg,png,webp|max:4096`).

### Frontend (`frontend/`)
- **Types** — replace the thin `AboutContent` in `lib/types/content.ts` with the
  §3 shape (+ `AboutStat`, `AboutValue`, extend `AboutSection`).
- **API/actions** — `getAboutContent()` (mirror `getLanding()` → `/about-content`),
  `updateAbout(blob)` + `uploadAboutImage(file)` (mirror
  `updateLanding`/`uploadLandingImage` in `lib/actions/admin.ts`).
- **`AboutEditor`** (mirror `LandingEditor`, no reorder/workspace/testimonials
  parts): tabbed bilingual inputs for every field + `SectionImageField`
  (cropper via `useImageCropper`) for the 2 hero images + each section image.
  Builds payload by pruning empties + stripping `*Url` keys. Saves via
  `updateAbout`, revalidates the public About page.
- **Admin wiring** — surface `AboutEditor` where About is edited today (the admin
  CRM area that currently renders `ContentManager` for `about`).
- **Public page** — rewire `about/page.tsx` to read every field via `cmsText(...,
  dict fallback)` and render `imageUrl`s with the existing placeholder as
  fallback (no image uploaded → today's look).

## 5. Files

**Backend (new):** `app/Services/AboutContentService.php`,
`app/Http/Controllers/AboutController.php`,
`app/Http/Controllers/Admin/AdminAboutController.php`,
`app/Http/Requests/About/UploadAboutImageRequest.php`,
`routes/api/admin-about.php` (+ a public `GET /about-content` route).
**Frontend (new):** `src/components/features/admin/AboutEditor.tsx`,
`src/lib/api/about.ts`.
**Frontend (edit):** `lib/types/content.ts` (AboutContent),
`lib/actions/admin.ts` (updateAbout + uploadAboutImage),
`app/[locale]/(public)/about/page.tsx` (read all fields + images), the admin CRM
page/component that mounts the About editor.

## 6. Verification

- `php -l` on every PHP file; `composer api:docs` regenerated (new endpoints).
- `tsc --noEmit` + `npm run lint` + `next build` green.
- Manual: edit every field + upload hero/section images in admin → public About
  reflects them (ar + en, light + dark); with NO content saved the page is
  byte-for-byte today's default look (i18n fallback + placeholders).

## 7. Out of scope / follow-ups

- Applying the motion/Reveal animations to About (and faq/contact/login) — the
  separate, already-approved next task, done after this lands.
