<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

// Parámetros de búsqueda
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$busqueda_variedad = $_GET['busqueda_variedad'] ?? '';

// Consulta base
$sql = "SELECT D.ID_Desinfeccion, D.ID_Variedad, D.Explantes_Iniciales, D.Explantes_Desinfectados,
               D.FechaHr_Desinfeccion, D.HrFn_Desinfeccion, D.Estado_Desinfeccion,
               O.Nombre, O.Apellido_P, O.Apellido_M,
               V.Codigo_Variedad, V.Nombre_Variedad
        FROM desinfeccion_explantes D
        LEFT JOIN operadores O ON D.Operador_Responsable = O.ID_Operador
        LEFT JOIN variedades V ON D.ID_Variedad = V.ID_Variedad
        WHERE 1";

// Filtros dinámicos
$params = [];
$types = "";

if (!empty($fecha_desde)) {
    $sql .= " AND D.FechaHr_Desinfeccion >= ?";
    $params[] = $fecha_desde . " 00:00:00";
    $types .= "s";
}
if (!empty($fecha_hasta)) {
    $sql .= " AND D.FechaHr_Desinfeccion <= ?";
    $params[] = $fecha_hasta . " 23:59:59";
    $types .= "s";
}
if (!empty($busqueda_variedad)) {
    $sql .= " AND (V.Codigo_Variedad LIKE ? OR V.Nombre_Variedad LIKE ?)";
    $term = '%' . $busqueda_variedad . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= "ss";
}

$sql .= " ORDER BY D.FechaHr_Desinfeccion DESC";
$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Historial de Desinfección</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
      <h2>Historial de Desinfección de Explantes</h2>
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

  <main>

    <!-- Botón para mostrar/ocultar filtros -->
    <div style="text-align: right; margin: 20px;">
      <button type="button" class="save-button" onclick="toggleFiltros()">🔍 Mostrar filtros</button>
    </div>

    <!-- Formulario de filtros -->
    <div id="filtros" style="display: none;">
      <form method="GET" class="form-doble-columna">
        <div class="content">
          <div class="section">
            <label for="fecha_desde">Desde:</label>
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>">

            <label for="fecha_hasta">Hasta:</label>
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>">
          </div>
          <div class="section">
            <label for="busqueda_variedad">Buscar Variedad:</label>
            <input type="text" name="busqueda_variedad" placeholder="Nombre o Código" value="<?= htmlspecialchars($busqueda_variedad) ?>">

            <div style="display: flex; gap: 10px; margin-top: 15px;">
              <button type="submit" class="save-button">🔍 Aplicar</button>
              <a href="historial_desinfeccion_explantes.php" class="save-button" style="background-color: #999;">🔄 Limpiar filtros</a>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- Tabla -->
    <div class="table-responsive">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>Código Variedad</th>
            <th>Nombre Variedad</th>
            <th>Explantes Iniciales</th>
            <th>Desinfectados</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Estado</th>
            <th>Responsable</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
              <td><?= $row['ID_Desinfeccion'] ?></td>
              <td><?= $row['Codigo_Variedad'] ?? '-' ?></td>
              <td><?= $row['Nombre_Variedad'] ?? '-' ?></td>
              <td><?= $row['Explantes_Iniciales'] ?></td>
              <td><?= $row['Explantes_Desinfectados'] ?? '-' ?></td>
              <td><?= $row['FechaHr_Desinfeccion'] ?></td>
              <td><?= $row['HrFn_Desinfeccion'] ?? '-' ?></td>
              <td><?= $row['Estado_Desinfeccion'] ?></td>
              <td><?= $row['Nombre'] . ' ' . $row['Apellido_P'] . ' ' . $row['Apellido_M'] ?></td>
            </tr>
          <?php } ?>
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
    filtros.style.display = filtros.style.display === "none" ? "block" : "none";
  }
</script>
</body>
</html>
