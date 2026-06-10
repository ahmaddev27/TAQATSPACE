# -*- coding: utf-8 -*-
"""
Build the bilingual (AR + EN) TaqatSpace features deck as a single, self-contained
HTML file that prints cleanly to PDF (one 16:9 slide per page). Screenshots are
the real prototype captures in ./img. Content is structured data below, so the
deck stays easy to edit and regenerate:  python build_deck.py
"""
import html
import os

OUT = os.path.join(os.path.dirname(__file__), "taqatspace-features.html")

BRAND = {
    "primary": "#1F82C7",
    "primary_dark": "#135789",
    "accent": "#F6A91B",
    "ink": "#0F1B2A",
    "muted": "#5B6B7B",
    "owner": "#176AA8",
    "freelancer": "#16A34A",
    "admin": "#F6A91B",
    "public": "#1F82C7",
}

# Role accent colors for the kicker chip / left rule.
ROLE_COLOR = {
    "public": BRAND["public"],
    "owner": BRAND["owner"],
    "freelancer": BRAND["freelancer"],
    "admin": BRAND["admin"],
    "system": "#52525B",
}


def esc(s):
    return html.escape(s, quote=True)


def bullets(items):
    """items: list of (ar, en) tuples -> bilingual list markup."""
    out = []
    for ar, en in items:
        out.append(
            f'<li><span class="ar">{esc(ar)}</span>'
            f'<span class="en">{esc(en)}</span></li>'
        )
    return '<ul class="blist">' + "".join(out) + "</ul>"


def steps(items):
    """items: list of (ar, en) -> numbered flow strip."""
    out = []
    for i, (ar, en) in enumerate(items, 1):
        out.append(
            f'<div class="step"><div class="step-n">{i}</div>'
            f'<div class="step-t"><span class="ar">{esc(ar)}</span>'
            f'<span class="en">{esc(en)}</span></div></div>'
        )
    return '<div class="flow">' + "".join(out) + "</div>"


def shot(slug, caption_ar="", caption_en=""):
    cap = ""
    if caption_ar or caption_en:
        cap = (
            f'<figcaption><span class="ar">{esc(caption_ar)}</span>'
            f'<span class="en">{esc(caption_en)}</span></figcaption>'
        )
    return (
        f'<figure class="shot"><img src="img/{slug}.png" '
        f'alt="{esc(caption_en or slug)}" />{cap}</figure>'
    )


# ---------------------------------------------------------------------------
#  SLIDES
# ---------------------------------------------------------------------------
slides = []


def cover():
    return f"""
    <section class="slide cover">
      <div class="cover-grid">
        <div>
          <div class="logo">TAQAT<span>.space</span></div>
          <h1 class="ar">منصّة مساحات العمل المشتركة في غزّة</h1>
          <h1 class="en">A Coworking-Space Marketplace &amp; Management Platform for Gaza</h1>
          <p class="lede ar">سوق رقمي ثنائي اللغة يربط العاملين المستقلّين بأصحاب المساحات، مع لوحة إدارة كاملة للمنصّة — من الاكتشاف والحجز إلى الاشتراكات والفوترة والتواصل الفوري.</p>
          <p class="lede en">A bilingual (Arabic-first) marketplace connecting freelancers with workspace owners, with a full super-admin back office — from discovery and booking to subscriptions, invoicing and realtime chat.</p>
          <div class="cover-tags">
            <span>Freelancer</span><span>Workspace Owner</span><span>Super Admin</span>
            <span>Arabic / English · RTL</span><span>Web + PWA Push</span>
          </div>
        </div>
        <div class="cover-art">
          {shot("01-public-home")}
        </div>
      </div>
      <div class="cover-foot"><span class="ar">عرض الميزات للإدارة — مناقشة المشروع</span><span class="en">Features overview · for management review</span></div>
    </section>"""


def section_divider(num, color, kicker, ar, en, sub_ar="", sub_en=""):
    return f"""
    <section class="slide divider" style="--rc:{color}">
      <div class="divider-num">{num}</div>
      <div class="divider-body">
        <div class="kicker" style="--rc:{color}">{esc(kicker)}</div>
        <h2 class="ar">{esc(ar)}</h2>
        <h2 class="en">{esc(en)}</h2>
        <p class="ar">{esc(sub_ar)}</p>
        <p class="en">{esc(sub_en)}</p>
      </div>
    </section>"""


def content(role, kicker_ar, kicker_en, ar_title, en_title,
            stories=None, features=None, flow=None, screenshot=None,
            cap_ar="", cap_en=""):
    color = ROLE_COLOR.get(role, BRAND["primary"])
    left = []
    left.append(
        f'<div class="kicker" style="--rc:{color}">'
        f'<span class="ar">{esc(kicker_ar)}</span>'
        f'<span class="en">{esc(kicker_en)}</span></div>'
    )
    left.append(f'<h3 class="ar">{esc(ar_title)}</h3>')
    left.append(f'<h3 class="en">{esc(en_title)}</h3>')
    if stories:
        left.append('<div class="block-label ar">قصص المستخدم</div>')
        left.append('<div class="block-label en">User stories</div>')
        left.append(
            '<div class="stories">'
            + "".join(
                f'<div class="story"><span class="ar">{esc(a)}</span>'
                f'<span class="en">{esc(e)}</span></div>'
                for a, e in stories
            )
            + "</div>"
        )
    if features:
        left.append('<div class="block-label ar">الميزات</div>')
        left.append('<div class="block-label en">Capabilities</div>')
        left.append(bullets(features))
    if flow:
        left.append('<div class="block-label ar">سير العمل</div>')
        left.append('<div class="block-label en">Flow</div>')
        left.append(steps(flow))

    right = shot(screenshot, cap_ar, cap_en) if screenshot else ""
    cls = "content" + (" no-shot" if not screenshot else "")
    return f"""
    <section class="slide {cls}" style="--rc:{color}">
      <div class="c-left">{''.join(left)}</div>
      <div class="c-right">{right}</div>
    </section>"""


def gallery(role, ar_title, en_title, shots):
    color = ROLE_COLOR.get(role, BRAND["primary"])
    cells = "".join(shot(s, a, e) for s, a, e in shots)
    return f"""
    <section class="slide gallery" style="--rc:{color}">
      <div class="g-head">
        <h3 class="ar">{esc(ar_title)}</h3>
        <h3 class="en">{esc(en_title)}</h3>
      </div>
      <div class="g-grid g-{len(shots)}">{cells}</div>
    </section>"""


# ---- Cover ----
slides.append(cover())

# ---- 1. Overview ----
slides.append(content(
    "system", "نظرة عامة", "Overview",
    "ما هي منصّة طاقة؟", "What is TaqatSpace?",
    features=[
        ("سوق عام لاكتشاف مساحات العمل المشتركة في غزّة وحجز مقعد فيها", "A public marketplace to discover Gaza coworking spaces and book a seat"),
        ("لوحة لأصحاب المساحات لإدارة المشتركين والمقاعد والفواتير والباقات", "An owner back office for members, seats, invoices and internet packages"),
        ("لوحة سوبر أدمن لإدارة المنصّة بالكامل: موافقات، مستخدمون، ماليّة، محتوى", "A super-admin console: approvals, users, finance and site content"),
        ("عربي أوّلاً (RTL) + إنجليزي، وضع داكن، وإشعارات فوريّة على الويب", "Arabic-first (RTL) + English, dark mode, and web push notifications"),
        ("الفوترة تتبّع يدوي يُديره المشرف بالإيصالات — بلا بوّابة دفع", "Invoicing is admin-managed manual tracking with receipts — no payment gateway"),
    ],
    screenshot="01-public-home",
    cap_ar="الصفحة الرئيسية العامّة", cap_en="Public landing page",
))

# ---- 2. Roles ----
slides.append(content(
    "system", "الأدوار", "The three roles",
    "ثلاثة أدوار، رحلة واحدة متّسقة", "Three roles, one coherent journey",
    features=[
        ("العامل المستقل: يكتشف مساحة، يطلب حجزاً، يصبح مشتركاً، ويتابع فواتيره", "Freelancer: discovers a space, requests a booking, becomes a member, tracks invoices"),
        ("صاحب المساحة: يُسجّل مساحته، يوافق على الطلبات، ويدير المقاعد والمشتركين والفوترة", "Workspace owner: registers a space, approves requests, runs seats, members and billing"),
        ("السوبر أدمن: يوافق على المساحات، يدير المستخدمين والماليّة والمحتوى والمدراء الفرعيّين", "Super admin: approves spaces, manages users, finance, content and sub-admins"),
        ("التواصل الفوري (دردشة) يربط الأدوار الثلاثة داخل المنصّة", "Realtime chat links all three roles inside the platform"),
    ],
    screenshot="32-admin-users",
    cap_ar="دليل المستخدمين لدى الأدمن", cap_en="Admin users directory",
))

# ---- 3. Architecture ----
slides.append(content(
    "system", "التقنية", "Architecture & stack",
    "بنية إنتاجيّة نظيفة وقابلة للتوسّع", "A clean, production-grade, scalable stack",
    features=[
        ("الخلفية: Laravel 13 — متحكّمات رفيعة ← خدمات ← مستودعات، مع API Resources", "Backend: Laravel 13 — thin controllers → services → repositories, API Resources"),
        ("الواجهة: Next.js 16 (App Router, RSC, Server Actions) وثنائيّة اللغة next-intl", "Frontend: Next.js 16 (App Router, RSC, Server Actions) with next-intl i18n"),
        ("قاعدة البيانات MySQL 8، وتخزين الوسائط على AWS S3 (روابط موقّعة)", "MySQL 8 database, media on AWS S3 (presigned URLs)"),
        ("Firebase: دردشة فوريّة (Firestore) + إشعارات الدفع (FCM)", "Firebase: realtime chat (Firestore) + push notifications (FCM)"),
        ("الدخول عبر Sanctum + الدخول الموحّد «سجّل عبر طاقة» (OIDC + PKCE)", "Auth via Sanctum + “Sign in with Taqat” SSO (OIDC + PKCE)"),
        ("صلاحيات دقيقة عبر Spatie، توثيق OpenAPI تلقائي + مجموعة Postman", "Granular Spatie permissions, auto OpenAPI docs + Postman collection"),
    ],
))

# ---- SECTION: Public ----
slides.append(section_divider(
    "01", ROLE_COLOR["public"], "PUBLIC SITE",
    "الموقع العام", "Public site",
    "الاكتشاف والتسجيل — أوّل لقاء مع المنصّة",
    "Discovery and sign-up — the platform's first impression",
))

slides.append(content(
    "public", "الموقع العام", "Public site",
    "صفحة هبوط تُدار بالكامل من لوحة الأدمن", "A landing page fully managed from the admin CMS",
    stories=[
        ("كزائر، أريد أن أفهم الخدمة بسرعة وأرى مساحات مميّزة لأبدأ", "As a visitor, I want to grasp the offering fast and see featured spaces"),
    ],
    features=[
        ("أقسام قابلة لإعادة الترتيب: البطل، الإحصائيات، لماذا نحن، كيف يعمل، المميّزة، آراء", "Reorderable sections: hero, stats, why-us, how-it-works, featured, testimonials"),
        ("محتوى وصور وترتيب الأقسام يُحرّرها الأدمن دون نشر برمجي", "Content, images and section order are edited by the admin — no deploy"),
        ("حركات ظهور خفيفة عند التمرير، وآمنة لتفضيل تقليل الحركة", "Subtle on-scroll reveal animations, reduced-motion safe"),
    ],
    flow=[
        ("يصل الزائر للرئيسية", "Visitor lands on home"),
        ("يستكشف المساحات", "Browses spaces"),
        ("يفتح تفاصيل مساحة", "Opens a space"),
        ("يسجّل أو يدخل", "Registers / signs in"),
    ],
    screenshot="01-public-home", cap_ar="الرئيسية", cap_en="Home",
))

slides.append(content(
    "public", "الموقع العام", "Public site",
    "اكتشاف المساحات والتفاصيل", "Explore spaces & space detail",
    stories=[
        ("كعامل مستقل، أريد تصفية المساحات حسب المدينة والسعر والتقييم لأجد الأنسب", "As a freelancer, I want to filter by city, price and rating to find the right space"),
        ("أريد رؤية الصور والمرافق وأنواع المقاعد والتقييمات قبل أن أحجز", "I want photos, amenities, seat types and reviews before I book"),
    ],
    features=[
        ("بحث وفلاتر: المدينة، نطاق السعر، التقييم، نوع المقعد", "Search & filters: city, price range, rating, seat type"),
        ("صفحة تفاصيل المساحة: معرض صور، مرافق، تسعير المقاعد، خريطة، تقييمات", "Space detail: gallery, amenities, seat pricing, map, reviews"),
        ("زر «اطلب حجزاً» يبدأ رحلة الاشتراك", "A “Request booking” CTA starts the subscription journey"),
    ],
    screenshot="02-public-explore", cap_ar="استكشاف المساحات", cap_en="Explore",
))

slides.append(gallery(
    "public", "الموقع العام — لقطات", "Public site — screens",
    [
        ("03-public-detail", "تفاصيل المساحة", "Workspace detail"),
        ("04-register-freelancer", "تسجيل عامل مستقل", "Register freelancer"),
        ("05-register-workspace", "تسجيل مساحة", "Register workspace"),
        ("06-login", "تسجيل الدخول + الدخول الموحّد", "Login + SSO"),
    ],
))

# ---- SECTION: Auth/SSO ----
slides.append(content(
    "system", "الدخول والهويّة", "Auth, SSO & onboarding",
    "دخول موحّد «سجّل عبر طاقة» + استكمال الحساب", "“Sign in with Taqat” SSO + onboarding",
    stories=[
        ("كمستخدم، أريد الدخول بحساب طاقة الموحّد دون كلمة مرور جديدة", "As a user, I want to sign in with my Taqat account — no new password"),
    ],
    features=[
        ("OIDC Authorization Code + PKCE، مع جلب البيانات من /userinfo", "OIDC Authorization Code + PKCE, claims from /userinfo"),
        ("حساب جديد عبر SSO يكمل خطوة اختيار الدور (مستقل / صاحب مساحة)", "A new SSO account completes a role-selection onboarding step"),
        ("الرمز يُحفظ في كوكي آمن (httpOnly)، وكل النداءات المُصادَقة من الخادم", "Token kept in an httpOnly cookie; authenticated calls run server-side"),
        ("خروج موحّد (Single Logout) من مزوّد الهويّة", "RP-initiated single logout at the identity provider"),
        ("رقم الهاتف يأتي من مزوّد الهويّة — لا نطلب تأكيده من جانبنا", "Phone comes from the IdP — we no longer re-confirm it on our side"),
    ],
    screenshot="06-login", cap_ar="تسجيل الدخول", cap_en="Login",
))

# ---- SECTION: Freelancer ----
slides.append(section_divider(
    "02", ROLE_COLOR["freelancer"], "FREELANCER",
    "لوحة العامل المستقل", "Freelancer dashboard",
    "الحجز، الاشتراك، الفواتير، والتواصل",
    "Booking, subscription, invoices and chat",
))

slides.append(content(
    "freelancer", "العامل المستقل", "Freelancer",
    "الرئيسية: كل شيء في لمحة", "Home: everything at a glance",
    stories=[
        ("كعامل مستقل، أريد رؤية حالة اشتراكي ومقعدي وفاتورتي القادمة فوراً", "As a freelancer, I want my subscription, seat and next invoice at a glance"),
        ("أريد تنبيهاً واضحاً إن لم يكن لديّ اشتراك نشط بعد", "I want a clear prompt when I don't have an active subscription yet"),
    ],
    features=[
        ("بطاقة حالة الاشتراك + المقعد المخصّص + موعد التجديد", "Subscription status + assigned seat + renewal date"),
        ("بطاقات: نوع المقعد، الفاتورة القادمة، الإشعارات غير المقروءة", "Tiles: seat type, next invoice, unread notifications"),
        ("روابط سريعة وحالة «لا اشتراك» مع دعوة للحجز", "Quick links and a no-subscription empty state with a CTA"),
    ],
    screenshot="20-freelancer-home", cap_ar="رئيسية العامل المستقل", cap_en="Freelancer home",
))

slides.append(content(
    "freelancer", "العامل المستقل", "Freelancer",
    "الاشتراك والفواتير", "Subscription & invoices",
    stories=[
        ("كعامل مستقل، أريد متابعة تفاصيل اشتراكي وإمكانيّة إلغائه", "As a freelancer, I want to see my subscription details and cancel it"),
        ("أريد سجلّ فواتيري وتنزيل نسخة PDF، وتنبيهاً بالمتأخّر منها", "I want my invoice history, PDF download, and an overdue alert"),
    ],
    features=[
        ("صفحة الاشتراك: المساحة، المقعد، السعر، الفترة، الإلغاء", "Subscription page: space, seat, price, period, cancel"),
        ("سجلّ الفواتير: الحالة، الاستحقاق، الدفع، الإجمالي المدفوع، تنزيل PDF", "Invoice history: status, due, paid, total paid, PDF download"),
        ("تنبيه أعلى الصفحة عند وجود فواتير متأخّرة", "An overdue banner when invoices are past due"),
    ],
    screenshot="21-freelancer-subscription", cap_ar="اشتراكي", cap_en="My subscription",
))

slides.append(gallery(
    "freelancer", "العامل المستقل — لقطات", "Freelancer — screens",
    [
        ("22-freelancer-invoices", "فواتيري", "My invoices"),
        ("23-freelancer-notifications", "الإشعارات", "Notifications"),
        ("24-freelancer-profile", "الملف الشخصي + الصورة", "Profile + avatar"),
        ("53-freelancer-home-en", "الواجهة بالإنجليزيّة", "English locale"),
    ],
))

slides.append(content(
    "freelancer", "العامل المستقل", "Freelancer",
    "رحلة الحجز حتى التفعيل", "From booking to active membership",
    flow=[
        ("يستكشف ويفتح مساحة", "Explore & open a space"),
        ("يرسل طلب حجز (نوع المقعد + رسالة)", "Send a booking request"),
        ("يوافق صاحب المساحة ويُخصّص مقعداً", "Owner approves + assigns a seat"),
        ("يُنشأ اشتراك نشط تلقائياً", "An active subscription is created"),
        ("تصل الفواتير + إشعار التخصيص", "Invoices + seat-assigned notice arrive"),
    ],
    features=[
        ("إشعار فوري عند الموافقة أو الرفض على الطلب", "Instant notification on approval or rejection"),
        ("بريد + إشعار داخل التطبيق + دفع على الويب", "Email + in-app + web push"),
    ],
))

# ---- SECTION: Owner ----
slides.append(section_divider(
    "03", ROLE_COLOR["owner"], "WORKSPACE OWNER",
    "لوحة صاحب المساحة", "Workspace owner",
    "إدارة المساحة بالكامل: مقاعد، مشتركون، فوترة، باقات",
    "Run the whole space: seats, members, billing, packages",
))

slides.append(content(
    "owner", "صاحب المساحة", "Owner",
    "لوحة التحكّم: نبض المساحة", "Dashboard: the pulse of the space",
    stories=[
        ("كصاحب مساحة، أريد لمحة عن المشتركين والإشغال والإيرادات المتتبَّعة", "As an owner, I want an at-a-glance view of members, occupancy and tracked revenue"),
    ],
    features=[
        ("إحصائيات: المشتركون، المقاعد المشغولة، الفواتير، الإيراد المتتبَّع", "Stats: members, occupied seats, invoices, tracked revenue"),
        ("الإيراد محاسبيّ يُديره صاحب المساحة يدويّاً — لا مبالغ بوّابة دفع", "Revenue is owner-managed bookkeeping — not gateway money"),
        ("روابط سريعة لكلّ أقسام الإدارة", "Quick links into every management area"),
    ],
    screenshot="10-owner-dashboard", cap_ar="لوحة صاحب المساحة", cap_en="Owner dashboard",
))

slides.append(content(
    "owner", "صاحب المساحة", "Owner",
    "المشتركون والمقاعد", "Members & seats",
    stories=[
        ("كصاحب مساحة، أريد إدارة مشتركيّ ورؤية تفاصيل كلّ واحد", "As an owner, I want to manage members and open each member's details"),
        ("أريد خريطة مقاعد بصريّة لأرى المتاح والمشغول وأخصّص المقاعد", "I want a visual seat map to see availability and assign seats"),
    ],
    features=[
        ("جدول المشتركين + درج تفاصيل (اشتراك، مقعد، فواتير)", "Members table + detail drawer (subscription, seat, invoices)"),
        ("خريطة مقاعد تفاعليّة بأنواع وحالات المقاعد", "Interactive seat map with seat types and statuses"),
        ("صور المستخدمين (أفتار) تظهر أينما وُجدت", "User avatars shown wherever a member appears"),
    ],
    screenshot="12-owner-seats", cap_ar="المقاعد", cap_en="Seats",
))

slides.append(content(
    "owner", "صاحب المساحة", "Owner",
    "طلبات الحجز والفوترة", "Booking requests & invoicing",
    stories=[
        ("كصاحب مساحة، أريد مراجعة طلبات الحجز والموافقة عليها بتخصيص مقعد", "As an owner, I want to review requests and approve them by assigning a seat"),
        ("أريد توليد الفواتير الشهريّة ومتابعة المدفوع والمتأخّر", "I want to generate monthly invoices and track paid vs overdue"),
    ],
    features=[
        ("طلب جديد يُنبّه صاحب المساحة فوراً", "A new request instantly notifies the owner"),
        ("الموافقة تُنشئ اشتراكاً وتشغل المقعد في معاملة واحدة", "Approval creates a subscription + occupies the seat atomically"),
        ("فواتير: توليد شهري، تعليم مدفوع، متأخّر، تذكير، PDF", "Invoices: monthly generation, mark-paid, overdue, reminders, PDF"),
    ],
    screenshot="13-owner-requests", cap_ar="طلبات الحجز", cap_en="Booking requests",
))

slides.append(gallery(
    "owner", "صاحب المساحة — لقطات", "Owner — screens",
    [
        ("11-owner-members", "المشتركون", "Members"),
        ("14-owner-invoices", "الفواتير", "Invoices"),
        ("15-owner-packages", "باقات الإنترنت", "Internet packages"),
        ("16-owner-reports", "التقارير", "Reports"),
    ],
))

slides.append(content(
    "owner", "صاحب المساحة", "Owner",
    "ما وراء الأساسيّات", "Beyond the basics",
    features=[
        ("باقات الإنترنت: إنشاؤها وتخصيصها للمشتركين", "Internet packages: create and assign to members"),
        ("المصروفات والموارد لإدارة تشغيل المساحة", "Expenses and resources to run space operations"),
        ("الإعلانات والرسائل الجماعيّة للمشتركين", "Announcements and broadcast messaging to members"),
        ("تقارير ورسوم بيانيّة + تصدير", "Reports, charts and export"),
        ("دردشة فوريّة مع المشتركين والأدمن", "Realtime chat with members and admins"),
    ],
    flow=[
        ("يسجّل المساحة", "Register space"),
        ("يوافق الأدمن", "Admin approves"),
        ("يجهّز المقاعد والباقات", "Set up seats & packages"),
        ("يستقبل الطلبات ويوافق", "Receive & approve requests"),
        ("يُفوتر ويُحلّل", "Invoice & analyze"),
    ],
))

# ---- SECTION: Admin ----
slides.append(section_divider(
    "04", ROLE_COLOR["admin"], "SUPER ADMIN",
    "لوحة السوبر أدمن", "Super admin console",
    "موافقات، مستخدمون، ماليّة، محتوى، ومدراء فرعيّون بصلاحيّات دقيقة",
    "Approvals, users, finance, content and scoped sub-admins",
))

slides.append(content(
    "admin", "السوبر أدمن", "Super admin",
    "إحصائيات المنصّة", "Platform analytics",
    stories=[
        ("كسوبر أدمن، أريد لوحة بمؤشّرات المنصّة كلّها لاتّخاذ القرار", "As a super admin, I want a platform-wide KPI dashboard"),
    ],
    features=[
        ("عدّادات: المساحات، المستخدمون، الاشتراكات، الفواتير", "Counters: workspaces, users, subscriptions, invoices"),
        ("تتبّع الإيرادات: المحصّل والمستحقّ (تتبّع يدوي بالإيصالات)", "Revenue tracking: collected vs outstanding (manual, receipt-based)"),
        ("توزيعات حسب المدينة والجنس لفهم القاعدة", "City and gender distributions to understand the base"),
    ],
    screenshot="30-admin-analytics", cap_ar="إحصائيات المنصّة", cap_en="Platform analytics",
))

slides.append(content(
    "admin", "السوبر أدمن", "Super admin",
    "الموافقات وإدارة المستخدمين", "Approvals & user management",
    stories=[
        ("كسوبر أدمن، أريد الموافقة على المساحات الجديدة أو تعليقها", "As a super admin, I want to approve or suspend new workspaces"),
        ("أريد دليل مستخدمين قابلاً للفلترة مع تفاصيل وتصدير", "I want a filterable user directory with detail and CSV export"),
    ],
    features=[
        ("المساحات: موافقة / تعليق، مع إشعار صاحب المساحة بالتغيير", "Workspaces: approve/suspend, notifying the owner of the change"),
        ("المستخدمون: فلترة، تفعيل/تعليق، صفحة تفصيل، تصدير CSV", "Users: filter, activate/suspend, detail page, CSV export"),
        ("إشعار المستخدم عند تعليق حسابه أو إعادة تفعيله", "User notified when their account is suspended or reactivated"),
    ],
    screenshot="31-admin-workspaces", cap_ar="إدارة المساحات", cap_en="Workspaces",
))

slides.append(content(
    "admin", "السوبر أدمن", "Super admin",
    "الماليّة والمدراء والصلاحيّات", "Finance, admins & permissions",
    stories=[
        ("كسوبر أدمن، أريد إنشاء مدراء فرعيّين ومنحهم صلاحيّات محدّدة بدقّة", "As a super admin, I want to create sub-admins with fine-grained permissions"),
    ],
    features=[
        ("ماليّة المنصّة: الاشتراكات والفواتير عبر كل المساحات", "Platform finance: subscriptions and invoices across all spaces"),
        ("إدارة المدراء: أدوار (سوبر/عادي) + 7 صلاحيّات مُسمّاة", "Admin management: roles (super/standard) + 7 named permissions"),
        ("الصلاحيّات تُقيّد كلّ مسار API وتُخفي عناصر الواجهة غير المتاحة", "Permissions gate every API route and hide unavailable UI"),
        ("السوبر أدمن يملك كل الصلاحيّات؛ العادي يملك ما يُمنح له مباشرة فقط", "Super admin holds all; a standard admin holds only its direct grants"),
    ],
    screenshot="33-admin-finance", cap_ar="الماليّة", cap_en="Finance",
))

slides.append(content(
    "admin", "السوبر أدمن", "Super admin",
    "إدارة محتوى الصفحة الرئيسيّة", "Landing-page content management",
    stories=[
        ("كسوبر أدمن، أريد تحرير محتوى الصفحة العامّة دون مطوّر", "As a super admin, I want to edit the public page without a developer"),
    ],
    features=[
        ("تحرير نصوص وصور وترتيب أقسام الصفحة الرئيسيّة", "Edit copy, images and section order of the landing page"),
        ("اختيار المساحات المميّزة وخطوات «كيف يعمل»", "Choose featured workspaces and the “how it works” steps"),
        ("كل التغييرات فوريّة على الموقع العام", "All changes are live on the public site immediately"),
    ],
    screenshot="32-admin-users", cap_ar="دليل المستخدمين", cap_en="Users directory",
))

# ---- SECTION: Cross-cutting ----
slides.append(section_divider(
    "05", ROLE_COLOR["system"], "PLATFORM SYSTEMS",
    "الأنظمة المشتركة", "Cross-cutting systems",
    "ما يربط الأدوار معاً ويجعل المنصّة حيّة",
    "What ties the roles together and keeps the platform alive",
))

slides.append(content(
    "system", "تواصل", "Communication",
    "الدردشة الفوريّة + الإشعارات", "Realtime chat + notifications",
    features=[
        ("دردشة فوريّة (Firestore) بين الأدوار، بمرفقات تُخزَّن على S3", "Realtime chat (Firestore) across roles, with S3-backed attachments"),
        ("جهات اتصال محدّدة حسب الدور + صورة المرسِل بجانب الرسالة", "Role-scoped contacts + the sender's avatar beside each message"),
        ("شارة عدد الرسائل غير المقروءة بجانب «المحادثات»", "An unread-messages badge next to the Chat nav item"),
        ("16 نوع إشعار: قاعدة بيانات + بريد + دفع على الويب (FCM)", "16 notification types: database + email + web push (FCM)"),
        ("أمثلة: طلب حجز جديد، الموافقة/الرفض، فاتورة، تخصيص مقعد، قرب انتهاء الاشتراك", "e.g. new booking request, approve/reject, invoice, seat assigned, expiry"),
    ],
    screenshot="23-freelancer-notifications", cap_ar="مركز الإشعارات", cap_en="Notifications center",
))

slides.append(content(
    "system", "الفوترة", "Billing model",
    "فوترة بتتبّع يدوي — لا بوّابة دفع", "Manual, tracked billing — no payment gateway",
    features=[
        ("الفواتير تُولَّد شهريّاً تلقائيّاً عبر مهمّة مجدوَلة", "Invoices auto-generate monthly via a scheduled job"),
        ("تعليم المتأخّر يوميّاً، وتذكيرات، وتنبيه قرب انتهاء الاشتراك", "Daily overdue marking, reminders, and subscription-expiry alerts"),
        ("المشرف يسجّل المدفوعات والإيصالات يدويّاً", "The admin records payments and receipts manually"),
        ("تنزيل الفاتورة PDF للمشترك وصاحب المساحة", "Invoice PDF download for the member and the owner"),
    ],
    screenshot="14-owner-invoices", cap_ar="فواتير صاحب المساحة", cap_en="Owner invoices",
))

slides.append(gallery(
    "system", "ثنائيّة اللغة + الوضع الداكن + نظام التصميم", "Bilingual + dark mode + design system",
    [
        ("50-public-home-en", "الواجهة بالإنجليزيّة (LTR)", "English (LTR)"),
        ("52-owner-invoices-dark", "الوضع الداكن", "Dark mode"),
        ("40-foundations", "نظام التصميم", "Design system"),
    ],
))

# ---- Closing ----
slides.append(f"""
    <section class="slide closing">
      <div class="logo">TAQAT<span>.space</span></div>
      <h2 class="ar">منصّة جاهزة، قابلة للتوسّع، وثنائيّة اللغة</h2>
      <h2 class="en">A ready, scalable, bilingual platform</h2>
      <div class="close-grid">
        <div><div class="cg-n">3</div><span class="ar">أدوار متكاملة</span><span class="en">Integrated roles</span></div>
        <div><div class="cg-n">23+</div><span class="ar">شاشة منتَج</span><span class="en">Product screens</span></div>
        <div><div class="cg-n">16</div><span class="ar">نوع إشعار</span><span class="en">Notification types</span></div>
        <div><div class="cg-n">AR/EN</div><span class="ar">عربي أوّلاً + إنجليزي</span><span class="en">Arabic-first + English</span></div>
      </div>
      <p class="ar">للمناقشة: خارطة الطريق، خطط التوسّع، ونماذج الإيرادات المستقبليّة.</p>
      <p class="en">For discussion: roadmap, scaling plans, and future revenue models.</p>
    </section>""")


# ---------------------------------------------------------------------------
#  TEMPLATE
# ---------------------------------------------------------------------------
CSS = """
:root{
  --primary:#1F82C7; --primary-dark:#135789; --accent:#F6A91B;
  --ink:#0F1B2A; --muted:#5B6B7B; --line:#E3E9EF; --bg:#F4F7FA; --card:#fff;
}
*{box-sizing:border-box;margin:0;padding:0}
html{font-size:15px}
body{font-family:'Cairo','Inter',system-ui,sans-serif;color:var(--ink);background:#dfe6ee;
  -webkit-print-color-adjust:exact;print-color-adjust:exact}
.en{font-family:'Inter',sans-serif}
.ar{direction:rtl}
.deck{display:flex;flex-direction:column;align-items:center;gap:22px;padding:26px 10px}

.slide{position:relative;width:1280px;height:720px;background:var(--card);overflow:hidden;
  box-shadow:0 10px 40px rgba(15,27,42,.16);border-radius:14px;
  display:flex;flex-direction:column}
.slide::after{content:'';position:absolute;inset-inline-start:0;top:0;bottom:0;width:8px;
  background:var(--rc,var(--primary))}

/* ---- cover ---- */
.cover{background:linear-gradient(135deg,#0E3A5C,#135789 55%,#1F82C7);color:#fff;padding:64px 72px;justify-content:center}
.cover::after{background:var(--accent)}
.cover-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:48px;align-items:center}
.logo{font-family:'Inter';font-weight:800;font-size:30px;letter-spacing:-.5px}
.logo span{color:var(--accent)}
.cover h1{font-size:33px;line-height:1.25;margin-top:18px;font-weight:700}
.cover h1.en{font-size:22px;opacity:.86;font-weight:600;margin-top:6px}
.cover .lede{margin-top:16px;font-size:15px;line-height:1.7;opacity:.92;max-width:560px}
.cover .lede.en{opacity:.72;font-size:13.5px}
.cover-tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:22px}
.cover-tags span{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);
  padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600}
.cover-art img{width:100%;border-radius:12px;box-shadow:0 18px 50px rgba(0,0,0,.4);
  border:1px solid rgba(255,255,255,.25)}
.cover-foot{position:absolute;bottom:26px;inset-inline-start:72px;inset-inline-end:72px;
  display:flex;justify-content:space-between;font-size:12px;opacity:.7}

/* ---- divider ---- */
.divider{background:linear-gradient(135deg,#0F1B2A,#1d2c3d);color:#fff;justify-content:center;padding:0 80px}
.divider::after{background:var(--rc)}
.divider-num{position:absolute;inset-inline-end:60px;top:40px;font-family:'Inter';font-weight:800;
  font-size:150px;color:rgba(255,255,255,.07);line-height:1}
.divider .kicker{color:var(--rc);font-family:'Inter';font-weight:700;letter-spacing:3px;font-size:14px;margin-bottom:14px}
.divider h2{font-size:42px;font-weight:700;line-height:1.2}
.divider h2.en{font-size:24px;opacity:.7;font-weight:600;margin-top:4px}
.divider p{margin-top:18px;font-size:15px;opacity:.78;max-width:680px}
.divider p.en{opacity:.55;font-size:13px;margin-top:2px}

/* ---- content ---- */
.content{flex-direction:row;padding:48px 56px 40px 64px;gap:40px}
.c-left{flex:1;min-width:0;display:flex;flex-direction:column}
.c-right{width:46%;display:flex;align-items:center}
.no-shot .c-left{flex:1}
.no-shot{padding-inline-end:64px}
.kicker{display:flex;gap:8px;align-items:baseline;font-weight:700;font-size:13px;
  color:var(--rc);margin-bottom:6px}
.kicker .en{opacity:.6;font-size:11px;text-transform:uppercase;letter-spacing:1px}
.slide h3{font-size:26px;font-weight:700;line-height:1.2}
.slide h3.en{font-size:16px;color:var(--muted);font-weight:600;margin-bottom:8px}
.block-label{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;
  color:var(--rc);margin-top:16px}
.block-label.en{color:var(--muted);opacity:.7;margin-top:1px;margin-bottom:6px;letter-spacing:.5px}
.stories{display:flex;flex-direction:column;gap:7px}
.story{background:var(--bg);border-inline-start:3px solid var(--rc);border-radius:8px;
  padding:8px 12px}
.story .ar{display:block;font-size:13.5px;font-weight:600;line-height:1.45}
.story .en{display:block;font-size:11.5px;color:var(--muted);line-height:1.4;font-style:italic}
.blist{list-style:none;display:flex;flex-direction:column;gap:6px;margin-top:4px}
.blist li{position:relative;padding-inline-start:20px;line-height:1.4}
.blist li::before{content:'';position:absolute;inset-inline-start:2px;top:8px;width:8px;height:8px;
  border-radius:2px;background:var(--rc)}
.blist li .ar{display:block;font-size:13.5px;font-weight:600}
.blist li .en{display:block;font-size:11.5px;color:var(--muted)}
.flow{display:flex;flex-wrap:wrap;gap:7px;margin-top:6px}
.step{display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--line);
  border-radius:9px;padding:7px 11px}
.step-n{width:22px;height:22px;border-radius:50%;background:var(--rc);color:#fff;display:grid;
  place-items:center;font-family:'Inter';font-weight:700;font-size:12px;flex:none}
.step-t .ar{display:block;font-size:12px;font-weight:600;line-height:1.3}
.step-t .en{display:block;font-size:10px;color:var(--muted)}
.shot{width:100%;background:var(--bg);border:1px solid var(--line);border-radius:12px;
  overflow:hidden;box-shadow:0 8px 26px rgba(15,27,42,.1)}
.shot img{width:100%;display:block}
.shot figcaption{padding:7px 12px;font-size:11px;display:flex;justify-content:space-between;
  border-top:1px solid var(--line);background:#fff}
.shot figcaption .en{color:var(--muted)}

/* ---- gallery ---- */
.gallery{padding:40px 56px}
.g-head{margin-bottom:18px}
.g-head h3{font-size:24px;font-weight:700}
.g-head h3.en{font-size:15px;color:var(--muted);font-weight:600}
.g-grid{flex:1;display:grid;gap:16px}
.g-grid.g-3{grid-template-columns:repeat(3,1fr)}
.g-grid.g-4{grid-template-columns:repeat(4,1fr)}
.g-grid .shot{align-self:start}

/* ---- closing ---- */
.closing{background:linear-gradient(135deg,#0E3A5C,#1F82C7);color:#fff;justify-content:center;
  align-items:center;text-align:center;padding:56px}
.closing::after{background:var(--accent)}
.closing .logo{font-size:34px}
.closing h2{font-size:30px;font-weight:700;margin-top:14px}
.closing h2.en{font-size:18px;opacity:.78;font-weight:600}
.close-grid{display:flex;gap:40px;margin:34px 0 26px}
.close-grid>div{display:flex;flex-direction:column;align-items:center;gap:3px}
.cg-n{font-family:'Inter';font-weight:800;font-size:40px;color:var(--accent);line-height:1}
.close-grid span{font-size:12px}
.close-grid .en{opacity:.6}
.closing p{font-size:14px;opacity:.85}
.closing p.en{opacity:.6;font-size:12px}

/* slide number chip */
.snum{position:absolute;inset-inline-end:20px;bottom:16px;font-family:'Inter';font-size:11px;
  color:var(--muted);font-weight:600;opacity:.7}
.cover .snum,.divider .snum,.closing .snum{color:rgba(255,255,255,.6)}

@media print{
  @page{size:1280px 720px;margin:0}
  body{background:#fff}
  .deck{padding:0;gap:0}
  .slide{box-shadow:none;border-radius:0;page-break-after:always;break-after:page}
  .slide:last-child{page-break-after:auto;break-after:auto}
}
"""


def build():
    numbered = []
    for i, s in enumerate(slides):
        # inject slide number chip before closing </section>
        chip = f'<div class="snum">{i+1} / {len(slides)}</div></section>'
        numbered.append(s.rstrip()[:-len("</section>")] + chip)
    body = "\n".join(numbered)
    doc = f"""<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>TAQAT.space — عرض الميزات / Features Deck</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet" />
<style>{CSS}</style>
</head>
<body>
<div class="deck">
{body}
</div>
</body>
</html>"""
    with open(OUT, "w", encoding="utf-8") as f:
        f.write(doc)
    print(f"Wrote {OUT} ({len(slides)} slides)")


if __name__ == "__main__":
    build()
