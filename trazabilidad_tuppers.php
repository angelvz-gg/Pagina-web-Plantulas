<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$sql = "SELECT T.ID_Tupper, T.Etiqueta_Tupper, V.Nombre_Variedad AS Variedad,
               COALESCE(
                   CASE WHEN DH.ID_Tupper IS NOT NULL THEN 'Disección de Hojas' END,
                   CASE WHEN D.ID_Tupper IS NOT NULL THEN 'División de Brotes' END,
                   CASE WHEN S.ID_Tupper IS NOT NULL THEN 'Siembra de Explantes' END,
                   'Sin proceso registrado'
               ) AS Etapa_Actual,
               COALESCE(DH.Fecha_Diseccion, D.Fecha_Division, S.Fecha_Siembra) AS Fecha_Etapa,
               COALESCE(DH.Operador_Responsable, D.Operador_Responsable, S.Operador_Responsable) AS Operador_Responsable
        FROM tuppers T
        LEFT JOIN variedades V ON T.ID_Variedad = V.ID_Variedad
        LEFT JOIN siembra_ecas S ON S.ID_Tupper = T.ID_Tupper
        LEFT JOIN division_ecas D ON D.ID_Tupper = T.ID_Tupper
        LEFT JOIN diseccion_hojas_ecas DH ON DH.ID_Tupper = T.ID_Tupper
        ORDER BY Fecha_Etapa DESC";

$result = $conn->query($sql);
$tuppers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vista de Trazabilidad de Tuppers</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
      <h2>Vista de Trazabilidad de Tuppers</h2>
      <div></div>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_egp.php'">🏠 Volver al inicio</button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main class="container mt-4">
    <table class="table table-striped table-hover">
      <thead class="table-dark">
        <tr>
          <th>ID Tupper</th>
          <th>Etiqueta</th>
          <th>Variedad</th>
          <th>Etapa Actual</th>
          <th>Fecha de Etapa</th>
          <th>Operador Responsable</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tuppers as $t): ?>
          <tr>
            <td><?= $t['ID_Tupper'] ?></td>
            <td><?= $t['Etiqueta_Tupper'] ?></td>
            <td><?= $t['Variedad'] ?></td>
            <td><?= $t['Etapa_Actual'] ?></td>
            <td><?= $t['Fecha_Etapa'] ?? 'Sin fecha' ?></td>
            <td><?= $t['Operador_Responsable'] ?? 'No asignado' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </main>

  <footer class="mt-5">
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>
</body>
</html>
