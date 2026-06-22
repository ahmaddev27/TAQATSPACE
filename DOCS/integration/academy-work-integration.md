# TaqatSpace (Work) — Integration Guide for the Academy Platform

**Audience:** Academy backend & frontend developers integrating with **TaqatSpace (“Work”)**.
**Goal:** let a **student** become a **freelancer** on Work and **request to join a coworking workspace**, while Academy can **list active workspaces**, **deep-link** the student into Work, and **track the booking outcome** (approved / rejected → member).

> Status: living document. Owners — Schema: `ahmedjaberdev` · Backend: `gssan1018` · Frontend: `abdqaddora732001` · SSO prod: `aqaddoura96`.

---

## 1. Integration model (read this first)

We use **one shared SSO identity** across Space / Academy / Work (the **Taqat IdP**). The student is **not** re-registered with a password on Work. Instead:

1. **Academy displays active workspaces** by calling our **public catalog** API.
2. When the student picks a workspace, **Academy redirects them to Work** (a deep-link).
3. On Work, the **shared Taqat SSO** logs the student in. A brand-new identity is auto-provisioned and the student **onboards as a freelancer (immediately active)**.
4. The student **submits a booking request** to that workspace (in the Work UI, with their own token).
5. The workspace **owner approves/rejects**. On a decision, **Work fires a webhook to Academy** so it can update the student’s status. Academy can also **pull membership status** any time via a server-to-server endpoint.

```
Academy UI                         Work (TaqatSpace)                 Taqat IdP
   |  GET /api/workspaces (public)        |                              |
   |------------------------------------->|                              |
   |  <-- active workspaces list ---------|                              |
   |                                      |                              |
   | redirect student (deep-link) ------> | GET /api/auth/taqat/redirect |
   |                                      |----------------------------->|
   |                                      |   login + consent            |
   |                                      |<-----------------------------|
   |                                      | provision user → onboarding  |
   |                                      | (freelancer, status=active)  |
   |                                      | POST /api/booking-requests    |
   |                                      | owner approves/rejects        |
   |  <==== webhook booking.approved =====|                              |
   |  GET /api/partner/.../membership --> | (server-to-server, API key)  |
```

**Why not `POST /api/auth/register`?** Direct registration leaves the account `pending_verification`, which is **blocked** from freelancer actions until an admin activates it. The **SSO onboarding path is the only one that yields an immediately-usable freelancer**, so always use the SSO redirect.

The cross-platform key is the SSO subject claim, stored on our side as **`sso_sub`**. Use it (or the student’s email) to look the student up via the status endpoint.

---

## 2. Conventions (apply to every request)

| Item | Value |
|---|---|
| Base URL | `https://<work-host>/api` (dev: `http://localhost:8000/api`) |
| Response envelope (success) | `{ "data": ..., "message"?: "..." }` |
| Response envelope (error) | `{ "message": "...", "errors"?: { "field": ["..."] } }` |
| Auth — end users | `Authorization: Bearer <sanctum_token>` (student’s token, via SSO) |
| Auth — server-to-server | `X-Api-Key: <partner_api_key>` (Academy’s key; **never** expose to the browser) |
| Language | `X-Locale: ar` or `en` (or `Accept-Language`); default `ar` |
| Content type | `application/json` |
| Pagination | `?page=N`; list responses include a `meta` block (`current_page`, `last_page`, `total`, `per_page`…) |

HTTP status codes follow REST: `200/201` success, `401` bad/missing auth, `403` wrong role / inactive, `404` not found, `409` conflict (e.g. already reviewed), `422` validation / business-rule error.

---

## 3. Requests Academy sends to us

### 3.1 List active workspaces (public — no auth)

```
GET /api/workspaces?city=غزة&min_price=0&max_price=500&amenities[]=wifi&min_rating=4&search=hub&sort=price_asc&page=1
X-Locale: en
```

Only **active + published** workspaces are returned. Supported query params: `city`, `min_price`, `max_price`, `amenities[]` (repeatable, AND semantics), `min_rating`, `search` (name/description), `sort` (`price_asc|price_desc|rating_desc`).

Response (`data` is an array; see field list in the Appendix):

```json
{
  "data": [
    {
      "id": "9f1c…",
      "name": "Gaza Hub",
      "city": "غزة",
      "city_id": "3a2b…",
      "address": "Omar Mukhtar St.",
      "price_per_month": "250.00",
      "amenities": ["wifi", "coffee"],
      "photos": ["https://…/cover.jpg"],
      "avg_rating": "4.50",
      "total_seats": 30,
      "seat_types": [
        { "type": "fixed", "price_monthly": "250.00", "price_daily": "20.00", "capacity": 10, "enabled": true }
      ],
      "status": "active",
      "created_at": "2026-06-01T10:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 72 }
}
```

### 3.2 Workspace detail (public)

```
GET /api/workspaces/{workspaceId}
```

Same shape as a list item **plus** `seats_summary` and `recent_reviews`. Use this to render a detail page before the student commits to joining.

### 3.3 Cities catalog (public) — for filters

```
GET /api/cities
→ { "data": [ { "id": "3a2b…", "name_ar": "غزة", "name_en": "Gaza", "is_active": true } ] }
```

### 3.4 Deep-link the student into Work (the hand-off)

Send the student’s browser to:

```
https://<work-frontend>/{locale}/explore?book={workspaceId}
```

- `{locale}` = `ar` (default, served at `/`) or `en`.
- `book={workspaceId}` lands the visitor on that workspace's detail page with the **booking action focused** (the workspace detail page is public, so it renders even before login).
- When the student submits the booking, if they are not logged in Work routes them through the **shared Taqat SSO**; after authenticating and a one-time **freelancer onboarding** (specialty/bio) they become active and can complete the request.

Academy does **not** call the SSO endpoints directly — it only needs to open this URL. The SSO + onboarding + booking all happen inside Work.

### 3.5 Student membership status (server-to-server — API key)

Poll this any time to reflect a student’s state inside Academy (e.g. “you’re a member of Gaza Hub”).

```
GET /api/partner/students/{identifier}/membership
X-Api-Key: <partner_api_key>
```

`{identifier}` = the student’s **SSO `sub`** (preferred) **or** their **email**.

```json
{
  "data": {
    "exists": true,
    "freelancer_active": true,
    "subscriptions": [
      {
        "workspace_id": "9f1c…",
        "workspace_name": "Gaza Hub",
        "status": "active",
        "start_date": "2026-06-22",
        "end_date": "2026-07-22"
      }
    ],
    "pending_booking": { "workspace_id": "9f1c…", "status": "pending" }
  }
}
```

If we have never seen the student: `{ "data": { "exists": false, "freelancer_active": false, "subscriptions": [], "pending_booking": null } }`.

---

## 4. What we send to you — Webhooks

When an owner decides on a booking, Work POSTs a webhook to the `webhook_url` you registered with us.

**Events:** `booking.approved`, `booking.rejected`.

**Request we send:**

```
POST <your-webhook_url>
Content-Type: application/json
X-Taqat-Signature: sha256=<hex hmac of the raw body>

{
  "event": "booking.approved",
  "occurred_at": "2026-06-22T12:34:56Z",
  "data": {
    "student_sub": "auth0|abc123",
    "student_email": "student@example.com",
    "workspace_id": "9f1c…",
    "workspace_name": "Gaza Hub",
    "booking_request_id": "7d2e…",
    "status": "approved",
    "subscription_id": "5b8a…"
  }
}
```

`subscription_id` is present only for `booking.approved`.

**Verify the signature** (the secret is the `webhook_secret` we share with you out-of-band):

```js
// Node.js example
const crypto = require("crypto");
const expected = "sha256=" + crypto
  .createHmac("sha256", WEBHOOK_SECRET)
  .update(rawRequestBody)            // the exact bytes received, before JSON.parse
  .digest("hex");
const ok = crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(req.header("X-Taqat-Signature")));
```

```php
// PHP example
$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $webhookSecret);
$ok = hash_equals($expected, $request->header('X-Taqat-Signature'));
```

**Delivery semantics:** respond `2xx` to acknowledge. Non-2xx / timeout is recorded as `failed` on our side and may be retried. Treat events as **idempotent** by `booking_request_id` + `event` (we may resend). Order is not guaranteed; reconcile with the status endpoint (§3.5) if needed.

---

## 5. Endpoints we are adding for this integration

These are net-new on the Work side (the catalog + SSO + booking already exist):

| Endpoint / capability | Type | Purpose |
|---|---|---|
| `GET /api/partner/students/{identifier}/membership` | server-to-server (`X-Api-Key`) | membership status lookup (§3.5) |
| `booking.approved` / `booking.rejected` webhooks | outbound | notify Academy of decisions (§4) |
| Partner API-key auth (`X-Api-Key`) | auth layer | gate the partner endpoints |
| CORS allow-list for Academy origin | config | let Academy’s browser call the public catalog |
| `?book={workspaceId}` deep-link | frontend | land the student on the booking action after SSO |

---

## 6. Onboarding & credentials (what each side provides)

**Academy gives us:**
- Your **webhook URL** (HTTPS) for booking events.
- Your browser **origin(s)** (for CORS on the public catalog), if you call it client-side.

**We give Academy (out-of-band, once):**
- A **partner API key** (`X-Api-Key`) — store server-side only.
- A **webhook signing secret** (`webhook_secret`) — used to verify §4 signatures.

> Keys/secrets are shared securely (not in tickets/chat). Rotate on request. We store only a **hash** of the API key; if lost, we re-issue.

**SSO:** Academy and Work must point at the **same Taqat IdP**. The student’s `sub` claim is the shared identity used in §3.5 and §4 payloads.

---

## 7. End-to-end checklist (happy path)

1. Academy renders workspaces via `GET /api/workspaces` (+ `/cities` for filters).
2. Student clicks “Join” → Academy opens `…/explore?book={workspaceId}`.
3. Work runs shared SSO → provisions user → freelancer onboarding (active).
4. Student submits the booking (`POST /api/booking-requests`, handled by Work UI).
5. Owner approves → Subscription created → Work sends `booking.approved` webhook.
6. Academy verifies the signature, marks the student a member (or reconciles via `GET /api/partner/students/{sub}/membership`).

---

## Appendix — reference shapes

### Workspace resource fields
`id, owner_id, name, description, address, city, city_id, phone, latitude, longitude, total_seats, price_per_month, amenities[], photos[] (resolved URLs), working_hours, status (pending|active|suspended|rejected), avg_rating, seat_types[], created_at`. Detail adds `seats_summary, recent_reviews`.

### Seat types
`type ∈ {fixed, flexible, private_office}`, each with `price_monthly?`, `price_daily?`, `capacity`, `enabled`.

### Booking → subscription state machine
`booking_request: pending → approved | rejected`. On **approved**, a `subscription` is created with `status=active`, `plan_type=monthly`, `start_date=today`, `end_date=+1 month`, an optional assigned seat, and a resolved `monthly_price`. Booking guards: workspace must be active; at most one pending request per student; not already actively subscribed to that workspace.

### Membership status response
`{ exists, freelancer_active, subscriptions:[{workspace_id, workspace_name, status, start_date, end_date}], pending_booking:{workspace_id, status}|null }`.
