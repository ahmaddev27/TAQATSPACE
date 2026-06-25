import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { Avatar } from "@/components/ui/Avatar";
import { Icon } from "@/components/ui/Icon";
import { StatusBadge } from "@/components/ui/Badge";
import { StatTile } from "@/components/ui/StatTile";
import { Rating } from "@/components/ui/Rating";
import { InvoiceStatusBadge } from "@/components/features/invoices/InvoiceStatusBadge";
import type { AdminWorkspaceDetail } from "@/lib/api/admin";

export interface WorkspaceDetailProps {
  workspace: AdminWorkspaceDetail;
  locale: string;
}

function money(value: string | null): string {
  if (value == null) return "—";
  return `₪ ${value}`;
}

/** Admin workspace detail: profile, owner, seats, members, invoices, reviews. */
export async function WorkspaceDetail({ workspace: w, locale }: WorkspaceDetailProps) {
  const t = await getTranslations("admin.workspaceDetail");
  const tc = await getTranslations("common");

  const seatType = (type: string): string => {
    const key = `seatType.${type}`;
    return tc.has(key) ? tc(key) : type;
  };

  return (
    <div className="page">
      <div className="crumbs" style={{ marginBottom: 16 }}>
        <Link href="/admin/workspaces">{t("back")}</Link>
        <Icon name="chevR" className="chev" size={14} />
        <span style={{ color: "var(--text)" }}>{w.name}</span>
      </div>

      {/* Header */}
      <div className="card card-pad">
        <div className="between wrap" style={{ gap: 16, alignItems: "flex-start" }}>
          <div className="row" style={{ gap: 14, alignItems: "center", minWidth: 0 }}>
            <Avatar initial={w.name.charAt(0)} src={w.logo} alt={w.name} size="lg" round />
            <div className="stack" style={{ gap: 6, minWidth: 0 }}>
              <h1 className="h2">{w.name}</h1>
              <div className="row muted wrap" style={{ gap: 10, fontSize: "var(--fs-sm)" }}>
                <span className="row" style={{ gap: 5 }}>
                  <Icon name="pin" size={15} />
                  {w.city}
                  {w.address ? ` · ${w.address}` : ""}
                </span>
                {Number(w.avg_rating) > 0 && <Rating value={Number(w.avg_rating)} />}
              </div>
            </div>
          </div>
          <div className="row" style={{ gap: 8 }}>
            <StatusBadge status={w.status} locale={locale} />
            <span className={`badge ${w.is_published ? "badge-success" : "badge-neutral"}`}>
              <span className="dot" />
              {w.is_published ? t("published") : t("unpublished")}
            </span>
          </div>
        </div>

        {w.owner && (
          <>
            <div className="divider" style={{ margin: "16px 0" }} />
            <div className="row wrap" style={{ gap: 24 }}>
              <span className="row" style={{ gap: 8 }}>
                <Avatar
                  initial={w.owner.name.charAt(0)}
                  src={w.owner.avatar}
                  alt={w.owner.name}
                  round
                />
                <span className="stack" style={{ gap: 2 }}>
                  <span className="muted-3" style={{ fontSize: "var(--fs-xs)" }}>
                    {t("owner")}
                  </span>
                  <span style={{ fontWeight: 600 }}>{w.owner.name}</span>
                </span>
              </span>
              <span className="muted-3 ltr" style={{ fontSize: "var(--fs-sm)" }}>
                {w.owner.email}
              </span>
              {w.owner.phone && (
                <span className="muted-3 ltr" style={{ fontSize: "var(--fs-sm)" }}>
                  {w.owner.phone}
                </span>
              )}
            </div>
          </>
        )}
      </div>

      {/* Stats */}
      <div className="grid-stats" style={{ marginTop: 18 }}>
        <StatTile icon="grid" label={t("seatsTotal")} value={w.seats.total} foot={`${w.seats.occupied} ${t("occupied")}`} />
        <StatTile icon="grid" label={t("seatsAvailable")} value={w.seats.available} foot={t("readyToAssign")} />
        <StatTile icon="users" label={t("membersCount")} value={w.subscriptions.filter((s) => s.status === "active").length} foot={t("activeSubs")} />
        <StatTile icon="receipt" amber={w.invoices_summary.overdue_count > 0} label={t("unpaid")} value={money(w.invoices_summary.total_unpaid)} foot={`${w.invoices_summary.overdue_count} ${t("overdue")}`} />
      </div>

      {/* Seat types & pricing */}
      <Section title={t("seatTypes")}>
        <div className="table-wrap">
          <table className="tbl">
            <thead>
              <tr>
                <th>{t("type")}</th>
                <th>{t("monthly")}</th>
                <th>{t("daily")}</th>
                <th>{t("capacity")}</th>
                <th>{t("enabled")}</th>
              </tr>
            </thead>
            <tbody>
              {w.seat_types.map((s) => (
                <tr key={s.type}>
                  <td>{seatType(s.type)}</td>
                  <td className="cell-num">{money(s.price_monthly)}</td>
                  <td className="cell-num">{money(s.price_daily)}</td>
                  <td className="cell-num">{s.capacity}</td>
                  <td>{s.enabled ? "✓" : "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Section>

      {/* Members */}
      <Section title={`${t("members")} (${w.subscriptions.length})`}>
        {w.subscriptions.length === 0 ? (
          <p className="muted">{t("noMembers")}</p>
        ) : (
          <div className="table-wrap">
            <table className="tbl">
              <thead>
                <tr>
                  <th>{t("member")}</th>
                  <th>{t("plan")}</th>
                  <th>{t("price")}</th>
                  <th>{t("seat")}</th>
                  <th>{t("status")}</th>
                  <th>{t("end")}</th>
                </tr>
              </thead>
              <tbody>
                {w.subscriptions.map((s) => (
                  <tr key={s.id}>
                    <td>
                      <div className="row" style={{ gap: 8, alignItems: "center" }}>
                        <Avatar initial={(s.member?.name ?? "—").charAt(0)} src={s.member?.avatar} round />
                        <div style={{ minWidth: 0 }}>
                          <div style={{ fontWeight: 600 }}>{s.member?.name ?? "—"}</div>
                          <div className="muted-3 ltr" style={{ fontSize: "var(--fs-xs)" }}>{s.member?.email}</div>
                        </div>
                      </div>
                    </td>
                    <td>{s.plan_type}</td>
                    <td className="cell-num">{money(s.monthly_price)}</td>
                    <td className="cell-num">{s.seat_number ?? "—"}</td>
                    <td><StatusBadge status={s.status} locale={locale} /></td>
                    <td className="cell-num ltr">{s.end_date ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Section>

      {/* Invoices */}
      <Section title={`${t("invoices")} (${w.invoices_summary.total_count})`}>
        {w.invoices.length === 0 ? (
          <p className="muted">{t("noInvoices")}</p>
        ) : (
          <div className="table-wrap">
            <table className="tbl">
              <thead>
                <tr>
                  <th>{t("invoiceNo")}</th>
                  <th>{t("amount")}</th>
                  <th>{t("due")}</th>
                  <th>{t("status")}</th>
                </tr>
              </thead>
              <tbody>
                {w.invoices.map((i) => (
                  <tr key={i.id}>
                    <td className="cell-num ltr" style={{ fontWeight: 600 }}>{i.invoice_number}</td>
                    <td className="cell-num">{money(i.amount)}</td>
                    <td className="cell-num ltr">{i.due_date ?? "—"}</td>
                    <td><InvoiceStatusBadge status={i.status} locale={locale} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Section>

      {/* Reviews */}
      <Section title={`${t("reviews")} (${w.reviews.length})`}>
        {w.reviews.length === 0 ? (
          <p className="muted">{t("noReviews")}</p>
        ) : (
          <div className="stack" style={{ gap: 14 }}>
            {w.reviews.map((r, i) => (
              <div key={i} className="stack" style={{ gap: 4 }}>
                <div className="row between">
                  <span className="row" style={{ gap: 8, fontWeight: 600 }}>
                    {r.reviewer}
                    <Rating value={r.rating} />
                  </span>
                  <span className="muted-3 ltr" style={{ fontSize: "var(--fs-xs)" }}>
                    {r.created_at?.slice(0, 10) ?? ""}
                  </span>
                </div>
                {r.comment && <p className="muted" style={{ fontSize: "var(--fs-sm)" }}>{r.comment}</p>}
              </div>
            ))}
          </div>
        )}
      </Section>

      {/* Profile extras */}
      {(w.description || w.amenities.length > 0) && (
        <Section title={t("profile")}>
          {w.description && <p style={{ lineHeight: 1.8, marginBottom: 12 }}>{w.description}</p>}
          {w.amenities.length > 0 && (
            <div className="row wrap" style={{ gap: 8 }}>
              {w.amenities.map((a) => (
                <span key={a} className="badge badge-neutral">{a}</span>
              ))}
            </div>
          )}
        </Section>
      )}
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="card card-pad" style={{ marginTop: 18 }}>
      <h3 className="h3" style={{ marginBottom: 14 }}>{title}</h3>
      {children}
    </div>
  );
}
