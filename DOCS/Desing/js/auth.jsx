/* TAQAT — auth: Login, Register (freelancer + workspace multi-step) */
const { useState:useStateA } = React;

function Login({ t, lang, go, toast }) {
  return (
    <div className="auth-wrap">
      <div className="auth-art">
        <a className="logo" onClick={()=>go('home')} style={{cursor:'pointer'}}>
          <span className="logo-word" style={{color:'#fff'}}>TAQAT</span>
        </a>
        <div>
          <div style={{marginBottom:24}}><TileLogo size={46} /></div>
          <p className="auth-quote">{lang==='ar'
            ? '«من بيتي إلى مكتب يليق بطموحي خلال دقائق. طاقت غيّرت طريقة عملي.»'
            : '“From home to an office worthy of my ambition in minutes. TAQAT changed how I work.”'}</p>
          <div className="row" style={{gap:10, marginTop:20}}>
            <Avatar initial="ل" round /><div><div style={{fontWeight:600}}>{lang==='ar'?'ليان أبو شاويش':'Lian Abu Shawish'}</div><div style={{opacity:.7, fontSize:'var(--fs-sm)'}}>{lang==='ar'?'مصممة UI/UX · غزة':'UI/UX Designer · Gaza'}</div></div>
          </div>
        </div>
        <div style={{opacity:.6, fontSize:'var(--fs-sm)'}} className="ltr">taqat.space</div>
      </div>

      <div className="auth-form-side">
        <div className="auth-form">
          <h1 className="h1">{t.login_t}</h1>
          <p className="muted" style={{marginTop:6, marginBottom:28}}>{t.login_sub}</p>
          <div className="stack" style={{gap:16}}>
            <Field label={t.email}><Input icon="mail" type="email" placeholder={lang==='ar'?'name@mail.ps':'name@mail.ps'} defaultValue="owner@manara.ps" /></Field>
            <Field label={t.password}><Input icon="lock" type="password" defaultValue="••••••••" /></Field>
            <div className="between">
              <label className="check"><input type="checkbox" defaultChecked />{t.remember}</label>
              <a className="link">{t.forgot}</a>
            </div>
            <Button variant="primary" size="lg" block onClick={()=>{ toast({tone:'ok',title:lang==='ar'?'تم تسجيل الدخول':'Signed in'}); go('owner:dashboard'); }}>{t.login}</Button>
          </div>
          <div className="auth-divider">{t.or}</div>
          <Button variant="secondary" size="lg" block icon="globe">{t.continueGoogle}</Button>
          <p className="muted" style={{textAlign:'center', marginTop:24, fontSize:'var(--fs-sm)'}}>
            {t.noAccount} <a className="link" onClick={()=>go('reg-free')}>{t.signup}</a>
          </p>
          <div className="card" style={{marginTop:24, padding:'12px 14px', background:'var(--surface-2)'}}>
            <div className="muted-3" style={{fontSize:'var(--fs-xs)', marginBottom:8}}>{lang==='ar'?'دخول سريع للعرض:':'Quick demo access:'}</div>
            <div className="row wrap" style={{gap:8}}>
              <button className="btn btn-secondary btn-sm" onClick={()=>go('owner:dashboard')}>{lang==='ar'?'مالك مساحة':'Owner'}</button>
              <button className="btn btn-secondary btn-sm" onClick={()=>go('member:home')}>{lang==='ar'?'عامل مستقل':'Freelancer'}</button>
              <button className="btn btn-secondary btn-sm" onClick={()=>go('admin:analytics')}>{lang==='ar'?'أدمن':'Admin'}</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function Stepper({ steps, current }) {
  return (
    <div className="stepper">
      {steps.map((s,i)=> (
        <React.Fragment key={i}>
          {i>0 && <div className={`step-line ${i<=current?'done':''}`}></div>}
          <div className={`step ${i<current?'done':i===current?'current':''}`}>
            <span className="step-dot">{i<current ? <Icon name="check" size={16} /> : i+1}</span>
            <span className="step-label">{s}</span>
          </div>
        </React.Fragment>
      ))}
    </div>
  );
}

function RegisterFreelancer({ t, lang, go, toast }) {
  const [step, setStep] = useStateA(0);
  const steps = lang==='ar' ? ['المعلومات الشخصية','التخصص','إثبات الهوية'] : ['Personal info','Specialty','ID verification'];
  const next = ()=> step<2 ? setStep(step+1) : (toast({tone:'ok',title:lang==='ar'?'تم إنشاء حسابك':'Account created'}), go('member:home'));
  return (
    <div className="pub">
      <div className="reg-wrap">
        <div style={{textAlign:'center', marginBottom:28}}>
          <span className="eyebrow">{lang==='ar'?'إنشاء حساب':'Sign up'}</span>
          <h1 className="h1" style={{marginTop:8}}>{lang==='ar'?'التسجيل كعامل مستقل':'Register as a freelancer'}</h1>
        </div>
        <Stepper steps={steps} current={step} />
        <div className="card reg-card">
          {step===0 && <div className="stack" style={{gap:16}}>
            <div className="grid2">
              <Field label={lang==='ar'?'الاسم الأول':'First name'}><Input placeholder={lang==='ar'?'أحمد':'Ahmad'} /></Field>
              <Field label={lang==='ar'?'اسم العائلة':'Last name'}><Input placeholder={lang==='ar'?'الفرا':'Al-Farra'} /></Field>
            </div>
            <Field label={t.email}><Input icon="mail" type="email" placeholder="name@mail.ps" /></Field>
            <Field label={lang==='ar'?'رقم الجوال':'Phone'} hint={lang==='ar'?'يُستخدم لتأكيد الحجوزات':'Used to confirm bookings'}><Input icon="phone" className="ltr" placeholder="+970 5xx xxx xxx" /></Field>
            <Field label={t.password}><Input icon="lock" type="password" /></Field>
          </div>}
          {step===1 && <div className="stack" style={{gap:16}}>
            <Field label={lang==='ar'?'مجال العمل':'Field of work'}>
              <Select><option>{lang==='ar'?'تطوير وبرمجة':'Software & development'}</option><option>{lang==='ar'?'تصميم':'Design'}</option><option>{lang==='ar'?'كتابة ومحتوى':'Writing & content'}</option><option>{lang==='ar'?'تسويق':'Marketing'}</option><option>{lang==='ar'?'أخرى':'Other'}</option></Select>
            </Field>
            <Field label={lang==='ar'?'المسمّى الوظيفي':'Job title'}><Input placeholder={lang==='ar'?'مطوّر واجهات':'Frontend developer'} /></Field>
            <Field label={lang==='ar'?'نبذة قصيرة':'Short bio'} hint={lang==='ar'?'تظهر لأصحاب المساحات':'Visible to space owners'}><textarea className="textarea" placeholder={lang==='ar'?'اكتب نبذة عن خبرتك…':'Tell us about your experience…'}></textarea></Field>
            <Field label={lang==='ar'?'المدينة المفضّلة':'Preferred city'}><Select><option>غزة</option><option>خان يونس</option><option>رفح</option><option>جباليا</option></Select></Field>
          </div>}
          {step===2 && <div className="stack" style={{gap:16}}>
            <div className="card" style={{padding:'14px 16px', background:'var(--info-bg)', borderColor:'transparent'}}>
              <div className="row" style={{gap:10, color:'var(--blue-700)'}}><Icon name="shield" size={18} /><span style={{fontSize:'var(--fs-sm)', fontWeight:500}}>{lang==='ar'?'بياناتك محمية وتُستخدم للتحقق فقط.':'Your data is protected and used for verification only.'}</span></div>
            </div>
            <Field label={lang==='ar'?'صورة الهوية (الوجه الأمامي)':'ID card (front)'}>
              <label className="upload"><Icon name="upload" /><span style={{fontWeight:600, color:'var(--text-2)'}}>{lang==='ar'?'اسحب الصورة أو اضغط للرفع':'Drag image or click to upload'}</span><span style={{fontSize:'var(--fs-xs)'}}>PNG · JPG · {lang==='ar'?'حتى ٥ ميجا':'up to 5MB'}</span></label>
            </Field>
            <Field label={lang==='ar'?'صورة شخصية':'Profile photo'}>
              <label className="upload"><Icon name="user" /><span style={{fontWeight:600, color:'var(--text-2)'}}>{lang==='ar'?'ارفع صورتك':'Upload your photo'}</span></label>
            </Field>
            <label className="check"><input type="checkbox" />{lang==='ar'?'أوافق على ':'I agree to the '}<a className="link">{lang==='ar'?'الشروط والأحكام':'terms & conditions'}</a></label>
          </div>}
        </div>
        <div className="between" style={{marginTop:24}}>
          <Button variant="ghost" icon="arrowR" onClick={()=> step>0?setStep(step-1):go('home')}>{lang==='ar'?'رجوع':'Back'}</Button>
          <Button variant="primary" iconEnd={step<2?'arrowL':'check'} onClick={next}>{step<2?(lang==='ar'?'التالي':'Next'):(lang==='ar'?'إنشاء الحساب':'Create account')}</Button>
        </div>
      </div>
    </div>
  );
}

function RegisterWorkspace({ t, lang, go, toast }) {
  const [step, setStep] = useStateA(0);
  const steps = lang==='ar' ? ['معلومات المساحة','الموقع','المقاعد والأسعار','المستندات'] : ['Space info','Location','Seats & pricing','Documents'];
  const done = step>=4;
  const next = ()=> setStep(step+1);
  if (done) return (
    <div className="pub"><div className="reg-wrap" style={{textAlign:'center', paddingTop:64}}>
      <div className="confirm-art"><Icon name="clock" size={34} /></div>
      <h1 className="h1" style={{marginTop:24}}>{lang==='ar'?'طلبك قيد المراجعة':'Your application is under review'}</h1>
      <p className="muted" style={{maxWidth:440, margin:'12px auto 0', lineHeight:1.7}}>{lang==='ar'
        ? 'شكراً لتسجيل مساحتك! سيقوم فريق طاقت بمراجعة بياناتك ومستنداتك خلال ٢٤–٤٨ ساعة، وستصلك رسالة عند الموافقة.'
        : 'Thanks for listing your space! The TAQAT team will review your details and documents within 24–48 hours, and you’ll get a message once approved.'}</p>
      <div className="card" style={{maxWidth:380, margin:'28px auto 0', padding:18, textAlign:'start'}}>
        <div className="between"><span className="muted">{lang==='ar'?'رقم الطلب':'Application ID'}</span><span className="tnum" style={{fontWeight:600}}>WS-2026-0418</span></div>
        <div className="divider" style={{margin:'12px 0'}}></div>
        <div className="between"><span className="muted">{lang==='ar'?'الحالة':'Status'}</span><StatusBadge status="pending" lang={lang} /></div>
      </div>
      <div className="row" style={{gap:12, justifyContent:'center', marginTop:28}}>
        <Button variant="secondary" onClick={()=>go('home')}>{lang==='ar'?'العودة للرئيسية':'Back home'}</Button>
        <Button variant="primary" onClick={()=>go('owner:dashboard')}>{lang==='ar'?'معاينة لوحة التحكم':'Preview dashboard'}</Button>
      </div>
    </div></div>
  );
  return (
    <div className="pub">
      <div className="reg-wrap">
        <div style={{textAlign:'center', marginBottom:28}}>
          <span className="eyebrow">{lang==='ar'?'انضم كشريك':'Become a partner'}</span>
          <h1 className="h1" style={{marginTop:8}}>{lang==='ar'?'سجّل مساحة العمل':'List your workspace'}</h1>
        </div>
        <Stepper steps={steps} current={step} />
        <div className="card reg-card">
          {step===0 && <div className="stack" style={{gap:16}}>
            <Field label={lang==='ar'?'اسم المساحة':'Space name'}><Input placeholder={lang==='ar'?'مساحة المنارة':'Al-Manara Space'} /></Field>
            <Field label={lang==='ar'?'وصف مختصر':'Short description'}><textarea className="textarea" placeholder={lang==='ar'?'مساحة عمل حديثة في قلب المدينة…':'A modern coworking space in the city center…'}></textarea></Field>
            <div className="grid2">
              <Field label={lang==='ar'?'سعة المقاعد':'Seat capacity'}><Input className="ltr" type="number" placeholder="48" /></Field>
              <Field label={lang==='ar'?'ساعات العمل':'Working hours'}><Input placeholder={lang==='ar'?'٨ ص – ١٠ م':'8am – 10pm'} /></Field>
            </div>
          </div>}
          {step===1 && <div className="stack" style={{gap:16}}>
            <div className="grid2">
              <Field label={lang==='ar'?'المدينة':'City'}><Select><option>غزة</option><option>خان يونس</option><option>رفح</option><option>جباليا</option><option>دير البلح</option></Select></Field>
              <Field label={lang==='ar'?'الحي / المنطقة':'Area / district'}><Input placeholder={lang==='ar'?'الرمال':'Al-Rimal'} /></Field>
            </div>
            <Field label={lang==='ar'?'العنوان التفصيلي':'Street address'}><Input icon="pin" placeholder={lang==='ar'?'شارع عمر المختار، عمارة…':'Omar Al-Mukhtar St, building…'} /></Field>
            <ImgPlaceholder label={lang==='ar'?'حدّد الموقع على الخريطة':'pin location on map'} color="#dde6ec" h={200} />
          </div>}
          {step===2 && <div className="stack" style={{gap:16}}>
            <p className="muted" style={{fontSize:'var(--fs-sm)'}}>{lang==='ar'?'أضف أنواع المقاعد والأسعار التي تقدّمها.':'Add the seat types and prices you offer.'}</p>
            {[['مقعد مفتوح','Open seat',8,'يومي'],['مكتب ثابت','Fixed desk',35,'شهري'],['مكتب خاص','Private office',95,'شهري']].map((r,i)=> (
              <div key={i} className="card" style={{padding:14, display:'grid', gridTemplateColumns:'1fr 110px 120px 40px', gap:10, alignItems:'center'}}>
                <Input defaultValue={lang==='ar'?r[0]:r[1]} />
                <Input className="ltr tnum" defaultValue={r[2]} />
                <Select defaultValue={r[3]}><option>يومي</option><option>شهري</option></Select>
                <button className="icon-btn"><Icon name="trash" size={18} /></button>
              </div>
            ))}
            <Button variant="ghost" icon="plus">{lang==='ar'?'إضافة نوع مقعد':'Add seat type'}</Button>
          </div>}
          {step===3 && <div className="stack" style={{gap:16}}>
            <div className="card" style={{padding:'14px 16px', background:'var(--info-bg)', borderColor:'transparent'}}>
              <div className="row" style={{gap:10, color:'var(--blue-700)'}}><Icon name="shield" size={18} /><span style={{fontSize:'var(--fs-sm)', fontWeight:500}}>{lang==='ar'?'المستندات مطلوبة للتحقق من ملكية المساحة.':'Documents are required to verify ownership.'}</span></div>
            </div>
            <Field label={lang==='ar'?'رخصة المساحة / السجل التجاري':'Business license / registration'}>
              <label className="upload"><Icon name="doc" /><span style={{fontWeight:600, color:'var(--text-2)'}}>{lang==='ar'?'ارفع الرخصة':'Upload license'}</span><span style={{fontSize:'var(--fs-xs)'}}>PDF · {lang==='ar'?'حتى ١٠ ميجا':'up to 10MB'}</span></label>
            </Field>
            <Field label={lang==='ar'?'هوية المالك':'Owner ID'}>
              <label className="upload"><Icon name="user" /><span style={{fontWeight:600, color:'var(--text-2)'}}>{lang==='ar'?'ارفع صورة الهوية':'Upload ID'}</span></label>
            </Field>
          </div>}
        </div>
        <div className="between" style={{marginTop:24}}>
          <Button variant="ghost" icon="arrowR" onClick={()=> step>0?setStep(step-1):go('home')}>{lang==='ar'?'رجوع':'Back'}</Button>
          <Button variant="primary" iconEnd={step<3?'arrowL':'check'} onClick={next}>{step<3?(lang==='ar'?'التالي':'Next'):(lang==='ar'?'إرسال للمراجعة':'Submit for review')}</Button>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { Login, Stepper, RegisterFreelancer, RegisterWorkspace });
