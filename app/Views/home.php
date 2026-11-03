<section class="hero-card p-4 p-md-5">
  <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <span class="badge text-bg-success-subtle mb-3">Tu espacio financiero</span>
  <h1 class="display-5 fw-bold">Una mirada más clara a tu día a día.</h1>
  <p class="lead text-secondary col-lg-8">Hola, <?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>. Aquí podrás organizar tu experiencia personal de forma sencilla.</p>
  <a href="/categorias" class="btn btn-success mt-2">Gestionar categorías</a>
  <a href="/movimientos" class="btn btn-outline-success mt-2">Ver movimientos</a>
  <div class="row g-3 mt-4">
    <div class="col-md-4"><div class="feature-card"><h2 class="h5">Simple</h2><p class="mb-0 text-secondary">Una interfaz pensada para encontrar lo importante.</p></div></div>
    <div class="col-md-4"><div class="feature-card"><h2 class="h5">Adaptable</h2><p class="mb-0 text-secondary">Diseñada para escritorio, tablet y móvil.</p></div></div>
    <div class="col-md-4"><div class="feature-card"><h2 class="h5">Tuya</h2><p class="mb-0 text-secondary">Una estructura preparada para crecer contigo.</p></div></div>
  </div>
</section>
