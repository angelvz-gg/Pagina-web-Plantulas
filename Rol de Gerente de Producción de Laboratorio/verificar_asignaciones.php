<?php
session_start();
require '../db.php';

if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
  header("Location: ../login.php");
  exit();
}

// Consulta de asignaciones
$query = "
  SELECT 
    a.Fecha,
    o.Nombre AS Operador,
    v.Nombre_Variedad AS Variedad,
    a.Rol,
    a.Cantidad_Tuppers
  FROM asignacion_lavado a
  JOIN Operadores o ON a.ID_Operador = o.ID_Operador
  JOIN Variedades v ON a.ID_Variedad = v.ID_Variedad
  ORDER BY a.Fecha DESC, o.Nombre ASC
";

$resultado = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Verificación de Asignaciones</title>
  <link rel="stylesheet" href="../style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-4">
    <h2 class="text-center mb-4">📋 Verificación de Asignaciones de Lavado</h2>

    <div class="text-end mb-3">
      <button class="btn btn-secondary" onclick="window.location.href='dashboard_gpl.php'">🔙 Volver al Dashboard</button>
    </div>

    <table class="table table-bordered table-hover table-striped">
      <thead class="table-dark">
        <tr>
          <th>Fecha</th>
          <th>Operador</th>
          <th>Variedad</th>
          <th>Rol</th>
          <th>Cantidad de Tuppers</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($fila = mysqli_fetch_assoc($resultado)) : ?>
          <tr>
            <td><?= $fila['Fecha'] ?></td>
            <td><?= htmlspecialchars($fila['Operador']) ?></td>
            <td><?= htmlspecialchars($fila['Variedad']) ?></td>
            <td><?= $fila['Rol'] ?></td>
            <td><?= $fila['Cantidad_Tuppers'] ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <footer class="text-center mt-4 mb-3">
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
