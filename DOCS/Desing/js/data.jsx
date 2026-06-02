/* TAQAT — sample Gaza content + i18n. Numbers/currency stay LTR tabular. */

const NIS = '₪';

const WORKSPACES = [
  { id:'w1', name:'مساحة المنارة', nameEn:'Al-Manara Space', city:'غزة', cityEn:'Gaza City', area:'الرمال', rating:4.8, reviews:126, price:35, seats:48, occ:84, amen:['wifi','coffee','parking','snow'], tag:'الأعلى تقييماً', tagEn:'Top rated', img:'#dbe7f0' },
  { id:'w2', name:'مركز خان يونس الرقمي', nameEn:'Khan Younis Digital Hub', city:'خان يونس', cityEn:'Khan Younis', area:'وسط البلد', rating:4.6, reviews:88, price:28, seats:36, occ:67, amen:['wifi','coffee','snow'], tag:'الأكثر طلباً', tagEn:'In demand', img:'#e7e2d8' },
  { id:'w3', name:'بيت العمل - رفح', nameEn:'WorkHouse Rafah', city:'رفح', cityEn:'Rafah', area:'تل السلطان', rating:4.4, reviews:54, price:22, seats:28, occ:43, amen:['wifi','parking'], tag:null, tagEn:null, img:'#dde7e1' },
  { id:'w4', name:'منصّة الشمال', nameEn:'North Platform', city:'جباليا', cityEn:'Jabalia', area:'معسكر جباليا', rating:4.7, reviews:71, price:30, seats:40, occ:72, amen:['wifi','coffee','parking','snow'], tag:'جديد', tagEn:'New', img:'#e6dde7' },
  { id:'w5', name:'فضاء دير البلح', nameEn:'Deir al-Balah Space', city:'دير البلح', cityEn:'Deir al-Balah', area:'البحر', rating:4.5, reviews:39, price:25, seats:24, occ:55, amen:['wifi','coffee'], tag:null, tagEn:null, img:'#dce4ea' },
  { id:'w6', name:'حاضنة النصيرات', nameEn:'Nuseirat Incubator', city:'النصيرات', cityEn:'Nuseirat', area:'السوق', rating:4.3, reviews:47, price:20, seats:32, occ:61, amen:['wifi','parking','coffee'], tag:null, tagEn:null, img:'#e9e3da' },
];

const MEMBERS = [
  { id:'m1', name:'أحمد الفرا', nameEn:'Ahmad Al-Farra', spec:'مطوّر واجهات', email:'ahmad.f@mail.ps', seat:'A-04', plan:'شهري', status:'active', since:'2025-09', avatar:'أ' },
  { id:'m2', name:'ليان أبو شاويش', nameEn:'Lian Abu Shawish', spec:'مصممة UI/UX', email:'lian.s@mail.ps', seat:'B-02', plan:'ربع سنوي', status:'active', since:'2025-07', avatar:'ل' },
  { id:'m3', name:'يوسف مقداد', nameEn:'Yousef Miqdad', spec:'كاتب محتوى', email:'y.miqdad@mail.ps', seat:'A-09', plan:'شهري', status:'pending', since:'2026-05', avatar:'ي' },
  { id:'m4', name:'سارة النجار', nameEn:'Sara Al-Najjar', spec:'محللة بيانات', email:'sara.n@mail.ps', seat:'C-01', plan:'سنوي', status:'active', since:'2025-03', avatar:'س' },
  { id:'m5', name:'محمد سكيك', nameEn:'Mohammed Skaik', spec:'مسوّق رقمي', email:'m.skaik@mail.ps', seat:'—', plan:'شهري', status:'suspended', since:'2024-11', avatar:'م' },
  { id:'m6', name:'رنا الهبيل', nameEn:'Rana Al-Hubail', spec:'مطوّرة تطبيقات', email:'rana.h@mail.ps', seat:'B-07', plan:'ربع سنوي', status:'active', since:'2025-12', avatar:'ر' },
  { id:'m7', name:'خالد عاشور', nameEn:'Khaled Ashour', spec:'مهندس برمجيات', email:'k.ashour@mail.ps', seat:'A-12', plan:'شهري', status:'active', since:'2026-01', avatar:'خ' },
];

const INVOICES = [
  { id:'INV-2605', member:'أحمد الفرا', amount:35, date:'2026-05-28', due:'2026-06-05', status:'paid', plan:'اشتراك شهري' },
  { id:'INV-2604', member:'ليان أبو شاويش', amount:95, date:'2026-05-26', due:'2026-06-03', status:'paid', plan:'اشتراك ربع سنوي' },
  { id:'INV-2603', member:'يوسف مقداد', amount:35, date:'2026-05-25', due:'2026-06-01', status:'pending', plan:'اشتراك شهري' },
  { id:'INV-2602', member:'سارة النجار', amount:320, date:'2026-05-20', due:'2026-05-27', status:'overdue', plan:'اشتراك سنوي' },
  { id:'INV-2601', member:'رنا الهبيل', amount:95, date:'2026-05-18', due:'2026-05-26', status:'paid', plan:'اشتراك ربع سنوي' },
  { id:'INV-2600', member:'خالد عاشور', amount:35, date:'2026-05-15', due:'2026-05-22', status:'pending', plan:'اشتراك شهري' },
];

const REQUESTS = [
  { id:'r1', name:'نور حمدان', spec:'مطوّرة Backend', date:'2026-06-01', seatPref:'مكتب خاص', avatar:'ن' },
  { id:'r2', name:'عمر زقوت', spec:'مصمم جرافيك', date:'2026-05-31', seatPref:'مقعد مفتوح', avatar:'ع' },
  { id:'r3', name:'دانا أبو ندى', spec:'مديرة مشاريع', date:'2026-05-31', seatPref:'قاعة اجتماعات', avatar:'د' },
];

const PLANS = [
  { id:'p1', name:'يومي', nameEn:'Daily', price:8, unit:'يوم', popular:false, feats:['مقعد مفتوح','إنترنت 50 ميجا','قهوة مجانية'] },
  { id:'p2', name:'شهري', nameEn:'Monthly', price:35, unit:'شهر', popular:true, feats:['مقعد ثابت','إنترنت 100 ميجا','قهوة + شاي','خصم 10% على القاعات'] },
  { id:'p3', name:'ربع سنوي', nameEn:'Quarterly', price:95, unit:'٣ أشهر', popular:false, feats:['مكتب مخصّص','إنترنت 200 ميجا','كل المرافق','٤ ساعات قاعات شهرياً'] },
];

const PACKAGES = [
  { id:'pk1', name:'أساسي', speed:'50 ميجا', cap:'غير محدود', price:0, members:18 },
  { id:'pk2', name:'احترافي', speed:'100 ميجا', cap:'غير محدود', price:10, members:22 },
  { id:'pk3', name:'مكثّف', speed:'200 ميجا', cap:'غير محدود', price:20, members:8 },
];

const ACTIVITY = [
  { ico:'wallet', tone:'ok', text:'دفعة جديدة من أحمد الفرا', sub:'فاتورة INV-2605', time:'قبل ٥ د', amt:'+35' },
  { ico:'user', tone:'info', text:'طلب انضمام جديد — نور حمدان', sub:'بانتظار الموافقة', time:'قبل ٢٢ د', amt:null },
  { ico:'grid', tone:'info', text:'تم تعيين المقعد B-07 لـ رنا الهبيل', sub:'الطابق الأول', time:'قبل ساعة', amt:null },
  { ico:'alert', tone:'warn', text:'فاتورة متأخرة — سارة النجار', sub:'INV-2602 · تجاوزت موعد الاستحقاق', time:'قبل ٣ س', amt:'-320' },
  { ico:'megaphone', tone:'info', text:'تم نشر إعلان: صيانة الإنترنت', sub:'مجدول الجمعة ٩ مساءً', time:'أمس', amt:null },
];

// Super admin platform-level data
const ADMIN_SPACES = [
  { id:'as1', name:'مساحة المنارة', owner:'سمير الأغا', city:'غزة', members:48, revenue:1680, status:'active', commission:15 },
  { id:'as2', name:'مركز خان يونس الرقمي', owner:'هبة قديح', city:'خان يونس', members:36, revenue:1008, status:'active', commission:15 },
  { id:'as3', name:'بيت العمل - رفح', owner:'وسيم بربخ', city:'رفح', members:28, revenue:616, status:'active', commission:12 },
  { id:'as4', name:'منصّة الشمال', owner:'آلاء مهنا', city:'جباليا', members:40, revenue:1200, status:'pending', commission:15 },
  { id:'as5', name:'فضاء دير البلح', owner:'كريم صيدم', city:'دير البلح', members:24, revenue:600, status:'active', commission:12 },
  { id:'as6', name:'حاضنة النصيرات', owner:'ميس البطش', city:'النصيرات', members:32, revenue:640, status:'suspended', commission:15 },
];

// 6-month series for charts
const REVENUE_SERIES = [
  { m:'ديسمبر', mEn:'Dec', v:9.2 }, { m:'يناير', mEn:'Jan', v:10.4 }, { m:'فبراير', mEn:'Feb', v:11.1 },
  { m:'مارس', mEn:'Mar', v:10.8 }, { m:'أبريل', mEn:'Apr', v:12.6 }, { m:'مايو', mEn:'May', v:13.8 },
];
const OCCUPANCY_SERIES = [
  { d:'السبت', dEn:'Sat', v:62 }, { d:'الأحد', dEn:'Sun', v:78 }, { d:'الإثنين', dEn:'Mon', v:84 },
  { d:'الثلاثاء', dEn:'Tue', v:80 }, { d:'الأربعاء', dEn:'Wed', v:88 }, { d:'الخميس', dEn:'Thu', v:71 }, { d:'الجمعة', dEn:'Fri', v:24 },
];

// Marquee value-prop ticker (bilingual pairs)
const MARQUEE = [
  ['مساحات موثّقة','Verified spaces'], ['حجز فوري','Instant booking'], ['فواتير شفافة','Transparent invoices'],
  ['خرائط مقاعد حيّة','Live seat maps'], ['باقات إنترنت','Internet packages'], ['دعم محلي','Local support'],
  ['٩ مدن ومناطق','9 cities & areas'], ['بدون رسوم خفية','No hidden fees'],
];

// Platform capabilities — numbered services grid
const CAPABILITIES = [
  { n:'01', ico:'search', ar:'اكتشف المساحات', en:'Discover spaces', arD:'تصفّح ١٢٨ مساحة على الخريطة وقارن حسب السعر والمرافق والتقييم.', enD:'Browse 128 spaces on the map and compare by price, amenities and rating.' },
  { n:'02', ico:'calendar', ar:'احجز مقعدك فوراً', en:'Book a seat instantly', arD:'اختر مقعداً أو مكتباً خاصاً وأرسل طلب الحجز خلال ثوانٍ.', enD:'Pick an open seat or private office and send a request in seconds.' },
  { n:'03', ico:'receipt', ar:'فواتير واشتراكات', en:'Invoices & subscriptions', arD:'تابع اشتراكك وفواتيرك وادفع إلكترونياً بشفافية كاملة.', enD:'Track your plan and invoices, and pay online with full transparency.' },
  { n:'04', ico:'grid', ar:'خرائط مقاعد حيّة', en:'Live seat maps', arD:'شاهد المقاعد المتاحة لحظياً واحجز المكان الذي يناسبك.', enD:'See available seats in real time and grab the spot that fits you.' },
  { n:'05', ico:'wifi', ar:'باقات إنترنت', en:'Internet packages', arD:'اختر سرعة الإنترنت المناسبة لعملك من باقات كل مساحة.', enD:'Choose the internet speed your work needs from each space’s packages.' },
  { n:'06', ico:'chart', ar:'تقارير لأصحاب المساحات', en:'Owner analytics', arD:'لوحة تحكم كاملة للإيرادات والإشغال وإدارة الأعضاء.', enD:'A full dashboard for revenue, occupancy and member management.' },
];

// Member stories
const TESTIMONIALS = [
  { ar:'«طاقت خلّتني ألاقي مساحة قريبة من بيتي بإنترنت سريع خلال دقائق. صرت أشتغل بتركيز أكبر بكثير.»', en:'“TAQAT let me find a space near home with fast internet in minutes. I work with so much more focus now.”', name:'أحمد الفرا', nameEn:'Ahmad Al-Farra', role:'مطوّر واجهات · غزة', roleEn:'Frontend Developer · Gaza', avatar:'أ' },
  { ar:'«كصاحبة مساحة، لوحة التحكم وفّرت عليّ ساعات. الفواتير والمقاعد صارت تُدار من مكان واحد.»', en:'“As a space owner, the dashboard saves me hours. Invoices and seats are all managed in one place.”', name:'هبة قديح', nameEn:'Heba Qudaih', role:'صاحبة مركز خان يونس الرقمي', roleEn:'Owner, Khan Younis Hub', avatar:'ه' },
  { ar:'«الشبكة اللي بنيتها مع باقي الفريلانسرز في المساحة ما لها ثمن. طاقت أكثر من مجرد حجز مقعد.»', en:'“The network I built with other freelancers here is priceless. TAQAT is more than just booking a seat.”', name:'ليان أبو شاويش', nameEn:'Lian Abu Shawish', role:'مصممة UI/UX · رفح', roleEn:'UI/UX Designer · Rafah', avatar:'ل' },
];

const AMENITY_LABELS = {
  wifi:{ ar:'إنترنت سريع', en:'Fast Wi-Fi', ico:'wifi' },
  coffee:{ ar:'قهوة مجانية', en:'Free coffee', ico:'coffee' },
  parking:{ ar:'موقف سيارات', en:'Parking', ico:'parking' },
  snow:{ ar:'تكييف', en:'Air conditioning', ico:'snow' },
};

const T = {
  ar: {
    dir:'rtl', langName:'العربية', other:'EN',
    nav_features:'المميزات', nav_explore:'استكشف المساحات', nav_how:'كيف تعمل', nav_about:'عن المنصة',
    login:'تسجيل الدخول', signup:'إنشاء حساب', getStarted:'ابدأ الآن',
    hero_kicker:'مفتوح الآن في كل قطاع غزة',
    hero_title:'طاقتك تجد مكانها.',
    hero_t1:'طاقتك تجد', hero_t2:'مكانها', hero_t3:'.',
    why_eyebrow:'لماذا طاقت', why_title1:'مبنية لجيل', why_title2:'غزة المُنتِج',
    why_sub:'طاقت أكثر من مجرّد منصّة حجز — هي البيت الرقمي لاقتصاد العمل الحر ومساحات العمل في قطاع غزة.',
    why1:'بيئة عمل احترافية وقريبة منك', why2:'مجتمع من آلاف الفريلانسرز والشركات', why3:'إدارة كاملة للفواتير والاشتراكات', why4:'ربط مباشر بين أصحاب المساحات والأعضاء',
    why_stat:'رضا الأعضاء', satisfaction:'٪٩٦',
    caps_eyebrow:'ماذا تقدّم المنصّة', caps_title1:'كل ما تحتاجه', caps_title2:'في مكان واحد',
    tstm_eyebrow:'قصص الأعضاء', tstm_title1:'ماذا يقول', tstm_title2:'مجتمعنا',
    spaces_eyebrow:'أنواع المساحات', spaces_title1:'مصمَّمة لكل', spaces_title2:'أسلوب عمل',
    hero_sub:'منصّة تربط أصحاب مساحات العمل بالعاملين المستقلين في قطاع غزة — احجز مقعدك، أدِر مساحتك، وتابع كل شيء من مكان واحد.',
    hero_cta1:'سجّل كعامل مستقل', hero_cta2:'سجّل مساحتك',
    stat_spaces:'مساحة عمل نشطة', stat_free:'عامل مستقل', stat_occ:'متوسط الإشغال', stat_cities:'مدن ومناطق',
    featured:'مساحات مختارة', featured_sub:'أعلى المساحات تقييماً عبر القطاع', viewAll:'عرض الكل',
    how_kicker:'كيف تعمل', how_title:'ثلاث خطوات إلى مكتبك',
    how1_t:'اكتشف', how1_d:'تصفّح المساحات حسب المدينة والسعر والمرافق على الخريطة.',
    how2_t:'احجز', how2_d:'اختر مقعدك أو مكتبك وأرسل طلب الحجز فوراً.',
    how3_t:'اعمل', how3_d:'ادفع اشتراكك وتابع فواتيرك ومقعدك من لوحتك.',
    cta_band_t:'جاهز تبدأ يومك في مكان يليق بطاقتك؟', cta_band_d:'انضم لمئات العاملين المستقلين وأصحاب المساحات في غزة.',
    book:'احجز الآن', perMonth:'/ شهرياً', perDay:'/ يومياً', seats:'مقعد', occupancy:'الإشغال', reviews:'تقييم',
    email:'البريد الإلكتروني', password:'كلمة المرور', forgot:'نسيت كلمة المرور؟', remember:'تذكّرني',
    login_t:'أهلاً بعودتك', login_sub:'سجّل دخولك لإدارة مساحتك أو اشتراكك.', noAccount:'ليس لديك حساب؟',
    or:'أو', continueGoogle:'المتابعة عبر Google',
    footer_tag:'منصّة مساحات العمل المشتركة في قطاع غزة.',
  },
  en: {
    dir:'ltr', langName:'English', other:'ع',
    nav_features:'Features', nav_explore:'Explore spaces', nav_how:'How it works', nav_about:'About',
    login:'Log in', signup:'Sign up', getStarted:'Get started',
    hero_kicker:'Now open across the Gaza Strip',
    hero_title:'Your energy finds its place.',
    hero_t1:'Your energy finds', hero_t2:'its place', hero_t3:'.',
    why_eyebrow:'Why TAQAT', why_title1:'Built for Gaza’s', why_title2:'productive generation',
    why_sub:'TAQAT is more than a booking platform — it’s the digital home of Gaza’s freelance and coworking economy.',
    why1:'A professional workspace close to you', why2:'A community of thousands of freelancers & firms', why3:'Full invoice & subscription management', why4:'A direct link between owners and members',
    why_stat:'member satisfaction', satisfaction:'96%',
    caps_eyebrow:'What the platform offers', caps_title1:'Everything you need', caps_title2:'in one place',
    tstm_eyebrow:'Member stories', tstm_title1:'What our', tstm_title2:'community says',
    spaces_eyebrow:'Space types', spaces_title1:'Designed for every', spaces_title2:'work style',
    hero_sub:'TAQAT connects coworking-space owners with independent workers across Gaza — book a seat, run your space, and track everything from one place.',
    hero_cta1:'Register as freelancer', hero_cta2:'List your space',
    stat_spaces:'Active spaces', stat_free:'Freelancers', stat_occ:'Avg. occupancy', stat_cities:'Cities & areas',
    featured:'Featured spaces', featured_sub:'Top-rated workspaces across the strip', viewAll:'View all',
    how_kicker:'How it works', how_title:'Three steps to your desk',
    how1_t:'Discover', how1_d:'Browse spaces by city, price and amenities on the map.',
    how2_t:'Book', how2_d:'Pick your seat or office and send a booking request instantly.',
    how3_t:'Work', how3_d:'Pay your subscription and track invoices and your seat from your dashboard.',
    cta_band_t:'Ready to start your day somewhere worthy of your energy?', cta_band_d:'Join hundreds of freelancers and space owners in Gaza.',
    book:'Book now', perMonth:'/ month', perDay:'/ day', seats:'seats', occupancy:'occupancy', reviews:'reviews',
    email:'Email address', password:'Password', forgot:'Forgot password?', remember:'Remember me',
    login_t:'Welcome back', login_sub:'Log in to manage your space or subscription.', noAccount:"Don't have an account?",
    or:'or', continueGoogle:'Continue with Google',
    footer_tag:'Coworking-space platform for the Gaza Strip.',
  }
};

Object.assign(window, { NIS, WORKSPACES, MEMBERS, INVOICES, REQUESTS, PLANS, PACKAGES, ACTIVITY, ADMIN_SPACES, REVENUE_SERIES, OCCUPANCY_SERIES, AMENITY_LABELS, MARQUEE, CAPABILITIES, TESTIMONIALS, T });
