@props(['name' => 'grid'])
<svg {{ $attributes }} width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('users')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18m-12 5 2 2 4-4"/>@break
@case('arrow')<path d="M5 12h14m-6-6 6 6-6 6"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('table')<rect x="5" y="5" width="14" height="14" rx="4"/><path d="M9 2v3m6-3v3M9 19v3m6-3v3M2 9h3m-3 6h3m14-6h3m-3 6h3"/>@break
@default<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>
@endswitch
</svg>
