<?php
// 1) Mostrar errores en pantalla (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2) Conexión y sesión
include '../db.php';
session_start();

// Verificar sesión y rol (10 = Encargado de Organización y Limpieza de Incubador)
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
    header('Location: ../login.php');
    exit();
}

// 3) Capturar filtros
$filter_fecha  = $_GET['fecha']  ?? '';
$filter_area   = $_GET['area']   ?? '';
$filter_estado = $_GET['estado'] ?? '';

// 4) Construir consulta con WHERE dinámico
$where = [];
if ($filter_fecha)  $where[] = "LR.Fecha = '" . $conn->real_escape_string($filter_fecha) . "'";
if ($filter_area)   $where[] = "LR.Area  = '" . $conn->real_escape_string($filter_area)   . "'";
if ($filter_estado) $where[] = "LR.Estado_Limpieza = '" . $conn->real_escape_string($filter_estado) . "'";

$sql = "
  SELECT
    LR.ID_Limpieza         AS id,
    CONCAT(O.Nombre,' ',O.Apellido_P,' ',O.Apellido_M) AS operador,
    LR.Fecha               AS fecha,
    TIME(LR.Hora_Registro) AS hora,
    LR.Area                AS area,
    LR.Estado_Limpieza     AS estado
  FROM registro_limpieza LR
  JOIN operadores O ON LR.ID_Operador = O.ID_Operador
";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY LR.Fecha DESC, LR.Hora_Registro DESC";

$result = $conn->query($sql);
if (!$result) {
    die("Error en la consulta: " . $conn->error);
}

// 5) Datos para selects dinámicos
$areasResult   = $conn->query("SELECT DISTINCT Area FROM registro_limpieza ORDER BY Area");
$estadosResult = $conn->query("SELECT DISTINCT Estado_Limpieza FROM registro_limpieza ORDER BY Estado_Limpieza");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Limpieza de Repisas</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand me-3" href="#">
          <img src="../logoplantulas.png" width="130" height="124" alt="Logo">
        </a>
        <div>
          <h2>Historial de Limpieza de Repisas</h2>
          <p class="mb-0">Revisa qué repisas y áreas se han limpiado.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <!-- Nav de Volver -->
        <nav class="navbar bg-body-tertiary mb-2">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="location.href='dashboard_gpl.php'">🔙 Volver al Dashboard</button>
            </div>
          </div>
        </nav>

        <!-- Nav de Filtros -->
        <nav class="filter-toolbar d-flex align-items-center gap-2 px-3 py-2" style="flex-wrap: nowrap; overflow-x: auto;">
          <!-- 1) Fecha -->
          <input type="date"
                 name="fecha"
                 form="filtrosForm"
                 class="form-control form-control-sm"
                 style="width:120px;"
                 value="<?= htmlspecialchars($filter_fecha) ?>"
          />

          <!-- 2) Área -->
          <select name="area"
                  form="filtrosForm"
                  class="form-select form-select-sm"
                  style="width:140px;"
          >
            <option value="">— Todas Áreas —</option>
            <?php while($a = $areasResult->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($a['Area'])?>"
                <?= $filter_area === $a['Area'] ? 'selected':''?>>
                <?= htmlspecialchars($a['Area']) ?>
              </option>
            <?php endwhile; ?>
          </select>

          <!-- 3) Estado -->
          <select name="estado"
                  form="filtrosForm"
                  class="form-select form-select-sm"
                  style="width:140px;"
          >
            <option value="">— Todos Estados —</option>
            <?php while($e = $estadosResult->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($e['Estado_Limpieza'])?>"
                <?= $filter_estado === $e['Estado_Limpieza'] ? 'selected':''?>>
                <?= htmlspecialchars($e['Estado_Limpieza']) ?>
              </option>
            <?php endwhile; ?>
          </select>

          <!-- 4) Botón Filtrar -->
          <button type="submit"
                  form="filtrosForm"
                  class="btn btn-success btn-sm"
          >
            Filtrar
          </button>
        </nav>
      </div>
    </header>

    <!-- Formulario oculto para filtros -->
    <form id="filtrosForm" method="GET" class="d-none"></form>

    <main class="container mt-4">
      <div class="table-responsive mb-4">
        <table class="table table-striped table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Operador</th>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Área</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td data-label="ID"><?= $row['id'] ?></td>
              <td data-label="Operador"><?= htmlspecialchars($row['operador']) ?></td>
              <td data-label="Fecha"><?= htmlspecialchars($row['fecha']) ?></td>
              <td data-label="Hora"><?= htmlspecialchars($row['hora']) ?></td>
              <td data-label="Área"><?= htmlspecialchars($row['area']) ?></td>
              <td data-label="Estado"><?= htmlspecialchars($row['estado']) ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </main>

    <footer class="text-center py-3">&copy; 2025 PLANTAS AGRODEX</footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
