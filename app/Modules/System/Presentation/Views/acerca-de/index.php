<?php

$releaseTexts = lang('Releases.history');
$channelKey = 'Sigra.channel' . ucfirst($sigra->channel);
$channelLabel = lang($channelKey);
if ($channelLabel === $channelKey) $channelLabel = $sigra->channel;

ob_start();
?>

<style>
    .about-version-line { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .65rem; }
    .about-version-pill { display: inline-flex; align-items: center; padding: .22rem .55rem; border: 1px solid rgba(8, 127, 120, .3); border-radius: 999px; background: rgba(8, 127, 120, .1); color: var(--teal); font-size: .72rem; font-weight: 800; letter-spacing: .03em; }
    .about-version-name { color: var(--muted); font-size: .8rem; font-weight: 700; }
    .about-version-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: .75rem; }
    .about-version-head h2 { margin-bottom: .2rem; }
    .about-version-head p { margin: 0; }
    .about-version-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .6rem; margin: 1rem 0; }
    .about-version-grid > div { padding: .8rem; border: 1px solid var(--line); border-radius: 12px; background: rgba(8, 127, 120, .05); }
    .about-version-grid span { display: block; color: var(--muted); font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .about-version-grid strong { display: block; margin-top: .25rem; font-family: 'Space Grotesk', sans-serif; font-size: 1.1rem; }
    .about-status { display: flex; align-items: center; gap: .6rem; padding: .8rem 1rem; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); color: var(--muted); font-size: .84rem; }
    .about-status-dot { width: .65rem; height: .65rem; flex: 0 0 .65rem; border-radius: 50%; background: #d97706; box-shadow: 0 0 0 .3rem rgba(217, 119, 6, .14); }
    .about-status-dot.ok { background: #16a34a; box-shadow: 0 0 0 .3rem rgba(22, 163, 74, .15); }
    .about-status-dot.error { background: #dc2626; box-shadow: 0 0 0 .3rem rgba(220, 38, 38, .15); }
    .provider-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 290px), 1fr)); gap: 1rem; margin-top: 1rem; }
    .provider-card { border: 1px solid var(--line); border-radius: 14px; padding: 1rem; background: var(--surface); }
    .provider-card header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; }
    .provider-card h3 { margin: 0; font-size: 1rem; }
    .provider-card .provider-host { color: var(--muted); font-size: .76rem; overflow-wrap: anywhere; margin: .5rem 0; }
    .provider-badge { border: 1px solid currentColor; border-radius: 999px; padding: .2rem .5rem; font-size: .72rem; color: var(--muted); }
    .provider-badge.ok { color: var(--teal); }
    .provider-badge.paused, .provider-badge.error, .provider-badge.invalid { color: #b45309; }
    html[data-bs-theme="dark"] .provider-badge.paused, html[data-bs-theme="dark"] .provider-badge.error, html[data-bs-theme="dark"] .provider-badge.invalid { color: #fbbf24; }
    .provider-result { font-size: .84rem; min-height: 2.5em; }
    .provider-card dl { margin: 0; }
    .provider-card dl > div { padding: .5rem 0; border-top: 1px solid var(--line); }
    .provider-card dt { color: var(--muted); font-size: .74rem; font-weight: 500; }
    .provider-card dd { margin: .15rem 0 0; font-size: .82rem; font-variant-numeric: tabular-nums; }
    .about-changelog { display: grid; gap: 1rem; margin-top: 1rem; }
    .about-release { padding: .15rem 0 .15rem 1rem; border-left: 3px solid var(--teal); }
    .about-release header { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; }
    .about-release header strong { font-family: 'Space Grotesk', sans-serif; font-size: 1.12rem; }
    .about-release header span { color: var(--muted); font-size: .78rem; }
    .about-release p { margin: .35rem 0 .45rem; }
    .about-release ul { margin: 0; padding-left: 1.1rem; color: var(--muted); font-size: .86rem; line-height: 1.55; }
    html[data-bs-theme="dark"] .about-version-grid > div { background: rgba(67, 197, 184, .08); }
    html[data-bs-theme="dark"] .about-version-pill { border-color: rgba(67, 197, 184, .35); background: rgba(67, 197, 184, .12); }
    @media (max-width: 700px) { .about-version-grid { grid-template-columns: 1fr 1fr; } }
</style>

<article class="about-page">
    <header class="about-hero">
        <div class="about-hero-copy">
            <p class="about-eyebrow"><?= esc(lang('Sigra.aboutEyebrow')) ?></p>
            <div class="about-version-line">
                <span class="about-version-pill">v<?= esc($sigra->version) ?></span>
                <span class="about-version-name"><?= esc(lang('Sigra.releaseName')) ?></span>
            </div>
            <h1><?= esc(lang('Sigra.aboutTitle')) ?></h1>
            <p class="about-lead"><?= esc(lang('Sigra.aboutLead')) ?></p>
        </div>
        <div class="about-brand-banner">
            <img src="<?= sigra_public_url('assets/images/sigra--logo2.png') ?>" alt="SIGRA">
            <span>SIGRA · v<?= esc($sigra->version) ?></span>
        </div>
    </header>

    <nav class="about-quick-links" aria-label="<?= esc(lang('Sigra.aboutNavAria')) ?>">
        <a href="#sources-health"><?= esc(lang('Sigra.providerTitle')) ?></a>
        <a href="#version"><?= esc(lang('Sigra.aboutNavVersion')) ?></a>
        <a href="#changelog"><?= esc(lang('Sigra.aboutNavChanges')) ?></a>
        <a href="<?= site_url('red') ?>"><?= esc(lang('Sigra.navLive')) ?></a>
        <a href="<?= site_url('politica-privacidad') ?>"><?= esc(lang('Sigra.legalPrivacy')) ?></a>
        <a href="<?= site_url('politica-cookies') ?>"><?= esc(lang('Sigra.legalCookies')) ?></a>
        <a href="<?= site_url('terminos-uso') ?>"><?= esc(lang('Sigra.legalTerms')) ?></a>
    </nav>

    <div class="about-content">
        <section class="about-section">
            <p class="about-kicker"><?= esc(lang('Sigra.projectKicker')) ?></p>
            <h2><?= esc(lang('Sigra.projectTitle')) ?></h2>
            <p><?= esc(lang('Sigra.projectText')) ?></p>
            <div class="about-feature-grid">
                <div class="about-feature"><strong><?= esc(lang('Sigra.featureMapTitle')) ?></strong><span><?= esc(lang('Sigra.featureMapText')) ?></span></div>
                <div class="about-feature"><strong><?= esc(lang('Sigra.featureNetworkTitle')) ?></strong><span><?= esc(lang('Sigra.featureNetworkText')) ?></span></div>
                <div class="about-feature"><strong><?= esc(lang('Sigra.featureAlertsTitle')) ?></strong><span><?= esc(lang('Sigra.featureAlertsText')) ?></span></div>
            </div>
        </section>

        <section class="about-section about-section-split">
            <div>
                <p class="about-kicker"><?= esc(lang('Sigra.sourcesKicker')) ?></p>
                <h2><?= esc(lang('Sigra.sourcesTitle')) ?></h2>
                <p><?= esc(lang('Sigra.sourcesText1')) ?></p>
                <p><?= esc(lang('Sigra.sourcesText2')) ?></p>
                <p class="small"><a href="https://www.seismicportal.eu/fdsn-wsevent.html" target="_blank" rel="noopener">EMSC</a> · <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>.
                    <a href="https://www.geonet.org.nz/policy" target="_blank" rel="noopener">GeoNet · Earth Sciences NZ · NHC · LINZ · NEMA · MBIE</a> · <a href="https://creativecommons.org/licenses/by/3.0/nz/" target="_blank" rel="noopener">CC BY 3.0 NZ</a>.
                    <a href="https://geofon.gfz.de/eqinfo/faq/" target="_blank" rel="noopener">GEOFON · GFZ · GEVN</a> · CC BY 4.0.
                    <a href="https://data.ingv.it/docs/implementation/" target="_blank" rel="noopener">INGV</a>.</p>
            </div>
            <dl class="about-facts">
                <div><dt><?= esc(lang('Sigra.factsSources')) ?></dt><dd><?= esc(lang('Sigra.factsSourcesValue')) ?></dd></div>
                <div><dt><?= esc(lang('Sigra.factsUpdate')) ?></dt><dd><?= lang('Sigra.factsUpdateValue') ?></dd></div>
                <div><dt><?= esc(lang('Sigra.factsMap')) ?></dt><dd><?= esc(lang('Sigra.factsMapValue')) ?></dd></div>
                <div><dt><?= esc(lang('Sigra.factsVersion')) ?></dt><dd>v<?= esc($sigra->version) ?> · <?= esc(lang('Sigra.releaseName')) ?></dd></div>
                <div><dt><?= esc(lang('Sigra.factsBuild')) ?></dt><dd><?= esc($sigra->build) ?> · <?= esc($channelLabel) ?></dd></div>
                <div><dt><?= esc(lang('Sigra.factsLicense')) ?></dt><dd><?= esc($sigra->license) ?></dd></div>
            </dl>
        </section>

        <section class="about-section" id="sources-health" aria-labelledby="sources-health-title">
            <div class="about-version-head">
                <div>
                    <h2 id="sources-health-title"><?= esc(lang('Sigra.providerTitle')) ?></h2>
                    <p><?= esc(lang('Sigra.providerIntro')) ?></p>
                </div>
                <a class="about-version-pill" href="<?= site_url('acerca-de') ?>#sources-health"><?= esc(lang('Sigra.providerRefresh')) ?></a>
            </div>
            <p class="small"><?= esc(lang('Sigra.providerTimezone')) ?></p>
            <div class="provider-grid">
                <?php foreach ($providerHealth as $provider): ?>
                <article class="provider-card">
                    <header>
                        <h3><?= esc($provider['name']) ?></h3>
                        <span class="provider-badge <?= esc($provider['state'], 'attr') ?>"><?= esc(lang('Sigra.providerState_' . $provider['state'])) ?></span>
                    </header>
                    <p class="provider-host"><?= esc($provider['host']) ?></p>
                    <p class="provider-result">
                        <?php if ($provider['http'] !== null): ?><strong>HTTP <?= esc((string) $provider['http']) ?></strong> · <?php endif; ?>
                        <?= esc(lang($provider['outcome'] !== null ? 'Sigra.providerOutcome_' . $provider['outcome'] : 'Sigra.providerNoResult')) ?>
                        <?php if (in_array($provider['http'], [403, 429], true)): ?><br><?= esc(lang('Sigra.providerHttp' . $provider['http'])) ?><?php endif; ?>
                    </p>
                    <dl>
                    <?php foreach (['last_attempt' => 'providerLastAttempt', 'last_check' => 'providerLastCheck', 'last_success' => 'providerLastSuccess', 'next_check' => 'providerNextCheck'] as $field => $label): ?>
                        <div>
                            <dt><?= esc(lang('Sigra.' . $label)) ?></dt>
                            <dd>
                                <?php if ($provider[$field] !== null): ?>
                                    <time datetime="<?= esc(gmdate('c', $provider[$field]), 'attr') ?>"><?= esc(gmdate('d/m/Y H:i:s', $provider[$field])) ?> UTC</time>
                                    <?php if ($field === 'next_check' && $provider['retry_due'] && $provider['state'] !== 'disabled'): ?><br><?= esc(lang('Sigra.providerDue')) ?><?php endif; ?>
                                <?php else: ?><?= esc(lang('Sigra.providerUnknownDate')) ?><?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                    </dl>
                </article>
                <?php endforeach; ?>
            </div>
            <p class="small mt-3"><?= esc(lang('Sigra.providerLegacy')) ?></p>
        </section>

        <section class="about-section" id="version">
            <div class="about-version-head">
                <div>
                    <p class="about-kicker"><?= esc(lang('Sigra.versionKicker')) ?></p>
                    <h2>v<?= esc($sigra->version) ?> · <?= esc(lang('Sigra.releaseName')) ?></h2>
                    <p><?= esc(lang('Sigra.versionPublished', [date('d/m/Y', strtotime($sigra->releaseDate))])) ?></p>
                </div>
                <span class="about-version-pill"><?= esc($channelLabel) ?></span>
            </div>
            <div class="about-version-grid">
                <div><span><?= esc(lang('Sigra.versionWeb')) ?></span><strong>v<?= esc($sigra->version) ?></strong></div>
                <div><span><?= esc(lang('Sigra.versionBuild')) ?></span><strong><?= esc($sigra->build) ?></strong></div>
                <div><span><?= esc(lang('Sigra.versionChannel')) ?></span><strong><?= esc($channelLabel) ?></strong></div>
                <div><span><?= esc(lang('Sigra.versionApi')) ?></span><strong><?= esc($sigra->apiVersion) ?></strong></div>
                <div><span><?= esc(lang('Sigra.versionAndroid')) ?></span><strong>v<?= esc($sigra->mobileVersion) ?></strong></div>
                <div><span><?= esc(lang('Sigra.versionLicense')) ?></span><strong><?= esc($sigra->license) ?></strong></div>
            </div>
            <div class="about-status" id="about-status">
                <span class="about-status-dot" id="about-status-dot"></span>
                <span id="about-status-text"><?= esc(lang('Sigra.statusChecking')) ?></span>
            </div>
        </section>

        <section class="about-section" id="changelog">
            <p class="about-kicker"><?= esc(lang('Sigra.changelogKicker')) ?></p>
            <h2><?= esc(lang('Sigra.changelogTitle')) ?></h2>
            <p><?= esc(lang('Sigra.changelogText')) ?></p>
            <div class="about-changelog">
                <?php foreach ($sigra->changelog as $version => $release):
                    $release = array_replace($release, $releaseTexts[$version] ?? []);
                ?>
                    <article class="about-release">
                        <header>
                            <strong>v<?= esc($version) ?></strong>
                            <span><?= esc($release['name']) ?> · <?= esc(date('d/m/Y', strtotime($release['date']))) ?></span>
                        </header>
                        <p><?= esc($release['summary']) ?></p>
                        <ul>
                            <?php foreach ($release['changes'] as $change): ?>
                                <li><?= esc($change) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="about-section about-section-split">
            <div>
                <p class="about-kicker"><?= esc(lang('Sigra.transparencyKicker')) ?></p>
                <h2><?= esc(lang('Sigra.transparencyTitle')) ?></h2>
                <p><?= esc(lang('Sigra.transparencyText')) ?></p>
                <div class="about-feature-grid">
                    <div class="about-feature"><strong><?= esc(lang('Sigra.healthTitle')) ?></strong><span><a href="<?= site_url('api/health') ?>">/api/health</a> <?= esc(lang('Sigra.healthText')) ?></span></div>
                    <div class="about-feature"><strong><?= esc(lang('Sigra.liveTitle')) ?></strong><span><a href="<?= site_url('red') ?>">/red</a> <?= esc(lang('Sigra.liveText')) ?></span></div>
                    <div class="about-feature"><strong><?= esc(lang('Sigra.apiTitle')) ?></strong><span><a href="<?= site_url('api/red/config') ?>">/api/red/config</a> <?= esc(lang('Sigra.apiText')) ?></span></div>
                </div>
            </div>
            <dl class="about-facts">
                <div><dt><?= esc(lang('Sigra.factAuthor')) ?></dt><dd><?= esc($sigra->author) ?></dd></div>
                <div><dt><?= esc(lang('Sigra.factProject')) ?></dt><dd><?= esc($sigra->project) ?> · <?= esc(lang('Sigra.tagline')) ?></dd></div>
                <div><dt><?= esc(lang('Sigra.factApiCompat')) ?></dt><dd><?= esc($sigra->apiVersion) ?></dd></div>
                <div><dt><?= esc(lang('Sigra.factMobile')) ?></dt><dd>Android v<?= esc($sigra->mobileVersion) ?></dd></div>
            </dl>
        </section>
    </div>

    <footer class="about-contact">
        <strong>SIGRA v<?= esc($sigra->version) ?></strong>
        <span><?= esc(lang('Sigra.versionBuild')) ?> <?= esc($sigra->build) ?> · <?= esc($channelLabel) ?> · <?= esc(lang('Sigra.aboutFooter')) ?></span>
        <a href="<?= site_url('sismos') ?>"><?= esc(lang('Sigra.backToMap')) ?></a>
    </footer>
</article>

<script>
const ABOUT_I18N = <?= json_encode([
    'online'   => lang('Sigra.statusOnline'),
    'degraded' => lang('Sigra.statusDegraded'),
    'error'    => lang('Sigra.statusError'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

document.addEventListener('DOMContentLoaded', function () {
    var punto = document.getElementById('about-status-dot');
    var texto = document.getElementById('about-status-text');
    if (!punto || !texto) return;

    fetch(<?= json_encode(site_url('api/health'), JSON_UNESCAPED_SLASHES) ?>, {
        headers: { Accept: 'application/json' },
        cache: 'no-store'
    })
        .then(function (respuesta) { return respuesta.json(); })
        .then(function (datos) {
            var ok = datos.status === 'ok' && datos.database === 'ok';
            punto.className = 'about-status-dot ' + (ok ? 'ok' : 'error');
            texto.textContent = ok
                ? ABOUT_I18N.online
                    .replace('{0}', datos.version || <?= json_encode($sigra->version) ?>)
                    .replace('{1}', datos.environment || 'production')
                : ABOUT_I18N.degraded;
        })
        .catch(function () {
            punto.className = 'about-status-dot error';
            texto.textContent = ABOUT_I18N.error;
        });
});
</script>

<?php
$contenido = ob_get_clean();
echo view('layouts/main', ['contenido' => $contenido, 'titulo' => lang('Sigra.aboutTitle')]);
