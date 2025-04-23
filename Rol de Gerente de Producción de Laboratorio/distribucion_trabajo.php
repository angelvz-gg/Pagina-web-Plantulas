<?php
include '../db.php';
session_start();

// Procesar asignación de lavado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_operador'], $_POST['id_preparacion'], $_POST['fecha'], $_POST['rol'], $_POST['cantidad'])) {
    $id_operador = intval($_POST['id_operador']);
    $id_preparacion = intval($_POST['id_preparacion']);
    $fecha = $_POST['fecha'];
    $rol = $_POST['rol'];
    $cantidad = intval($_POST['cantidad']);

    if ($id_operador && $id_preparacion && $rol && $cantidad > 0) {
        // Buscar datos de la preparación
        $stmt_info = $conn->prepare("
            SELECT pc.ID_Orden, pc.Tuppers_Buenos, l.ID_Variedad
            FROM preparacion_cajas pc
            INNER JOIN orden_tuppers_lavado otl ON pc.ID_Orden = otl.ID_Orden
            INNER JOIN lotes l ON otl.ID_Lote = l.ID_Lote
            WHERE pc.ID_Preparacion = ?
        ");
        $stmt_info->bind_param("i", $id_preparacion);
        $stmt_info->execute();
        $info = $stmt_info->get_result()->fetch_assoc();

        if ($info) {
            $id_orden = $info['ID_Orden'];
            $tuppers_buenos = $info['Tuppers_Buenos'];
            $id_variedad = $info['ID_Variedad'];

            if ($cantidad <= $tuppers_buenos) {
                // Insertar asignación
                $stmt_asignar = $conn->prepare("
                    INSERT INTO asignacion_lavado (ID_Operador, ID_Variedad, ID_Preparacion, Fecha, Rol, Cantidad_Tuppers)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_asignar->bind_param("iiissi", $id_operador, $id_variedad, $id_preparacion, $fecha, $rol, $cantidad);

                if ($stmt_asignar->execute()) {
                    // Actualizar cantidad de tuppers en la caja
                    $nuevo_total = $tuppers_buenos - $cantidad;
                    $stmt_update_caja = $conn->prepare("UPDATE preparacion_cajas SET Tuppers_Buenos = ? WHERE ID_Preparacion = ?");
                    $stmt_update_caja->bind_param("ii", $nuevo_total, $id_preparacion);
                    $stmt_update_caja->execute();

                    // Si ya no quedan tuppers, actualizar el estado de la orden a 'En Lavado'
                    if ($nuevo_total <= 0) {
                        $stmt_update_orden = $conn->prepare("UPDATE orden_tuppers_lavado SET Estado = 'En Lavado' WHERE ID_Orden = ?");
                        $stmt_update_orden->bind_param("i", $id_orden);
                        $stmt_update_orden->execute();
                    }

                    echo "<script>alert('✅ Asignación registrada correctamente.'); window.location.href='distribucion_trabajo.php';</script>";
                    exit();
                } else {
                    echo "<script>alert('❌ Error al registrar la asignación.');</script>";
                }
            } else {
                echo "<script>alert('❌ Error: La cantidad asignada supera los tuppers disponibles.');</script>";
            }
        }
    } else {
        echo "<script>alert('❌ Todos los campos son obligatorios.');</script>";
    }
}

// Obtener operadores activos
$operadores = $conn->query("
    SELECT ID_Operador, CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) AS NombreCompleto 
    FROM operadores 
    WHERE Activo = 1 AND ID_Rol = 2
    ORDER BY Nombre ASC
");

// Obtener cajas disponibles para asignación
$cajas = $conn->query("
    SELECT 
        pc.ID_Preparacion,
        v.Codigo_Variedad,
        v.Nombre_Variedad,
        pc.Tuppers_Buenos,
        l.Fecha AS Fecha_Ingreso,
        CASE WHEN l.ID_Etapa = 2 THEN 'Multiplicación' WHEN l.ID_Etapa = 3 THEN 'Enraizamiento' ELSE 'Otra' END AS Etapa_Origen,
        CONCAT(o.Nombre, ' ', o.Apellido_P, ' ', o.Apellido_M) AS Responsable
    FROM preparacion_cajas pc
    INNER JOIN orden_tuppers_lavado otl ON pc.ID_Orden = otl.ID_Orden
    INNER JOIN lotes l ON otl.ID_Lote = l.ID_Lote
    INNER JOIN variedades v ON l.ID_Variedad = v.ID_Variedad
    INNER JOIN operadores o ON l.ID_Operador = o.ID_Operador
    WHERE otl.Estado = 'Caja Preparada' AND pc.Tuppers_Buenos > 0
    ORDER BY pc.Fecha_Registro ASC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Distribución de Trabajo - Lavado de Plantas</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
<header>
    <div class="encabezado">
        <a class="navbar-brand" href="#"><img src="../logoplantulas.png" alt="Logo" width="130" height="124"></a>
        <h2>Distribución de Trabajo - Lavado de Plantas</h2>
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
    <div class="section">
        <h3 class="mb-4 text-center">📋 Cajas Disponibles para Lavado</h3>

        <?php if ($cajas->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>ID Caja</th>
                            <th>Código Variedad</th>
                            <th>Nombre Variedad</th>
                            <th>Tuppers Buenos</th>
                            <th>Fecha Ingreso</th>
                            <th>Etapa Origen</th>
                            <th>Responsable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($caja = $cajas->fetch_assoc()): ?>
                            <tr>
                                <td><?= $caja['ID_Preparacion'] ?></td>
                                <td><?= htmlspecialchars($caja['Codigo_Variedad']) ?></td>
                                <td><?= htmlspecialchars($caja['Nombre_Variedad']) ?></td>
                                <td><?= $caja['Tuppers_Buenos'] ?></td>
                                <td><?= htmlspecialchars($caja['Fecha_Ingreso']) ?></td>
                                <td><?= htmlspecialchars($caja['Etapa_Origen']) ?></td>
                                <td><?= htmlspecialchars($caja['Responsable']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center">
                <strong>🔔 No hay cajas preparadas disponibles actualmente.</strong>
            </div>
        <?php endif; ?>
    </div>

    <div class="section mt-5">
        <h3 class="mb-4 text-center">🌱 Registrar Asignación de Trabajo</h3>
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Operador:</label>
                <select name="id_operador" class="form-select" required>
                    <option value="">-- Seleccionar Operador --</option>
                    <?php foreach ($operadores as $op): ?>
                        <option value="<?= $op['ID_Operador'] ?>"><?= htmlspecialchars($op['NombreCompleto']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Caja (Preparación):</label>
                <select name="id_preparacion" class="form-select" required>
                    <option value="">-- Seleccionar Caja --</option>
                    <?php
                    $cajas_select = $conn->query("
                        SELECT pc.ID_Preparacion, v.Nombre_Variedad, pc.Tuppers_Buenos
                        FROM preparacion_cajas pc
                        INNER JOIN orden_tuppers_lavado otl ON pc.ID_Orden = otl.ID_Orden
                        INNER JOIN lotes l ON otl.ID_Lote = l.ID_Lote
                        INNER JOIN variedades v ON l.ID_Variedad = v.ID_Variedad
                        WHERE otl.Estado = 'Caja Preparada' AND pc.Tuppers_Buenos > 0
                    ");
                    while ($caja = $cajas_select->fetch_assoc()): ?>
                        <option value="<?= $caja['ID_Preparacion'] ?>">
                            <?= htmlspecialchars($caja['Nombre_Variedad']) ?> - <?= $caja['Tuppers_Buenos'] ?> tuppers
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Fecha:</label>
                <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Rol:</label>
                <select name="rol" class="form-select" required>
                    <option value="">-- Seleccionar Rol --</option>
                    <option value="Supervisor">Supervisor</option>
                    <option value="Lavador">Lavador</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Cantidad de Tuppers:</label>
                <input type="number" name="cantidad" class="form-control" min="1" required>
            </div>

            <div class="col-12 text-center">
                <button type="submit" class="save-button">Registrar Asignación</button>
            </div>
        </form>
    </div>
</main>

<footer class="text-center mt-4" style="background-color: #45814d; color: white;">
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
