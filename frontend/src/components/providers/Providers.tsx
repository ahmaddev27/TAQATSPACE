"use client";

import type { ReactNode } from "react";
import { AuthProvider } from "./AuthProvider";
import { ToastProvider } from "./ToastProvider";
import { ConfirmProvider } from "@/components/ui/ConfirmDialog";

/** Client-side provider tree (Auth + Toast + Confirm dialog). */
export function Providers({ children }: { children: ReactNode }) {
  return (
    <ToastProvider>
      <ConfirmProvider>
        <AuthProvider>{children}</AuthProvider>
      </ConfirmProvider>
    </ToastProvider>
  );
}
