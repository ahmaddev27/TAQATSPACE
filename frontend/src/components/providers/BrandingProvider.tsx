"use client";

import { createContext, useContext, type ReactNode } from "react";
import type { Branding } from "@/lib/types";

/**
 * Holds the admin-set branding (logo URLs, site name) for the whole app. Seeded
 * once from the server in the root layout and read by client components
 * (Sidebar, PublicHeader, BrandLogo) without prop-drilling or importing any
 * server-only module. Defaults to `{}` so the app falls back to built-in
 * branding when nothing is configured.
 */
const BrandingContext = createContext<Branding>({});

export interface BrandingProviderProps {
  branding: Branding;
  children: ReactNode;
}

export function BrandingProvider({ branding, children }: BrandingProviderProps) {
  return (
    <BrandingContext.Provider value={branding}>
      {children}
    </BrandingContext.Provider>
  );
}

/** The active site branding (logo URLs, site name). */
export function useBranding(): Branding {
  return useContext(BrandingContext);
}
