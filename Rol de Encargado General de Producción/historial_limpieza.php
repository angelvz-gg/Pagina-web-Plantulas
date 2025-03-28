<?php
include '../db.php';
session_start();

// Filtros
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$estadoFiltro = $_GET['estado'] ?? '';

// Consulta con filtros
$sql = "SELECT rl.ID_Limpieza, rl.Fecha, rl.Area, rl.Estado_Limpieza, 
               CONCAT(o.Nombre, ' ', o.Apellido_P, ' ', o.Apellido_M) AS NombreCompleto
        FROM registro_limpieza rl
        JOIN operadores o ON rl.ID_Operador = o.ID_Operador
        WHERE rl.Fecha BETWEEN ? AND ?";
$params = [$fechaDesde, $fechaHasta];
$types = 'ss';

if (!empty($estadoFiltro)) {
    $sql .= " AND rl.Estado_Limpieza = ?";
    $params[] = $estadoFiltro;
    $types .= 's';
}

$sql .= " ORDER BY rl.Fecha DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Anular asignación
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["anular_id"])) {
    $idAnular = $_POST["anular_id"];
    $anularStmt = $conn->prepare("UPDATE registro_limpieza SET Estado_Limpieza = 'Anulado' WHERE ID_Limpieza = ?");
    $anularStmt->bind_param("i", $idAnular);
    $anularStmt->execute();
    header("Location: historial_limpieza.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial de Limpieza</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .anular-btn {
      background-color: transparent !important;
      color: #dc3545 !important;
      border: 1px solid #dc3545 !important;
      padding: 4px 8px;
      font-size: 13px;
      border-radius: 4px;
    }
    .anular-btn:hover {
      background-color: #dc3545 !important;
      color: white !important;
    }
    .text-muted-small {
      font-size: 13px;
      color: #888;
    }
  </style>
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
        </a>
        <div>
          <h2>Historial de Limpieza</h2>
          <p>Consulta de asignaciones realizadas o anuladas.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_egp.php'">🔄 Regresar</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
      <div class="section">
        <h2>🧾 Historial de Asignaciones</h2>

        <div class="text-center mb-3">
          <button class="btn btn-secondary btn-sm" onclick="toggleFiltros()" id="btnFiltros">🔍 Mostrar filtros</button>
        </div>

        <div id="filtros" style="display: none;">
          <form method="GET" class="form-doble-columna">
            <div class="row g-3">
              <div class="col-md-4">
                <label for="fecha_desde">Desde:</label>
                <input type="date" name="fecha_desde" value="<?= $fechaDesde ?>" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="fecha_hasta">Hasta:</label>
                <input type="date" name="fecha_hasta" value="<?= $fechaHasta ?>" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="estado">Estado:</label>
                <select name="estado" class="form-select">
                  <option value="">-- Todos --</option>
                  <option value="Pendiente" <?= $estadoFiltro == 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                  <option value="Realizada" <?= $estadoFiltro == 'Realizada' ? 'selected' : '' ?>>Realizada</option>
                  <option value="Anulado" <?= $estadoFiltro == 'Anulado' ? 'selected' : '' ?>>Anulado</option>
                </select>
              </div>
              <div class="col-12 text-center">
                <button type="submit">🔍 Filtrar</button>
                <a href="historial_limpieza.php" class="btn btn-secondary btn-sm">🧹 Limpiar filtros</a>
              </div>
            </div>
          </form>
          <hr />
        </div>

        <table class="table">
          <thead>
            <tr>
              <th>🆔 ID</th>
              <th>👤 Operador</th>
              <th>📅 Fecha</th>
              <th>🧽 Área</th>
              <th>📌 Estado</th>
              <th>⚙️ Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <?php $esHoy = ($row['Fecha'] === date('Y-m-d')); ?>
                <tr>
                  <td><?= $row['ID_Limpieza'] ?></td>
                  <td><?= htmlspecialchars($row['NombreCompleto']) ?></td>
                  <td><?= $row['Fecha'] ?></td>
                  <td><?= htmlspecialchars($row['Area']) ?></td>
                  <td><?= $row['Estado_Limpieza'] ?></td>
                <td>
                    <?php if ($row['Estado_Limpieza'] !== 'Anulado' && $esHoy): ?>
                        <form method="POST" class="form-inline" onsubmit="return confirm('¿Estás seguro de anular esta asignación?');">
                        <input type="hidden" name="anular_id" value="<?= $row['ID_Limpieza'] ?>">
                        <button type="submit" class="btn-anular">🗑 Anular</button>
                        </form>
                    <?php elseif ($row['Estado_Limpieza'] !== 'Anulado' && !$esHoy): ?>
                        <span class="text-muted-small">Solo hoy</span>
                    <?php else: ?>
                        <span class="text-muted">N/A</span>
                    <?php endif; ?>
                </td>


                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center">No hay asignaciones en el rango seleccionado.</td>
              </tr>
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
      const filtros = document.getElementById("filtros");
      const boton = document.getElementById("btnFiltros");
      const visible = filtros.style.display === "block";
      filtros.style.display = visible ? "none" : "block";
      boton.innerText = visible ? "🔍 Mostrar filtros" : "❌ Ocultar filtros";
    }
  </script>
</body>
</html>
