<?php

use Config\Red;

$redConfig = config(Red::class);

/**
 * red/index.php
 * Panel público de la red ciudadana de teléfonos.
 */

ob_start();
?>

<style>
    .red-hero { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(280px, .75fr); gap: 1rem; margin-bottom: 1rem; }
    .red-panel { border: 1px solid var(--line); border-radius: 16px; background: var(--surface); box-shadow: var(--shadow); }
    .red-hero-copy { padding: clamp(1.3rem, 4vw, 2.6rem); background: linear-gradient(135deg, rgba(8, 127, 120, .14), transparent 70%), var(--surface); }
    .red-eyebrow { margin: 0 0 .5rem; color: var(--teal); font-size: .72rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .red-hero h1 { margin: 0 0 .8rem; font-size: clamp(1.9rem, 4vw, 3.3rem); line-height: 1; }
    .red-hero p { max-width: 720px; margin: 0 0 1rem; color: var(--muted); line-height: 1.6; }
    .red-status { display: inline-flex; align-items: center; gap: .5rem; padding: .45rem .75rem; border: 1px solid var(--line); border-radius: 999px; background: rgba(255,255,255,.6); color: var(--ink); font-size: .8rem; font-weight: 700; }
    .red-status-dot { width: .65rem; height: .65rem; border-radius: 50%; background: #d97706; box-shadow: 0 0 0 .3rem rgba(217, 119, 6, .14); }
    .red-status.is-online .red-status-dot { background: #15803d; box-shadow: 0 0 0 .3rem rgba(21, 128, 61, .14); }
    .red-status.is-alert .red-status-dot { background: #dc2626; box-shadow: 0 0 0 .3rem rgba(220, 38, 38, .16); animation: red-pulse .9s infinite; }
    .red-cta { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.1rem; }
    .red-cta .btn { min-height: 44px; border-radius: 11px; font-weight: 700; }
    .red-device-card { display: flex; min-height: 100%; flex-direction: column; justify-content: space-between; gap: 1rem; padding: 1.4rem; background: #edf6f4; }
    .red-device-card h2 { margin: 0; font-size: 1.15rem; }
    .red-device-card p { margin: 0; }
    .red-device-phone { display: grid; place-items: center; min-height: 150px; }
    .red-device-phone span { font-size: 5rem; line-height: 1; filter: drop-shadow(0 12px 20px rgba(8, 127, 120, .22)); }
    .red-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .7rem; margin-bottom: 1rem; }
    .red-stat { padding: 1rem; border: 1px solid var(--line); border-radius: 14px; background: var(--surface); box-shadow: 0 8px 22px rgba(23, 35, 41, .05); }
    .red-stat span, .red-stat strong { display: block; }
    .red-stat span { color: var(--muted); font-size: .74rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .red-stat strong { margin-top: .35rem; font-family: 'Space Grotesk', sans-serif; font-size: 1.8rem; line-height: 1; }
    .red-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr); gap: 1rem; align-items: start; margin-bottom: 1rem; }
    .red-map-panel { position: relative; display: flex; flex-direction: column; align-self: stretch; height: auto; max-height: 900px; min-height: 620px; overflow: hidden; }
    #mapa-red { flex: 1 1 auto; width: 100%; min-height: 0; height: 100%; }
    .red-side { display: grid; align-content: start; gap: 1rem; }
    .red-card { padding: 1.1rem; border: 1px solid var(--line); border-radius: 14px; background: var(--surface); }
    .red-card h2, .red-card h3 { margin: 0 0 .7rem; font-size: 1.05rem; }
    .red-alert-list { display: grid; gap: .65rem; max-height: 340px; overflow-y: auto; padding-right: .15rem; }
    .red-alert-item { padding: .75rem; border: 1px solid var(--line); border-left: 4px solid #d97706; border-radius: 10px; background: rgba(245, 248, 247, .7); }
    .red-alert-item.is-roja { border-left-color: #dc2626; }
    .red-alert-item.is-naranja { border-left-color: #ea580c; }
    .red-alert-item.is-amarilla { border-left-color: #ca8a04; }
    .red-alert-item.is-informativa { border-left-color: #0d9488; }
    .red-alert-item strong { display: block; font-size: .98rem; }
    .red-alert-item span { display: block; margin-top: .2rem; color: var(--muted); font-size: .78rem; line-height: 1.45; }
    .red-alert-foot { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-top: .5rem; }
    .red-share { padding: .2rem .55rem; border: 1px solid var(--line); border-radius: 999px; background: transparent; color: var(--muted); font-size: .72rem; font-weight: 700; }
    .red-share:hover { border-color: var(--teal); color: var(--teal); }
    .red-risk { display: grid; gap: .55rem; }
    .red-risk-level { display: inline-flex; align-items: center; gap: .45rem; width: fit-content; padding: .3rem .6rem; border-radius: 999px; font-size: .74rem; font-weight: 800; }
    .red-risk-level.is-bajo { background: rgba(22, 163, 74, .12); color: #15803d; }
    .red-risk-level.is-medio { background: rgba(202, 138, 4, .14); color: #a16207; }
    .red-risk-level.is-alto { background: rgba(234, 88, 12, .14); color: #c2410c; }
    .red-risk-level.is-muy-alto { background: rgba(220, 38, 38, .14); color: #b91c1c; }
    .red-risk p { margin: 0; color: var(--muted); font-size: .84rem; line-height: 1.5; }
    .red-mini { display: flex; flex-wrap: wrap; gap: .35rem; }
    .red-chip { padding: .22rem .55rem; border: 1px solid var(--line); border-radius: 999px; background: rgba(245,248,247,.6); color: var(--muted); font-size: .72rem; font-weight: 700; }
    .red-wave-legend { display: flex; flex-wrap: wrap; gap: .4rem .8rem; margin-top: .55rem; color: var(--muted); font-size: .72rem; }
    .red-wave-legend.red-wave-legend-map { position: absolute; z-index: 500; left: 12px; bottom: 12px; margin-top: 0; padding: .42rem .6rem; border: 1px solid rgba(223, 231, 229, .9); border-radius: 10px; background: rgba(255, 255, 255, .92); box-shadow: 0 8px 20px rgba(23, 35, 41, .12); pointer-events: none; }
    .red-wave-legend span { display: inline-flex; align-items: center; gap: .3rem; }
    .red-wave-line { display: inline-block; width: 1.6rem; height: 0; border-top: 2px dashed #0d9488; }
    .red-wave-line.s { border-top-color: #dc2626; }
    .red-steps { display: grid; gap: .8rem; }
    .red-step { display: grid; grid-template-columns: 2rem minmax(0, 1fr); gap: .7rem; align-items: start; }
    .red-step b { display: grid; width: 2rem; height: 2rem; place-items: center; border-radius: 50%; background: var(--teal); color: #fff; font-size: .85rem; }
    .red-step p { margin: 0; color: var(--muted); font-size: .88rem; line-height: 1.5; }
    .red-step strong { color: var(--ink); }
    .red-note { padding: .9rem 1rem; border: 1px solid rgba(217, 119, 6, .35); border-radius: 12px; background: #fffbeb; color: #7c2d12; font-size: .85rem; line-height: 1.55; }
    .red-live-note { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-top: .5rem; color: var(--muted); font-size: .72rem; }
    .red-updated { display: inline-flex; align-items: center; gap: .35rem; }
    .red-updated i { width: .45rem; height: .45rem; border-radius: 50%; background: #16a34a; }
    .red-install-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin: .7rem 0 1rem; }
    html[data-bs-theme="dark"] .red-device-card { background: #203c3b; }
    html[data-bs-theme="dark"] .red-status { background: rgba(32, 45, 51, .8); }
    html[data-bs-theme="dark"] .red-alert-item, html[data-bs-theme="dark"] .red-note, html[data-bs-theme="dark"] .red-chip { background: #202d33; color: #fde68a; }
    html[data-bs-theme="dark"] .red-alert-item span { color: #cbd5d9; }
    html[data-bs-theme="dark"] .red-wave-legend.red-wave-legend-map { border-color: #35434a; background: rgba(25, 35, 41, .94); color: #cbd5d9; }
    @keyframes red-pulse { 0%,100% { transform: scale(.9); } 50% { transform: scale(1.25); } }
    @media (max-width: 950px) { .red-hero, .red-grid { grid-template-columns: 1fr; } .red-stats { grid-template-columns: repeat(2, 1fr); } .red-map-panel { align-self: stretch; height: 460px; max-height: 460px; min-height: 460px; } #mapa-red { height: 100%; min-height: 0; } }
    @media (max-width: 520px) { .red-stats { grid-template-columns: 1fr 1fr; } .red-device-phone span { font-size: 4rem; } }
</style>

<div class="red-hero">
    <div class="red-panel red-hero-copy">
        <p class="red-eyebrow"><?= esc(lang('Sigra.redEyebrow')) ?></p>
        <h1><?= esc(lang('Sigra.redTitle')) ?></h1>
        <p><?= esc(lang('Sigra.redIntro')) ?></p>
        <div class="red-status" id="red-estado"><i class="red-status-dot"></i><span><?= esc(lang('Sigra.redChecking')) ?></span></div>
        <div class="red-cta">
            <button class="btn btn-report" type="button" data-sigra-alerts-toggle><?= esc(lang('Sigra.redEnableAlerts')) ?></button>
            <a href="#instalar" class="btn btn-outline-secondary"><?= esc(lang('Sigra.redInstallApp')) ?></a>
            <button class="btn btn-outline-secondary" type="button" data-pwa-install hidden><?= esc(lang('Sigra.redInstallWeb')) ?></button>
        </div>
        <p class="small text-secondary mt-3 mb-0"><?= esc(lang('Sigra.redNoSms')) ?></p>
    </div>
    <div class="red-panel red-device-card">
        <div>
            <p class="red-eyebrow"><?= esc(lang('Sigra.redSensorEyebrow')) ?></p>
            <h2><?= esc(lang('Sigra.redSensorTitle')) ?></h2>
            <p><?= esc(lang('Sigra.redSensorText')) ?></p>
        </div>
        <div class="red-device-phone" aria-hidden="true"><span><?= sigra_icon('phone') ?></span></div>
        <div class="small text-secondary"><?= esc(lang('Sigra.redWaves')) ?></div>
    </div>
</div>

<div class="red-stats" aria-live="polite">
    <div class="red-stat"><span><?= esc(lang('Sigra.redStatOnline')) ?></span><strong id="stat-online">—</strong></div>
    <div class="red-stat"><span><?= esc(lang('Sigra.redStatMonitoring')) ?></span><strong id="stat-monitoring">—</strong></div>
    <div class="red-stat"><span><?= esc(lang('Sigra.redStatDetections')) ?></span><strong id="stat-detections">—</strong></div>
    <div class="red-stat"><span><?= esc(lang('Sigra.redStatAlerts')) ?></span><strong id="stat-alerts">—</strong></div>
</div>

<div class="red-grid">
    <section class="red-panel red-map-panel" aria-label="<?= esc(lang('Sigra.redMapAria')) ?>">
        <div id="mapa-red"></div>
        <div id="red-wave-legend" class="red-wave-legend red-wave-legend-map">
            <span><i class="red-wave-line"></i> <?= esc(lang('Sigra.redLegendP')) ?></span>
            <span><i class="red-wave-line s"></i> <?= esc(lang('Sigra.redLegendS')) ?></span>
        </div>
    </section>

    <aside class="red-side">
        <section class="red-card red-risk" id="red-riesgo">
            <h2><?= esc(lang('Sigra.redRiskTitle')) ?></h2>
            <div id="riesgo-nivel" class="red-risk-level is-bajo"><?= esc(lang('Sigra.redRiskNoLocation')) ?></div>
            <p id="riesgo-texto"><?= esc(lang('Sigra.redRiskText')) ?></p>
            <div id="riesgo-detalle" class="red-mini"></div>
            <button class="btn btn-report btn-sm" type="button" id="red-usar-ubicacion"><?= esc(lang('Sigra.redUseLocation')) ?></button>
        </section>

        <section class="red-card">
            <h2><?= esc(lang('Sigra.redAlertsTitle')) ?></h2>
            <p class="small text-secondary mb-2"><?= esc(lang('Sigra.redAlertsText')) ?></p>
            <div class="red-mini mb-2">
                <span class="red-chip"><?= esc(lang('Sigra.redStatusLabel')) ?> <strong data-sigra-alert-status><?= esc(lang('Sigra.redInactive')) ?></strong></span>
                <span class="red-chip"><?= esc(lang('Sigra.redRequiresPermission')) ?></span>
            </div>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-sigra-alerts-toggle><?= esc(lang('Sigra.redEnableAlerts')) ?></button>
        </section>

        <section class="red-card">
            <h2><?= esc(lang('Sigra.redLastAlerts')) ?></h2>
            <div id="red-alertas" class="red-alert-list">
                <p class="small text-secondary mb-0"><?= esc(lang('Sigra.redNoAlerts')) ?></p>
            </div>
            <div class="red-live-note">
                <span class="red-updated"><i></i> <?= esc(lang('Sigra.redLive')) ?></span>
                <span id="red-actualizado">—</span>
            </div>
        </section>

        <section class="red-card">
            <h2><?= esc(lang('Sigra.redHowTitle')) ?></h2>
            <div class="red-steps">
                <div class="red-step"><b>1</b><p><strong><?= esc(lang('Sigra.redStep1Title')) ?></strong> <?= esc(lang('Sigra.redStep1Text')) ?></p></div>
                <div class="red-step"><b>2</b><p><strong><?= esc(lang('Sigra.redStep2Title')) ?></strong> <?= esc(lang('Sigra.redStep2Text')) ?></p></div>
                <div class="red-step"><b>3</b><p><strong><?= esc(lang('Sigra.redStep3Title')) ?></strong> <?= esc(lang('Sigra.redStep3Text')) ?></p></div>
            </div>
        </section>
    </aside>
</div>

<section id="instalar" class="red-card mb-4">
    <div class="row g-4 align-items-start">
        <div class="col-lg-7">
            <p class="red-eyebrow"><?= esc(lang('Sigra.redInstallEyebrow')) ?></p>
            <h2 class="h4"><?= esc(lang('Sigra.redInstallTitle')) ?></h2>
            <p class="text-secondary mb-3"><?= esc(lang('Sigra.redInstallText')) ?></p>
            <div class="red-install-actions">
                <?php if ($redConfig->apkUrl !== ''): ?>
                    <a class="btn btn-report" href="<?= esc($redConfig->apkUrl) ?>"><?= esc(lang('Sigra.redDownloadApk')) ?></a>
                <?php endif; ?>
                <button class="btn btn-outline-secondary" type="button" data-pwa-install hidden><?= esc(lang('Sigra.redInstallWebBtn')) ?></button>
                <button class="btn btn-outline-secondary" type="button" data-sigra-alerts-toggle><?= esc(lang('Sigra.redEnableAlerts')) ?></button>
            </div>
            <ol class="small text-secondary">
                <li><?= lang('Sigra.redInstallStep1') ?></li>
                <li><?= esc(lang('Sigra.redInstallStep2')) ?></li>
                <li><?= lang('Sigra.redInstallStep3') ?></li>
                <li><?= esc(lang('Sigra.redInstallStep4')) ?></li>
            </ol>
            <div class="alert alert-warning small mb-0">
                <strong><?= esc(lang('Sigra.redImportant')) ?></strong> <?= esc(lang('Sigra.redImportantText')) ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="red-note mb-3">
                <strong><?= esc(lang('Sigra.redPrivacy')) ?></strong> <?= esc(lang('Sigra.redPrivacyText')) ?>
            </div>
            <div class="red-card">
                <h3><?= esc(lang('Sigra.redRequirements')) ?></h3>
                <ul class="small text-secondary mb-0">
                    <li><?= esc(lang('Sigra.redReqAndroid')) ?></li>
                    <li><?= esc(lang('Sigra.redReqBrowser')) ?></li>
                    <li><?= esc(lang('Sigra.redReqSensor')) ?></li>
                    <li><?= esc(lang('Sigra.redReqPermissions')) ?></li>
                    <li><?= esc(lang('Sigra.redReqForeground')) ?></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var RED_I18N = <?= json_encode([
        'locale' => service('request')->getLocale() === 'en' ? 'en-US' : 'es-CO',
        'layerPhones' => lang('Sigra.redLayerPhones'),
        'layerDetections' => lang('Sigra.redLayerDetections'),
        'layerAlerts' => lang('Sigra.redLayerAlerts'),
        'layerWaves' => lang('Sigra.redLayerWaves'),
        'layerMe' => lang('Sigra.redLayerMe'),
        'severityVeryHigh' => lang('Sigra.redSeverityVeryHigh'),
        'severityHigh' => lang('Sigra.redSeverityHigh'),
        'severityModerate' => lang('Sigra.redSeverityModerate'),
        'severityInfo' => lang('Sigra.redSeverityInfo'),
        'sourceSgc' => lang('Sigra.redSourceSgc'),
        'sourceNetwork' => lang('Sigra.redSourceNetwork'),
        'statusActive' => lang('Sigra.redStatusActive'),
        'statusFinished' => lang('Sigra.redStatusFinished'),
        'share' => lang('Sigra.redShare'),
        'phones' => lang('Sigra.redPhones'),
        'noAlerts' => lang('Sigra.redNoAlerts'),
        'phonePopup' => lang('Sigra.redPhonePopup'),
        'detectionPopup' => lang('Sigra.redDetectionPopup'),
        'estimated' => lang('Sigra.redEstimated'),
        'confidence' => lang('Sigra.redConfidence'),
        'radius' => lang('Sigra.redRadius'),
        'origin' => lang('Sigra.redOrigin'),
        'approxLocation' => lang('Sigra.redApproxLocation'),
        'noLocation' => lang('Sigra.redNoLocation'),
        'noActiveAlerts' => lang('Sigra.redNoActiveAlerts'),
        'noActiveAlertsText' => lang('Sigra.redNoActiveAlertsText'),
        'locationSaved' => lang('Sigra.redLocationSaved'),
        'riskVeryHigh' => lang('Sigra.redRiskVeryHigh'),
        'riskHigh' => lang('Sigra.redRiskHigh'),
        'riskMedium' => lang('Sigra.redRiskMedium'),
        'riskLow' => lang('Sigra.redRiskLow'),
        'riskEstimated' => lang('Sigra.redRiskEstimated'),
        'sComing' => lang('Sigra.redSComing'),
        'sArriving' => lang('Sigra.redSArriving'),
        'dropCoverHold' => lang('Sigra.redDropCoverHold'),
        'eta' => lang('Sigra.redEta'),
        'sInProgress' => lang('Sigra.redSInProgress'),
        'statusActiveAlert' => lang('Sigra.redStatusActiveAlert'),
        'statusOperational' => lang('Sigra.redStatusOperational'),
        'statusForming' => lang('Sigra.redStatusForming'),
        'updated' => lang('Sigra.redUpdated'),
        'connectionError' => lang('Sigra.redConnectionError'),
        'updateLocation' => lang('Sigra.redUpdateLocation'),
        'searchingLocation' => lang('Sigra.redSearchingLocation'),
        'locationActive' => lang('Sigra.redLocationActive'),
        'locationError' => lang('Sigra.redLocationError'),
        'shareTitle' => lang('Sigra.redShareTitle'),
        'shareText' => lang('Sigra.redShareText'),
        'copied' => lang('Sigra.redCopied'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function rt(clave) {
        var texto = RED_I18N[clave] || clave;
        for (var i = 1; i < arguments.length; i += 1) {
            texto = texto.replace(new RegExp('\\{' + (i - 1) + '\\}', 'g'), arguments[i]);
        }
        return texto;
    }

    var urlRed = <?= json_encode(parse_url(site_url('api/red/red'), PHP_URL_PATH), JSON_UNESCAPED_SLASHES) ?>;
    var mapa = L.map('mapa-red').setView([4.5709, -74.2973], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(mapa);

    var capaDispositivos = L.layerGroup().addTo(mapa);
    var capaDetecciones = L.layerGroup().addTo(mapa);
    var capaAlertas = L.layerGroup().addTo(mapa);
    var capaOndas = L.layerGroup().addTo(mapa);
    var capaUsuario = L.layerGroup().addTo(mapa);

    L.control.layers(null, {
        [RED_I18N.layerPhones]: capaDispositivos,
        [RED_I18N.layerDetections]: capaDetecciones,
        [RED_I18N.layerAlerts]: capaAlertas,
        [RED_I18N.layerWaves]: capaOndas,
        [RED_I18N.layerMe]: capaUsuario
    }, { collapsed: true }).addTo(mapa);

    window.setTimeout(function () { mapa.invalidateSize(); }, 500);
    window.addEventListener('resize', function () { mapa.invalidateSize(); });

    var ondas = {};
    var alertasPorUuid = {};
    var ultimosDatos = null;
    var marcadorUsuario = null;

    function escapar(valor) {
        return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
        });
    }

    function parseFecha(valor) {
        if (!valor) return null;
        var fecha = new Date(String(valor).replace(' ', 'T'));
        return isNaN(fecha.getTime()) ? null : fecha;
    }

    function colorSeveridad(severidad) {
        return { roja: '#dc2626', naranja: '#ea580c', amarilla: '#ca8a04', informativa: '#0d9488' }[severidad] || '#0d9488';
    }

    function etiquetaSeveridad(severidad) {
        return {
            roja: RED_I18N.severityVeryHigh,
            naranja: RED_I18N.severityHigh,
            amarilla: RED_I18N.severityModerate,
            informativa: RED_I18N.severityInfo
        }[severidad] || RED_I18N.severityInfo;
    }

    function alertaActiva(alerta) {
        if (!alerta || alerta.status !== 'active') return false;
        var expira = parseFecha(alerta.expires_at);
        return !expira || expira.getTime() >= Date.now();
    }

    function distanciaKm(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2 +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function renderAlertas(alertas) {
        var contenedor = document.getElementById('red-alertas');
        alertasPorUuid = {};
        if (!Array.isArray(alertas) || alertas.length === 0) {
            contenedor.innerHTML = '<p class="small text-secondary mb-0">' + escapar(RED_I18N.noAlerts) + '</p>';
            return;
        }

        contenedor.innerHTML = alertas.map(function (alerta) {
            alertasPorUuid[alerta.uuid] = alerta;
            var hora = alerta.created_at ? new Date(String(alerta.created_at).replace(' ', 'T')).toLocaleString(RED_I18N.locale) : '';
            var origen = alerta.source === 'sgc' ? RED_I18N.sourceSgc : RED_I18N.sourceNetwork;
            var estado = alertaActiva(alerta) ? RED_I18N.statusActive : RED_I18N.statusFinished;
            var distancia = alerta.distance_km != null ? ' · ' + Number(alerta.distance_km).toFixed(1) + ' km' : '';
            return '<article class="red-alert-item is-' + escapar(alerta.severity) + '">' +
                '<strong>M ' + escapar(Number(alerta.magnitude).toFixed(1)) + ' · ' + escapar(origen) + '</strong>' +
                '<span>' + escapar(alerta.latitude.toFixed(3)) + ', ' + escapar(alerta.longitude.toFixed(3)) +
                distancia + ' · ' + escapar(alerta.device_count) + ' ' + escapar(RED_I18N.phones) + ' · ' +
                escapar(RED_I18N.confidence.toLowerCase()) + ' ' +
                escapar(Math.round(Number(alerta.confidence) * 100)) + '%</span>' +
                '<span>' + escapar(hora) + ' · ' + escapar(RED_I18N.radius.toLowerCase()) + ' ' + escapar(Math.round(alerta.radius_km)) + ' km · ' + escapar(estado) + '</span>' +
                '<div class="red-alert-foot"><span class="red-chip">' + escapar(etiquetaSeveridad(alerta.severity)) + '</span>' +
                '<button class="red-share" type="button" data-share="' + escapar(alerta.uuid) + '">' + escapar(RED_I18N.share) + '</button></div>' +
                '</article>';
        }).join('');
    }

    function renderMapa(datos) {
        capaDispositivos.clearLayers();
        capaDetecciones.clearLayers();
        capaAlertas.clearLayers();
        capaOndas.clearLayers();
        ondas = {};

        (datos.devices || []).forEach(function (punto) {
            L.circleMarker([punto.lat, punto.lng], {
                radius: punto.monitoring ? 5 : 3,
                color: '#ffffff',
                weight: 1,
                fillColor: punto.monitoring ? '#087f78' : '#64748b',
                fillOpacity: 0.8
            }).bindPopup(RED_I18N.phonePopup).addTo(capaDispositivos);
        });

        (datos.detections || []).forEach(function (punto) {
            L.circleMarker([punto.lat, punto.lng], {
                radius: 3,
                color: '#fed7aa',
                weight: 1,
                fillColor: '#f97316',
                fillOpacity: 0.75
            }).bindPopup(rt('detectionPopup', Math.round(Number(punto.confidence) * 100))).addTo(capaDetecciones);
        });

        (datos.recent_alerts || []).filter(alertaActiva).forEach(function (alerta) {
            var color = colorSeveridad(alerta.severity);
            L.circle([alerta.latitude, alerta.longitude], {
                radius: Number(alerta.radius_km) * 1000,
                color: color,
                weight: 1,
                fillColor: color,
                fillOpacity: 0.04
            }).addTo(capaAlertas);
            L.circleMarker([alerta.latitude, alerta.longitude], {
                radius: Math.max(6, Math.min(18, 3 + Number(alerta.magnitude) * 2)),
                color: '#ffffff',
                weight: 2,
                fillColor: color,
                fillOpacity: 0.88
            }).bindPopup(
                '<strong>' + rt('estimated', Number(alerta.magnitude).toFixed(1)) + '</strong><br>' +
                escapar(RED_I18N.confidence) + ': ' + Math.round(Number(alerta.confidence) * 100) + '%<br>' +
                escapar(RED_I18N.phones) + ': ' + alerta.device_count + '<br>' +
                escapar(RED_I18N.radius) + ': ' + Math.round(alerta.radius_km) + ' km<br>' +
                escapar(RED_I18N.origin) + ': ' + (alerta.source === 'sgc' ? RED_I18N.sourceSgc : RED_I18N.sourceNetwork)
            ).addTo(capaAlertas);

            var origen = Number(alerta.origin_time_ms) || (parseFecha(alerta.created_at) || new Date()).getTime();
            var p = L.circle([alerta.latitude, alerta.longitude], {
                radius: 0,
                color: '#0d9488',
                weight: 2,
                dashArray: '7 7',
                fill: false,
                opacity: 0.85
            }).addTo(capaOndas);
            var s = L.circle([alerta.latitude, alerta.longitude], {
                radius: 0,
                color: '#dc2626',
                weight: 2.4,
                dashArray: '4 8',
                fill: false,
                opacity: 0.9
            }).addTo(capaOndas);
            ondas[alerta.uuid] = { origin: origen, p: p, s: s };
        });

        var ubicacion = window.SigraRed ? window.SigraRed.getLocation() : null;
        if (ubicacion) {
            capaUsuario.clearLayers();
            marcadorUsuario = L.circleMarker([ubicacion.lat, ubicacion.lng], {
                radius: 7,
                color: '#ffffff',
                weight: 2,
                fillColor: '#087f78',
                fillOpacity: 1
            }).bindPopup(RED_I18N.approxLocation).addTo(capaUsuario);
        }
    }

    function actualizarOndas() {
        var ahora = Date.now();
        Object.keys(ondas).forEach(function (uuid) {
            var onda = ondas[uuid];
            var segundos = Math.max(0, (ahora - onda.origin) / 1000);
            onda.p.setRadius(Math.min(800, segundos * 6) * 1000);
            onda.s.setRadius(Math.min(800, segundos * 3.5) * 1000);
        });
    }
    window.setInterval(actualizarOndas, 250);

    function renderRiesgo(datos) {
        var nivel = document.getElementById('riesgo-nivel');
        var texto = document.getElementById('riesgo-texto');
        var detalle = document.getElementById('riesgo-detalle');
        var ubicacion = window.SigraRed ? window.SigraRed.getLocation() : null;

        detalle.innerHTML = '';
        if (!ubicacion) {
            nivel.className = 'red-risk-level is-bajo';
            nivel.textContent = RED_I18N.noLocation;
            texto.textContent = <?= json_encode(lang('Sigra.redRiskText'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            return;
        }

        var activas = (datos.recent_alerts || []).filter(alertaActiva).filter(function (alerta) {
            return alerta.distance_km == null || alerta.radius_km == null || Number(alerta.distance_km) <= Number(alerta.radius_km);
        });
        if (activas.length === 0) {
            nivel.className = 'red-risk-level is-bajo';
            nivel.textContent = RED_I18N.noActiveAlerts;
            texto.textContent = RED_I18N.noActiveAlertsText;
            detalle.innerHTML = '<span class="red-chip">' + escapar(RED_I18N.locationSaved) + '</span>';
            return;
        }

        var cercana = activas
            .map(function (alerta) {
                return {
                    alerta: alerta,
                    distancia: distanciaKm(ubicacion.lat, ubicacion.lng, alerta.latitude, alerta.longitude)
                };
            })
            .sort(function (a, b) { return a.distancia - b.distancia; })[0];

        var alerta = cercana.alerta;
        var distancia = cercana.distancia;
        var origen = Number(alerta.origin_time_ms) || Date.now();
        var eta = (origen + (distancia / 3.5) * 1000 - Date.now()) / 1000;
        var magnitud = Number(alerta.magnitude);
        var puntaje = magnitud + (distancia < 50 ? 1.4 : (distancia < 150 ? .7 : 0));

        nivel.className = 'red-risk-level ' + (puntaje >= 6 ? 'is-muy-alto' : puntaje >= 5 ? 'is-alto' : puntaje >= 3.8 ? 'is-medio' : 'is-bajo');
        nivel.textContent = puntaje >= 6 ? RED_I18N.riskVeryHigh : puntaje >= 5 ? RED_I18N.riskHigh : puntaje >= 3.8 ? RED_I18N.riskMedium : RED_I18N.riskLow;
        texto.textContent = rt('riskEstimated', magnitud.toFixed(1), distancia.toFixed(1)) + ' ' +
            (eta > 0 ? rt('sComing', Math.round(eta)) : RED_I18N.sArriving) +
            ' ' + RED_I18N.dropCoverHold;
        detalle.innerHTML = '<span class="red-chip">' + escapar(eta > 0 ? rt('eta', Math.round(eta)) : RED_I18N.sInProgress) + '</span>' +
            '<span class="red-chip">' + escapar(RED_I18N.radius) + ' ' + Math.round(alerta.radius_km) + ' km</span>' +
            '<span class="red-chip">' + escapar(RED_I18N.confidence) + ' ' + Math.round(Number(alerta.confidence) * 100) + '%</span>';
    }

    function actualizar() {
        var ubicacion = window.SigraRed ? window.SigraRed.getLocation() : null;
        var url = urlRed;
        if (ubicacion) {
            url += '?lat=' + encodeURIComponent(ubicacion.lat) + '&lng=' + encodeURIComponent(ubicacion.lng);
        }

        fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' })
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) {
                if (datos.status !== 'ok') return;
                ultimosDatos = datos;
                document.getElementById('stat-online').textContent = datos.stats.devices_online;
                document.getElementById('stat-monitoring').textContent = datos.stats.devices_monitoring;
                document.getElementById('stat-detections').textContent = datos.stats.detections_24h;
                document.getElementById('stat-alerts').textContent = datos.stats.alerts_24h;

                var activas = (datos.recent_alerts || []).filter(alertaActiva);
                var estado = document.getElementById('red-estado');
                estado.classList.toggle('is-online', Boolean(datos.operational));
                estado.classList.toggle('is-alert', activas.length > 0);
                estado.querySelector('span').textContent = activas.length > 0
                    ? rt('statusActiveAlert', activas.length)
                    : (datos.operational
                        ? rt('statusOperational', datos.stats.devices_online)
                        : rt('statusForming', datos.config.min_devices));

                document.getElementById('red-actualizado').textContent = rt('updated', new Date().toLocaleTimeString(RED_I18N.locale, { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
                renderAlertas(datos.recent_alerts);
                renderMapa(datos);
                renderRiesgo(datos);
            })
            .catch(function () {
                document.getElementById('red-estado').querySelector('span').textContent = RED_I18N.connectionError;
            });
    }

    var botonUbicacion = document.getElementById('red-usar-ubicacion');
    if (botonUbicacion) {
        if (window.SigraRed && window.SigraRed.getLocation()) {
            botonUbicacion.textContent = RED_I18N.updateLocation;
        }
        botonUbicacion.addEventListener('click', function () {
            if (!window.SigraRed) return;
            botonUbicacion.disabled = true;
            botonUbicacion.textContent = RED_I18N.searchingLocation;
            window.SigraRed.requestLocation().then(function (ubicacion) {
                botonUbicacion.textContent = RED_I18N.locationActive;
                mapa.setView([ubicacion.lat, ubicacion.lng], 8);
                actualizar();
            }).catch(function (error) {
                botonUbicacion.disabled = false;
                botonUbicacion.textContent = RED_I18N.updateLocation;
                var texto = document.getElementById('riesgo-texto');
                if (texto) texto.textContent = error.message || RED_I18N.locationError;
            });
        });
    }

    document.getElementById('red-alertas').addEventListener('click', function (event) {
        var boton = event.target.closest('[data-share]');
        if (!boton) return;
        var alerta = alertasPorUuid[boton.getAttribute('data-share')];
        if (!alerta) return;
        var distanciaTexto = alerta.distance_km != null ? Number(alerta.distance_km).toFixed(1) + ' km · ' : '';
        var texto = rt(
            'shareText',
            Number(alerta.magnitude).toFixed(1),
            distanciaTexto,
            Math.round(Number(alerta.confidence) * 100),
            window.location.origin + <?= json_encode(parse_url(site_url('red'), PHP_URL_PATH), JSON_UNESCAPED_SLASHES) ?>
        );

        if (navigator.share) {
            navigator.share({ title: RED_I18N.shareTitle, text: texto }).catch(function () {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(texto).then(function () {
                boton.textContent = RED_I18N.copied;
                window.setTimeout(function () { boton.textContent = RED_I18N.share; }, 2000);
            }).catch(function () {});
        }
    });

    document.addEventListener('sigra:location', function () {
        actualizar();
    });

    actualizar();
    window.setInterval(actualizar, 10000);
});
</script>

<?php
$contenido = ob_get_clean();
return view('layouts/main', ['contenido' => $contenido, 'titulo' => lang('Sigra.redPageTitle')]);
