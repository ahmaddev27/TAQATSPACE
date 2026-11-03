import { notFound } from "next/navigation";
import { getTranslations, setRequestLocale } from "next-intl/server";
import { ForbiddenError } from "@/lib/api";
import { ownerRouter } from "@/lib/api/routers";
import { CommandsTable } from "@/components/features/owner/CommandsTable";
import { CreateRouterForm } from "@/components/features/owner/CreateRouterForm";
import { InstallCommand } from "@/components/features/owner/InstallCommand";
import { RouterCard } from "@/components/features/owner/RouterCard";
import type { OwnerRouter } from "@/lib/types/router";

export const dynamic = "force-dynamic";

/** A workspace without the `network` flag gets a 403 from the API: the page does not exist for it. */
async function loadRouter(): Promise<OwnerRouter | null> {
  try {
    return await ownerRouter();
  } catch (error) {
    if (error instanceof ForbiddenError) notFound();
    throw error;
  }
}

export default async function ApiIntegrationPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale);
  const t = await getTranslations("integration");
  const router = await loadRouter();

  return (
    <div className="page">
      <div className="page-head">
        <div>
          <h1 className="h1">{t("title")}</h1>
          <p className="muted" style={{ marginTop: 5 }}>
            {t("subtitle")}
          </p>
        </div>
      </div>

      {router === null ? (
        <div className="card card-pad">
          <CreateRouterForm />
        </div>
      ) : (
        <div className="stack" style={{ gap: 18 }}>
          <div className="card card-pad">
            <RouterCard router={router} />
          </div>
          <div className="card card-pad stack" style={{ gap: 12 }}>
            <h4 className="h4">{t("install.title")}</h4>
            <InstallCommand routerId={router.id} />
          </div>
          <div className="card card-pad stack" style={{ gap: 12 }}>
            <h4 className="h4">{t("commands.title")}</h4>
            <CommandsTable commands={router.commands ?? []} />
          </div>
        </div>
      )}
    </div>
  );
}
