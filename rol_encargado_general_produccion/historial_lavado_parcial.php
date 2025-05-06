<?php
include '../db.php';
session_start();

$fechaFiltro = $_GET['fecha'] ?? date('Y-m-d');
$operadorFiltro = $_GET['operador'] ?? '';

$sql = "SELECT R.Fecha, R.Hora_Registro, R.Tuppers_Lavados, R.Observaciones,
               CONCAT(O.Nombre, ' ', O.Apellido_P, ' ', O.Apellido_M) AS Operador
        FROM reporte_lavado_parcial R
        JOIN operadores O ON R.ID_Operador = O.ID_Operador
        WHERE R.Fecha = ?
";

$params = [$fechaFiltro];
$types = 's';

if (!empty($operadorFiltro)) {
    $sql .= " AND R.ID_Operador = ?";
    $params[] = $operadorFiltro;
    $types .= 'i';
}

$sql .= " ORDER BY R.Hora_Registro DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$operadores = $conn->query("SELECT ID_Operador, CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) AS NombreCompleto FROM operadores WHERE Activo = 1 ORDER BY Nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial de Lavado Parcial</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
        </a>
        <div>
          <h2>Historial de Lavado Parcial</h2>
          <p>Consulta los avances registrados durante media jornada.</p>
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
        <h2>📋 Reportes Registrados</h2>

        <!-- Filtros -->
        <form method="GET" class="form-doble-columna mb-4">
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label for="fecha">Fecha:</label>
              <input type="date" id="fecha" name="fecha" class="form-control" value="<?= $fechaFiltro ?>">
            </div>
            <div class="col-md-4">
              <label for="operador">Operador:</label>
              <select id="operador" name="operador" class="form-select">
                <option value="">-- Todos --</option>
                <?php while ($op = $operadores->fetch_assoc()): ?>
                  <option value="<?= $op['ID_Operador'] ?>" <?= ($op['ID_Operador'] == $operadorFiltro) ? 'selected' : '' ?>>
                    <?= $op['NombreCompleto'] ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-5 d-flex gap-2">
              <button type="submit">🔍 Filtrar</button>
              <a href="historial_lavado_parcial.php" class="btn btn-secondary">🧹 Limpiar Filtros</a>
            </div>
          </div>
        </form>

        <!-- Tabla de resultados -->
        <table class="table">
          <thead>
            <tr>
              <th>📅 Fecha</th>
              <th>⏰ Hora</th>
              <th>👤 Operador</th>
              <th>🧴 Tuppers Lavados</th>
              <th>🗒 Observaciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['Fecha']) ?></td>
                  <td><?= date('H:i', strtotime($row['Hora_Registro'])) ?></td>
                  <td><?= htmlspecialchars($row['Operador']) ?></td>
                  <td><?= (int)$row['Tuppers_Lavados'] ?></td>
                  <td><?= htmlspecialchars($row['Observaciones'] ?? '-') ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5">No hay registros disponibles.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
