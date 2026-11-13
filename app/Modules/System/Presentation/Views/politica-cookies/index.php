<?php
ob_start();
?>
<article class="about-page legal-page">
    <header class="legal-header">
        <p class="about-kicker"><?= esc(lang('Sigra.legalKicker')) ?></p>
        <h1><?= esc(lang('Sigra.cookiesTitle')) ?></h1>
        <p><?= esc(lang('Sigra.cookiesSubtitle')) ?></p>
    </header>
    <nav class="about-quick-links" aria-label="<?= esc(lang('Sigra.legalNavAria')) ?>">
        <a href="<?= site_url('acerca-de') ?>"><?= esc(lang('Sigra.legalAbout')) ?></a>
        <a href="<?= site_url('politica-privacidad') ?>"><?= esc(lang('Sigra.legalPrivacy')) ?></a>
        <a href="<?= site_url('terminos-uso') ?>"><?= esc(lang('Sigra.legalTerms')) ?></a>
    </nav>
    <section class="about-section">
        <h2><?= esc(lang('Sigra.cookiesTechTitle')) ?></h2>
        <p><?= esc(lang('Sigra.cookiesTechP1')) ?></p>
        <p><?= esc(lang('Sigra.cookiesTechP2')) ?></p>
    </section>
    <section class="about-section">
        <h2><?= esc(lang('Sigra.cookiesNoticeTitle')) ?></h2>
        <p><?= esc(lang('Sigra.cookiesNoticeP1')) ?></p>
    </section>
    <footer class="about-contact">
        <a href="<?= site_url('sismos') ?>"><?= esc(lang('Sigra.legalBack')) ?></a>
    </footer>
</article>
<?php
$contenido = ob_get_clean();
echo view('layouts/main', ['contenido' => $contenido, 'titulo' => lang('Sigra.cookiesTitle')]);
