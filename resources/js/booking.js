export function initializeReservation() {
    const form=document.getElementById('reservation-form');
    if(!form)return;
    const initial=JSON.parse(document.getElementById('reservation-data').textContent);
    const catalog=JSON.parse(document.getElementById('booking-translations').textContent);
    const t=key=>catalog[document.documentElement.lang][key]??key;
    const el=id=>document.getElementById(id);
    let tables=initial.tables, selected=new Map(), loading=false, errorKey=null, pending;
    for(const row of initial.selected ?? []){const id=Number(row.table_id);if(tables.some(table=>table.table_id===id))selected.set(id,String(row.pax??''));}
    const submit=el('reservation-submit'), noPayment=form.querySelector('[name=payment_method_id]').options.length<2;
    function hidden() {
        el('reservation-hidden').replaceChildren();let index=0;
        for(const [id,pax] of selected){for(const [field,value] of Object.entries({table_id:id,pax})){const input=document.createElement('input');input.type='hidden';input.name=`tables[${index}][${field}]`;input.value=String(value);el('reservation-hidden').append(input);}index++;}
        el('reservation-total').textContent=[...selected.values()].reduce((sum,value)=>sum+(/^\d+$/.test(value)?Number(value):0),0);
        el('reservation-empty').hidden=selected.size>0;
    }
    function showError(key){errorKey=key;el('reservation-error').hidden=!key;if(key)el('reservation-error').textContent=t(key);}
    function renderSelection(){
        el('reservation-selection').replaceChildren();
        for(const [id,pax] of selected){const table=tables.find(table=>table.table_id===id);const row=document.createElement('div');row.className='selected-row';const code=document.createElement('strong');code.textContent=table.table_code;const label=document.createElement('label');label.append(document.createTextNode(t('pax')));const input=document.createElement('input');input.type='number';input.min='1';input.max='999';input.step='1';input.required=true;input.value=pax;input.dataset.reservationPax=String(id);input.setAttribute('aria-label',`${t('pax')} ${table.table_code}`);label.append(input);row.append(code,label);el('reservation-selection').append(row);}
        hidden();
    }
    function renderTables(){
        el('reservation-tables').replaceChildren();
        const groups=new Map();for(const table of tables){const section=table.section_name||t('unnamed_section');if(!groups.has(section))groups.set(section,[]);groups.get(section).push(table);}
        if(!tables.length){const note=document.createElement('p');note.className='board-message muted';note.textContent=t('no_tables');el('reservation-tables').append(note);}
        for(const [section,items] of groups){const heading=document.createElement('h3');heading.className='board-section';heading.textContent=section;const grid=document.createElement('div');grid.className='table-grid';for(const table of items){const chosen=selected.has(table.table_id);const button=document.createElement('button');button.type='button';button.className=`dining-table${!table.available?' reserved':''}`;button.setAttribute('aria-pressed',String(chosen));button.dataset.reservationTable=String(table.table_id);button.disabled=!table.available&&!chosen;const shape=document.createElement('span');shape.className='table-shape';shape.setAttribute('aria-hidden','true');const code=document.createElement('strong');code.textContent=table.table_code;const status=document.createElement('small');status.textContent=t(chosen?'selected':table.available?'available':'busy');button.append(shape,code,status);if(table.historical){const hint=document.createElement('small');hint.textContent=t('historical');button.append(hint);}grid.append(button);}el('reservation-tables').append(heading,grid);}
    }
    el('reservation-tables').addEventListener('click',event=>{const button=event.target.closest('[data-reservation-table]');if(!button||loading)return;const id=Number(button.dataset.reservationTable);if(selected.has(id))selected.delete(id);else selected.set(id,'1');showError(null);renderTables();renderSelection();});
    el('reservation-selection').addEventListener('input',event=>{if(event.target.dataset.reservationPax){selected.set(Number(event.target.dataset.reservationPax),event.target.value);showError(null);hidden();}});
    el('reservation-clear').addEventListener('click',()=>{selected.clear();showError(null);renderTables();renderSelection();});
    el('reservation-date').addEventListener('change',async()=>{
        pending?.abort();pending=new AbortController();selected.clear();loading=true;submit.disabled=true;renderSelection();showError(null);el('availability-state').hidden=false;el('availability-state').textContent=t('loading');
        const url=new URL(form.dataset.availabilityUrl);url.searchParams.set('date',el('reservation-date').value);if(form.dataset.bookingId)url.searchParams.set('except',form.dataset.bookingId);
        try{const response=await fetch(url,{headers:{Accept:'application/json'},signal:pending.signal});if(!response.ok)throw new Error('availability');const data=await response.json();tables=data.tables;renderTables();el('availability-state').hidden=true;loading=false;submit.disabled=noPayment;}
        catch(error){if(error.name==='AbortError')return;tables=[];renderTables();el('availability-state').textContent=t('load_error');loading=true;}
    });
    form.addEventListener('submit',event=>{
        if(loading){event.preventDefault();return;}
        const valid=selected.size>0&&[...selected.entries()].every(([id,pax])=>/^\d+$/.test(pax)&&Number(pax)>=1&&Number(pax)<=999&&tables.find(table=>table.table_id===id)?.available);
        if(!valid){event.preventDefault();showError('no_selection');return;}
        hidden();submit.disabled=true;
    });
    window.addEventListener('pageshow',()=>{submit.disabled=loading||noPayment;});
    document.addEventListener('dineflow:locale',()=>{renderTables();renderSelection();if(errorKey)showError(errorKey);if(loading)el('availability-state').textContent=t('load_error');});
    renderTables();renderSelection();
}
