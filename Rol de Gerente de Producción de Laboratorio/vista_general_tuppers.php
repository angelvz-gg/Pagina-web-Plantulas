<?php
include '../db.php';
session_start();

// Proteger que solo accedan Gerentes de Producción
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
    header("Location: ../login.php");
    exit();
}

// Variables de filtros (si existen)
$filtros = [];
$where = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['estado'])) {
        $estado = $_GET['estado'];
        $where[] = "et.Estado = '$estado'";
    }

    if (!empty($_GET['etapa'])) {
        $etapa = intval($_GET['etapa']);
        $where[] = "l.ID_Etapa = $etapa";
    }

    if (!empty($_GET['variedad'])) {
        $variedad = intval($_GET['variedad']);
        $where[] = "l.ID_Variedad = $variedad";
    }

    if (!empty($_GET['fecha'])) {
        $fecha = $_GET['fecha'];
        $where[] = "l.Fecha = '$fecha'";
    }

    if (!empty($_GET['responsable'])) {
        $responsable = intval($_GET['responsable']);
        $where[] = "l.ID_Operador = $responsable";
    }
}

// Armar consulta
$consulta = "
    SELECT 
        l.ID_Lote,
        v.Codigo_Variedad,
        v.Nombre_Variedad,
        v.Color,
        l.Fecha,
        l.ID_Etapa,
        CONCAT(o.Nombre, ' ', o.Apellido_P, ' ', o.Apellido_M) AS Nombre_Responsable,
        COALESCE(m.Tuppers_Llenos, e.Tuppers_Llenos, 0) AS Tuppers_Existentes,
        COALESCE(et.Estado, 'Sin Registro') AS Estado_Tupper
    FROM lotes l
    LEFT JOIN variedades v ON l.ID_Variedad = v.ID_Variedad
    LEFT JOIN operadores o ON l.ID_Operador = o.ID_Operador
    LEFT JOIN multiplicacion m ON l.ID_Lote = m.ID_Lote
    LEFT JOIN enraizamiento e ON l.ID_Lote = e.ID_Lote
    LEFT JOIN estado_tuppers et ON l.ID_Lote = et.ID_Tupper
";

if (!empty($where)) {
    $consulta .= " WHERE " . implode(' AND ', $where);
}

$consulta .= " ORDER BY l.Fecha DESC";

$resultado = $conn->query($consulta);

// Obtener opciones de variedades y operadores para los filtros
$variedades = $conn->query("SELECT ID_Variedad, Nombre_Variedad FROM variedades ORDER BY Nombre_Variedad ASC");
$operadores = $conn->query("SELECT ID_Operador, CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) AS NombreCompleto FROM operadores WHERE Activo = 1 ORDER BY Nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vista General de Tuppers</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    #filtrosFormulario {
      display: none;
      margin-top: 20px;
    }
  </style>
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
      </a>
      <h2>📋 Vista General de Tuppers</h2>
    </div>

    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_gpl.php'">🔙 Regresar</button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main class="container mt-4">

    <div class="d-flex justify-content-end mb-3">
      <button class="btn btn-primary" onclick="mostrarFiltros()">🔍 Filtrar</button>
    </div>

    <form method="GET" id="filtrosFormulario" class="row g-3">
      <div class="col-md-3">
        <label>Estado:</label>
        <select name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="Bueno">Bueno</option>
          <option value="Infectado">Infectado</option>
          <option value="Desechado">Desechado</option>
        </select>
      </div>

      <div class="col-md-3">
        <label>Etapa:</label>
        <select name="etapa" class="form-select">
          <option value="">Todas</option>
          <option value="2">Multiplicación</option>
          <option value="3">Enraizamiento</option>
        </select>
      </div>

      <div class="col-md-3">
        <label>Variedad:</label>
        <select name="variedad" class="form-select">
          <option value="">Todas</option>
          <?php while ($var = $variedades->fetch_assoc()): ?>
            <option value="<?= $var['ID_Variedad'] ?>"><?= htmlspecialchars($var['Nombre_Variedad']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label>Responsable:</label>
        <select name="responsable" class="form-select">
          <option value="">Todos</option>
          <?php while ($op = $operadores->fetch_assoc()): ?>
            <option value="<?= $op['ID_Operador'] ?>"><?= htmlspecialchars($op['NombreCompleto']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-md-3 mt-3">
        <label>Fecha Ingreso:</label>
        <input type="date" name="fecha" class="form-control">
      </div>

      <div class="col-md-3 mt-4">
        <button type="submit" class="btn btn-success mt-2">Aplicar Filtros</button>
      </div>
    </form>

    <div class="table-responsive mt-4">
      <table class="table table-bordered table-hover text-center">
        <thead class="table-dark">
          <tr>
            <th>ID Lote</th>
            <th>Variedad</th>
            <th>Color</th>
            <th>Fecha Ingreso</th>
            <th>Cantidad Tuppers</th>
            <th>Etapa</th>
            <th>Responsable</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($resultado->num_rows > 0): ?>
            <?php while ($row = $resultado->fetch_assoc()): ?>
              <tr>
                <td><?= $row['ID_Lote'] ?></td>
                <td><?= htmlspecialchars($row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad']) ?></td>
                <td><?= htmlspecialchars($row['Color'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($row['Fecha']) ?></td>
                <td><?= $row['Tuppers_Existentes'] ?></td>
                <td><?= ($row['ID_Etapa'] == 2) ? "Multiplicación" : (($row['ID_Etapa'] == 3) ? "Enraizamiento" : "Otra") ?></td>
                <td><?= htmlspecialchars($row['Nombre_Responsable']) ?></td>
                <td><?= htmlspecialchars($row['Estado_Tupper']) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="8">No se encontraron tuppers.</td>
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
function mostrarFiltros() {
  var filtros = document.getElementById('filtrosFormulario');
  filtros.style.display = filtros.style.display === 'none' ? 'block' : 'none';
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
