# Frontend Motion & Polish Foundation — Design Spec

- **Date:** 2026-06-11
- **Branch:** `feat/frontend-motion-polish`
- **Status:** Approved (design) — pending implementation plan

## 1. Context

TaqatSpace's frontend (Next.js 16 App Router, TS, next-intl ar-RTL/en-LTR,
light/dark) ships a **pure-CSS** design system ported from `DOCS/Desing`:
- Motion tokens in `src/styles/tokens.css`: `--t-fast` (150ms), `--t-base`
  (200ms), `--t-slow` (250ms), all sharing `cubic-bezier(.2,0,0,1)`.
- 8 keyframes already exist (`fade`, `pop`, `slidein`, `shimmer`, `btnspin`,
  `loaderBar`, `marq`, `ed-rise`) and ~54 `transition:` declarations.
- `prefers-reduced-motion: reduce` is already respected in 6 places.
- **No animation library** is installed (and none will be added).

## 2. Goal & non-goals

**Goal:** add a small, reusable **motion vocabulary** and a subtle, professional
polish pass to the **global UI primitives** (buttons, cards, modals/drawers,
fields, tabs, tables/stat grids) plus light route transitions — so motion feels
consistent and intentional across the whole app, built entirely on the existing
pure-CSS token system.

**Taste:** subtle & polished (B2B-appropriate restraint), not flashy.

**Non-goals (YAGNI):**
- No Framer Motion or any animation dependency.
- No parallax, scroll-jacking, hero video, or "wow" set-pieces.
- No structural/architecture refactor in this spec (a later, separate effort).
- No redesign of components — only motion/transition refinement + utilities.

## 3. Architecture

Three additive pieces; everything else is small edits to existing primitives.

### 3.1 Motion foundation — `src/styles/motion.css`

A new stylesheet imported once where the global styles are composed (alongside
`tokens.css`/`app.css`). It defines:

**Added tokens** (extend, don't replace `--t-*`):
- `--ease-emphasized: cubic-bezier(.2, .8, .2, 1)` — entrances/reveals.
- `--ease-spring: cubic-bezier(.34, 1.56, .64, 1)` — gentle overshoot for
  delight accents (used sparingly).

**Utility classes** (the complete vocabulary — composable, opacity/transform only):
- `.u-press` — `:active { transform: scale(.97) }` tactile feedback for buttons
  and clickable rows; `transition: transform var(--t-fast)`.
- `.u-lift` — hover `translateY(-2px)` + shadow step-up for cards/tiles.
- `.u-reveal` — initial `opacity: 0; transform: translateY(8px)`; when `.is-in`
  is present → animates to `opacity: 1; transform: none` over `--t-slow`
  `--ease-emphasized`.
- **Stagger:** elements set `style={{ "--i": index }}`; `.u-reveal` uses
  `transition-delay: calc(var(--i, 0) * 40ms)` (capped — see §6).

**Reduced-motion:** a single `@media (prefers-reduced-motion: reduce)` block
neutralizes `.u-press`/`.u-lift`/`.u-reveal` (no transform, no delay, content
shown instantly).

### 3.2 `<Reveal>` component — `src/components/ui/Reveal.tsx`

The only JavaScript in this work. A thin client wrapper:
- Renders a configurable element (`as` prop, default `div`) with class
  `u-reveal` + any passed `className`, and `style={{ "--i": index }}` when an
  `index` is given.
- On mount, an `IntersectionObserver` adds `is-in` when the element enters the
  viewport (threshold ~0.1, `rootMargin` a little early), then disconnects
  (one-shot).
- **Progressive enhancement / SSR-safe:** the server-rendered markup includes
  `is-in` by default (visible). The client removes it on mount *only if* the
  element is not yet in view AND reduced-motion is not requested; otherwise it
  stays visible. Net effect: no-JS, JS-error, or reduced-motion users always see
  content (never hidden), while JS users get the reveal.
- No external deps; ~40 lines.

**Interface:**
```tsx
<Reveal as="li" index={i} className="...">{children}</Reveal>
```
- `as?: keyof JSX.IntrinsicElements` (default `"div"`)
- `index?: number` (stagger order)
- `className?: string`, `children: ReactNode`
- passes through remaining props to the element.

### 3.3 Route transitions — `template.tsx`

Add `template.tsx` to the dashboard route group and the public route group.
App Router re-mounts `template` on every navigation, so a CSS entrance class
(`u-reveal` + `is-in` applied immediately, i.e. a one-shot fade-rise) plays per
route change. Reduced-motion neutralizes it. No JS.

## 4. Primitive polish pass

Refine existing components/CSS (no API changes) to use the vocabulary:

| Primitive | Change |
|---|---|
| `Button` | `.u-press` tactile active-scale; ensure hover uses `--t-fast`. Spinner unchanged. |
| `Card` / `StatTile` | `.u-lift` on interactive cards; consistent shadow/`translateY` transition. |
| `Modal` / `Drawer` | Refine: backdrop `fade`, panel scale-in (modal) / `slidein` (drawer) on `--ease-emphasized`; verify no janky exit. |
| `Field` / `Input` | Focus-ring transition; a *very* subtle error nudge (2–3px, one cycle) gated by reduced-motion. |
| `Tabs` | Animated active-indicator slide (transform/opacity). |
| Tables / stat grids | Wrap first-paint rows/tiles in `<Reveal index>` for a gentle staggered entrance. |
| Toasts | Already `slidein`; align easing only. |

Each change is independent and small; none alters component props or markup
contracts beyond adding classes / an optional wrapper.

## 5. File / module summary

- **New:** `src/styles/motion.css`, `src/components/ui/Reveal.tsx`,
  `app/[locale]/(dashboard)/template.tsx`, `app/[locale]/(public)/template.tsx`.
- **Edited (small):** the global style entry (import `motion.css`), `Button`,
  `Card`/`StatTile`, `Modal`, `Drawer`, `Field`/`Input`, `Tabs`, and the
  table/stat-grid call sites that opt into staggered reveal.

Each unit has one purpose: `motion.css` = the vocabulary; `Reveal` = "play when
visible"; `template.tsx` = per-route entrance; primitive edits = apply the
vocabulary. They communicate only through class names + the `--i` variable.

## 6. Performance & accessibility

- **GPU-friendly:** animate only `opacity` and `transform` (no width/height/top
  layout properties) → no layout thrash, no CLS.
- **No CLS:** revealed elements reserve their space (opacity/transform only); the
  SSR default-visible markup means no first-paint jump.
- **Stagger cap:** `--i` delay is capped (e.g. effective max ~6 steps / 240ms) so
  long lists don't feel slow; beyond the cap, items share the max delay.
- **Reduced-motion:** every utility + `template` entrance is neutralized under
  `prefers-reduced-motion: reduce`.
- **No JS animation loops:** IntersectionObserver only toggles a class once.

## 7. Verification

- `npm run lint`, `tsc --noEmit`, `next build` all green.
- Manual pass: ar-RTL + en-LTR × light + dark × normal + reduced-motion.
- Spot-check: no content hidden with JS disabled; no CLS regression on key pages
  (home, explore, a dashboard table); transitions feel subtle, not distracting.

## 8. Out of scope / follow-ups

- Broader frontend **structure/architecture** changes (the user's larger goal) —
  a separate spec after this motion foundation lands.
- Page-specific bespoke animations (marketing hero set-pieces, etc.).
