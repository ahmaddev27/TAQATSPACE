"use client";

import { useLocale } from "next-intl";
import { useTransition } from "react";
import { usePathname, useRouter } from "@/i18n/navigation";
import { Icon } from "@/components/ui/Icon";

/** Switches between ar/en while preserving the current pathname. */
export function LanguageToggle() {
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const [isPending, startTransition] = useTransition();

  const next = locale === "ar" ? "en" : "ar";
  const label = next === "en" ? "English" : "العربية";

  function switchLocale() {
    startTransition(() => {
      router.replace(pathname, { locale: next });
    });
  }

  return (
    <button
      type="button"
      className="lang-toggle"
      onClick={switchLocale}
      disabled={isPending}
      aria-label={label}
    >
      <Icon name="globe" size={16} />
      {label}
    </button>
  );
}
