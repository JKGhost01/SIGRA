<?php
$sigraConfig = config(\Config\Sigra::class);
$locale = service('request')->getLocale();
$locale = in_array($locale, ['es', 'en'], true) ? $locale : 'es';
$langQuery = service('request')->getUri()->getQuery();
$langTo = uri_string() . ($langQuery !== '' ? '?' . $langQuery : '');
$langUrl = static fn (string $code): string => site_url('language/' . $code) . '?to=' . rawurlencode($langTo);
?>
<!DOCTYPE html>
<html lang="<?= esc($locale) ?>" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="generator" content="SIGRA <?= esc($sigraConfig->version) ?>">
<meta name="application-version" content="<?= esc($sigraConfig->version) ?>">
<meta name="theme-color" content="#087F78">
<title><?= esc($titulo ?? 'SIGRA') ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="manifest" href="<?= sigra_public_url('manifest.webmanifest') ?>">
<link rel="apple-touch-icon" href="<?= sigra_public_url('assets/images/apple-touch-icon.png') ?>">

<script>
window.SIGRA_ICONS = <?= json_encode(array_combine(['pin', 'home', 'sun', 'moon'], array_map('sigra_icon', ['pin', 'home', 'sun', 'moon'])), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<style>
    :root { color-scheme: light; --ink: #172329; --muted: #6d7b80; --line: #dfe7e5; --paper: #f5f8f7; --surface: #fff; --teal: #087f78; --coral: #e55c4d; --shadow: 0 18px 50px rgba(23, 35, 41, .08); }
    * { box-sizing: border-box; }
    body { min-width: 320px; background: var(--paper); color: var(--ink); font-family: 'DM Sans', sans-serif; }
    h1, h2, h3, h4, h5, .navbar-brand { font-family: 'Space Grotesk', sans-serif; }
    .navbar { position: relative; z-index: 1200; height: 82px; min-height: 82px; max-height: 82px; overflow: visible; background: rgba(255, 255, 255, .94); border-bottom: 1px solid var(--line); }
    .navbar .container-fluid { gap: .9rem; height: 82px; min-height: 82px; max-height: 82px; padding-top: 0; padding-bottom: 0; align-items: center; }
    .navbar-brand { min-width: 0; height: 100%; color: var(--ink); font-size: 1.15rem; display: inline-flex; align-items: center; justify-content: center; gap: .55rem; padding: 0; margin: 0 .2rem 0 .15rem; line-height: 1; overflow: visible; }
    .sigra-logo { display: block; width: auto; max-width: 240px; min-width: 0; height: 44px; max-height: 44px; object-fit: contain; filter: drop-shadow(0 8px 18px rgba(11, 56, 104, .08)); margin: 0; }
    .sigra-version { display: inline-flex; align-items: center; padding: .18rem .42rem; border: 1px solid rgba(8, 127, 120, .28); border-radius: 999px; background: rgba(8, 127, 120, .09); color: var(--teal); font-family: 'DM Sans', sans-serif; font-size: .64rem; font-weight: 800; letter-spacing: .02em; line-height: 1; white-space: nowrap; }
    .sigra-version:hover { background: rgba(8, 127, 120, .15); }
    .navbar .nav-link { color: var(--muted); font-weight: 600; font-size: .9rem; line-height: 1.2; padding-top: .2rem; padding-bottom: .2rem; }
    .navbar .nav-link:hover, .navbar .nav-link.active { color: var(--teal); }
    .navbar-toggler { border: 1px solid var(--line); border-radius: 10px; padding: .4rem .5rem; color: var(--ink); }
    .navbar-toggler:focus { box-shadow: 0 0 0 .2rem rgba(8, 127, 120, .12); }
    .navbar-toggler-icon { width: 1.2rem; height: 1.2rem; }
    .btn-report { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border: 0; border-radius: 11px; background: linear-gradient(135deg, #e55c4d, #c93a2c); color: #fff; font-weight: 800; padding: .65rem 1rem; box-shadow: 0 8px 18px rgba(201, 58, 44, .18); }
    .btn-report:hover { background: linear-gradient(135deg, #f06a5a, #d24535); color: #fff; box-shadow: 0 10px 22px rgba(201, 58, 44, .26); }
    .btn-report .btn-icon { width: 1.05rem; height: 1.05rem; flex: 0 0 auto; }
    .page-wrap { width: min(1500px, 100%); margin: 0 auto; padding: 1.5rem; }
    .dashboard-shell { display: grid; grid-template-columns: minmax(290px, 360px) minmax(0, 1fr); min-height: calc(100vh - 78px); overflow: hidden; border: 1px solid var(--line); border-radius: 18px; background: var(--surface); box-shadow: var(--shadow); }
    .event-sidebar { min-width: 0; border-right: 1px solid var(--line); background: #fbfcfc; }
    .event-sidebar > div:first-child { background: linear-gradient(135deg, rgba(8, 127, 120, .08), transparent 70%); }
    .event-sidebar > div:first-child h1 { letter-spacing: -.02em; }
    .event-sidebar > div:first-child .bg-success { width: .7rem; height: .7rem; padding: 0 !important; box-shadow: 0 0 0 .3rem rgba(25, 135, 84, .12); }
    .event-sidebar > div:nth-child(2) { background: rgba(245, 248, 247, .7); }
    .event-sidebar .form-label { color: var(--ink); letter-spacing: .02em; }
    .event-sidebar .form-select, .event-sidebar .form-control { min-height: 2.45rem; box-shadow: 0 2px 8px rgba(23, 35, 41, .04); }
    .event-filters { background: rgba(245, 248, 247, .55); }
    .event-filters-summary { padding: .65rem .9rem; color: var(--teal); font-size: .69rem; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; cursor: pointer; list-style: none; }
    .event-filters-summary::-webkit-details-marker { display: none; }
    .event-filters-summary::after { content: '⌃'; float: right; font-size: .9rem; line-height: 1; }
    .event-filters:not([open]) .event-filters-summary::after { content: '⌄'; }
    .event-filters[open] .event-filters-summary { border-bottom: 1px solid var(--line); }
    .event-filters .form-label { margin-bottom: .15rem; color: var(--muted); font-size: .67rem; letter-spacing: .045em; text-transform: uppercase; }
    .event-filters .form-select, .event-filters .form-control, .event-filters .input-group-text { min-height: 2.1rem; font-size: .82rem; }
    .event-filters .input-group-text { background: #fff; color: var(--muted); }
    .event-filters #estado-ubicacion { line-height: 1.35; }
    html[data-bs-theme="dark"] .event-filters { background: rgba(25, 35, 41, .42); }
    html[data-bs-theme="dark"] .event-filters .input-group-text { background: #202d33; }
    .mobile-events-toggle { display: none; }
    .event-details-header { display: none; }
    .event-sidebar, #eventos-lista { scrollbar-width: auto; scrollbar-color: #7fa9a3 transparent; }
    .event-sidebar::-webkit-scrollbar, #eventos-lista::-webkit-scrollbar { width: 12px; }
    .event-sidebar::-webkit-scrollbar-track, #eventos-lista::-webkit-scrollbar-track { margin: .35rem 0; border-radius: 999px; background: rgba(8, 127, 120, .05); }
    .event-sidebar::-webkit-scrollbar-thumb, #eventos-lista::-webkit-scrollbar-thumb { min-height: 4rem; border: 3px solid transparent; border-radius: 999px; background: #7fa9a3; background-clip: padding-box; }
    .event-sidebar::-webkit-scrollbar-thumb:hover, #eventos-lista::-webkit-scrollbar-thumb:hover { background: #087f78; background-clip: padding-box; }
    .map-stage { position: relative; min-height: 620px; background: #dbe8e4; }
    .leaflet-container { background: #aad3df; }
    html[data-bs-theme="dark"] .leaflet-container { background: #0b1a24; }
    #mapa { width: 100%; height: 100%; min-height: 620px; border-radius: 0; }
    #mapa-elegir { width: 100%; height: 460px; border-radius: 12px; }
    .map-info { position: absolute; z-index: 700; top: 50%; left: 0; width: 2.8rem; max-width: min(290px, calc(100% - 24px)); padding: 0; border: 1px solid rgba(255, 255, 255, .8); border-left: 0; border-radius: 0 12px 12px 0; background: rgba(255, 255, 255, .94); box-shadow: 0 8px 24px rgba(23, 35, 41, .12); transform: translateY(-50%); transition: width .18s ease; overflow: hidden; }
    .map-info.is-open { width: min(290px, calc(100% - 24px)); }
    .map-info-toggle { display: flex; width: 2.8rem; min-height: 3.2rem; flex: 0 0 2.8rem; align-items: center; justify-content: center; padding: .6rem .35rem; border: 0; background: transparent; color: var(--ink); font-family: 'Space Grotesk', sans-serif; font-size: 0; font-weight: 700; line-height: 1.1; }
    .map-info-toggle::after { content: '›'; color: var(--teal); font-size: 1.35rem; line-height: 1; }
    .map-info.is-open .map-info-toggle::after { content: '‹'; }
    .map-info-content { min-width: 0; padding: .7rem .8rem; }
    .map-info strong { display: block; font-family: 'Space Grotesk', sans-serif; }
    .map-layer-toggle { flex: 0 0 auto; width: 2rem; height: 2rem; border: 0; border-radius: 999px; background: rgba(255, 255, 255, .16); color: #f8fafc; font-size: 1.1rem; line-height: 1; cursor: pointer; }
    .map-layer-switch.is-collapsed > button:not(.map-layer-toggle) { display: none; }
    .map-layer-switch.is-collapsed { width: 2.75rem !important; }
    .map-info > span { color: var(--muted); font-size: .82rem; }
    .map-legend { display: flex; flex-wrap: wrap; gap: .5rem .65rem; margin-top: .55rem; color: var(--muted); font-size: .72rem; }
    .map-legend-item { display: inline-flex; align-items: center; gap: .25rem; }
    .map-legend-dot { display: inline-block; width: .65rem; height: .65rem; border: 1px solid rgba(23, 35, 41, .35); border-radius: 50%; }
    .map-legend-dot.small { background: #00d639; }
    .map-legend-dot.medium { width: .8rem; height: .8rem; background: #ffe500; }
    .map-legend-dot.high { width: .95rem; height: .95rem; background: #ffb800; }
    .map-legend-dot.strong { width: 1.1rem; height: 1.1rem; background: #f20d19; }
    .map-legend-dot.deep { background: #1019d6; }
    @media (max-width: 575px) { .map-legend { gap: .35rem; font-size: .68rem; } }
    .report-shell { display: grid; grid-template-columns: minmax(300px, .8fr) minmax(0, 1.2fr); gap: 1rem; }
    .report-form-panel, .report-map-panel { border: 1px solid var(--line); border-radius: 16px; background: var(--surface); box-shadow: var(--shadow); }
    .report-map-panel { min-height: 620px; overflow: hidden; }
    .report-map-panel #mapa-elegir { height: 520px; border-radius: 0; }
    .location-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
    .location-actions .btn { min-height: 46px; border-radius: 9px; font-weight: 600; }
    .location-coordinates { background: #f5f8f7; border: 1px solid var(--line); border-radius: 10px; }
    .location-coordinates .form-control { background: transparent; border: 0; }
    @media (max-width: 850px) { .report-shell { grid-template-columns: 1fr; } .report-map-panel { min-height: auto; } .report-map-panel #mapa-elegir { height: 440px; } }
    .card { border: 1px solid var(--line); border-radius: 14px; box-shadow: none; }
    .form-control, .form-select { color: var(--ink); background-color: #fff; border-color: var(--line); border-radius: 9px; }
    .form-control::placeholder { color: var(--muted); }
    .form-control:focus, .form-select:focus { color: var(--ink); background-color: #fff; border-color: var(--teal); box-shadow: 0 0 0 .2rem rgba(8, 127, 120, .12); }
    .badge-estado-activo { background-color: #fbe1dd; color: #9d3025; }
    .badge-estado-revisado { background-color: #d9f0e4; color: #17633c; }
    .badge-estado-pendiente { background-color: #fff0c7; color: #795b06; }
    .badge.bg-dark-subtle.text-dark { background-color: #e9eeee !important; color: #172329 !important; }
    .alert { --bs-alert-color: var(--ink); --bs-alert-bg: #fff; --bs-alert-border-color: var(--line); }
    .table { --bs-table-color: var(--ink); --bs-table-bg: transparent; --bs-table-border-color: var(--line); }
    .sigra-icon { display: inline-block; width: 1.2em; height: 1.2em; vertical-align: -.2em; flex-shrink: 0; }
    .icono-vector { color: #087f78; line-height: 1; text-align: center; }
    .icono-vector .sigra-icon { width: 24px; height: 24px; }
    .evento-item[data-id] { cursor: pointer; border: 1px solid transparent !important; border-radius: 12px; padding: .8rem; transition: background-color .15s ease, border-color .15s ease, transform .15s ease, box-shadow .15s ease; }
    .evento-item:nth-child(odd) { background-color: #f1f3f4; }
    .evento-item:nth-child(even) { background-color: #fff; }
    .evento-item[data-id]:hover, .evento-item[data-id]:focus { background-color: #eef5f3; border-color: #b8d8d2 !important; outline: none; transform: translateX(2px); box-shadow: 0 5px 14px rgba(23, 35, 41, .08); }
    .evento-item.evento-nuevo { background-color: #fff2c7; border-left: 4px solid #e09b00; padding-left: .45rem; }
    .evento-item.evento-nuevo:hover, .evento-item.evento-nuevo:focus { background-color: #ffe9a3; }
    .sismo-aviso { position: fixed; z-index: 1300; top: 5.5rem; right: 1rem; width: min(360px, calc(100% - 2rem)); padding: .85rem 1rem; border: 1px solid #e09b00; border-radius: 12px; background: #fff8df; color: #684900; box-shadow: 0 12px 30px rgba(23, 35, 41, .16); font-weight: 700; opacity: 0; transform: translateY(-.5rem); pointer-events: none; transition: opacity .2s ease, transform .2s ease; }
    .sismo-aviso.visible { opacity: 1; transform: translateY(0); }
    .datos-actualizados { display: none; position: fixed; z-index: 1250; right: 50%; bottom: 1.25rem; width: max-content; max-width: calc(100% - 2rem); padding: .7rem 1rem; border: 1px solid rgba(8, 127, 120, .25); border-radius: 999px; background: rgba(8, 127, 120, .96); color: #fff; box-shadow: 0 10px 24px rgba(23, 35, 41, .2); font-size: .86rem; opacity: 0; transform: translate(50%, 1rem); pointer-events: none; transition: opacity .2s ease, transform .2s ease; }
    .datos-actualizados.visible { display: block; opacity: 1; transform: translate(50%, 0); }
    .theme-toggle { display: inline-grid; width: 2.5rem; height: 2.5rem; place-items: center; border: 1px solid var(--line); border-radius: 9px; background: transparent; color: var(--ink); font-size: 1.2rem; line-height: 1; }
    .theme-toggle:hover { border-color: var(--teal); color: var(--teal); }
    .nav-red-live { display: inline-flex; align-items: center; gap: .4rem; padding: .48rem .72rem !important; border: 1px solid rgba(8, 127, 120, .25); border-radius: 999px; background: rgba(8, 127, 120, .08); color: var(--teal) !important; font-weight: 800 !important; }
    .nav-red-live:hover, .nav-red-live.active { background: rgba(8, 127, 120, .15); border-color: rgba(8, 127, 120, .45); }
    .red-live-dot { display: inline-block; width: .58rem; height: .58rem; border-radius: 50%; background: #d97706; box-shadow: 0 0 0 .25rem rgba(217, 119, 6, .14); animation: sigra-pulse 2.2s ease-in-out infinite; }
    .nav-red-live.is-online .red-live-dot { background: #16a34a; box-shadow: 0 0 0 .25rem rgba(22, 163, 74, .16); }
    .nav-red-live.has-alert .red-live-dot { background: #dc2626; box-shadow: 0 0 0 .25rem rgba(220, 38, 38, .18); animation-duration: .9s; }
    .red-live-count { min-width: 1.45rem; padding: .05rem .36rem; border-radius: 999px; background: rgba(8, 127, 120, .14); font-size: .7rem; text-align: center; }
    .nav-red-live.has-alert .red-live-count { background: #dc2626; color: #fff; }
    .web-alerts-toggle { position: relative; display: inline-grid; width: 2.5rem; height: 2.5rem; place-items: center; border: 1px solid rgba(217, 119, 6, .35); border-radius: 50%; background: rgba(245, 158, 11, .10); color: #b45309; line-height: 1; transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease; }
    .web-alerts-toggle svg { width: 1.15rem; height: 1.15rem; }
    .web-alerts-toggle:hover { border-color: #d97706; background: rgba(245, 158, 11, .18); color: #92400e; }
    .web-alerts-toggle[aria-pressed="true"] { background: linear-gradient(135deg, #f59e0b, #d97706); border-color: #d97706; color: #fff; box-shadow: 0 0 0 .22rem rgba(245, 158, 11, .14); }
    .web-alerts-toggle[aria-pressed="true"]::after { content: ''; position: absolute; right: .18rem; bottom: .18rem; width: .42rem; height: .42rem; border-radius: 50%; background: #16a34a; border: 1px solid #fff; }
    .web-alerts-toggle.is-blocked { color: #b91c1c; border-color: rgba(185, 28, 28, .35); background: rgba(185, 28, 28, .08); }
    html[data-bs-theme="dark"] .web-alerts-toggle { color: #fbbf24; background: rgba(245, 158, 11, .12); }
    html[data-bs-theme="dark"] .web-alerts-toggle[aria-pressed="true"] { color: #111; }
    .lang-switch { display: inline-flex; align-items: center; gap: .1rem; padding: .18rem; border: 1px solid var(--line); border-radius: 999px; background: var(--surface); }
    .lang-switch a { padding: .26rem .48rem; border-radius: 999px; color: var(--muted); font-size: .68rem; font-weight: 800; letter-spacing: .03em; line-height: 1; text-decoration: none; }
    .lang-switch a:hover { color: var(--teal); }
    .lang-switch a.active { background: var(--teal); color: #fff; }
    html[data-bs-theme="dark"] .lang-switch { background: #151e23; }
    @media (max-width: 991px) { .lang-switch { margin: .35rem auto; } }
    @keyframes sigra-pulse { 0%, 100% { transform: scale(.92); } 50% { transform: scale(1.25); } }
    .sigra-alert-overlay { position: fixed; inset: 0; z-index: 3000; display: none; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 23, 42, .82); backdrop-filter: blur(7px); }
    .sigra-alert-overlay.is-open { display: flex; }
    .sigra-alert-card { width: min(580px, 100%); max-height: calc(100vh - 2rem); overflow-y: auto; padding: clamp(1.2rem, 3vw, 1.8rem); border-radius: 24px; background: linear-gradient(160deg, #450a0a, #991b1b 42%, #c2410c); color: #fff; box-shadow: 0 28px 80px rgba(0, 0, 0, .45); animation: sigra-alert-in .28s ease-out; }
    .sigra-alert-kicker { margin: 0 0 .4rem; color: #fed7aa; font-size: .72rem; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; }
    .sigra-alert-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: .4rem; }
    .sigra-alert-mag { font-family: 'Space Grotesk', sans-serif; font-size: clamp(2rem, 8vw, 3.2rem); font-weight: 700; line-height: 1; }
    .sigra-alert-sev { padding: .28rem .6rem; border: 1px solid rgba(255, 255, 255, .4); border-radius: 999px; background: rgba(255, 255, 255, .14); font-size: .75rem; font-weight: 800; }
    .sigra-alert-place { margin: 0 0 .9rem; color: #ffedd5; font-size: .9rem; }
    .sigra-alert-count { display: flex; align-items: baseline; gap: .55rem; margin: .6rem 0 .3rem; }
    .sigra-alert-count strong { font-family: 'Space Grotesk', sans-serif; font-size: clamp(3.2rem, 16vw, 5.8rem); line-height: .9; }
    .sigra-alert-count small { color: #ffedd5; font-size: .85rem; font-weight: 700; line-height: 1.3; }
    .sigra-alert-meta { margin: 0 0 1rem; color: #ffedd5; font-size: .86rem; }
    .sigra-alert-instructions { display: grid; grid-template-columns: repeat(3, 1fr); gap: .55rem; margin: 1rem 0; }
    .sigra-alert-instructions div { padding: .7rem .5rem; border: 1px solid rgba(255, 255, 255, .2); border-radius: 12px; background: rgba(255, 255, 255, .1); text-align: center; }
    .sigra-alert-instructions strong, .sigra-alert-instructions span { display: block; }
    .sigra-alert-instructions strong { font-size: .78rem; letter-spacing: .05em; }
    .sigra-alert-instructions span { margin-top: .2rem; color: #ffedd5; font-size: .7rem; line-height: 1.3; }
    .sigra-alert-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; }
    .sigra-alert-actions .btn { border-radius: 11px; font-weight: 800; }
    .sigra-alert-actions a { color: #fff; border-color: rgba(255, 255, 255, .5); }
    .sigra-alert-actions a:hover { background: rgba(255, 255, 255, .12); color: #fff; }
    .sigra-alert-disclaimer { margin: .9rem 0 0; color: #ffedd5; font-size: .72rem; line-height: 1.45; }
    body.sigra-alert-open { overflow: hidden; }
    .sigra-toast { position: fixed; z-index: 3200; right: 1rem; bottom: 5.5rem; max-width: min(380px, calc(100% - 2rem)); padding: .8rem 1rem; border: 1px solid rgba(8, 127, 120, .3); border-radius: 12px; background: rgba(8, 127, 120, .96); color: #fff; box-shadow: 0 14px 35px rgba(23, 35, 41, .24); font-size: .85rem; font-weight: 700; opacity: 0; transform: translateY(.6rem); pointer-events: none; transition: opacity .2s ease, transform .2s ease; }
    .sigra-toast.is-visible { opacity: 1; transform: translateY(0); }
    @keyframes sigra-alert-in { from { opacity: 0; transform: translateY(1rem) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @media (max-width: 520px) { .sigra-alert-instructions { grid-template-columns: 1fr; } .sigra-alert-count strong { font-size: 4.2rem; } }
    html[data-bs-theme="dark"] .nav-red-live { background: rgba(67, 197, 184, .12); border-color: rgba(67, 197, 184, .3); }
    html[data-bs-theme="dark"] .red-live-count { background: rgba(67, 197, 184, .18); }
    html[data-bs-theme="dark"] .web-alerts-toggle { color: #fbbf24; }
    html[data-bs-theme="dark"] { color-scheme: dark; --ink: #e8eef0; --muted: #9aaeb4; --line: #35434a; --paper: #11181c; --surface: #192329; --teal: #43c5b8; --coral: #f27668; --shadow: 0 18px 50px rgba(0, 0, 0, .28); }
    html[data-bs-theme="dark"] .navbar { background: rgba(15, 21, 25, .96); }
    html[data-bs-theme="dark"] .sigra-logo { filter: brightness(1.75) saturate(1.05); }
    html[data-bs-theme="dark"] .navbar-collapse { background: rgba(15, 21, 25, .98); }
    html[data-bs-theme="dark"] .event-sidebar { background: #151e23; }
    html[data-bs-theme="dark"] .event-sidebar > div:first-child { background: linear-gradient(135deg, rgba(67, 197, 184, .12), transparent 70%); }
    html[data-bs-theme="dark"] .event-sidebar > div:nth-child(2) { background: rgba(25, 35, 41, .72); }
    html[data-bs-theme="dark"] .event-sidebar, html[data-bs-theme="dark"] #eventos-lista { scrollbar-color: #527d79 transparent; }
    html[data-bs-theme="dark"] .event-sidebar::-webkit-scrollbar-track, html[data-bs-theme="dark"] #eventos-lista::-webkit-scrollbar-track { background: rgba(67, 197, 184, .06); }
    html[data-bs-theme="dark"] .event-sidebar::-webkit-scrollbar-thumb, html[data-bs-theme="dark"] #eventos-lista::-webkit-scrollbar-thumb { background: #527d79; background-clip: padding-box; }
    html[data-bs-theme="dark"] .event-sidebar::-webkit-scrollbar-thumb:hover, html[data-bs-theme="dark"] #eventos-lista::-webkit-scrollbar-thumb:hover { background: #43c5b8; background-clip: padding-box; }
    html[data-bs-theme="dark"] .map-stage { background: #26383d; }
    html[data-bs-theme="dark"] .map-copyright { color: rgba(225, 238, 237, .88); text-shadow: 0 1px 2px rgba(0, 0, 0, .8); }
    html[data-bs-theme="dark"] .map-info { border-color: rgba(133, 177, 175, .4); background: rgba(22, 34, 39, .94); box-shadow: 0 8px 24px rgba(0, 0, 0, .28); }
    html[data-bs-theme="dark"] .location-coordinates { background: #202d33; }
    html[data-bs-theme="dark"] .form-control, html[data-bs-theme="dark"] .form-select { background-color: #202d33; }
    html[data-bs-theme="dark"] .badge.bg-dark-subtle.text-dark { background-color: #34484e !important; color: #e8eef0 !important; }
    html[data-bs-theme="dark"] .alert { --bs-alert-bg: #202d33; }
    html[data-bs-theme="dark"] .evento-item:nth-child(odd) { background-color: #202d33; }
    html[data-bs-theme="dark"] .evento-item:nth-child(even) { background-color: #172126; }
    html[data-bs-theme="dark"] .evento-item[data-id]:hover, html[data-bs-theme="dark"] .evento-item[data-id]:focus { background-color: #28413f; }
    html[data-bs-theme="dark"] .evento-item[data-id]:hover, html[data-bs-theme="dark"] .evento-item[data-id]:focus { border-color: #3b7770 !important; box-shadow: 0 5px 14px rgba(0, 0, 0, .2); }
    html[data-bs-theme="dark"] .evento-item.evento-nuevo { background-color: #4a4022; }
    html[data-bs-theme="dark"] .evento-item.evento-nuevo:hover, html[data-bs-theme="dark"] .evento-item.evento-nuevo:focus { background-color: #5a4b25; }
    html[data-bs-theme="dark"] .cookie-banner { background: rgba(25, 35, 41, .97); box-shadow: 0 14px 35px rgba(0, 0, 0, .4); }
    html[data-bs-theme="dark"] .btn-report:hover { background: linear-gradient(135deg, #f06a5a, #d24535); }
    html[data-bs-theme="dark"] .site-footer { border-top-color: rgba(53, 67, 74, .8); background: rgba(17, 24, 28, .86); }
    .about-page { width: min(1120px, 100%); margin: 0 auto; }
    .about-hero { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(260px, .85fr); gap: 1.25rem; align-items: stretch; margin-bottom: 1rem; }
    .about-hero-copy, .about-brand-banner, .about-section, .about-contact { border: 1px solid var(--line); border-radius: 14px; background: var(--surface); box-shadow: 0 12px 30px rgba(23, 35, 41, .06); }
    .about-hero-copy { padding: clamp(1.4rem, 4vw, 3.2rem); background: linear-gradient(135deg, rgba(8, 127, 120, .12), transparent 70%), var(--surface); }
    .about-eyebrow, .about-kicker { margin: 0 0 .6rem; color: var(--teal); font-size: .73rem; font-weight: 800; letter-spacing: .11em; text-transform: uppercase; }
    .about-hero h1 { max-width: 680px; margin: 0 0 .9rem; font-size: clamp(2rem, 5vw, 4rem); line-height: .98; }
    .about-lead { max-width: 650px; margin: 0; color: var(--muted); font-size: 1.08rem; line-height: 1.6; }
    .about-brand-banner { display: flex; min-height: 330px; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; padding: 2rem; background: #edf6f4; }
    .about-brand-banner img { width: min(420px, 100%); height: auto; object-fit: contain; }
    .about-brand-banner span { color: #17635c; font-size: .8rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .about-quick-links { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; padding: .25rem 0; }
    .about-quick-links a { padding: .55rem .8rem; border: 1px solid var(--line); border-radius: 999px; color: var(--ink); font-size: .82rem; font-weight: 700; text-decoration: none; }
    .about-quick-links a:hover { border-color: var(--teal); color: var(--teal); }
    .about-content { display: grid; gap: 1rem; }
    .about-section { scroll-margin-top: 1rem; padding: clamp(1.1rem, 3vw, 2rem); }
    .about-section h2 { margin: 0 0 .75rem; font-size: clamp(1.35rem, 2.5vw, 2rem); }
    .about-section p { max-width: 820px; margin: 0 0 .8rem; color: var(--muted); line-height: 1.65; }
    .about-section p:last-child { margin-bottom: 0; }
    .about-feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; margin-top: 1.3rem; }
    .about-feature { padding: 1rem; border-left: 3px solid var(--teal); background: rgba(8, 127, 120, .06); }
    .about-feature strong, .about-feature span { display: block; }
    .about-feature strong { margin-bottom: .35rem; }
    .about-feature span { color: var(--muted); font-size: .86rem; line-height: 1.5; }
    .about-section-split { display: grid; grid-template-columns: minmax(0, 1fr) minmax(250px, .65fr); gap: 2rem; }
    .about-facts { display: grid; align-content: start; gap: .75rem; margin: 0; }
    .about-facts div { padding-bottom: .75rem; border-bottom: 1px solid var(--line); }
    .about-facts dt { color: var(--muted); font-size: .75rem; font-weight: 700; text-transform: uppercase; }
    .about-facts dd { margin: .2rem 0 0; font-weight: 700; }
    .about-contact { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-top: 1rem; padding: 1rem 1.25rem; }
    .about-contact span { color: var(--muted); font-size: .84rem; }
    .about-contact a { color: var(--teal); font-weight: 800; text-decoration: none; }
    html[data-bs-theme="dark"] .about-brand-banner { background: #203c3b; }
    html[data-bs-theme="dark"] .about-brand-banner span { color: #b4ece4; }
    @media (max-width: 850px) { .about-hero, .about-section-split { grid-template-columns: 1fr; } .about-brand-banner { min-height: 250px; padding: 1.25rem; } .about-brand-banner img { width: min(360px, 100%); } .about-feature-grid { grid-template-columns: 1fr; } .about-contact { align-items: flex-start; flex-direction: column; } }
    .site-footer { display: flex; width: 100%; flex-wrap: wrap; align-items: center; justify-content: center; gap: .35rem .75rem; padding: .35rem 1rem .45rem; border-top: 1px solid var(--line); background: var(--surface); color: var(--muted); text-align: center; font-size: .72rem; line-height: 1.2; overflow-wrap: normal; }
    .site-footer a { color: var(--teal); font-weight: 700; text-decoration: none; white-space: nowrap; }
    .site-footer a:hover { text-decoration: underline; }
    .site-footer-version { color: var(--teal); font-weight: 700; white-space: nowrap; }
    @media (max-width: 520px) { .site-footer { flex-direction: column; gap: .15rem; padding: .4rem .75rem; font-size: .66rem; } }
    .map-copyright { position: absolute; z-index: 650; left: 12px; bottom: 4px; color: rgba(52, 73, 78, .9); font-size: .74rem; font-weight: 600; line-height: 1.2; text-shadow: 0 1px 2px rgba(255, 255, 255, .75); pointer-events: none; }
    .cookie-banner { position: fixed; z-index: 1200; right: 1rem; bottom: 1rem; width: min(480px, calc(100% - 2rem)); padding: 1rem 1.1rem; border: 1px solid var(--line); border-radius: 12px; background: rgba(255, 255, 255, .97); box-shadow: 0 14px 35px rgba(23, 35, 41, .18); }
    .cookie-banner p { margin: 0; color: var(--muted); font-size: .86rem; line-height: 1.45; }
    .cookie-banner button { margin-top: .75rem; }
    body:has(.dashboard-shell) .cookie-banner { bottom: 8rem; }
    body:has(.dashboard-shell) { height: 100vh; overflow: hidden; }
    body:has(.dashboard-shell) .page-wrap { width: 100%; height: calc(100vh - 82px); padding: 0; }
    body:has(.dashboard-shell) .dashboard-shell { height: 100%; min-height: 0; border: 0; border-radius: 0; box-shadow: none; }
    body:has(.dashboard-shell) .event-sidebar { height: 100%; overflow-y: auto; }
    body:has(.dashboard-shell) .map-stage, body:has(.dashboard-shell) #mapa { height: 100%; min-height: 0; }
    body:has(.dashboard-shell) .site-footer { position: fixed; right: 0; bottom: 0; left: 0; z-index: 700; display: flex; text-align: center; }
    body:has(.dashboard-shell) .leaflet-control-attribution { display: block !important; visibility: visible !important; margin-bottom: 0 !important; }
    body:has(.dashboard-shell) .leaflet-bottom.leaflet-right { bottom: 1.8rem !important; }
    @media (max-width: 850px) {
        .page-wrap { padding: .75rem; }
        body:has(.dashboard-shell) { height: 100vh; min-height: 100vh; overflow: hidden; }
        body:has(.dashboard-shell) .page-wrap { height: auto; min-height: 0; padding: 0; }
        body:has(.dashboard-shell) .dashboard-shell { display: flex; flex-direction: column; height: auto; min-height: 0; overflow: visible; border: 0; border-radius: 0; box-shadow: none; }
        body:has(.dashboard-shell) .map-stage { order: 1; flex: none; height: min(62vh, 560px); min-height: 430px; }
        body:has(.dashboard-shell) #mapa { height: 100%; min-height: 430px; }
        body:has(.dashboard-shell) .event-sidebar { order: 2; width: 100%; height: auto; max-height: none; overflow: visible; border: 0; border-top: 1px solid var(--line); border-radius: 0; box-shadow: none; }
        body:has(.dashboard-shell) .event-sidebar > div:first-child { position: static; min-height: 0; padding: 0 !important; border: 0 !important; background: transparent; box-shadow: none; }
        body:has(.dashboard-shell) .event-sidebar > div:first-child > .d-flex { display: none !important; }
        body:has(.dashboard-shell) .mobile-events-toggle { position: fixed; right: 1rem; bottom: 5rem; z-index: 1160; display: inline-flex; align-items: center; gap: .45rem; min-height: 2.9rem; padding: .7rem .9rem; border: 1px solid var(--line); border-radius: 999px; background: var(--surface); color: var(--ink); box-shadow: 0 10px 25px rgba(23, 35, 41, .2); font-size: .82rem; font-weight: 700; }
        body:has(.dashboard-shell) .mobile-events-toggle .list-icon { color: var(--teal); font-size: 1.05rem; line-height: 1; }
        body:has(.dashboard-shell) .event-sidebar.details-open .mobile-events-toggle { display: none; }
        body:has(.dashboard-shell) .event-details { display: none; }
        body:has(.dashboard-shell) .event-sidebar.details-open .event-details { position: fixed; z-index: 1150; right: .75rem; bottom: .75rem; left: .75rem; display: block; max-height: min(70vh, 620px); overflow-y: auto; border: 1px solid var(--line); border-radius: 14px; background: var(--surface); box-shadow: 0 18px 45px rgba(23, 35, 41, .24); }
        body:has(.dashboard-shell) .event-details-header { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .85rem 1rem; border-bottom: 1px solid var(--line); background: var(--surface); }
        body:has(.dashboard-shell) .event-details-header h2 { font-size: 1rem; }
        body:has(.dashboard-shell) .mobile-events-close { display: inline-grid; width: 2rem; height: 2rem; place-items: center; border: 1px solid var(--line); border-radius: 50%; background: transparent; color: var(--ink); font-size: 1.25rem; line-height: 1; }
        body:has(.dashboard-shell) #eventos-lista { padding: .8rem 1rem 1.5rem !important; }
        body:has(.dashboard-shell) .map-copyright { display: none; }
        body:has(.dashboard-shell) .site-footer { right: 0; left: 0; z-index: 700; display: flex; margin: 0; padding: .2rem .75rem .3rem; border-top-color: rgba(223, 231, 229, .7); background: rgba(255, 255, 255, .82); font-size: .65rem; }
    }
    @media (max-width: 991px) {
        .navbar { height: 72px; min-height: 72px; max-height: 72px; }
        .navbar .container-fluid { gap: .5rem; height: 72px; min-height: 72px; max-height: 72px; padding: .55rem .75rem !important; display: flex; align-items: center; position: relative; }
        .navbar-brand { display: flex; align-items: center; justify-content: center; height: 100%; font-size: .98rem; margin: 0; padding: 0; line-height: 0; }
        .sigra-logo { display: block; height: 40px; max-height: 40px; width: auto; max-width: 180px; object-fit: contain; margin: 0; vertical-align: middle; }
        .sigra-version { padding: .14rem .34rem; font-size: .56rem; }
        .navbar-toggler { display: inline-flex; margin-left: auto; border: 1px solid var(--line); border-radius: 10px; padding: .35rem .45rem; }
        .navbar-toggler:focus { box-shadow: 0 0 0 .2rem rgba(8, 127, 120, .12); }
        .navbar-toggler-icon { width: 1.1rem; height: 1.1rem; }
        .navbar-collapse { display: none; position: absolute; z-index: 1100; top: calc(100% + 8px); right: .75rem; left: auto; width: min(250px, calc(100vw - 1.5rem)); background: rgba(255, 255, 255, .98); border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 18px 35px rgba(23, 35, 41, .12); padding: .5rem; }
        .navbar-collapse.is-open { display: block; animation: menu-drop .16s ease-out; }
        .navbar-nav { width: 100%; display: grid; gap: .15rem; }
        .navbar-nav .nav-item { width: 100%; }
        .navbar-nav .nav-link { display: block; width: 100%; padding: .7rem .8rem; border-radius: 10px; font-size: .82rem; }
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active { background: rgba(8, 127, 120, .06); }
        .navbar-nav .btn-report { display: inline-flex; width: 100%; justify-content: center; margin-top: .2rem; }
        .navbar-nav .theme-item { margin-top: .35rem; padding-top: .5rem; border-top: 1px solid var(--line); }
        .navbar-nav .theme-toggle { display: grid; margin: 0 auto; }
        .brand-mark { width: 34px; height: 34px; margin-right: .4rem; }
        .system-name { max-width: 180px; font-size: .74rem; }
        .system-version { margin-left: .2rem; font-size: .58rem; }
        body:has(.dashboard-shell) .map-stage { height: calc(100vh - 72px); min-height: 0; }
        body:has(.dashboard-shell) #mapa { min-height: 0; }
        body:has(.dashboard-shell) .event-sidebar > div:first-child { min-height: 92px; padding-top: 1rem !important; }
        body:has(.dashboard-shell) .map-info { top: 50%; right: auto; bottom: auto; left: 0; max-width: calc(100% - 12px); }
        body:has(.dashboard-shell) .map-legend { gap: .3rem .55rem; font-size: .66rem; }
        body:has(.dashboard-shell) .map-layer-switch { top: 12px !important; right: 0 !important; left: 5.5rem !important; width: auto; max-width: none; flex-wrap: wrap; justify-content: flex-start; border-radius: 0 !important; }
        body:has(.dashboard-shell) .map-layer-switch.is-collapsed { right: 0 !important; left: auto !important; }
        body:has(.dashboard-shell) .map-layer-switch > button[data-capa] { padding: 5px 9px !important; font-size: 11px !important; }
        body:has(.dashboard-shell) .leaflet-top.leaflet-left { top: .75rem; bottom: auto; left: .75rem; }
        body:has(.dashboard-shell) .cookie-banner { bottom: .75rem; }
    }
    @media (max-width: 850px) {
        body:has(.dashboard-shell) .event-sidebar > div:first-child { min-height: 0; padding-top: 0 !important; }
        html[data-bs-theme="dark"] body:has(.dashboard-shell) .site-footer { border-top-color: rgba(53, 67, 74, .8); background: rgba(17, 24, 28, .86); color: var(--muted); }
        html[data-bs-theme="dark"] body:has(.dashboard-shell) .event-sidebar > div:first-child { box-shadow: 0 6px 16px rgba(0, 0, 0, .24); }
    }
    @keyframes menu-drop { from { opacity: 0; transform: translateY(-.35rem); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand" href="<?= site_url('sismos') ?>" aria-label="<?= esc(lang('Sigra.ariaVersion', [$sigraConfig->version])) ?>">
            <img class="sigra-logo" src="<?= sigra_public_url('assets/images/sigra-logo.png') ?>" alt="SIGRA">
            <span class="sigra-version" title="SIGRA <?= esc($sigraConfig->label()) ?> · <?= esc(lang('Sigra.releaseName')) ?> · build <?= esc($sigraConfig->build) ?>">v<?= esc($sigraConfig->version) ?></span>
        </a>

        <button class="navbar-toggler" type="button" aria-controls="mainNavbar" aria-expanded="false" aria-label="<?= esc(lang('Sigra.navMenu')) ?>">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-collapse" id="mainNavbar">
            <?php $rutaActual = trim(uri_string(), '/'); ?>
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <li class="nav-item"><a class="nav-link nav-red-live <?= $rutaActual === 'red' ? 'active' : '' ?>" href="<?= site_url('red') ?>" aria-label="<?= esc(lang('Sigra.navLive')) ?>">
                    <span class="red-live-dot" aria-hidden="true"></span>
                    <span><?= esc(lang('Sigra.navLive')) ?></span>
                    <span class="red-live-count" id="nav-red-count">—</span>
                </a></li>
                <li class="nav-item"><a class="nav-link <?= $rutaActual === 'sismos' ? 'active' : '' ?>" href="<?= site_url('sismos') ?>"><?= esc(lang('Sigra.navMap')) ?></a></li>
                <li class="nav-item"><a class="nav-link nav-historico <?= str_starts_with($rutaActual, 'sismos/historico') ? 'active' : '' ?>" href="<?= site_url('sismos/historico') ?>"><?= esc(lang('Sigra.navHistory')) ?></a></li>
                <li class="nav-item"><a class="nav-link <?= str_starts_with($rutaActual, 'acerca-de') ? 'active' : '' ?>" href="<?= site_url('acerca-de') ?>"><?= esc(lang('Sigra.navAbout')) ?></a></li>
                <li class="nav-item">
                    <div class="lang-switch" role="group" aria-label="<?= esc(lang('Sigra.langSwitchAria')) ?>">
                        <a href="<?= esc($langUrl('es')) ?>" class="<?= $locale === 'es' ? 'active' : '' ?>" lang="es" hreflang="es" title="<?= esc(lang('Sigra.langSpanish')) ?>">ES</a>
                        <a href="<?= esc($langUrl('en')) ?>" class="<?= $locale === 'en' ? 'active' : '' ?>" lang="en" hreflang="en" title="<?= esc(lang('Sigra.langEnglish')) ?>">EN</a>
                    </div>
                </li>
                <li class="nav-item"><a class="btn btn-report" href="<?= site_url('sismos/create') ?>">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 21s6.5-5.7 6.5-10.5a6.5 6.5 0 1 0-13 0C5.5 15.3 12 21 12 21Z"/>
                        <circle cx="12" cy="10.5" r="2.3"/>
                    </svg>
                    <span><?= esc(lang('Sigra.navReport')) ?></span>
                </a></li>
                <li class="nav-item">
                    <button id="web-alerts-toggle" class="web-alerts-toggle" type="button" aria-label="<?= esc(lang('Sigra.navAlerts')) ?>" title="<?= esc(lang('Sigra.navAlerts')) ?>" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
                        </svg>
                    </button>
                </li>
                <li class="nav-item theme-item"><button id="theme-toggle" class="theme-toggle" type="button" aria-label="<?= esc(lang('Sigra.navThemeDark')) ?>" title="<?= esc(lang('Sigra.navThemeDark')) ?>" aria-pressed="false"><?= sigra_icon('moon') ?></button></li>
            </ul>
        </div>
    </div>
</nav>

<main class="page-wrap">
    <?= $contenido ?? '' ?>
</main>

<div id="sismo-aviso" class="sismo-aviso" role="status" aria-live="polite"></div>
<div id="datos-actualizados" class="datos-actualizados" role="status" aria-live="polite"><?= esc(lang('Sigra.updatedToast')) ?></div>

<div id="sigra-alert-overlay" class="sigra-alert-overlay" role="alertdialog" aria-modal="true" aria-labelledby="sigra-alert-title" hidden>
    <div class="sigra-alert-card">
        <p class="sigra-alert-kicker" id="sigra-alert-kicker"><?= esc(lang('Sigra.alertKicker')) ?></p>
        <div class="sigra-alert-head">
            <strong class="sigra-alert-mag" id="sigra-alert-mag">M —</strong>
            <span class="sigra-alert-sev" id="sigra-alert-sev"><?= esc(lang('Sigra.alertEstimate')) ?></span>
        </div>
        <p class="sigra-alert-place" id="sigra-alert-place"><?= esc(lang('Sigra.alertEvent')) ?></p>
        <div class="sigra-alert-count">
            <strong id="sigra-alert-seconds">--</strong>
            <small id="sigra-alert-title"><?= esc(lang('Sigra.alertSeconds')) ?></small>
        </div>
        <p class="sigra-alert-meta" id="sigra-alert-meta"><?= esc(lang('Sigra.alertCalc')) ?></p>
        <div class="sigra-alert-instructions" aria-label="<?= esc(lang('Sigra.alertInstructions')) ?>">
            <div><strong><?= esc(lang('Sigra.alertDrop')) ?></strong><span><?= esc(lang('Sigra.alertDropDesc')) ?></span></div>
            <div><strong><?= esc(lang('Sigra.alertCover')) ?></strong><span><?= esc(lang('Sigra.alertCoverDesc')) ?></span></div>
            <div><strong><?= esc(lang('Sigra.alertHold')) ?></strong><span><?= esc(lang('Sigra.alertHoldDesc')) ?></span></div>
        </div>
        <div class="sigra-alert-actions">
            <button type="button" class="btn btn-light" id="sigra-alert-dismiss"><?= esc(lang('Sigra.alertSafe')) ?></button>
            <a class="btn btn-outline-light" href="<?= site_url('red') ?>"><?= esc(lang('Sigra.alertMap')) ?></a>
        </div>
        <p class="sigra-alert-disclaimer"><?= esc(lang('Sigra.alertDisclaimer')) ?></p>
    </div>
</div>

<footer class="site-footer">
    <span class="site-footer-version">SIGRA <?= esc($sigraConfig->label()) ?> · <?= esc(lang('Sigra.releaseName')) ?></span>
    <span>© 2026 <?= esc($sigraConfig->author) ?> · <?= esc(lang('Sigra.footerOpenSource', [$sigraConfig->license])) ?></span>
    <span><?= esc(lang('Sigra.footerSources')) ?></span>
    <a href="<?= site_url('politica-cookies') ?>"><?= esc(lang('Sigra.footerCookies')) ?></a>
    <a href="<?= site_url('politica-privacidad') ?>"><?= esc(lang('Sigra.footerPrivacy')) ?></a>
    <a href="<?= site_url('terminos-uso') ?>"><?= esc(lang('Sigra.footerTerms')) ?></a>
</footer>

<section id="cookie-banner" class="cookie-banner" hidden aria-label="<?= esc(lang('Sigra.footerCookies')) ?>">
    <p><?= esc(lang('Sigra.cookieText')) ?></p>
    <button id="accept-cookies" type="button" class="btn btn-report btn-sm"><?= esc(lang('Sigra.cookieAccept')) ?></button>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    window.SIGRA_ASSETS = <?= json_encode(['icon' => sigra_public_url('assets/images/icon-192.png')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.SIGRA_I18N = <?= json_encode([
        'locale'     => $locale,
        'themeDark'  => lang('Sigra.navThemeDark'),
        'themeLight' => lang('Sigra.navThemeLight'),
        'map'        => [
            'layerMap'        => lang('Sigra.layerMap'),
            'layerDark'       => lang('Sigra.layerDark'),
            'layerSatellite'  => lang('Sigra.layerSatellite'),
            'layerTopo'       => lang('Sigra.layerTopo'),
            'layerChange'     => lang('Sigra.layerChange'),
            'collapseLayers'  => lang('Sigra.collapseLayers'),
            'expandLayers'    => lang('Sigra.expandLayers'),
            'reportedByUsers' => lang('Sigra.reportedByUsers'),
            'popupQuake'      => lang('Sigra.popupQuake'),
            'popupSource'     => lang('Sigra.popupSource'),
            'popupMagnitude'  => lang('Sigra.popupMagnitude'),
            'popupDepth'      => lang('Sigra.popupDepth'),
            'popupLocation'   => lang('Sigra.popupLocation'),
            'popupDate'       => lang('Sigra.popupDate'),
            'popupFelt'       => lang('Sigra.popupFelt'),
            'popupInstrumental' => lang('Sigra.popupInstrumental'),
            'popupPga'        => lang('Sigra.popupPga'),
            'intensityVeryStrong' => lang('Sigra.intensityVeryStrong'),
            'intensityStrong'     => lang('Sigra.intensityStrong'),
            'intensityModerate'   => lang('Sigra.intensityModerate'),
            'intensityLight'      => lang('Sigra.intensityLight'),
            'intensityVeryLight'  => lang('Sigra.intensityVeryLight'),
            'intensityUnavailable'=> lang('Sigra.intensityUnavailable'),
            'yourLocation'    => lang('Sigra.yourLocation'),
            'geoUnsupported'  => lang('Sigra.geoUnsupported'),
            'geoDenied'       => lang('Sigra.geoDenied'),
            'noData'          => lang('Sigra.noData'),
            'viewOfficial'    => lang('Sigra.viewOfficial'),
            'activateLocation'=> lang('Sigra.activateLocation'),
            'pointSelected'   => lang('Sigra.pointSelected'),
            'geoNotAllowed'   => lang('Sigra.geoNotAllowed'),
            'searchingLocation' => lang('Sigra.searchingLocation'),
            'locationFound'   => lang('Sigra.locationFound'),
            'locationFailed'  => lang('Sigra.locationFailed'),
            'kmFromYou'       => lang('Sigra.kmFromYou'),
        ],
        'red' => [
            'navAlerts'        => lang('Sigra.navAlerts'),
            'navAlertsOn'      => lang('Sigra.navAlertsOn'),
            'navAlertsBlocked' => lang('Sigra.navAlertsBlocked'),
            'geoUnsupported'   => lang('Sigra.redGeoUnsupported'),
            'locationError'    => lang('Sigra.redLocationError'),
            'notificationsBlocked' => lang('Sigra.redNotificationsBlocked'),
            'notificationsDenied'  => lang('Sigra.redNotificationsDenied'),
            'alertSpeech'      => lang('Sigra.redAlertSpeech'),
            'networkEvent'     => lang('Sigra.redNetworkEvent'),
            'enableLocationS'  => lang('Sigra.redEnableLocationS'),
            'secondsUntilS'    => lang('Sigra.redSecondsUntilS'),
            'sArrivingShort'   => lang('Sigra.redSArrivingShort'),
            'distanceFromYou'  => lang('Sigra.redDistanceFromYou'),
            'phonesOnline'     => lang('Sigra.redPhonesOnline'),
            'alertsSuffix'     => lang('Sigra.redAlertsSuffix'),
            'officialEvent'    => lang('Sigra.redOfficialEvent'),
            'infoAlert'        => lang('Sigra.redInfoAlert'),
            'earlyWarning'     => lang('Sigra.redEarlyWarning'),
            'alertsOff'        => lang('Sigra.redAlertsOff'),
            'alertsOnToast'    => lang('Sigra.redAlertsOnToast'),
            'quake'            => lang('Sigra.redQuake'),
            'now'              => lang('Sigra.redNow'),
            'alertsDisable'    => lang('Sigra.navAlertsDisable'),
            'notificationsBlockedShort' => lang('Sigra.redNotificationsBlockedShort'),
            'notificationsUnsupported'  => lang('Sigra.redNotificationsUnsupported'),
            'notificationsBlockedText'  => lang('Sigra.redNotificationsBlockedText'),
            'alertsActiveDisable'       => lang('Sigra.redAlertsActiveDisable'),
            'notSupportedShort'         => lang('Sigra.redNotSupportedShort'),
            'blockedShort'              => lang('Sigra.redBlockedShort'),
            'active'                    => lang('Sigra.redActive'),
            'inactive'                  => lang('Sigra.redInactive'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.SIGRA_RED_API = <?= json_encode(parse_url(site_url('api/red/red'), PHP_URL_PATH), JSON_UNESCAPED_SLASHES) ?>;
    window.SIGRA_RED_URL = <?= json_encode(site_url('red')) ?>;
</script>
<script src="<?= sigra_public_url('assets/js/sigra-red.js') ?>"></script>
<script>
    (function () {
        const root = document.documentElement;
        const toggle = document.getElementById('theme-toggle');

        function actualizarTema(tema) {
            const oscuro = tema === 'dark';
            const i18n = window.SIGRA_I18N || {};
            root.dataset.bsTheme = oscuro ? 'dark' : 'light';
            toggle.innerHTML = oscuro ? window.SIGRA_ICONS.sun : window.SIGRA_ICONS.moon;
            const etiqueta = oscuro ? (i18n.themeLight || 'Activar tema claro') : (i18n.themeDark || 'Activar tema oscuro');
            toggle.setAttribute('aria-label', etiqueta);
            toggle.setAttribute('title', etiqueta);
            toggle.setAttribute('aria-pressed', oscuro ? 'true' : 'false');
        }

        const temaGuardado = localStorage.getItem('sismos_theme') || 'light';
        actualizarTema(temaGuardado);
        toggle.addEventListener('click', function () {
            const temaNuevo = root.dataset.bsTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('sismos_theme', temaNuevo);
            actualizarTema(temaNuevo);
        });
    }());

    (function () {
        const banner = document.getElementById('cookie-banner');
        const accepted = localStorage.getItem('sismos_cookie_consent');
        if (!accepted) {
            banner.hidden = false;
        }
        document.getElementById('accept-cookies').addEventListener('click', function () {
            localStorage.setItem('sismos_cookie_consent', 'accepted');
            banner.hidden = true;
        });
    }());

    (function () {
        const toggle = document.querySelector('.navbar-toggler');
        const menu = document.getElementById('mainNavbar');

        if (!toggle || !menu) return;

        function setMenuState(open) {
            menu.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
        }

        setMenuState(false);

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            const isOpen = menu.classList.contains('is-open');
            setMenuState(!isOpen);
        });

        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setMenuState(false);
            });
        });

        document.addEventListener('click', function (event) {
            if (!menu.classList.contains('is-open') || menu.contains(event.target) || toggle.contains(event.target)) return;
            setMenuState(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setMenuState(false);
        });
    }());
</script>
</body>
</html>
