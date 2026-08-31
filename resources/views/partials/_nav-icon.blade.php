{{-- Shared inline line-icon set for the sidebar nav — kept as one small
     partial so every icon stays visually consistent (16x16, 1.6 stroke,
     currentColor) without adding an icon-font/CDN dependency. --}}
@php($paths = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'quotations' => '<path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
    'orders' => '<path d="M4 8h16l-1.5 11.2a2 2 0 0 1-2 1.8H7.5a2 2 0 0 1-2-1.8L4 8z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/>',
    'invoices' => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/><path d="M6 15h4"/>',
    'customers' => '<circle cx="9" cy="8" r="3.3"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16.5 5.3a3.3 3.3 0 0 1 0 6.4"/><path d="M17.5 14a6.5 6.5 0 0 1 4 6"/>',
    'deliveries' => '<rect x="1.5" y="7" width="12" height="10" rx="1.5"/><path d="M13.5 10.5H17l3.5 3.5V17h-7z"/><circle cx="6" cy="19" r="1.7"/><circle cx="17.5" cy="19" r="1.7"/>',
    'products' => '<path d="M12 2.5 21 7.5v9L12 21.5 3 16.5v-9z"/><path d="M3 7.5 12 12.5l9-5"/><path d="M12 12.5v9"/>',
    'inventory' => '<rect x="3" y="3" width="18" height="5" rx="1.5"/><rect x="3" y="10.5" width="18" height="10" rx="1.5"/><path d="M8 15h8"/>',
    'purchases' => '<circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M2 3h2.4l2.6 12.4a2 2 0 0 0 2 1.6h8.4a2 2 0 0 0 2-1.6L21 7.5H5.6"/>',
    'expenses' => '<rect x="2.5" y="6" width="19" height="13" rx="2.2"/><path d="M2.5 10.5h19"/><circle cx="17" cy="14.5" r="1.4"/>',
    'accounting' => '<rect x="4.5" y="2.5" width="15" height="19" rx="2"/><path d="M8 7h8M8 11h.01M12 11h.01M16 11h.01M8 14.5h.01M12 14.5h.01M16 14.5h.01M8 18h.01M12 18h.01M16 18h.01"/>',
    'reports' => '<path d="M4 20V10M11 20V4M18 20v-7"/><path d="M2.5 20h19"/>',
    'roles' => '<path d="M12 2.5 4.5 6v6c0 5 3.2 8.4 7.5 9.7 4.3-1.3 7.5-4.7 7.5-9.7V6z"/><path d="m9 12 2 2 4-4"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    'audit' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 3v2h6V3M8 11h8M8 15h5"/>',
    'system' => '<rect x="2.5" y="4" width="19" height="6" rx="1.5"/><rect x="2.5" y="14" width="19" height="6" rx="1.5"/><path d="M6 7h.01M6 17h.01"/>',
    'import' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M3.5 19h17"/>',
][$name] ?? '')
<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $paths !!}</svg>
