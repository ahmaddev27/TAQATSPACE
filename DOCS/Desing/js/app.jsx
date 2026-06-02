/* TAQAT — app shell: routing, language/theme, screen launcher, tweaks */
const { useState:useStateApp, useEffect:useEffectApp } = React;

const SCREEN_TITLE = {
  'owner:dashboard':{ar:'لوحة التحكم',en:'Dashboard',sub:{ar:'نظرة عامة على مساحتك اليوم',en:'Your space at a glance today'}},
  'owner:members':{ar:'إدارة المشتركين',en:'Members',sub:{ar:'اقبل، علّق، وتابع أعضاءك',en:'Accept, suspend and track members'}},
  'owner:seats':{ar:'إدارة المقاعد',en:'Seats',sub:{ar:'عيّن المقاعد وتابع الحضور',en:'Assign seats and track attendance'}},
  'owner:requests':{ar:'طلبات الحجز',en:'Booking requests',sub:{ar:'راجع الطلبات الواردة',en:'Review incoming requests'}},
  'owner:invoices':{ar:'الفواتير والمدفوعات',en:'Invoices & payments',sub:{ar:'أصدر الفواتير وتابع المدفوعات',en:'Issue invoices and track payments'}},
  'owner:packages':{ar:'باقات الإنترنت',en:'Internet packages',sub:{ar:'أدِر السرعات والأسعار',en:'Manage speeds and pricing'}},
  'owner:reports':{ar:'التقارير',en:'Reports',sub:{ar:'الإيراد والإشغال وأكثر الأعضاء نشاطاً',en:'Revenue, occupancy and top members'}},
  'owner:messages':{ar:'الرسائل',en:'Messages',sub:null},
  'owner:announce':{ar:'الإعلانات',en:'Announcements',sub:null},
  'owner:settings':{ar:'الإعدادات',en:'Settings',sub:null},
  'member:home':{ar:'مرحباً، أحمد',en:'Welcome, Ahmad',sub:{ar:'إليك ملخّص اشتراكك اليوم',en:'Here’s your subscription summary'}},
  'member:explore':{ar:'استكشف المساحات',en:'Explore spaces',sub:{ar:'قارن المساحات واحجز مقعدك',en:'Compare spaces and book a seat'}},
  'member:subscription':{ar:'اشتراكي وحجوزاتي',en:'My subscription',sub:{ar:'خطتك الحالية وخيارات التجديد',en:'Your current plan and renewal'}},
  'member:invoices':{ar:'فواتيري',en:'My invoices',sub:{ar:'سجل الفواتير والدفع',en:'Invoice history and payments'}},
  'member:notifications':{ar:'الإشعارات',en:'Notifications',sub:null},
  'member:profile':{ar:'الملف الشخصي',en:'Profile',sub:{ar:'عدّل معلوماتك وبياناتك',en:'Edit your information'}},
  'admin:analytics':{ar:'إحصائيات المنصة',en:'Platform analytics',sub:{ar:'أداء طاقت عبر القطاع',en:'TAQAT performance across the strip'}},
  'admin:workspaces':{ar:'إدارة المساحات',en:'Workspace management',sub:{ar:'وافق، ارفض، وعلّق المساحات',en:'Approve, reject and suspend spaces'}},
  'admin:users':{ar:'إدارة المستخدمين',en:'User management',sub:{ar:'كل الحسابات والصلاحيات',en:'All accounts and access'}},
  'admin:finance':{ar:'المالية والعمولات',en:'Finance & commissions',sub:{ar:'العمولات والتحويلات والتقارير',en:'Commissions, transfers and reports'}},
  'admin:announce':{ar:'الإعلانات العامة',en:'General announcements',sub:null},
  'admin:settings':{ar:'إعدادات المنصة',en:'Platform settings',sub:null},
};

const LAUNCH_GROUPS = [
  { label:{ar:'الموقع العام',en:'Public site'}, color:'#1F82C7', items:[
    ['home',{ar:'الرئيسية',en:'Home'}],['explore',{ar:'استكشف المساحات',en:'Explore'}],['detail',{ar:'تفاصيل المساحة',en:'Workspace detail'}],
    ['reg-free',{ar:'تسجيل عامل مستقل',en:'Register freelancer'}],['reg-space',{ar:'تسجيل مساحة',en:'Register workspace'}],['login',{ar:'تسجيل الدخول',en:'Login'}],
  ]},
  { label:{ar:'لوحة صاحب المساحة',en:'Owner dashboard'}, color:'#176AA8', items:[
    ['owner:dashboard',{ar:'لوحة التحكم',en:'Dashboard'}],['owner:members',{ar:'المشتركون',en:'Members'}],['owner:seats',{ar:'المقاعد',en:'Seats'}],
    ['owner:requests',{ar:'طلبات الحجز',en:'Requests'}],['owner:invoices',{ar:'الفواتير',en:'Invoices'}],['owner:packages',{ar:'باقات الإنترنت',en:'Packages'}],['owner:reports',{ar:'التقارير',en:'Reports'}],
  ]},
  { label:{ar:'لوحة العامل المستقل',en:'Freelancer dashboard'}, color:'#16A34A', items:[
    ['member:home',{ar:'الرئيسية',en:'Home'}],['member:subscription',{ar:'اشتراكي',en:'Subscription'}],['member:invoices',{ar:'فواتيري',en:'Invoices'}],['member:notifications',{ar:'الإشعارات',en:'Notifications'}],['member:profile',{ar:'الملف الشخصي',en:'Profile'}],
  ]},
  { label:{ar:'لوحة الأدمن',en:'Super admin'}, color:'#F6A91B', items:[
    ['admin:analytics',{ar:'الإحصائيات',en:'Analytics'}],['admin:workspaces',{ar:'إدارة المساحات',en:'Workspaces'}],['admin:users',{ar:'المستخدمون',en:'Users'}],['admin:finance',{ar:'المالية',en:'Finance'}],
  ]},
  { label:{ar:'نظام التصميم',en:'Design system'}, color:'#52525B', items:[ ['foundations',{ar:'الأسس والمكوّنات',en:'Foundations'}] ]},
];

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "primary": ["#1F82C7","#176AA8","#EAF4FB"],
  "accent": "#F6A91B",
  "radius": "signature",
  "textSize": 16
}/*EDITMODE-END*/;

function ScreenLauncher({ route, go, lang }) {
  const [open, setOpen] = useStateApp(false);
  const ar = lang==='ar';
  return (
    <>
      <button className={`launcher-fab ${open?'is-open':''}`} onClick={()=>setOpen(!open)} aria-label="screens">
        <Icon name={open?'x':'layers'} size={20} />{!open && <span>{ar?'الشاشات':'Screens'}</span>}
      </button>
      {open && <div className="launcher-panel">
        <div className="launcher-head"><span className="fnd-label" style={{margin:0}}>{ar?'تنقّل بين الشاشات':'Jump to screen'}</span><span className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{ar?'٢٠ شاشة':'20 screens'}</span></div>
        <div className="launcher-scroll">
          {LAUNCH_GROUPS.map((g,i)=> (
            <div key={i} className="launcher-group">
              <div className="launcher-group-h"><span className="lg-dot" style={{background:g.color}}></span>{ar?g.label.ar:g.label.en}</div>
              {g.items.map(([id,lbl])=> (
                <button key={id} className={`launcher-item ${route===id?'active':''}`} onClick={()=>{go(id); setOpen(false);}}>
                  {ar?lbl.ar:lbl.en}{route===id && <Icon name="check" size={15} />}
                </button>
              ))}
            </div>
          ))}
        </div>
      </div>}
    </>
  );
}

function App() {
  const [lang, setLang] = useStateApp(()=> localStorage.getItem('tq_lang')||'ar');
  const [theme, setTheme] = useStateApp(()=> localStorage.getItem('tq_theme')||'light');
  const [route, setRoute] = useStateApp(()=> localStorage.getItem('tq_route')||'home');
  const [params, setParams] = useStateApp({});
  const [toasts, setToasts] = useStateApp([]);
  const [tw, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const t = T[lang];

  useEffectApp(()=>{ document.documentElement.lang = lang; document.documentElement.dir = T[lang].dir; localStorage.setItem('tq_lang',lang); }, [lang]);
  useEffectApp(()=>{ document.documentElement.setAttribute('data-theme', theme); localStorage.setItem('tq_theme',theme); }, [theme]);
  useEffectApp(()=>{ localStorage.setItem('tq_route',route); const s=document.querySelector('.dash-scroll, .fnd, .pub'); if(s) s.scrollTop=0; window.scrollTo(0,0); }, [route]);

  // apply visual tweaks
  useEffectApp(()=>{
    const r = document.documentElement.style;
    const [p,ph] = tw.primary; r.setProperty('--primary',p); r.setProperty('--primary-hover',ph); r.setProperty('--ring',p); r.setProperty('--info',p);
    // derive a theme-aware soft tint from the current surface so dark mode stays dark
    r.setProperty('--primary-soft', `color-mix(in srgb, ${p} ${theme==='dark'?'22%':'12%'}, var(--surface))`);
    r.setProperty('--accent', tw.accent);
    const rad = {sharp:[6,8,12], signature:[12,14,20], round:[16,20,28]}[tw.radius]||[12,14,20];
    r.setProperty('--r-lg',rad[0]+'px'); r.setProperty('--r-tile',rad[1]+'px'); r.setProperty('--r-2xl',rad[2]+'px');
    r.fontSize = tw.textSize+'px';
  }, [tw, theme]);

  const go = (r, p={}) => { setRoute(r); setParams(p); };
  const toast = (tt) => { const id=Date.now()+Math.random(); setToasts(x=>[...x,{...tt,id}]); setTimeout(()=>setToasts(x=>x.filter(z=>z.id!==id)), 3200); };
  const shared = { lang, t, go, theme, setTheme, setLang, toast, params };

  // ---- render screen ----
  let body;
  const dashFor = (role, comp) => <DashShell role={role} active={route} {...shared} title={SCREEN_TITLE[route]?(lang==='ar'?SCREEN_TITLE[route].ar:SCREEN_TITLE[route].en):''} subtitle={SCREEN_TITLE[route]&&SCREEN_TITLE[route].sub?(lang==='ar'?SCREEN_TITLE[route].sub.ar:SCREEN_TITLE[route].sub.en):null} actions={dashActions(role)}>{comp}</DashShell>;

  if (route==='foundations') body = <Foundations {...shared} />;
  else if (route==='login') body = <Login {...shared} />;
  else if (route==='reg-free' || route==='reg-space' || route==='home' || route==='explore' || route==='detail') {
    const inner = route==='home'?<Home {...shared} />:route==='explore'?<Explore {...shared} />:route==='detail'?<WorkspaceDetail {...shared} />:route==='reg-free'?<RegisterFreelancer {...shared} />:<RegisterWorkspace {...shared} />;
    const noFooter = route==='reg-free'||route==='reg-space';
    body = <div className="pub-wrap"><PublicHeader {...shared} setLang={setLang} setTheme={setTheme} route={route} />{inner}{!noFooter && <PublicFooter t={t} lang={lang} go={go} />}</div>;
  }
  else if (route.startsWith('owner:')) {
    const c = { 'owner:dashboard':<OwnerDashboard {...shared} />, 'owner:members':<OwnerMembers {...shared} />, 'owner:seats':<OwnerSeats {...shared} />, 'owner:requests':<OwnerRequests {...shared} />, 'owner:invoices':<OwnerInvoices {...shared} />, 'owner:packages':<OwnerPackages {...shared} />, 'owner:reports':<OwnerReports {...shared} /> }[route] || <ComingSoon lang={lang} title={lang==='ar'?SCREEN_TITLE[route].ar:SCREEN_TITLE[route].en} icon={route.includes('messages')?'chat':route.includes('announce')?'megaphone':'settings'} />;
    body = dashFor('owner', c);
  }
  else if (route.startsWith('member:')) {
    const c = { 'member:home':<MemberHome {...shared} />, 'member:explore':<Explore {...shared} />, 'member:subscription':<MemberSubscription {...shared} />, 'member:invoices':<MemberInvoices {...shared} />, 'member:notifications':<MemberNotifications {...shared} />, 'member:profile':<MemberProfile {...shared} /> }[route];
    body = dashFor('member', c);
  }
  else if (route.startsWith('admin:')) {
    const c = { 'admin:analytics':<AdminAnalytics {...shared} />, 'admin:workspaces':<AdminWorkspaces {...shared} />, 'admin:users':<AdminUsers {...shared} />, 'admin:finance':<AdminFinance {...shared} /> }[route] || <ComingSoon lang={lang} title={lang==='ar'?SCREEN_TITLE[route].ar:SCREEN_TITLE[route].en} icon={route.includes('announce')?'megaphone':'settings'} />;
    body = dashFor('admin', c);
  }
  else body = <Home {...shared} />;

  return (
    <>
      {body}
      <ToastHost toasts={toasts} />
      <ScreenLauncher route={route} go={go} lang={lang} />
      <TweaksPanel title={lang==='ar'?'تخصيص':'Tweaks'}>
        <TweakSection label={lang==='ar'?'اللغة والمظهر':'Language & theme'} />
        <TweakRadio label={lang==='ar'?'اللغة':'Language'} value={lang} options={[{value:'ar',label:'العربية'},{value:'en',label:'English'}]} onChange={setLang} />
        <TweakToggle label={lang==='ar'?'الوضع الداكن':'Dark mode'} value={theme==='dark'} onChange={v=>setTheme(v?'dark':'light')} />
        <TweakSection label={lang==='ar'?'العلامة':'Brand'} />
        <TweakColor label={lang==='ar'?'اللون الأساسي':'Primary'} value={tw.primary} options={[["#1F82C7","#176AA8","#EAF4FB"],["#135789","#0E3A5C","#E7EEF4"],["#2563EB","#1D4ED8","#EFF4FF"],["#0E7490","#155E75","#E6F2F4"]]} onChange={v=>setTweak('primary',v)} />
        <TweakColor label={lang==='ar'?'لون الطاقة':'Accent'} value={tw.accent} options={["#F6A91B","#FBC03A","#DB9009"]} onChange={v=>setTweak('accent',v)} />
        <TweakSection label={lang==='ar'?'الشكل':'Shape & type'} />
        <TweakRadio label={lang==='ar'?'الزوايا':'Corners'} value={tw.radius} options={[{value:'sharp',label:lang==='ar'?'حادة':'Sharp'},{value:'signature',label:lang==='ar'?'مميّزة':'Signature'},{value:'round',label:lang==='ar'?'دائرية':'Round'}]} onChange={v=>setTweak('radius',v)} />
        <TweakSlider label={lang==='ar'?'حجم النص':'Text size'} value={tw.textSize} min={14} max={18} step={1} unit="px" onChange={v=>setTweak('textSize',v)} />
      </TweaksPanel>
    </>
  );
}

function dashActions(role) {
  // returns null; actions are screen-specific and handled within screens. Kept for future.
  return null;
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
