<?php
// Activar detección de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../db.php';
session_start();

// 1) Verificar sesión iniciada
if (!isset($_SESSION['ID_Operador'])) {
    header('Location: ../login.php');
    exit();
}

// 2) Verificar rol = 9 (Encargado de Incubadora y Suministro de Material)
if ($_SESSION['Rol'] != 9) {
    header('Location: ../login.php');
    exit();
}

// 3) Consulta inventario etapa 3
$sql = "
  SELECT
    E.ID_Enraizamiento          AS id,
    CONCAT(V.Codigo_Variedad, ' – ', V.Nombre_Variedad) AS variedad,
    E.Fecha_Siembra,
    E.Cantidad_Dividida,
    E.Tuppers_Llenos,
    E.Tuppers_Desocupados,
    CONCAT(O.Nombre, ' ', O.Apellido_P, ' ', O.Apellido_M) AS operador,
    E.Estado_Revision
  FROM enraizamiento E
  LEFT JOIN variedades V ON E.ID_Variedad = V.ID_Variedad
  LEFT JOIN operadores O ON E.Operador_Responsable = O.ID_Operador
  ORDER BY E.Fecha_Siembra DESC
";
$result = $conn->query($sql);
if (!$result) {
    die("Error en consulta inventario_etapa3: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inventario Etapa 3</title>
  <link rel="stylesheet" href="../style.css?v=<?=time()?>">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  />
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand me-3" href="dashboard_eism.php">
          <img src="../logoplantulas.png" width="130" height="124" alt="Logo">
        </a>
        <div>
          <h2>Inventario Etapa 3 (Enraizamiento)</h2>
          <p class="mb-0">Stock de brotes enraizados y tuppers disponibles.</p>
        </div>
      </div>
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="location.href='dashboard_eism.php'">🔙 Volver al Dashboard</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="container mt-4">
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Variedad</th>
              <th>Fecha Siembra</th>
              <th>Cantidad Dividida</th>
              <th>Tuppers Llenos</th>
              <th>Tuppers Vacíos</th>
              <th>Operador</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td data-label="ID"><?= htmlspecialchars($row['id']) ?></td>
              <td data-label="Variedad"><?= htmlspecialchars($row['variedad']) ?></td>
              <td data-label="Fecha Siembra"><?= htmlspecialchars($row['Fecha_Siembra']) ?></td>
              <td data-label="Cantidad"><?= htmlspecialchars($row['Cantidad_Dividida']) ?></td>
              <td data-label="Llenos"><?= htmlspecialchars($row['Tuppers_Llenos']) ?></td>
              <td data-label="Vacíos"><?= htmlspecialchars($row['Tuppers_Desocupados']) ?></td>
              <td data-label="Operador"><?= htmlspecialchars($row['operador']) ?></td>
              <td data-label="Estado">
                <?php if ($row['Estado_Revision'] === 'Consolidado'): ?>
                  <span class="badge bg-success">Consolidado</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark"><?= htmlspecialchars($row['Estado_Revision']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </main>

    <footer class="text-center py-3">&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</footer>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
  ></script>
</body>
</html>
