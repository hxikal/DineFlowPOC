import { initializeReservation } from './booking';
initializeReservation();
import { initializeWorkspace } from './workspace';
if (!document.getElementById('translations')) {
    initializeWorkspace();
} else {const dictionaries = JSON.parse(document.getElementById('translations').textContent);
let locale = document.documentElement.lang;
const screen = document.body.dataset.screen;
const $ = (id) => document.getElementById(id);
const t = (key) => dictionaries[locale][key] ?? key;
const key = 'dineflow.p1.draft.v1';
let selected = {};
let errorKey = null;
let reviewOpen = false;
let detailTable = null;
let detailReserved = true;
const fields = ['guest-name', 'guest-contact', 'payment', 'remark'];
let draft = {};
try { draft = JSON.parse(sessionStorage.getItem(key) || '{}'); } catch { /* Storage can be disabled. */ }
if (draft.outlet && ['restaurant','terrace'].includes(draft.outlet)) $('outlet').value = draft.outlet;
if (/^\d{4}-\d{2}-\d{2}$/.test(draft.date || '')) $('dining-date').value = draft.date;
fields.forEach(id => { if ($(id) && typeof draft[id] === 'string') $(id).value = draft[id]; });
if (draft.selected && typeof draft.selected === 'object') {
    for (const [table, pax] of Object.entries(draft.selected)) {
        if (/^A(03|04|06|07|09|10|11|12)$/.test(table)) selected[table] = String(pax).slice(0, 4);
    }
}
function save() {
    draft.outlet = $('outlet').value;
    draft.date = $('dining-date').value;
    fields.forEach(id => { if ($(id)) draft[id] = $(id).value; });
    draft.selected = selected;
    try { sessionStorage.setItem(key, JSON.stringify(draft)); } catch { /* Language switches still preserve the DOM. */ }
}
function total() { return Object.values(selected).reduce((sum, value) => sum + (/^\d+$/.test(value) ? Number(value) : 0), 0); }
function translate() {
    document.documentElement.lang = locale;
    document.title = `${t(screen)} · DineFlow`;
    document.querySelectorAll('[data-i18n]').forEach(el => el.textContent = t(el.dataset.i18n));
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => el.placeholder = t(el.dataset.i18nPlaceholder));
    document.querySelectorAll('[data-i18n-aria]').forEach(el => el.setAttribute('aria-label', t(el.dataset.i18nAria)));
    document.querySelectorAll('[data-locale]').forEach(el => el.setAttribute('aria-pressed', String(el.dataset.locale === locale)));
    if ($('form-error') && errorKey) $('form-error').textContent = t(errorKey);
    renderSelection();
    renderReview();
    renderDetail();
}
function renderSelection() {
    document.querySelectorAll('[data-table]').forEach(button => {
        const isSelected = Object.hasOwn(selected, button.dataset.table);
        button.setAttribute('aria-pressed', String(isSelected));
        const status = button.querySelector('[data-table-status]');
        status.dataset.i18n = isSelected ? 'selected' : button.dataset.reserved === 'true' ? 'booked' : 'available';
        status.textContent = t(status.dataset.i18n);
    });
    if (!$('selected-tables')) return;
    const activeInput = document.activeElement?.dataset.pax;
    // Update labels only when a pax field is being edited; keep keyboard focus intact.
    if (!activeInput) {
        $('selected-tables').replaceChildren();
        Object.entries(selected).forEach(([table, pax]) => {
            const row = document.createElement('div'); row.className = 'selected-row';
            const name = document.createElement('strong'); name.textContent = table;
            const label = document.createElement('label'); label.append(document.createTextNode(t('pax')));
            const input = document.createElement('input');
            input.type = 'number'; input.min = '1'; input.max = '999'; input.step = '1'; input.required = true;
            input.value = pax; input.dataset.pax = table; input.setAttribute('aria-label', `${t('pax')} ${table}`);
            label.append(input); row.append(name, label); $('selected-tables').append(row);
        });
    }
    $('no-selection').hidden = Object.keys(selected).length > 0;
    $('total-pax').textContent = total();
}
function renderReview() {
    if (!$('review')) return;
    $('review').hidden = !reviewOpen;
    if (!reviewOpen) return;
    // User input is always rendered as text, never HTML.
    $('review-values').textContent = `${$('guest-name').value} · ${$('guest-contact').value} · ${$('outlet').selectedOptions[0].textContent} · ${$('dining-date').value} · ${Object.entries(selected).map(([table,pax]) => `${table}: ${pax}`).join(', ')} · ${total()} ${t('pax')} · ${t($('payment').value)} · ${$('remark').value}`;
}
function renderDetail() {
    if (!$('table-detail') || !detailTable) return;
    $('table-detail').hidden = false;
    $('table-detail').textContent = `${t('table_detail')}: ${detailTable} · ${t(detailReserved ? 'booked' : 'available')} · ${t('demo')}`;
}
function invalidate() { reviewOpen = false; errorKey = null; if ($('form-error')) $('form-error').hidden = true; renderReview(); }
for (const button of document.querySelectorAll('[data-locale]')) {
    button.addEventListener('click', async () => {
        save();
        const buttons = document.querySelectorAll('[data-locale]'); buttons.forEach(el => el.disabled = true);
        try {
            const response = await fetch(document.body.dataset.localeUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({locale: button.dataset.locale}),
            });
            if (!response.ok) throw new Error('Locale failed');
            const data = await response.json(); locale = data.locale;
            $('language-error').hidden = true; translate();
        } catch { $('language-error').textContent = t('network_error'); $('language-error').hidden = false; }
        finally { buttons.forEach(el => el.disabled = false); }
    });
}
document.querySelectorAll('[data-table]').forEach(button => button.addEventListener('click', () => {
    if (button.dataset.reserved === 'true') { detailTable = button.dataset.table; detailReserved = true; renderDetail(); return; }
    if (screen === 'tables') { detailTable = button.dataset.table; detailReserved = false; renderDetail(); return; }
    if (Object.hasOwn(selected, button.dataset.table)) delete selected[button.dataset.table];
    else selected[button.dataset.table] = '4';
    invalidate(); renderSelection(); save();
}));
document.addEventListener('input', event => {
    if (event.target.dataset.pax) { selected[event.target.dataset.pax] = event.target.value; invalidate(); renderSelection(); save(); }
    else if (fields.includes(event.target.id)) { invalidate(); save(); }
});
['outlet','dining-date'].forEach(id => $(id).addEventListener('change', () => {
    selected = {}; detailTable = null; $('table-detail')?.setAttribute('hidden', ''); invalidate(); renderSelection(); save();
}));
$('clear-tables')?.addEventListener('click', () => { selected = {}; invalidate(); renderSelection(); save(); });
$('booking-form')?.addEventListener('submit', event => {
    event.preventDefault();
    const validPax = Object.values(selected).every(value => /^\d+$/.test(value) && Number(value) >= 1 && Number(value) <= 999);
    errorKey = $('dining-date').value < document.body.dataset.today ? 'past_date'
        : !$('guest-name').value.trim() || !$('guest-contact').value.trim() || !$('payment').value || !Object.keys(selected).length || !validPax || !$('dining-date').value ? 'invalid' : null;
    $('form-error').hidden = !errorKey;
    if (errorKey) { $('form-error').textContent = t(errorKey); reviewOpen = false; }
    else { reviewOpen = true; }
    renderReview(); if (reviewOpen) $('review').focus();
});
const mobile = matchMedia('(max-width:700px)');
function setMenu(open) {
    $('sidebar').classList.toggle('is-open', open);
    $('menu-toggle').setAttribute('aria-expanded', String(open));
    $('sidebar').inert = mobile.matches && !open;
}
$('menu-toggle').addEventListener('click', () => setMenu(!$('sidebar').classList.contains('is-open')));
mobile.addEventListener('change', () => setMenu(false));
setMenu(false);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && $('sidebar').classList.contains('is-open')) {
        setMenu(false); $('menu-toggle').focus();
    }
});
document.addEventListener('click', event => {
    if (!$('sidebar').contains(event.target) && !$('menu-toggle').contains(event.target)) setMenu(false);
});
translate();


}
