<?php
include '../db.php';
session_start();

// Obtener valores de filtro desde GET
$codigoFiltro = $_GET['codigo_medio'] ?? '';
$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';
$cantidadMinima = $_GET['cantidad_minima'] ?? '';
$estadoFiltro = $_GET['estado'] ?? '';

// Armar la consulta dinámica
$sql = "SELECT Codigo_Medio, Fecha_Preparacion, Cantidad_Preparada, Cantidad_Disponible, Estado 
        FROM medios_nutritivos_madre 
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($codigoFiltro)) {
    $sql .= " AND Codigo_Medio = ?";
    $params[] = $codigoFiltro;
    $types .= "s";
}
if (!empty($fechaDesde)) {
    $sql .= " AND Fecha_Preparacion >= ?";
    $params[] = $fechaDesde;
    $types .= "s";
}
if (!empty($fechaHasta)) {
    $sql .= " AND Fecha_Preparacion <= ?";
    $params[] = $fechaHasta;
    $types .= "s";
}
if (is_numeric($cantidadMinima)) {
    $sql .= " AND Cantidad_Disponible >= ?";
    $params[] = $cantidadMinima;
    $types .= "i";
}
if (!empty($estadoFiltro)) {
    $sql .= " AND Estado = ?";
    $params[] = $estadoFiltro;
    $types .= "s";
}

$sql .= " ORDER BY Fecha_Preparacion DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$codigosQuery = $conn->query("SELECT DISTINCT Codigo_Medio FROM medios_nutritivos_madre ORDER BY Codigo_Medio ASC");
$codigos = $codigosQuery->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inventario de soluciones madre</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
        </a>
        <div>
          <h2>Inventario de soluciones madre</h2>
          <p>Consulta la cantidad restante de cada medio nutritivo madre.</p>
        </div>
      </div>
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_egp.php'">
                🔄 Regresar
              </button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
      <div class="section">
        <h2>📊 Cantidad Disponible de Soluciones Madre</h2>

        <!-- Botón para mostrar/ocultar filtros -->
        <div class="text-center mb-3">
          <button type="button" id="toggleBtn" onclick="toggleFiltros()">🔍 Mostrar filtros</button>
        </div>

        <!-- Filtros colapsables -->
        <div id="filtros-contenedor" style="display: none;">
          <form method="GET" class="form-doble-columna">
            <div class="row g-3">
              <div class="col-md-3">
                <label for="codigo_medio">Código del Medio</label>
                <select name="codigo_medio" id="codigo_medio" class="form-select">
                  <option value="">-- Todos --</option>
                  <?php foreach ($codigos as $codigo): ?>
                    <option value="<?= $codigo['Codigo_Medio'] ?>" <?= ($codigoFiltro == $codigo['Codigo_Medio']) ? 'selected' : '' ?>>
                      <?= $codigo['Codigo_Medio'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-3">
                <label for="fecha_desde">Desde</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="<?= $fechaDesde ?>">
              </div>

              <div class="col-md-3">
                <label for="fecha_hasta">Hasta</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="<?= $fechaHasta ?>">
              </div>

              <div class="col-md-3">
                <label for="cantidad_minima">Cantidad Mínima Disponible (L)</label>
                <input type="number" step="0.1" name="cantidad_minima" id="cantidad_minima" class="form-control" value="<?= $cantidadMinima ?>">
              </div>

              <div class="col-md-3">
                <label for="estado">Estado</label>
                <select name="estado" id="estado" class="form-select">
                  <option value="">-- Todos --</option>
                  <option value="Disponible" <?= ($estadoFiltro == 'Disponible') ? 'selected' : '' ?>>Disponible</option>
                  <option value="Consumido" <?= ($estadoFiltro == 'Consumido') ? 'selected' : '' ?>>Consumido</option>
                </select>
              </div>

              <div class="col-md-12 d-flex justify-content-center mt-3">
                <button type="submit">🔍 Filtrar</button>
              </div>
            </div>
          </form>
          <hr />
        </div>

        <!-- Tabla -->
        <table class="table mt-4">
          <thead>
            <tr>
              <th>Código del Medio</th>
              <th>Fecha de Preparación</th>
              <th>Cantidad Inicial (L)</th>
              <th>Cantidad Usada (L)</th>
              <th>Cantidad Restante (L)</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
             <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                 $preparada = (int)$row['Cantidad_Preparada'];
                  $disponible = is_null($row['Cantidad_Disponible']) ? $preparada : (int)$row['Cantidad_Disponible'];
                 $cantidadUsada = max(0, $preparada - $disponible);
                ?>
           <tr>
            <td><?= htmlspecialchars($row['Codigo_Medio']) ?></td>
            <td><?= htmlspecialchars($row['Fecha_Preparacion']) ?></td>
            <td><?= $preparada ?></td>
            <td>
              <?= $cantidadUsada > 0 ? $cantidadUsada : '<span style="color: gray;">— Aún no se ha usado —</span>' ?>
            </td>
            <td><?= $disponible ?></td>
            <td><?= $row['Estado'] ?></td>
          </tr>
        <?php endwhile; ?>
        <?php else: ?>
                <tr><td colspan="6">No hay registros que coincidan con los filtros.</td></tr>
              <?php endif; ?>
        </tbody>

        </table>
      </div>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script>
    function toggleFiltros() {
      const filtros = document.getElementById("filtros-contenedor");
      const boton = document.getElementById("toggleBtn");
      const visible = filtros.style.display === "block";
      filtros.style.display = visible ? "none" : "block";
      boton.innerHTML = visible ? "🔍 Mostrar filtros" : "❌ Ocultar filtros";
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
          integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
