<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($titulo ?? 'SIGRA') ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
    body { background-color: #f4f6f8; color: #212529; }
    .navbar-brand { font-weight: 600; }
    .navbar-toggler { display: none; }
    .mobile-nav { display: flex; align-items: center; gap: 1rem; }
    #mapa, #mapa-elegir { height: 480px; width: 100%; border-radius: .5rem; }
    #mapa-elegir { height: 420px; }
    .badge-estado-activo { background-color: #dc3545; }
    @media (max-width: 767px) {
        .navbar-toggler { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border: 1px solid rgba(255,255,255,.35); border-radius: 10px; background: rgba(255,255,255,.05); color: #fff; }
        .mobile-nav { width: 100%; }
        .navbar-nav { display: none; }
        .navbar-nav.is-open { display: grid; width: 100%; gap: .5rem; margin-top: .75rem; }
        .navbar-collapse { display: none; }
        .navbar-collapse.is-open { display: block; }
    }
    .badge-estado-revisado { background-color: #198754; }
    .badge-estado-pendiente { background-color: #ffc107; color: #212529; }
    .card { border: none; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
    .icono-emoji { font-size: 22px; line-height: 1; text-align: center; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?= site_url('sismos') ?>">SDRS</a>
        <div class="mobile-nav">
            <button class="navbar-toggler" type="button" aria-label="Toggle navigation" aria-expanded="false">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse" id="mainNavbar">
                <div class="navbar-nav">
                    <a class="nav-link" href="<?= site_url('sismos') ?>">Sismos</a>
                    <a class="nav-link" href="<?= site_url('sismos/create') ?>">Registrar sismo</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<main class="container pb-5">
    <?= $contenido ?? '' ?>
</main>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    (function () {
        const toggle = document.querySelector('.navbar-toggler');
        const menu = document.getElementById('mainNavbar');
        const nav = menu ? menu.querySelector('.navbar-nav') : null;
        if (!toggle || !menu || !nav) return;

        function setMenuState(open) {
            menu.classList.toggle('is-open', open);
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
        }

        setMenuState(false);
        toggle.addEventListener('click', function () {
            const open = !menu.classList.contains('is-open');
            setMenuState(open);
        });
    }());
</script>
</body>
</html>