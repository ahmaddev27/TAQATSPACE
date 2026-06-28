# خطة ربط MikroTik — تزويد المشتركين بإنترنت (user/password)

> الهدف: عند تفعيل اشتراك العضو، يتولّد له **اسم مستخدم وكلمة مرور** للدخول على إنترنت المساحة عبر **Hotspot** على راوتر MikroTik، بسرعة وحدّ بيانات مأخوذين من `InternetPackage`. وعند الإيقاف/الإلغاء/الانتهاء يتعطّل تلقائياً.

---

## 1. القرارات المعمارية

| القرار | الاختيار | السبب |
|---|---|---|
| آلية الربط | **RouterOS API مباشرة** (Phase 1) | راوتر واحد لكل مساحة؛ أبسط من RADIUS. RADIUS يُؤجَّل لمرحلة تعدّد الفروع. |
| نوع الوصول | **Hotspot** (captive portal) | لابتوبات/موبايلات بدون إعداد عميل؛ دخول بالمتصفح user/pass. |
| تنفيذ الاتصال | **Queued Job** | عزل فشل الراوتر عن الـ request؛ إعادة المحاولة تلقائياً. |
| ربط دورة الحياة | **SubscriptionObserver** | نفس نمط `SeatObserver` الموجود — اتساق معماري. |
| التفعيل | **Feature flag** `services.mikrotik.enabled` | تشغيل/إطفاء بدون مساس بباقي النظام. |

**المكتبة:** `evilfreelancer/routeros-api-php` (Composer) — عميل RouterOS API ناضج.

---

## 2. تغييرات قاعدة البيانات

### 2.1 إعدادات الراوتر لكل مساحة (migration جديدة)
الراوتر مرتبط بالمساحة، فالإعدادات على `workspaces` (أو جدول `workspace_router_settings` منفصل لو صار فيه أكثر من راوتر لاحقاً):

```
workspace_router_settings:
  id
  workspace_id        (FK, unique)
  host                (IP الراوتر)
  port                (افتراضي 8728 / 8729 TLS)
  username            (مستخدم API على الراوتر)
  password_encrypted  (Crypt::encrypt)
  use_tls             (boolean)
  hotspot_server      (اسم خادم الـ hotspot على الراوتر، افتراضي "all")
  is_active           (boolean)
  last_sync_at        (timestamp nullable)
  timestamps
```

### 2.2 بيانات دخول العضو (migration على subscriptions)
```
subscriptions:
  internet_username        (string, nullable, unique)
  internet_password_enc    (text, nullable)   ← Crypt::encrypt
  router_synced_at         (timestamp, nullable)
  router_sync_status       (enum: pending|synced|failed|disabled, default pending)
```
> السبب في تخزين كلمة المرور مشفّرة (لا hash): نحتاج نعرضها للعضو في لوحته ونعيد إرسالها للراوتر عند إعادة المزامنة.

---

## 3. طبقة الخدمة (Service Layer)

```
app/Services/Router/
  RouterGateway.php            (interface — عقد مجرّد)
  MikrotikRouterGateway.php    (تنفيذ RouterOS API)
  NullRouterGateway.php        (no-op عند إطفاء الـ flag / للاختبارات)
  RouterCredentialFactory.php  (توليد username/password آمن)
  DTO/RouterUser.php           (بيانات المستخدم: user, pass, rateLimit, dataLimitBytes)
```

### 3.1 العقد
```php
interface RouterGateway
{
    public function upsertUser(WorkspaceRouterSettings $cfg, RouterUser $user): void;
    public function disableUser(WorkspaceRouterSettings $cfg, string $username): void;
    public function removeUser(WorkspaceRouterSettings $cfg, string $username): void;
    public function fetchUsage(WorkspaceRouterSettings $cfg, string $username): ?int; // bytes
}
```

### 3.2 تحويل الباقة → حدود الراوتر
- `InternetPackage.speed_mbps` → `rate-limit = "{speed}M/{speed}M"`
- `InternetPackage.data_limit_gb` → `limit-bytes-total = data_limit_gb * 1024^3` (إن وُجد؛ null = غير محدود)
- اسم المستخدم: من بريد العضو أو رقم العضوية (مثلاً `m{member_id}`)
- كلمة المرور: عشوائية 8–10 محارف (`RouterCredentialFactory`)

### 3.3 ربط الـ Hotspot user على RouterOS
المسار في الراوتر: `/ip/hotspot/user`
الحقول: `name`, `password`, `profile` (أو `rate-limit` مباشرة), `limit-bytes-total`, `comment = "taqat:sub:{id}"`.
> نستخدم `comment` كمفتاح ربط idempotent — نبحث به قبل الإنشاء (upsert) لتفادي التكرار.

---

## 4. ربط دورة حياة الاشتراك

### 4.1 SubscriptionObserver
```
created            → إن كان Active: dispatch SyncRouterUserJob (upsert)
updated (status):
  → Active         : SyncRouterUserJob (upsert/enable)
  → Suspended      : DisableRouterUserJob
  → Cancelled/Expired : DisableRouterUserJob (أو remove حسب السياسة)
deleting           → RemoveRouterUserJob
```
> نراقب تغيّر `status` فقط (`$subscription->wasChanged('status')`) لتفادي مزامنات غير ضرورية — نفس أسلوب `SeatObserver`.

### 4.2 الـ Jobs (queued, مع backoff)
```
app/Jobs/Router/
  SyncRouterUserJob.php     (ينشئ/يحدّث + يولّد credentials إن لزم + يحدّث router_sync_status)
  DisableRouterUserJob.php
  RemoveRouterUserJob.php
```
- `tries = 5`, `backoff = [60, 300, 900, ...]`
- عند الفشل النهائي: `router_sync_status = failed` + تنبيه للمالك (notification موجودة بالنظام).
- كل Job يجلب `WorkspaceRouterSettings`؛ إن `is_active = false` أو الـ flag مطفأ → يخرج بهدوء (NullRouterGateway).

---

## 5. واجهات الاستخدام

### 5.1 المالك (Owner)
- صفحة **إعدادات المساحة → الإنترنت/الراوتر**: إدخال host/port/user/pass/TLS + زر **اختبار الاتصال** (يستدعي `/api/workspace/router/test`).
- في صفحة العضو: حالة المزامنة (`synced/failed/pending`) + زر **إعادة المزامنة** و**توليد كلمة مرور جديدة**.

### 5.2 العضو (Freelancer)
- في لوحته: بطاقة **بيانات إنترنت المساحة** → username + password (مع زر إظهار/إخفاء) + QR للدخول السريع + السرعة وحدّ البيانات من الباقة.

### 5.3 نقاط API جديدة
```
GET   /api/workspace/router            (إعدادات الراوتر — owner)
PUT   /api/workspace/router            (حفظ الإعدادات — owner)
POST  /api/workspace/router/test       (اختبار الاتصال — owner)
POST  /api/owner/members/{id}/internet/resync   (إعادة مزامنة — owner)
POST  /api/owner/members/{id}/internet/rotate   (كلمة مرور جديدة — owner)
GET   /api/me/internet                 (بيانات دخول العضو — freelancer)
```

---

## 6. حدّ البيانات (data cap) — اختياري Phase 2
- **بسيط:** الاعتماد على `limit-bytes-total` في الـ hotspot user (الراوتر يقطع تلقائياً عند الوصول).
- **متقدّم:** Scheduled command `router:poll-usage` يستدعي `fetchUsage` دورياً، يخزّن الاستهلاك، ويظهره للعضو/المالك + ينبّه عند الاقتراب من الحد. (يتكامل مع نمط الـ scheduler الموجود.)

---

## 7. الأمان

- كلمات مرور الراوتر والمستخدمين **مشفّرة** بـ `Crypt` (مفتاح `APP_KEY`)، لا تُخزَّن plain.
- مستخدم API على الراوتر بصلاحيات محدودة (group مخصص: hotspot فقط، لا full).
- يفضّل **TLS (8729)** + تقييد وصول API لـ IP السيرفر فقط (firewall على الراوتر).
- عدم تسريب credentials في الـ logs (إخفاء في `SyncRouterUserJob`).
- التحقق من الصلاحيات: نقاط الإعدادات `role.owner`؛ نقطة العضو تُرجع بياناته فقط.

---

## 8. الإعدادات (config + env)

```
config/services.php → 'mikrotik' => [
    'enabled' => env('MIKROTIK_ENABLED', false),
    'timeout' => env('MIKROTIK_TIMEOUT', 5),
]
```
الاتصال الفعلي لكل راوتر من `workspace_router_settings` (DB)، مش من env — عشان دعم عدة مساحات.

---

## 9. الاختبارات

- **Unit:** `RouterCredentialFactory` (قوة/تفرّد), تحويل الباقة → rate-limit/bytes.
- **Feature (بـ NullRouterGateway / fake):** الـ Observer يطلق الـ Job الصحيح عند تغيّر الحالة؛ نقاط API للإعدادات والعضو.
- **Integration (يدوي/staging):** اتصال حقيقي براوتر تجريبي — إنشاء/تعطيل/حذف مستخدم + اختبار الاتصال.

---

## 10. خطة التنفيذ (Phases)

| Phase | المحتوى | المخرَج |
|---|---|---|
| **1 — الأساس** | المكتبة، `RouterGateway` + Mikrotik impl + Null، DTO، CredentialFactory، migration الإعدادات + أعمدة subscriptions، config/flag | عقد + بنية جاهزة (بدون أتمتة) |
| **2 — الأتمتة** | `SubscriptionObserver` + الـ 3 Jobs (queued + backoff + sync status) | المزامنة التلقائية مع دورة حياة الاشتراك |
| **3 — الواجهات** | إعدادات الراوتر للمالك + اختبار الاتصال + بطاقة العضو + نقاط API | تجربة كاملة end-to-end |
| **4 — الاستهلاك (اختياري)** | `router:poll-usage` scheduled + تنبيهات حدّ البيانات | مراقبة استهلاك |

كل Phase = فرع + PR مستقل بنفس سير عملنا.

---

## 11. مخاطر / ملاحظات
- الراوتر لازم يكون **متصل ومُعَدّ مسبقاً** (hotspot server + DNS + IP pool). الربط البرمجي يدير المستخدمين فقط، مش إعداد الشبكة الأساسي.
- لو المساحة بدون راوتر MikroTik → الـ flag مطفأ والميزة مخفية (no-op).
- RADIUS يبقى مسار ترقية مستقبلي لو احتجنا محاسبة مركزية أو عدة راوترات بنفس الحساب.
</content>
