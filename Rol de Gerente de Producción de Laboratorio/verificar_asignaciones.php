<?php
session_start();
require '../db.php';

if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
    header("Location: ../login.php");
    exit();
}

// Eliminar asignación
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    $delete = $conn->prepare("DELETE FROM asignacion_lavado WHERE ID = ?");
    $delete->bind_param("i", $id_eliminar);
    $delete->execute();
    echo "<script>alert('✅ Asignación eliminada correctamente.'); window.location.href='verificar_asignaciones.php';</script>";
    exit();
}

// Filtros
$operador = $_GET['operador'] ?? '';
$variedad = $_GET['variedad'] ?? '';
$estado = $_GET['estado'] ?? '';
$fecha = $_GET['fecha'] ?? '';

// Consulta de asignaciones
$query = "
    SELECT 
        a.ID,
        a.Fecha,
        o.Nombre AS Operador,
        v.Nombre_Variedad AS Variedad,
        a.Rol,
        a.Cantidad_Tuppers,
        a.Estado_Final
    FROM asignacion_lavado a
    JOIN operadores o ON a.ID_Operador = o.ID_Operador
    JOIN variedades v ON a.ID_Variedad = v.ID_Variedad
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($operador)) {
    $query .= " AND o.Nombre LIKE ?";
    $params[] = "%$operador%";
    $types .= 's';
}

if (!empty($variedad)) {
    $query .= " AND v.Nombre_Variedad LIKE ?";
    $params[] = "%$variedad%";
    $types .= 's';
}

if (!empty($estado)) {
    if ($estado == 'Sin Cierre') {
        $query .= " AND (a.Estado_Final IS NULL OR a.Estado_Final = '')";
    } else {
        $query .= " AND a.Estado_Final = ?";
        $params[] = $estado;
        $types .= 's';
    }
}

if (!empty($fecha)) {
    $query .= " AND a.Fecha = ?";
    $params[] = $fecha;
    $types .= 's';
}

$query .= " ORDER BY a.Fecha DESC, o.Nombre ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificación de Asignaciones</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    #filtros {
      display: none;
      margin-bottom: 20px;
      padding: 20px;
      border: 1px solid #ccc;
      border-radius: 10px;
      background-color: #f8f9fa;
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
            <h2>📋 Verificación de Asignaciones de Lavado</h2>
        </div>

        <div class="barra-navegacion">
            <nav class="navbar bg-body-tertiary">
                <div class="container-fluid">
                    <div class="Opciones-barra">
                        <button onclick="window.location.href='dashboard_gpl.php'">🔙 Volver al Dashboard</button>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main class="container mt-4">
        <div class="text-center mb-3">
            <button class="btn btn-primary" onclick="mostrarFiltros()">🔍 Mostrar/Ocultar Filtros</button>
        </div>

        <div id="filtros">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="operador" class="form-control" placeholder="Buscar Operador..." value="<?= htmlspecialchars($operador) ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" name="variedad" class="form-control" placeholder="Buscar Variedad..." value="<?= htmlspecialchars($variedad) ?>">
                </div>
                <div class="col-md-3">
                    <select name="estado" class="form-select">
                        <option value="">-- Estado Final --</option>
                        <option value="Completada" <?= ($estado == 'Completada') ? 'selected' : '' ?>>✅ Completada</option>
                        <option value="Incompleta" <?= ($estado == 'Incompleta') ? 'selected' : '' ?>>⚠️ Incompleta</option>
                        <option value="Sin Cierre" <?= ($estado == 'Sin Cierre') ? 'selected' : '' ?>>⏳ Sin Cierre</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($fecha) ?>">
                </div>
                <div class="col-md-12 text-center">
                    <button type="submit" class="btn btn-success">Aplicar Filtros</button>
                    <a href="verificar_asignaciones.php" class="btn btn-secondary">Limpiar Filtros</a>
                </div>
            </form>
        </div>

        <table class="table table-bordered table-hover table-striped mt-4">
            <thead class="table-dark">
                <tr>
                    <th>Fecha</th>
                    <th>Operador</th>
                    <th>Variedad</th>
                    <th>Rol</th>
                    <th>Cantidad de Tuppers</th>
                    <th>Estado Final</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fila = $resultado->fetch_assoc()) : ?>
                    <tr>
                        <td><?= htmlspecialchars($fila['Fecha']) ?></td>
                        <td><?= htmlspecialchars($fila['Operador']) ?></td>
                        <td><?= htmlspecialchars($fila['Variedad']) ?></td>
                        <td><?= htmlspecialchars($fila['Rol']) ?></td>
                        <td><?= htmlspecialchars($fila['Cantidad_Tuppers']) ?></td>
                        <td>
                            <?php 
                                if ($fila['Estado_Final'] === 'Completada') {
                                    echo '✅ Completada';
                                } elseif ($fila['Estado_Final'] === 'Incompleta') {
                                    echo '⚠️ Incompleta';
                                } else {
                                    echo '⏳ Sin Cierre';
                                }
                            ?>
                        </td>
                        <td>
                            <a href="verificar_asignaciones.php?eliminar=<?= $fila['ID'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que deseas eliminar esta asignación?')">🗑️ Eliminar</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </main>

    <footer class="text-center mt-4 mb-3">
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function mostrarFiltros() {
    var filtros = document.getElementById('filtros');
    filtros.style.display = (filtros.style.display === 'none') ? 'block' : 'none';
}
</script>
</body>
</html>
