# Frontend Motion & Polish Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a small, reusable pure-CSS motion vocabulary plus a subtle polish pass to the global UI primitives and route transitions, so motion feels consistent and intentional across TaqatSpace's frontend.

**Architecture:** One new stylesheet (`motion.css`) defines easing tokens + composable utility classes (`.u-press`, `.u-lift`, `.u-reveal` + stagger) and enhances existing primitive classes (`.btn`, `.card`, `.stat-tile`, `.input`, `.modal`, `.drawer`, `.tabs`). One tiny SSR-safe `<Reveal>` IntersectionObserver component drives scroll-in reveals. Per-route `template.tsx` files add a one-shot entrance. Everything is `transform`/`opacity` only and neutralized under `prefers-reduced-motion`.

**Tech Stack:** Next.js 16 App Router, TypeScript, pure CSS (no animation library). Verification: `tsc --noEmit`, `npm run lint`, `next build` (no unit-test runner exists in `frontend/` — this is a visual feature, so gates are typecheck/lint/build + a manual visual check).

**Working dir for all commands:** `c:\laragon\www\TaqatSpace\frontend`
**Branch:** `feat/frontend-motion-polish`

---

## File Structure

- **Create** `src/styles/motion.css` — easing tokens, `.u-*` utilities, primitive-polish rules, reduced-motion block. One responsibility: the motion vocabulary + global polish.
- **Modify** `src/app/globals.css` — import `motion.css` last.
- **Create** `src/components/ui/Reveal.tsx` — SSR-safe "play when visible" wrapper. One responsibility: toggle `is-in` on scroll.
- **Create** `src/app/[locale]/(dashboard)/template.tsx` — per-route entrance for dashboards.
- **Create** `src/app/[locale]/(public)/template.tsx` — per-route entrance for public pages.
- **Modify** 2 representative call sites — opt into staggered `<Reveal>` (explore results grid + an owner stat grid).

---

## Task 1: Motion foundation — `motion.css` (tokens + utilities)

**Files:**
- Create: `src/styles/motion.css`
- Modify: `src/app/globals.css:9` (add import after `refine.css`)

- [ ] **Step 1: Create `src/styles/motion.css`**

```css
/* TAQAT — motion vocabulary: easing tokens + composable utilities.
   All effects are transform/opacity only (GPU-friendly, no layout/CLS) and are
   neutralized under prefers-reduced-motion. Builds on the --t-* tokens. */

:root {
  /* Entrances/reveals: decelerate into place. */
  --ease-emphasized: cubic-bezier(0.2, 0.8, 0.2, 1);
  /* Gentle overshoot for occasional delight accents (used sparingly). */
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
  /* Max stagger steps so long lists never feel slow (see .u-reveal). */
  --u-stagger-step: 40ms;
}

/* Tactile press feedback for buttons/clickable rows. */
.u-press {
  transition: transform var(--t-fast);
}
.u-press:active {
  transform: scale(0.97);
}

/* Hover lift for cards/tiles. */
.u-lift {
  transition:
    transform var(--t-base),
    box-shadow var(--t-base);
}
.u-lift:hover {
  transform: translateY(-2px);
  box-shadow: var(--sh-md);
}

/* Reveal: hidden until `.is-in` is added (by <Reveal> or a template). */
.u-reveal {
  opacity: 0;
  transform: translateY(8px);
  transition:
    opacity var(--t-slow) var(--ease-emphasized),
    transform var(--t-slow) var(--ease-emphasized);
  /* Stagger: consumers set style="--i: <n>"; capped at 6 steps (240ms). */
  transition-delay: calc(min(var(--i, 0), 6) * var(--u-stagger-step));
}
.u-reveal.is-in {
  opacity: 1;
  transform: none;
}

@media (prefers-reduced-motion: reduce) {
  .u-press,
  .u-lift,
  .u-reveal {
    transition: none;
    transition-delay: 0ms;
  }
  .u-press:active,
  .u-lift:hover {
    transform: none;
  }
  .u-reveal {
    opacity: 1;
    transform: none;
  }
}
```

- [ ] **Step 2: Import it in `globals.css`**

Change `src/app/globals.css` so the import list ends with `motion.css`:

```css
@import "../styles/refine.css";
@import "../styles/motion.css";
```

- [ ] **Step 3: Typecheck + lint + build**

Run: `npx tsc --noEmit && npm run lint && npm run build`
Expected: tsc exit 0, eslint no output, `next build` completes ("Compiled successfully").

- [ ] **Step 4: Commit**

```bash
git add src/styles/motion.css src/app/globals.css
git commit -m "feat(motion): motion.css vocabulary (press/lift/reveal + tokens)"
```

---

## Task 2: `<Reveal>` component

**Files:**
- Create: `src/components/ui/Reveal.tsx`

- [ ] **Step 1: Create `src/components/ui/Reveal.tsx`**

```tsx
"use client";

import {
  useEffect,
  useRef,
  useState,
  type CSSProperties,
  type ElementType,
  type ReactNode,
} from "react";

export interface RevealProps {
  /** Element to render (default "div"). */
  as?: ElementType;
  /** Stagger order — drives the CSS `--i` delay. */
  index?: number;
  className?: string;
  children: ReactNode;
}

/**
 * Plays the `.u-reveal` entrance when the element scrolls into view. SSR-safe:
 * the server renders it visible (`is-in`), so no-JS / JS-error / reduced-motion
 * users never see hidden content. After hydration the client hides + reveals
 * only when it can observe and motion is allowed.
 */
export function Reveal({ as, index, className, children }: RevealProps) {
  const Tag = (as ?? "div") as ElementType;
  const ref = useRef<HTMLElement | null>(null);
  const [shown, setShown] = useState(true);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const reduce = window.matchMedia?.(
      "(prefers-reduced-motion: reduce)",
    ).matches;
    if (reduce || typeof IntersectionObserver === "undefined") return;

    const rect = el.getBoundingClientRect();
    const inView = rect.top < window.innerHeight && rect.bottom > 0;
    if (inView) return;

    setShown(false);
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            setShown(true);
            observer.disconnect();
          }
        }
      },
      { threshold: 0.1, rootMargin: "0px 0px -40px 0px" },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const classes = ["u-reveal", shown ? "is-in" : "", className ?? ""]
    .filter(Boolean)
    .join(" ");

  return (
    <Tag
      ref={ref as React.Ref<HTMLElement>}
      className={classes}
      style={index != null ? ({ "--i": index } as CSSProperties) : undefined}
    >
      {children}
    </Tag>
  );
}
```

- [ ] **Step 2: Typecheck + lint + build**

Run: `npx tsc --noEmit && npm run lint && npm run build`
Expected: tsc exit 0, eslint no output, build "Compiled successfully".

- [ ] **Step 3: Commit**

```bash
git add src/components/ui/Reveal.tsx
git commit -m "feat(motion): SSR-safe <Reveal> IntersectionObserver wrapper"
```

---

## Task 3: Global primitive polish (CSS)

Enhance existing primitive classes from `motion.css` — DRY (applies app-wide without editing each component's markup). Append to `src/styles/motion.css`.

**Files:**
- Modify: `src/styles/motion.css` (append below the utilities)

- [ ] **Step 1: Append primitive-polish rules to `src/styles/motion.css`**

```css
/* ---- Primitive polish: refine existing classes, transform/opacity only ---- */

/* Buttons: tactile press (the spinner / disabled states are untouched). */
.btn:not(:disabled):active {
  transform: scale(0.97);
}

/* Cards & stat tiles: a subtle hover lift for the interactive ones. The
   existing `.card-hover` opt-in keeps non-interactive cards flat. */
.card-hover,
.stat-tile {
  transition:
    transform var(--t-base),
    box-shadow var(--t-base),
    border-color var(--t-base);
}
.card-hover:hover,
.stat-tile:hover {
  transform: translateY(-2px);
  box-shadow: var(--sh-md);
}

/* Inputs: smooth the focus ring/border instead of snapping. */
.input {
  transition:
    border-color var(--t-fast),
    box-shadow var(--t-fast),
    background var(--t-fast);
}

/* A very subtle one-shot nudge for a field flagged invalid. */
@keyframes u-field-nudge {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-3px); }
  75% { transform: translateX(3px); }
}
.input[aria-invalid="true"] {
  animation: u-field-nudge 220ms var(--ease-emphasized);
}

/* Tabs: animate the active state transition. */
.tabs button {
  transition:
    color var(--t-fast),
    background var(--t-fast);
}

@media (prefers-reduced-motion: reduce) {
  .btn:not(:disabled):active,
  .card-hover:hover,
  .stat-tile:hover {
    transform: none;
  }
  .input[aria-invalid="true"] {
    animation: none;
  }
}
```

- [ ] **Step 2: Typecheck + lint + build**

Run: `npx tsc --noEmit && npm run lint && npm run build`
Expected: all green.

- [ ] **Step 3: Manual visual check**

Run `npm run dev`, then in the browser: hover a dashboard stat tile (lifts), press a button (scales), focus an input (ring eases in). Toggle dark mode — still subtle. Confirm nothing feels distracting.

- [ ] **Step 4: Commit**

```bash
git add src/styles/motion.css
git commit -m "feat(motion): subtle polish for buttons/cards/inputs/tabs"
```

---

## Task 4: Route transitions — `template.tsx`

App Router re-mounts `template.tsx` on every navigation, so a one-shot CSS entrance plays per route change.

**Files:**
- Create: `src/app/[locale]/(dashboard)/template.tsx`
- Create: `src/app/[locale]/(public)/template.tsx`

- [ ] **Step 1: Add the entrance class to `motion.css`**

Append to `src/styles/motion.css`:

```css
/* Per-route entrance (App Router template.tsx). One-shot fade-rise. */
@keyframes u-route-in {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: none; }
}
.u-route {
  animation: u-route-in var(--t-base) var(--ease-emphasized) both;
}
@media (prefers-reduced-motion: reduce) {
  .u-route { animation: none; }
}
```

- [ ] **Step 2: Create `src/app/[locale]/(dashboard)/template.tsx`**

```tsx
/**
 * App Router re-mounts this on every navigation within the dashboard group, so
 * the `.u-route` one-shot entrance plays per route change (reduced-motion safe).
 */
export default function DashboardTemplate({
  children,
}: {
  children: React.ReactNode;
}) {
  return <div className="u-route">{children}</div>;
}
```

- [ ] **Step 3: Create `src/app/[locale]/(public)/template.tsx`**

```tsx
/**
 * Per-route entrance for the public marketing pages (re-mounts on navigation).
 */
export default function PublicTemplate({
  children,
}: {
  children: React.ReactNode;
}) {
  return <div className="u-route">{children}</div>;
}
```

- [ ] **Step 4: Typecheck + lint + build**

Run: `npx tsc --noEmit && npm run lint && npm run build`
Expected: all green.

- [ ] **Step 5: Manual visual check**

`npm run dev` → navigate between dashboard pages and between public pages: each route fades-rises in subtly. Confirm no layout shift and that a hard refresh isn't janky.

- [ ] **Step 6: Commit**

```bash
git add src/styles/motion.css "src/app/[locale]/(dashboard)/template.tsx" "src/app/[locale]/(public)/template.tsx"
git commit -m "feat(motion): subtle per-route entrance transitions"
```

---

## Task 5: Opt-in staggered reveals (representative call sites)

Demonstrate the stagger on two real lists: the explore results grid and an owner stat grid.

**Files:**
- Modify: `src/components/features/public/ExploreResults.tsx`
- Modify: `src/components/features/owner/ResourcesTable.tsx`

- [ ] **Step 1: Find the explore results grid markup**

Run: `grep -nE "WorkspaceCard|\.map\(|grid" src/components/features/public/ExploreResults.tsx | head`
Expected: a `.map(...)` rendering `WorkspaceCard` items inside a grid container.

- [ ] **Step 2: Wrap each explore result in `<Reveal index>`**

Import at the top of `ExploreResults.tsx`:

```tsx
import { Reveal } from "@/components/ui/Reveal";
```

Wrap each mapped card (replace the existing `<WorkspaceCard ... />` map body) so each item reveals with a stagger — e.g.:

```tsx
{items.map((w, i) => (
  <Reveal key={w.id} index={i}>
    <WorkspaceCard workspace={w} {/* keep existing props */} />
  </Reveal>
))}
```

(Use the file's actual variable names for the list + item props; only the wrapper + `index={i}` are new.)

- [ ] **Step 3: Wrap the owner resource stat tiles in `<Reveal index>`**

In `src/components/features/owner/ResourcesTable.tsx`, import `Reveal` and wrap each `<StatTile … />` inside the `.grid-stats` block with `<Reveal index={i}>` (pass the tile's position as `index`). Keep all existing `StatTile` props.

- [ ] **Step 4: Typecheck + lint + build**

Run: `npx tsc --noEmit && npm run lint && npm run build`
Expected: all green.

- [ ] **Step 5: Manual visual check**

`npm run dev` → open `/explore` (cards stagger-reveal as they enter the viewport) and the owner resources page (stat tiles stagger in). Disable JS (or set reduced-motion) → content still fully visible.

- [ ] **Step 6: Commit**

```bash
git add src/components/features/public/ExploreResults.tsx src/components/features/owner/ResourcesTable.tsx
git commit -m "feat(motion): staggered reveal on explore grid + owner stats"
```

---

## Task 6: Final verification pass

**Files:** none (verification only)

- [ ] **Step 1: Full gates**

Run: `npx tsc --noEmit && npm run lint && npm run build`
Expected: tsc exit 0, eslint clean, `next build` "Compiled successfully".

- [ ] **Step 2: Manual matrix check**

`npm run dev` and verify across: Arabic (`/`) RTL + English (`/en`) LTR × light + dark × normal + OS reduced-motion. Check: buttons press, cards/tiles lift, inputs focus-ease, routes fade-rise, explore/stats stagger — all subtle. With reduced-motion on, motion is off but nothing is hidden or broken. No content shift (CLS) on first paint of home, explore, and a dashboard table.

- [ ] **Step 3: Push the branch**

```bash
git push origin feat/frontend-motion-polish
```

---

## Self-review notes

- **Spec coverage:** §3.1 → Task 1; §3.2 → Task 2; §4 primitives → Task 3; §3.3 route transitions → Task 4; stagger demo → Task 5; §7 verification → Task 6. All spec sections map to a task.
- **No placeholders:** all CSS/TSX is concrete; Task 5 intentionally defers to the file's real variable names (the only non-literal), with a grep step to surface them.
- **Type consistency:** `RevealProps` (`as`/`index`/`className`/`children`) is used identically in Tasks 2 and 5; the `--i` CSS variable is defined in Task 1 and consumed by Task 5; `.u-route` defined in Task 4 step 1 and used in steps 2–3.
