/* TAQAT — datatable helpers: sortable headers, checkbox, pagination, filter chips */
const { useState:useStateDT, useRef:useRefDT, useEffect:useEffectDT, useMemo:useMemoDT } = React;

/* indeterminate-capable checkbox */
function Cbx({ checked, indeterminate, onChange, ...rest }) {
  const ref = useRefDT(null);
  useEffectDT(()=>{ if (ref.current) ref.current.indeterminate = !!indeterminate && !checked; }, [indeterminate, checked]);
  return <input ref={ref} type="checkbox" className="cbx" checked={!!checked} onChange={onChange} onClick={e=>e.stopPropagation()} {...rest} />;
}

/* sortable header cell */
function Th({ id, sort, setSort, children, num, plain }) {
  if (plain) return <th className={num?'num':''}>{children}</th>;
  const active = sort && sort.key===id;
  const cls = `sortable ${active?'active':''} ${active&&sort.dir==='desc'?'desc':''} ${num?'num':''}`;
  const toggle = ()=> setSort(active ? { key:id, dir: sort.dir==='asc'?'desc':'asc' } : { key:id, dir:'asc' });
  return (
    <th className={cls} onClick={toggle}>
      {children}<span className="sort-i"><Icon name="arrowUp" size={13} /></span>
    </th>
  );
}

/* generic sorter */
function sortRows(rows, sort, accessors={}) {
  if (!sort) return rows;
  const get = accessors[sort.key] || (r=>r[sort.key]);
  const out = [...rows].sort((a,b)=>{
    const va=get(a), vb=get(b);
    if (typeof va==='number' && typeof vb==='number') return va-vb;
    return String(va).localeCompare(String(vb), 'ar');
  });
  return sort.dir==='desc' ? out.reverse() : out;
}

/* pagination control */
function Pager({ page, pages, setPage, lang }) {
  if (pages<=1) return <div className="pager"><button className="pg-num" aria-current="true">1</button></div>;
  const nums = []; for (let i=1;i<=pages;i++) nums.push(i);
  return (
    <div className="pager">
      <button className="pg-edge" disabled={page===1} onClick={()=>setPage(page-1)} aria-label="prev"><Icon name="chevL" size={16} /></button>
      {nums.map(n=> <button key={n} className="pg-num" aria-current={page===n} onClick={()=>setPage(n)}>{n}</button>)}
      <button className="pg-edge" disabled={page===pages} onClick={()=>setPage(page+1)} aria-label="next"><Icon name="chevR" size={16} /></button>
    </div>
  );
}

/* removable filter chips */
function FilterChips({ chips, onClear, lang }) {
  if (!chips.length) return null;
  const ar = lang==='ar';
  return (
    <div className="filter-chips">
      <span className="fc-label">{ar?'عوامل التصفية:':'Filters:'}</span>
      {chips.map(c=> (
        <span key={c.key} className="chip">{c.label}<button onClick={()=>c.onRemove()} aria-label="remove"><Icon name="x" size={13} /></button></span>
      ))}
      <button className="chip-clear" onClick={onClear}>{ar?'مسح الكل':'Clear all'}</button>
    </div>
  );
}

Object.assign(window, { Cbx, Th, sortRows, Pager, FilterChips });
