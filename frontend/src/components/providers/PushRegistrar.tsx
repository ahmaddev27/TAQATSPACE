"use client";

import { useEffect, useRef } from "react";
import { useAuth } from "@/components/providers/AuthProvider";
import { useToast } from "@/components/providers/ToastProvider";
import { isFirebaseConfigured } from "@/lib/firebase/app";
import { registerForPush, unregisterPush } from "@/lib/firebase/messaging";

/**
 * Bridges the auth session to FCM web push. Renders nothing.
 *
 * Mounted inside the dashboard shell (only authenticated, active users reach it).
 * When an authenticated session is present it registers an FCM token with the
 * backend and shows foreground pushes as toasts; on sign-out it unregisters the
 * token. The whole effect is a no-op when Firebase is unconfigured, so the app
 * is unaffected until credentials are added.
 *
 * All work is fire-and-forget and swallowed inside the messaging layer — push
 * setup never blocks rendering and never throws into React.
 */
export function PushRegistrar() {
  const { isAuthenticated } = useAuth();
  const { toast } = useToast();

  // Keep the latest toast fn without re-running the register effect on identity
  // changes — registration should track auth state only.
  const toastRef = useRef(toast);
  useEffect(() => {
    toastRef.current = toast;
  }, [toast]);

  useEffect(() => {
    if (!isFirebaseConfigured() || !isAuthenticated) return;

    void registerForPush((payload) => {
      const note = payload.notification;
      if (!note?.title) return;
      toastRef.current({ tone: "info", title: note.title, body: note.body });
    });

    return () => {
      void unregisterPush();
    };
  }, [isAuthenticated]);

  return null;
}
