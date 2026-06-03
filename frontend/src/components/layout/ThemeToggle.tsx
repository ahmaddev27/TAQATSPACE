"use client";

import { useSyncExternalStore } from "react";
import { Icon } from "@/components/ui/Icon";

type Theme = "light" | "dark";

const STORAGE_KEY = "taqat_theme";

function getSnapshot(): Theme {
  return document.documentElement.getAttribute("data-theme") === "dark"
    ? "dark"
    : "light";
}

function getServerSnapshot(): Theme {
  return "light";
}

/** Subscribe to <html data-theme> mutations so the icon stays in sync. */
function subscribe(callback: () => void): () => void {
  const observer = new MutationObserver(callback);
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["data-theme"],
  });
  return () => observer.disconnect();
}

/** Toggles light/dark by setting [data-theme] on <html>; persists in localStorage. */
export function ThemeToggle() {
  const theme = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
  const isDark = theme === "dark";

  function toggle() {
    const next: Theme = isDark ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", next);
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // storage unavailable — ignore
    }
    // Mirror to a cookie so the server renders [data-theme] without a flash.
    document.cookie = `${STORAGE_KEY}=${next}; path=/; max-age=31536000; samesite=lax`;
  }

  return (
    <button
      type="button"
      className="lang-toggle"
      onClick={toggle}
      aria-label={isDark ? "Light mode" : "Dark mode"}
    >
      <Icon name={isDark ? "sun" : "moon"} size={16} />
    </button>
  );
}
