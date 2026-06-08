"use client";

import { TileLogo } from "./TileLogo";
import { useBranding } from "@/components/providers/BrandingProvider";

export interface BrandLogoProps {
  /** Height of the rendered logo image, in px. Mirrors the TileLogo `size`. */
  size?: number;
  /** Accessible label; defaults to the admin site name (or "TAQAT"). */
  alt?: string;
  /**
   * Force the dark-mode logo regardless of the active theme. Use on always-dark
   * surfaces (e.g. the login art panel) so the logo reads against the dark bg.
   */
  forceDark?: boolean;
}

/**
 * The brand mark, rendered consistently everywhere.
 *
 * When the admin has uploaded logos, both the light and dark variants are
 * rendered as `<img>` and toggled purely via CSS (`[data-theme]`), so the right
 * one shows for the active theme with no hydration flash — even inside client
 * components, with no prop-drilling. When a theme's logo is unset we fall back
 * to the other one (a single logo still works), and when neither is set we fall
 * back to the built-in {@link TileLogo} motif.
 *
 * The logo URLs come from {@link useBranding} (server-fetched in the root
 * layout), so this never refetches and never flashes.
 */
export function BrandLogo({ size = 26, alt, forceDark }: BrandLogoProps) {
  const branding = useBranding();
  const light = branding.logo_lightUrl;
  const dark = branding.logo_darkUrl;
  const label = alt ?? branding.site_name ?? "TAQAT";

  // No admin logos at all → built-in tile motif.
  if (!light && !dark) {
    return <TileLogo size={size} />;
  }

  // A single logo (only one variant set) is used for both themes.
  const lightSrc = light ?? dark;
  const darkSrc = dark ?? light;
  const single = lightSrc === darkSrc;

  // Always-dark surface: render only the dark variant, theme-independent.
  if (forceDark) {
    return (
      // eslint-disable-next-line @next/next/no-img-element
      <img
        src={darkSrc}
        alt={label}
        className="brand-logo-img"
        style={{ height: size, width: "auto" }}
      />
    );
  }

  return (
    <span className="brand-logo" style={{ height: size, display: "inline-flex" }}>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={lightSrc}
        alt={label}
        className={single ? "brand-logo-img" : "brand-logo-img is-light"}
        style={{ height: size, width: "auto" }}
      />
      {!single && (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={darkSrc}
          alt={label}
          className="brand-logo-img is-dark"
          style={{ height: size, width: "auto" }}
        />
      )}
    </span>
  );
}
