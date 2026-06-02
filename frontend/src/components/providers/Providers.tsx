"use client";

import type { ReactNode } from "react";
import { AuthProvider } from "./AuthProvider";
import { ToastProvider } from "./ToastProvider";

/** Client-side provider tree (Auth + Toast). */
export function Providers({ children }: { children: ReactNode }) {
  return (
    <ToastProvider>
      <AuthProvider>{children}</AuthProvider>
    </ToastProvider>
  );
}
