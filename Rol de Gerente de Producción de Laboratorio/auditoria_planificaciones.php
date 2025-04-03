<?php
session_start();
require '../db.php';

if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
  header("Location: ../login.php");
  exit();
}

// Consulta los cambios de estado en planificaciones desde la auditoría
$query = "
SELECT 
  a.Fecha_Cambio,
  a.Valor_Anterior,
  a.Valor_Nuevo,
  o.Nombre AS Usuario,
  v.Nombre_Variedad,
  v.Especie,
  p.Fecha_Planificacion
FROM auditoria_laboratorio a
JOIN Operadores o ON a.Operador_Responsable = o.ID_Operador
JOIN Planificacion_Produccion p ON a.Valor_Nuevo IN ('Planificada','En proceso','Finalizada','Cancelada') 
JOIN Variedades v ON p.ID_Variedad = v.ID_Variedad
WHERE a.Tabla_Afectada = 'Planificacion_Produccion'
  AND a.Campo_Afectado = 'Estado'
ORDER BY a.Fecha_Cambio DESC
";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Auditoría de Planificaciones</title>
  <link rel="stylesheet" href="../style.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
  <div class="container mt-4">
    <h2 class="text-center mb-4">🕵️ Auditoría de Cambios en Planificaciones</h2>

    <div class="text-end mb-3">
      <button onclick="window.location.href='dashboard_gpl.php'" class="btn btn-secondary">🔙 Volver al Dashboard</button>
    </div>

    <table class="table table-bordered table-striped table-hover">
      <thead class="table-dark">
        <tr>
          <th>Fecha de Cambio</th>
          <th>Usuario</th>
          <th>Especie</th>
          <th>Variedad</th>
          <th>Fecha de Planificación</th>
          <th>Estado Anterior</th>
          <th>Estado Nuevo</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <tr>
            <td><?= $row['Fecha_Cambio'] ?></td>
            <td><?= htmlspecialchars($row['Usuario']) ?></td>
            <td><?= htmlspecialchars($row['Especie']) ?></td>
            <td><?= htmlspecialchars($row['Nombre_Variedad']) ?></td>
            <td><?= $row['Fecha_Planificacion'] ?></td>
            <td><?= $row['Valor_Anterior'] ?></td>
            <td><?= $row['Valor_Nuevo'] ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <footer class="text-center mt-4 mb-3">
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>
</body>
</html>
