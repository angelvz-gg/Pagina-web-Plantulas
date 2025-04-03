<?php
session_start();
require '../db.php';

if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
  header("Location: ../login.php");
  exit();
}

$filtro_fecha = $_GET['fecha'] ?? date('Y-m-d');

$query = "
  SELECT 
    o.Nombre AS Operador,
    v.Nombre_Variedad AS Variedad,
    a.Rol,
    a.Fecha,
    SUM(a.Cantidad_Tuppers) AS Total_Tuppers
  FROM asignacion_lavado a
  JOIN Operadores o ON a.ID_Operador = o.ID_Operador
  JOIN Variedades v ON a.ID_Variedad = v.ID_Variedad
  WHERE a.Fecha = ?
  GROUP BY a.ID_Operador, a.ID_Variedad, a.Rol, a.Fecha
  ORDER BY o.Nombre ASC, a.Fecha DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filtro_fecha);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Rendimiento del Personal</title>
  <link rel="stylesheet" href="../style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-4">
    <h2 class="text-center mb-4">📈 Rendimiento del Personal en Lavado</h2>

    <form method="GET" class="row mb-4">
      <div class="col-md-4">
        <label class="form-label">Seleccionar Fecha:</label>
        <input type="date" name="fecha" value="<?= $filtro_fecha ?>" class="form-control" onchange="this.form.submit()">
      </div>
      <div class="col-md-8 text-end align-self-end">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <button onclick="window.location.href='dashboard_egp.php'" class="btn btn-secondary">🔙 Regresar</button>
      </div>
    </form>

    <table class="table table-bordered table-hover table-striped">
      <thead class="table-dark">
        <tr>
          <th>Fecha</th>
          <th>Operador</th>
          <th>Rol</th>
          <th>Variedad</th>
          <th>Total de Tuppers Lavados</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($resultado->num_rows > 0): ?>
          <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
              <td><?= $fila['Fecha'] ?></td>
              <td><?= htmlspecialchars($fila['Operador']) ?></td>
              <td><?= $fila['Rol'] ?></td>
              <td><?= $fila['Variedad'] ?></td>
              <td><?= $fila['Total_Tuppers'] ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5" class="text-center">No hay registros para esta fecha.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <footer class="text-center mt-4 mb-3">
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
