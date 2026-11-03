<section class="hero-card p-4 p-md-5">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><span class="badge text-bg-success-subtle mb-2">Tu resumen financiero</span><h1 class="h2 fw-bold mb-1">Dashboard</h1><p class="text-secondary mb-0">Hola, <?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>. Aquí está el estado de tus movimientos.</p></div>
    <a href="/movimientos/nuevo" class="btn btn-success">Registrar movimiento</a>
  </div>
  <?php if ($success): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $message): ?><li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <form method="get" action="/" class="row g-3 align-items-end mb-4">
    <div class="col-sm-6 col-lg-3"><label for="periodo" class="form-label">Período</label><select id="periodo" name="periodo" class="form-select"><option value="todo" <?= $filters['periodo'] === 'todo' ? 'selected' : '' ?>>Todo el historial</option><option value="mes_actual" <?= $filters['periodo'] === 'mes_actual' ? 'selected' : '' ?>>Mes actual</option><option value="mes_anterior" <?= $filters['periodo'] === 'mes_anterior' ? 'selected' : '' ?>>Mes anterior</option><option value="anio_actual" <?= $filters['periodo'] === 'anio_actual' ? 'selected' : '' ?>>Año actual</option><option value="personalizado" <?= $filters['periodo'] === 'personalizado' ? 'selected' : '' ?>>Rango personalizado</option></select></div>
    <div class="col-sm-6 col-lg-3"><label for="desde" class="form-label">Desde</label><input class="form-control" type="date" id="desde" name="desde" value="<?= htmlspecialchars($filters['desde'], ENT_QUOTES, 'UTF-8') ?>"></div>
    <div class="col-sm-6 col-lg-3"><label for="hasta" class="form-label">Hasta</label><input class="form-control" type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($filters['hasta'], ENT_QUOTES, 'UTF-8') ?>"></div>
    <div class="col-sm-6 col-lg-3 d-flex gap-2"><button class="btn btn-outline-success" type="submit">Aplicar</button><a class="btn btn-outline-secondary" href="/">Restablecer</a></div>
  </form>

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><article class="feature-card"><p class="small text-secondary mb-1">Saldo del período</p><p class="h3 fw-bold mb-0 <?= $summary['saldo'] >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format($summary['saldo'], 2, ',', '.') ?></p></article></div>
    <div class="col-6 col-xl-3"><article class="feature-card"><p class="small text-secondary mb-1">Ingresos</p><p class="h3 fw-bold text-success mb-0">$<?= number_format($summary['ingresos'], 2, ',', '.') ?></p></article></div>
    <div class="col-6 col-xl-3"><article class="feature-card"><p class="small text-secondary mb-1">Gastos</p><p class="h3 fw-bold text-danger mb-0">$<?= number_format($summary['gastos'], 2, ',', '.') ?></p></article></div>
    <div class="col-6 col-xl-3"><article class="feature-card"><p class="small text-secondary mb-1">Movimientos</p><p class="h3 fw-bold mb-0"><?= number_format($summary['cantidad'], 0, ',', '.') ?></p></article></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-7"><article class="feature-card"><h2 class="h5 fw-semibold">Ingresos y gastos por mes</h2><div class="chart-wrap"><canvas id="comparisonChart" aria-label="Gráfica de ingresos y gastos por mes"></canvas></div></article></div>
    <div class="col-lg-5"><article class="feature-card"><h2 class="h5 fw-semibold">Gastos por categoría</h2><div class="chart-wrap"><canvas id="categoryChart" aria-label="Distribución de gastos por categoría"></canvas></div><?php if (!$categories): ?><p class="text-secondary small mb-0">No hay gastos en este período.</p><?php endif; ?></article></div>
    <div class="col-12"><article class="feature-card"><h2 class="h5 fw-semibold">Evolución del saldo</h2><div class="chart-wrap chart-wrap-wide"><canvas id="balanceChart" aria-label="Evolución del saldo acumulado"></canvas></div><?php if (!$evolution): ?><p class="text-secondary small mb-0">No hay movimientos para mostrar la evolución.</p><?php endif; ?></article></div>
  </div>

  <article class="feature-card"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-semibold mb-0">Movimientos recientes</h2><a href="/movimientos" class="link-success">Ver todos</a></div>
    <?php if (!$recent): ?><p class="text-secondary mb-0">Todavía no hay movimientos en este período.</p><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Tipo</th><th>Categoría</th><th>Descripción</th><th class="text-end">Valor</th></tr></thead><tbody><?php foreach ($recent as $item): ?><tr><td><?= htmlspecialchars(date('d/m/Y', strtotime($item['fecha'])), ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge <?= $item['tipo'] === 'ingreso' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $item['tipo'] === 'ingreso' ? 'Ingreso' : 'Gasto' ?></span></td><td><?= htmlspecialchars($item['categoria'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($item['descripcion'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td><td class="text-end fw-semibold <?= $item['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' ?>"><?= $item['tipo'] === 'gasto' ? '−' : '+' ?>$<?= number_format((float) $item['monto'], 2, ',', '.') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  </article>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
  if (typeof Chart === 'undefined') return;
  const monthly = <?= json_encode($monthly, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const categories = <?= json_encode($categories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const evolution = <?= json_encode($evolution, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const money = value => '$' + Number(value).toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const base = {responsive: true, maintainAspectRatio: false};
  new Chart(document.getElementById('comparisonChart'), {type: 'bar', data: {labels: monthly.map(x => x.periodo), datasets: [{label: 'Ingresos', data: monthly.map(x => Number(x.ingresos)), backgroundColor: '#198754'}, {label: 'Gastos', data: monthly.map(x => Number(x.gastos)), backgroundColor: '#dc3545'}]}, options: {...base, scales: {y: {beginAtZero: true, ticks: {callback: money}}}}});
  new Chart(document.getElementById('categoryChart'), {type: 'doughnut', data: {labels: categories.map(x => x.categoria), datasets: [{data: categories.map(x => Number(x.total)), backgroundColor: categories.map((x, i) => /^#[0-9a-f]{6}$/i.test(x.color || '') ? x.color : ['#198754','#0d6efd','#fd7e14','#6f42c1','#dc3545','#20c997'][i % 6])}]}, options: {...base, plugins: {legend: {position: 'bottom'}}}});
  new Chart(document.getElementById('balanceChart'), {type: 'line', data: {labels: evolution.map(x => x.fecha), datasets: [{label: 'Saldo acumulado', data: evolution.map(x => Number(x.saldo)), borderColor: '#123c31', backgroundColor: '#19875422', fill: true, tension: .25}]}, options: {...base, scales: {y: {ticks: {callback: money}}}}});
})();
</script>
