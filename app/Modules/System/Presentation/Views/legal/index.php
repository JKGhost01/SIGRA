<?php ob_start(); ?>
<article class="about-page legal-page">
    <header class="legal-header">
        <p class="about-kicker"><?= esc($english ? 'Legal information · Web and Android' : 'Información legal · Web y Android') ?></p>
        <h1><?= esc($document['title']) ?></h1>
        <p><?= esc(($english ? 'Notice version: ' : 'Versión del aviso: ') . $legal->policyVersion) ?></p>
    </header>
    <nav class="about-quick-links" aria-label="<?= esc($english ? 'Legal documents' : 'Documentos legales') ?>">
        <?php foreach ($links as $key => $url): ?>
            <a href="<?= esc($url, 'attr') ?>"<?= $key === $kind ? ' aria-current="page"' : '' ?>><?= esc($documents[$key]['title']) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (!$legal->contactReady()): ?>
        <p class="alert alert-warning" role="status"><?= esc($english
            ? 'Controller contact details are pending configuration. This notice cannot be considered complete until the operator supplies them.'
            : 'Falta configurar el canal completo del responsable. Este aviso no se considera completo hasta que el operador publique esos datos.') ?></p>
    <?php endif; ?>
    <?php foreach ($document['sections'] as $index => [$heading, $paragraphs]): ?>
        <section class="about-section" aria-labelledby="legal-section-<?= (int) $index ?>">
            <h2 id="legal-section-<?= (int) $index ?>"><?= esc($heading) ?></h2>
            <?php foreach ($paragraphs as $paragraph): ?><p><?= esc($paragraph) ?></p><?php endforeach; ?>
        </section>
    <?php endforeach; ?>
    <section class="about-section">
        <h2><?= esc($english ? 'Controller and rights requests' : 'Responsable y canal de derechos') ?></h2>
        <dl>
            <dt><?= esc($english ? 'Controller' : 'Responsable') ?></dt><dd><?= esc($legal->responsible) ?></dd>
            <dt><?= esc($english ? 'Address and city' : 'Dirección y domicilio') ?></dt><dd><?= esc(trim($legal->address . ' · ' . $legal->city, ' ·')) ?></dd>
            <dt><?= esc($english ? 'Email' : 'Correo') ?></dt><dd><?= esc($legal->email) ?></dd>
            <dt><?= esc($english ? 'Phone' : 'Teléfono') ?></dt><dd><?= esc($legal->phone) ?></dd>
        </dl>
        <p><?= esc($english ? 'Applicable reference framework: Colombian Law 1581 of 2012 and Decree 1074 of 2015.' : 'Marco de referencia: Ley 1581 de 2012 y Decreto 1074 de 2015 de Colombia.') ?></p>
        <a href="https://cancilleria.gov.co/normograma/compilacion/docs/ley_1581_2012.htm" target="_blank" rel="noopener noreferrer"><?= esc($english ? 'Read the law' : 'Consultar la norma') ?></a>
    </section>
</article>
<?php
$contenido = ob_get_clean();
echo view('layouts/main', ['contenido' => $contenido, 'titulo' => $document['title']]);
