<?php
session_start();
include '../db.php';

if (!isset($_SESSION["ID_Operador"])) {
    header("Location: ../login.php");
    exit();
}

$operatorId = $_SESSION["ID_Operador"];

// Consulta para reportes rechazados de Multiplicación
$sql_multiplicacion = "SELECT M.ID_Multiplicacion, V.Codigo_Variedad, V.Nombre_Variedad, M.Fecha_Siembra, M.Observaciones_Revision
    FROM Multiplicacion M
    LEFT JOIN Variedades V ON M.ID_Variedad = V.ID_Variedad
    WHERE M.Operador_Responsable = ? AND M.Estado_Revision = 'Rechazado'";
$stmt_m = $conn->prepare($sql_multiplicacion);
$stmt_m->bind_param("i", $operatorId);
$stmt_m->execute();
$result_m = $stmt_m->get_result();

// Consulta para reportes rechazados de Enraizamiento
$sql_enraizamiento = "SELECT E.ID_Enraizamiento, V.Codigo_Variedad, V.Nombre_Variedad, E.Fecha_Siembra, E.Observaciones_Revision
    FROM Enraizamiento E
    LEFT JOIN Variedades V ON E.ID_Variedad = V.ID_Variedad
    WHERE E.Operador_Responsable = ? AND E.Estado_Revision = 'Rechazado'";
$stmt_e = $conn->prepare($sql_enraizamiento);
$stmt_e->bind_param("i", $operatorId);
$stmt_e->execute();
$result_e = $stmt_e->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Notificaciones de Rechazo - Operador</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
      </a>
      <h2>Notificaciones de Rechazo</h2>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_cultivo.php'">🏠 Volver al inicio</button>
          </div>
        </div>
      </nav>
    </div>
  </header>
  <main>
    <div class="container mt-4">
      <h3>Tienes notificaciones de rechazo pendientes</h3>
      <?php if($result_m->num_rows == 0 && $result_e->num_rows == 0): ?>
        <div class="alert alert-info">No tienes notificaciones de rechazo.</div>
      <?php else: ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Variedad</th>
              <th>Fecha de Siembra</th>
              <th>Observaciones</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result_m->fetch_assoc()): ?>
            <tr>
              <td>Multiplicación</td>
              <td><?= $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad'] ?></td>
              <td><?= $row['Fecha_Siembra'] ?></td>
              <td><?= $row['Observaciones_Revision'] ?></td>
              <td>
                <a href="corregir_reporte.php?tipo=multiplicacion&id=<?= $row['ID_Multiplicacion'] ?>" class="btn btn-warning btn-sm">Corregir</a>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php while($row = $result_e->fetch_assoc()): ?>
            <tr>
              <td>Enraizamiento</td>
              <td><?= $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad'] ?></td>
              <td><?= $row['Fecha_Siembra'] ?></td>
              <td><?= $row['Observaciones_Revision'] ?></td>
              <td>
                <a href="corregir_reporte.php?tipo=enraizamiento&id=<?= $row['ID_Enraizamiento'] ?>" class="btn btn-warning btn-sm">Corregir</a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>
  <footer class="mt-4">
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
