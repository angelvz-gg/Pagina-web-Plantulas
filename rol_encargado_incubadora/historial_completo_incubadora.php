<?php
include '../db.php';
session_start();

// 1) Verificar sesión y rol
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 9) {
    header('Location: ../login.php');
    exit();
}

// 2) Capturar filtros
$filter_fecha = $_GET['fecha'] ?? '';
$filter_tipo  = $_GET['tipo']  ?? 'all';

$where = [];
if ($filter_fecha) {
    $where[] = "r.fecha = '" . $conn->real_escape_string($filter_fecha) . "'";
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 3) Consulta
$sql = "
    SELECT
      r.fecha,
      r.turno,
      r.temperatura_inferior,
      r.temperatura_media,
      r.temperatura_superior,
      r.humedad_superior,
      r.humedad_inferior,
      r.fecha_hora_registro,
      CONCAT(o.Nombre, ' ', o.Apellido_P, ' ', o.Apellido_M) AS operador
    FROM registro_parametros_incubadora r
    JOIN operadores o ON r.id_operador = o.ID_Operador
    $where_sql
    ORDER BY r.fecha_hora_registro DESC
";
$result = $conn->query($sql);

// 4) Colores por fecha
$colors = ['#f1f8e9','#e1f5fe','#fff3e0','#f3e5f5','#e8f5e9','#e3f2fd'];
$date_colors = []; $idx = 0;
foreach ($result as $row) {
    $f = $row['fecha'];
    if (!isset($date_colors[$f])) {
        $date_colors[$f] = $colors[$idx++ % count($colors)];
    }
}
// volver a ejecutar
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Historial Completo - Incubadora</title>
  <link rel="stylesheet" href="../style.css?v=<?=time();?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"/>
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado d-flex align-items-center">
      <a class="navbar-brand me-3" href="dashboard_eism.php">
        <img src="../logoplantulas.png" width="130" height="124" alt="Logo"/>
      </a>
      <div>
        <h2>Historial Completo de Parámetros</h2>
        <p>Filtra antes de ver los datos</p>
      </div>
    </div>

    <div class="barra-navegacion">
      <!-- 1) Nav de Volver -->
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="location.href='dashboard_eism.php'">🔙 Volver</button>
          </div>
        </div>
      </nav>

      <!-- 2) Nav de Filtros (mismo patrón que exportar_reportes.php) -->
      <nav class="filter-toolbar d-flex align-items-center gap-2 px-3 py-2">
        <select
          name="tipo"
          form="filtrarForm"
          class="form-select form-select-sm"
          style="min-width:160px;"
        >
          <option value="all" <?= $filter_tipo==='all'         ? 'selected':'' ?>>— Todos —</option>
          <option value="temperaturas" <?= $filter_tipo==='temperaturas' ? 'selected':'' ?>>Temperaturas</option>
          <option value="humedades"    <?= $filter_tipo==='humedades'    ? 'selected':'' ?>>Humedades</option>
        </select>

        <input
          type="date"
          name="fecha"
          form="filtrarForm"
          class="form-control form-control-sm"
          style="max-width:140px;"
          value="<?= htmlspecialchars($filter_fecha) ?>"
        />

        <button
          type="submit"
          form="filtrarForm"
          class="btn btn-success btn-sm"
        >Filtrar</button>
      </nav>
    </div>
  </header>

  <!-- Form oculto para los filtros -->
  <form id="filtrarForm" method="GET" class="d-none"></form>

  <main class="container-fluid mt-4 flex-fill">
    <div class="table-responsive w-100">
      <table class="table table-striped align-middle w-100">
        <thead class="table-light">
          <tr>
            <th>Fecha</th><th>Turno</th>
            <?php if ($filter_tipo==='all' || $filter_tipo==='temperaturas'): ?>
              <th>Inf. (°C)</th><th>Med. (°C)</th><th>Sup. (°C)</th>
            <?php endif; ?>
            <?php if ($filter_tipo==='all' || $filter_tipo==='humedades'): ?>
              <th>Hum. Sup. (%)</th><th>Hum. Inf. (%)</th>
            <?php endif; ?>
            <th>Operador</th><th>Registrado a las</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($r = $result->fetch_assoc()): ?>
          <tr style="background-color: <?= $date_colors[$r['fecha']] ?>;">
            <td><?= htmlspecialchars($r['fecha']) ?></td>
            <td><?= htmlspecialchars($r['turno']) ?></td>
            <?php if ($filter_tipo==='all' || $filter_tipo==='temperaturas'): ?>
              <td style="background-color: rgba(255,0,0,0.1);"><?= htmlspecialchars($r['temperatura_inferior']) ?></td>
              <td style="background-color: rgba(255,0,0,0.1);"><?= htmlspecialchars($r['temperatura_media']) ?></td>
              <td style="background-color: rgba(255,0,0,0.1);"><?= htmlspecialchars($r['temperatura_superior']) ?></td>
            <?php endif; ?>
            <?php if ($filter_tipo==='all' || $filter_tipo==='humedades'): ?>
              <td style="background-color: rgba(0,0,255,0.1);"><?= htmlspecialchars($r['humedad_superior']) ?></td>
              <td style="background-color: rgba(0,0,255,0.1);"><?= htmlspecialchars($r['humedad_inferior']) ?></td>
            <?php endif; ?>
            <td><?= htmlspecialchars($r['operador']) ?></td>
            <td><?= htmlspecialchars($r['fecha_hora_registro']) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </main>

  <footer class="text-center py-3">
    &copy; 2025 PLANTAS AGRODEX
  </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
