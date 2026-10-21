export function initializeWorkspace() {
    const catalog = JSON.parse(document.getElementById('admin-translations').textContent);
    let locale = document.documentElement.lang;
    const error = document.getElementById('language-error');
    const bookingCatalog = JSON.parse(document.getElementById('booking-translations')?.textContent || '{}');
    document.querySelectorAll('[data-locale]').forEach(button => button.addEventListener('click',async () => {
        const buttons=document.querySelectorAll('[data-locale]'); buttons.forEach(b=>b.disabled=true);
        try {
            const response=await fetch(document.body.dataset.localeUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({locale:button.dataset.locale})});
            if(!response.ok) throw new Error('Locale failed');
            const data=await response.json(); const oldLocale=locale; locale=data.locale;
            const replacements=[...Object.entries(catalog[oldLocale]).map(([key,text])=>[text,catalog[locale][key]]),...Object.entries(bookingCatalog[oldLocale]||{}).map(([key,text])=>[text,bookingCatalog[locale][key]])].sort((a,b)=>b[0].length-a[0].length);
            document.documentElement.lang=locale;
            document.title=`${catalog[locale][document.body.dataset.pageKey]} · DineFlow`;
            document.querySelectorAll('[data-admin]').forEach(el=>el.textContent=catalog[locale][el.dataset.admin] || el.dataset.admin);
            document.querySelectorAll('[data-locale]').forEach(el=>el.setAttribute('aria-pressed',String(el.dataset.locale===locale)));
            document.querySelectorAll('[role=alert] p,[role=alert] li,.success-message').forEach(el=>{for(const [before,after] of replacements){if(el.textContent.startsWith(before)){el.textContent=after+el.textContent.slice(before.length);break;}}});
            document.querySelectorAll('[data-booking]').forEach(el=>el.textContent=bookingCatalog[locale]?.[el.dataset.booking] || el.dataset.booking);
            document.dispatchEvent(new CustomEvent('dineflow:locale',{detail:{locale}}));
            error.hidden=true;
        } catch {error.hidden=false;error.textContent=locale==='ms'?'Bahasa belum dapat ditukar. Cuba lagi.':'Unable to change language. Try again.';}
        finally {buttons.forEach(b=>b.disabled=false);}
    }));
    const sidebar=document.getElementById('sidebar'), toggle=document.getElementById('menu-toggle');
    if(!sidebar || !toggle) return;
    const mobile=matchMedia('(max-width:700px)');
    function menu(open){sidebar.classList.toggle('is-open',open);sidebar.inert=mobile.matches&&!open;toggle.setAttribute('aria-expanded',String(open));}
    toggle.addEventListener('click',()=>menu(!sidebar.classList.contains('is-open')));
    mobile.addEventListener('change',()=>menu(false)); menu(false);
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&sidebar.classList.contains('is-open')){menu(false);toggle.focus();}});
    document.addEventListener('click',e=>{if(!sidebar.contains(e.target)&&!toggle.contains(e.target))menu(false);});
}
