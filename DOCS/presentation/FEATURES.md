# TaqatSpace — Detailed Features, Flows & User Stories
# طاقة سبيس — المرجع التفصيلي للميزات والتدفّقات وقصص المستخدم

> Companion reference for the features deck (`taqatspace-features.html` / `.pdf`).
> Bilingual. Screenshots live in `./img` (captured from the design prototype).

---

## 0. Platform summary · ملخّص المنصّة

TaqatSpace is a bilingual (Arabic-first, RTL + English) coworking-space marketplace
and management platform for Gaza, with three roles — **Freelancer**, **Workspace
Owner**, **Super Admin** — plus a public site.

- **Backend:** Laravel 13 (thin controllers → services → repositories, FormRequests,
  API Resources, enums), MySQL 8, AWS S3 (presigned media URLs).
- **Frontend:** Next.js 16 (App Router, RSC, Server Actions), next-intl (AR/EN, RTL),
  httpOnly-cookie ↔ Bearer auth bridge.
- **Realtime:** Firebase — Firestore chat + FCM web push.
- **Auth:** Laravel Sanctum + “Sign in with Taqat” SSO (OIDC Authorization Code + PKCE).
- **Authorization:** Spatie roles (`super_admin` / `admin`) + 7 granular permissions.
- **Billing model:** admin-managed **manual tracking with receipts — NO payment
  gateway, NO self-serve plans.** Invoices are records, not charges.

---

## 1. Public site · الموقع العام

**Screens:** `01-public-home`, `02-public-explore`, `03-public-detail`,
`04-register-freelancer`, `05-register-workspace`, `06-login`.

### Features
- CMS-managed landing page: reorderable sections (hero, stats, why-us, how-it-works,
  featured workspaces, testimonials), edited by the admin with no deploy.
- Subtle on-scroll reveal animations (reduced-motion safe).
- Explore: search + filters by city, price range, rating, seat type.
- Workspace detail: photo gallery, amenities, seat-type pricing, map, reviews, “Request booking”.
- Registration: freelancer (3-step) and workspace owner (4-step → pending admin approval).
- Login: email/password + “Sign in with Taqat” SSO.

### User stories
- كزائر، أريد أن أفهم الخدمة بسرعة وأرى مساحات مميّزة. / As a visitor, I want to grasp the offering and see featured spaces.
- كعامل مستقل، أريد تصفية المساحات حسب المدينة والسعر والتقييم. / As a freelancer, I want to filter by city, price and rating.
- أريد رؤية الصور والمرافق والتقييمات قبل الحجز. / I want photos, amenities and reviews before booking.

### Flow
Home → Explore (filter) → Workspace detail → Register / Login → role onboarding.

---

## 2. Auth, SSO & onboarding · الدخول والهويّة

### Features
- OIDC Authorization Code + PKCE; claims read from `/userinfo`.
- New SSO accounts complete a **role-selection onboarding** step (freelancer / owner).
- Token stored in an httpOnly cookie; all authenticated calls run server-side.
- RP-initiated **single logout** at the IdP (`sso_logout_url` returned by `POST /logout`).
- **Phone is provided by the IdP** — no longer re-confirmed on our side at onboarding.

### Flow
“Sign in with Taqat” → IdP consent → callback → user provisioned → (if new) pick role
→ freelancer active immediately / owner pending admin approval.

---

## 3. Freelancer · العامل المستقل

**Screens:** `20-freelancer-home`, `21-freelancer-subscription`, `22-freelancer-invoices`,
`23-freelancer-notifications`, `24-freelancer-profile`, `53-freelancer-home-en`.

### Features
- Home: subscription status, assigned seat, next invoice, unread notifications, quick links,
  and a clear “no active subscription” empty state.
- Subscription: space, seat, price, period, cancel.
- Invoices: full history, status, due/paid dates, total paid, PDF download, overdue banner.
- Notifications center: feed + mark read.
- Profile: avatar upload (image cropper), personal info, password change.
- Realtime chat with workspace owners and platform admins.

### User stories
- أريد رؤية حالة اشتراكي ومقعدي وفاتورتي القادمة فوراً. / I want my subscription, seat and next invoice at a glance.
- أريد سجلّ فواتيري وتنزيل PDF وتنبيهاً بالمتأخّر. / I want invoice history, PDF, and an overdue alert.
- أريد طلب حجز مقعد في مساحة تناسبني. / I want to request a seat in a space that suits me.

### Flow (booking → active)
Explore → open space → send booking request (seat type + message) → owner approves &
assigns a seat → active subscription auto-created → invoices + seat-assigned notification arrive.

---

## 4. Workspace owner · صاحب المساحة

**Screens:** `10-owner-dashboard`, `11-owner-members`, `12-owner-seats`, `13-owner-requests`,
`14-owner-invoices`, `15-owner-packages`, `16-owner-reports`, `52-owner-invoices-dark`.

### Features
- Dashboard: members, occupied seats, invoices, tracked revenue (owner bookkeeping — not gateway money).
- Members: table + detail drawer (subscription, seat, invoices); avatars shown.
- Seats: interactive seat map with seat types and statuses; assign/unassign.
- Booking requests: review → **approve (creates subscription + occupies seat atomically)** or reject.
- Invoices: monthly generation, mark-paid, overdue, reminders, PDF.
- Internet packages: create and assign to members.
- Expenses, resources, announcements, broadcast messaging.
- Reports + charts + export. Realtime chat with members and admins.

### User stories
- أريد لمحة عن المشتركين والإشغال والإيرادات. / I want an at-a-glance view of members, occupancy and revenue.
- أريد مراجعة طلبات الحجز والموافقة بتخصيص مقعد. / I want to review requests and approve by assigning a seat.
- أريد توليد الفواتير الشهريّة ومتابعة المدفوع والمتأخّر. / I want monthly invoices and to track paid vs overdue.

### Flow
Register space → admin approves → set up seats & packages → receive booking requests →
approve/assign → manage members → invoice → analyze reports.

---

## 5. Super admin · السوبر أدمن

**Screens:** `30-admin-analytics`, `31-admin-workspaces`, `32-admin-users`,
`33-admin-finance`, `51-admin-analytics-en`.

### Features
- Analytics: workspaces / users / subscriptions / invoices counters; revenue tracking
  (collected vs outstanding); city + gender distributions.
- Workspaces: approve / suspend (owner notified of the change).
- Users: filterable directory, activate/suspend, detail page, CSV export; user notified on
  suspend/reactivate.
- Finance: platform-wide subscriptions and invoices.
- **Admin management:** create sub-admins; roles (super/standard) + 7 named permissions;
  every API route is permission-gated and unavailable UI is hidden.
  - super_admin holds **all** permissions; a standard admin holds **only its direct grants**.
- Landing CMS: edit copy, images, section order, featured workspaces, how-it-works steps.

### User stories
- أريد لوحة بمؤشّرات المنصّة كلّها. / I want a platform-wide KPI dashboard.
- أريد الموافقة على المساحات الجديدة أو تعليقها. / I want to approve or suspend workspaces.
- أريد إنشاء مدراء فرعيّين بصلاحيّات دقيقة. / I want sub-admins with fine-grained permissions.
- أريد تحرير الصفحة الرئيسيّة دون مطوّر. / I want to edit the landing page without a developer.

---

## 6. Cross-cutting systems · الأنظمة المشتركة

### Realtime chat (Firestore)
Role-scoped contacts; attachments stored on S3 (signed URLs); the sender's avatar beside
each message; an unread-messages badge next to the Chat nav item.

### Notifications (16 types · database + email + FCM web push)
`new_booking_request`, `booking_approved`, `booking_rejected`, `invoice_created`,
`invoice_paid`, `invoice_overdue`, `invoice_reminder`, `subscription_expiring`,
`seat_assigned`, `new_message`, `new_announcement`, `new_review`,
`new_workspace_registration`, `account_status_changed`, `workspace_status_changed`,
`reset_password`. Bilingual push copy derived per type.

### Scheduled jobs (cron)
- `invoices:generate-monthly` — monthly on the 1st.
- `invoices:mark-overdue` — daily.
- `subscriptions:notify-expiring` — daily (reminds members ~3 days before expiry).
- Requires `schedule:run` (cron) + a `queue:work` worker on the server.

### Media
Avatars + workspace photos on S3 (presigned/temporary URLs); in-app image cropper.

### Internationalization & theming
Arabic-first (RTL) + English throughout; dark mode.

---

## 7. Talking points for the discussion · نقاط للنقاش
- Roadmap & next milestones (auth/workspace management, analytics deepening).
- Scaling: caching, queues, read replicas as volume grows.
- Future revenue models (today: manual receipt-based tracking, no gateway).
- Security & ops: secret rotation, cron/queue supervision, backups.
