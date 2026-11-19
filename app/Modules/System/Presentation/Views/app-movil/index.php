<?php ob_start(); ?>
<style>
.mobile-page { max-width: 1120px; margin: 0 auto; padding: 2rem 1rem 4rem; color: var(--text); }
.mobile-hero { display: grid; grid-template-columns: 1.2fr 1fr; gap: 3rem; align-items: center; padding: 3rem 0; }
.mobile-eyebrow { color: var(--teal); font-size: .76rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.mobile-hero h1 { font-family: 'Space Grotesk', sans-serif; font-size: clamp(2.6rem, 6vw, 4.5rem); letter-spacing: -.05em; line-height: 1.05; margin: 1rem 0; }
.mobile-lead { color: var(--muted); line-height: 1.8; max-width: 40rem; }
.mobile-tags, .mobile-actions { display: flex; gap: .65rem; flex-wrap: wrap; margin-top: 1.25rem; }
.mobile-tag { padding: .3rem .7rem; border: 1px solid var(--line); border-radius: 999px; font-size: .78rem; }
.mobile-action { display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--line); border-radius: 10px; padding: .8rem 1.1rem; color: var(--text); font-weight: 700; text-decoration: none; }
.mobile-action.primary { background: var(--teal); border-color: var(--teal); color: white; }
.mobile-action:hover { opacity: .85; }
.mobile-preview { padding: 2rem; border: 1px solid var(--line); border-radius: 24px; background: radial-gradient(ellipse at top right, rgba(8,127,120,.18), transparent 75%); }
.mobile-preview header { display: flex; justify-content: space-between; align-items: center; }
.mobile-preview h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.15rem; margin: 0; }
.mobile-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 2rem 0; }
.mobile-stats strong { display: block; font-family: 'Space Grotesk', sans-serif; font-size: 2.5rem; }
.mobile-stats span, .mobile-note { color: var(--muted); font-size: .8rem; line-height: 1.6; }
.mobile-preview a { color: var(--teal); }
.mobile-section { padding: 2rem 0; border-top: 1px solid var(--line); }
.mobile-section h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.7rem; }
.mobile-features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1.5rem; }
.mobile-feature, .mobile-download { padding: 1.5rem; border: 1px solid var(--line); border-radius: 16px; background: var(--surface); }
.mobile-feature h3 { font-size: 1rem; font-weight: 700; }
.mobile-feature p { color: var(--muted); font-size: .9rem; line-height: 1.7; margin-bottom: 0; }
.mobile-download { margin-top: 1.5rem; }
.mobile-download p { max-width: 48rem; color: var(--muted); line-height: 1.7; }
.mobile-download .mobile-status { color: var(--teal); font-size: .8rem; font-weight: 700; }
.mobile-steps { padding-left: 1.25rem; color: var(--muted); line-height: 1.8; }
.mobile-steps li { padding: .3rem 0; }
@media (max-width: 700px) { .mobile-hero { grid-template-columns: 1fr; gap: 1.5rem; padding: 1.5rem 0 2rem; } .mobile-features { grid-template-columns: 1fr; } .mobile-preview { padding: 1.5rem; } }
</style>
<article class="mobile-page">
    <section class="mobile-hero">
        <div>
            <p class="mobile-eyebrow">SIGRA / ANDROID</p>
            <h1><?= esc(lang('Sigra.mobileTitle')) ?></h1>
            <p class="mobile-lead"><?= esc(lang('Sigra.mobileLead')) ?></p>
            <div class="mobile-tags">
                <span class="mobile-tag">Android 8.0+</span>
                <span class="mobile-tag">v<?= esc($mobile['version']) ?></span>
                <span class="mobile-tag"><?= esc(lang('Sigra.mobileExperimental')) ?></span>
            </div>
            <div class="mobile-actions">
                <a class="mobile-action primary" href="#descarga"><?= esc(lang($mobile['apk'] !== null ? 'Sigra.mobileDownload' : 'Sigra.mobileAvailability')) ?></a>
                <a class="mobile-action" href="<?= esc($mobile['repository'], 'attr') ?>" target="_blank" rel="noopener"><?= esc(lang('Sigra.mobileRepository')) ?></a>
            </div>
        </div>
        <aside class="mobile-preview" aria-labelledby="mobile-network-title">
            <header><h2 id="mobile-network-title"><?= esc(lang('Sigra.mobileCommunity')) ?></h2><span class="mobile-tag"><?= esc(lang('Sigra.mobileNetwork')) ?></span></header>
            <div class="mobile-stats" aria-live="polite">
                <div><strong data-sigra-registered>—</strong><span><?= esc(lang('Sigra.mobileRegistered')) ?></span></div>
                <div><strong data-sigra-online>—</strong><span><?= esc(lang('Sigra.mobileOnline')) ?></span></div>
            </div>
            <p class="mobile-note"><?= esc(lang('Sigra.mobileCountNote')) ?></p>
            <a href="<?= site_url('red') ?>"><?= esc(lang('Sigra.mobileViewNetwork')) ?> &rarr;</a>
        </aside>
    </section>
    <section class="mobile-section">
        <p class="mobile-eyebrow"><?= esc(lang('Sigra.mobileFeaturesKicker')) ?></p>
        <h2><?= esc(lang('Sigra.mobileFeaturesTitle')) ?></h2>
        <div class="mobile-features">
            <?php foreach (['Map', 'Alerts', 'Sensors'] as $feature): ?>
            <div class="mobile-feature"><h3><?= esc(lang('Sigra.mobileFeature' . $feature)) ?></h3><p><?= esc(lang('Sigra.mobileFeature' . $feature . 'Text')) ?></p></div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="mobile-section" id="descarga" aria-labelledby="mobile-download-title">
        <p class="mobile-eyebrow">ANDROID / APK</p>
        <h2 id="mobile-download-title"><?= esc(lang('Sigra.mobileDownloadTitle')) ?></h2>
        <div class="mobile-download">
            <p class="mobile-status"><?= esc(lang($mobile['apk'] !== null ? 'Sigra.mobileReady' : 'Sigra.mobilePending')) ?></p>
            <h3>SIGRA Mobile <span>v<?= esc($mobile['version']) ?></span></h3>
            <p><?= esc(lang($mobile['published'] ? 'Sigra.mobilePublishedVersion' : 'Sigra.mobileSourceVersion')) ?></p>
            <?php if ($mobile['apk'] !== null): ?>
                <a class="mobile-action primary" href="<?= esc($mobile['apk'], 'attr') ?>"><?= esc(lang('Sigra.mobileDownload')) ?></a>
                <ol class="mobile-steps mt-3">
                    <li><?= esc(lang('Sigra.mobileStepDownload')) ?></li>
                    <li><?= esc(lang('Sigra.mobileStepInstall')) ?></li>
                    <li><?= esc(lang('Sigra.mobileStepPermissions')) ?></li>
                </ol>
            <?php else: ?>
                <p><?= esc(lang('Sigra.mobilePendingText')) ?></p>
            <?php endif; ?>
            <div class="mobile-actions"><a class="mobile-action" href="<?= esc($mobile['release'], 'attr') ?>" target="_blank" rel="noopener"><?= esc(lang('Sigra.mobileReleases')) ?></a></div>
            <?php if ($mobile['stale']): ?><p class="mobile-note mt-3"><?= esc(lang('Sigra.mobileStale')) ?></p><?php endif; ?>
        </div>
        <p class="mobile-note mt-3"><?= esc(lang('Sigra.mobileDisclaimer')) ?></p>
    </section>
</article>
<?php
$contenido = ob_get_clean();
echo view('layouts/main', ['contenido' => $contenido, 'titulo' => lang('Sigra.navLive')]);
