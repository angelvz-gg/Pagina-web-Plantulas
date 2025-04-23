<?php
include '../db.php';
session_start();

if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 7) {
  header("Location: ../login.php");
  exit();
}

$query = "
  SELECT 
    d.ID_Dilucion,
    m.Codigo_Medio,
    d.Fecha_Preparacion,
    d.Cantidad_MedioMadre,
    d.Volumen_Final,
    d.Tuppers_Llenos,
    o.Nombre,
    m.Cantidad_Disponible,
    (d.Cantidad_MedioMadre + m.Cantidad_Disponible) AS Cantidad_Original
  FROM dilucion_llenado_tuppers d
  JOIN medios_nutritivos_madre m ON d.ID_MedioNM = m.ID_MedioNM
  JOIN operadores o ON d.Operador_Responsable = o.ID_Operador
  ORDER BY m.Codigo_Medio, d.Fecha_Preparacion DESC
";

$resultado = $conn->query($query);

$colorMap = [];
$colorIndex = 1;
$historial = [];

while ($row = $resultado->fetch_assoc()) {
  $codigo = $row['Codigo_Medio'];
  if (!isset($colorMap[$codigo])) {
    $colorMap[$codigo] = $colorIndex;
    $colorIndex++;
    if ($colorIndex > 6) $colorIndex = 1;
  }
  $row['color_class'] = 'color-medio-' . $colorMap[$codigo];
  $historial[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Historial de Homogenizaciones</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../style.css?v=<?=time();?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .color-medio-1 { background-color: #b3e5fc !important; }
    .color-medio-2 { background-color: #a5d6a7 !important; }
    .color-medio-3 { background-color: #ffe082 !important; }
    .color-medio-4 { background-color: #f8bbd0 !important; }
    .color-medio-5 { background-color: #d1c4e9 !important; }
    .color-medio-6 { background-color: #ce93d8 !important; }
  </style>
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
      </a>
      <div>
        <h2>Historial de Homogenizaciones</h2>
        <p>Consulta las diluciones y tuppers llenados según el medio nutritivo utilizado.</p>
      </div>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_rpmc.php'">🔄 Regresar</button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main class="container py-4">
    <h4 class="mb-4 text-center">📋 Registro de Diluciones y Tuppers Llenados</h4>

    <!-- Leyenda de colores -->
    <div class="mb-4">
      <strong>Leyenda de Medios Nutritivos:</strong>
      <div class="d-flex flex-wrap gap-3 mt-2">
        <?php foreach ($colorMap as $codigo => $colorNum): ?>
          <div class="d-flex align-items-center gap-2">
            <div style="width: 20px; height: 20px; border-radius: 4px;" class="color-medio-<?= $colorNum ?>"></div>
            <span><?= htmlspecialchars($codigo) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Tabla -->
    <div class="table-responsive">
      <table class="table table-bordered table-striped" id="tabla-historial">
        <thead>
          <tr>
            <th>ID Dilución</th>
            <th>Código del Medio</th>
            <th>Fecha</th>
            <th>Litros Usados</th>
            <th>Volumen Final</th>
            <th>Tuppers Llenados</th>
            <th>Cantidad Original</th>
            <th>Cantidad Disponible</th>
            <th>Responsable</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($historial as $row): ?>
          <tr>
            <td class="<?= $row['color_class'] ?>"><?= $row['ID_Dilucion'] ?></td>
            <td class="<?= $row['color_class'] ?>"><?= htmlspecialchars($row['Codigo_Medio']) ?></td>
            <td class="<?= $row['color_class'] ?>"><?= $row['Fecha_Preparacion'] ?></td>
            <td class="<?= $row['color_class'] ?>"><?= number_format($row['Cantidad_MedioMadre'], 2) ?> L</td>
            <td class="<?= $row['color_class'] ?>"><?= number_format($row['Volumen_Final'], 2) ?> L</td>
            <td class="<?= $row['color_class'] ?>"><?= $row['Tuppers_Llenos'] ?></td>
            <td class="<?= $row['color_class'] ?>"><?= number_format($row['Cantidad_Original'], 2) ?> L</td>
            <td class="<?= $row['color_class'] ?>"><?= number_format($row['Cantidad_Disponible'], 2) ?> L</td>
            <td class="<?= $row['color_class'] ?>"><?= htmlspecialchars($row['Nombre']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <footer class="text-center p-3">
    &copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
