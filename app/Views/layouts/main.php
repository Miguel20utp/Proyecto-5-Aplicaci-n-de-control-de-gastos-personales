<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Control personal de gastos">
  <title><?= htmlspecialchars(($title ?? 'Inicio') . ' | ' . (getenv('APP_NAME') ?: 'Control de Gastos'), ENT_QUOTES, 'UTF-8') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark site-nav"><div class="container"><a class="navbar-brand fw-semibold" href="/">Balance</a><div class="d-flex flex-wrap align-items-center gap-3"><span class="navbar-text text-white-50">Control de gastos personales</span><?php if (!empty($_SESSION['user']['id'])): ?><a class="text-white text-decoration-none" href="/">Dashboard</a><a class="text-white text-decoration-none" href="/movimientos">Movimientos</a><a class="text-white text-decoration-none" href="/categorias">Categorías</a><a class="text-white text-decoration-none" href="/reportes">Reportes</a><span class="text-white"><?= htmlspecialchars($_SESSION['user']['nombre'], ENT_QUOTES, 'UTF-8') ?></span><form method="post" action="/logout" class="m-0"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Services\Session::csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-outline-light btn-sm" type="submit">Cerrar sesión</button></form><?php else: ?><a class="btn btn-outline-light btn-sm" href="/login">Iniciar sesión</a><?php endif; ?></div></div></nav>
<main class="container py-5"><?= $content ?></main>
<footer class="container py-4 text-center text-secondary small">Una forma más clara de cuidar tu dinero.</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
