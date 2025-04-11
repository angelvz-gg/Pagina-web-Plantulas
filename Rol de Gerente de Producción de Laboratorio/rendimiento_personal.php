<?php
session_start();
require '../db.php';

if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
    header("Location: ../login.php");
    exit();
}

$filtro_fecha = $_GET['fecha'] ?? date('Y-m-d');

$query = "
    SELECT 
        o.Nombre AS Operador,
        v.Nombre_Variedad AS Variedad,
        a.Rol,
        a.Fecha,
        SUM(a.Cantidad_Tuppers) AS Total_Tuppers
    FROM asignacion_lavado a
    JOIN Operadores o ON a.ID_Operador = o.ID_Operador
    JOIN Variedades v ON a.ID_Variedad = v.ID_Variedad
    WHERE a.Fecha = ?
    GROUP BY a.ID_Operador, a.ID_Variedad, a.Rol, a.Fecha
    ORDER BY o.Nombre ASC, a.Fecha DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filtro_fecha);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rendimiento del Personal</title>
    <link rel="stylesheet" href="../style.css?v=<?=time();?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
</head>
<body>
<div class="contenedor-pagina">
    <header>
        <div class="encabezado">
            <a class="navbar-brand" href="#">
                <img src="../logoplantulas.png" alt="Logo" width="130" height="124" />
            </a>
            <h2>📈 Rendimiento del Personal en Lavado</h2>
        </div>

        <div class="barra-navegacion">
            <nav class="navbar bg-body-tertiary">
                <div class="container-fluid">
                    <div class="Opciones-barra">
                        <button onclick="window.location.href='dashboard_gpl.php'" >🏡 Volver al Dashboard</button>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main class="container mt-4">
        <!-- Filtro por fecha -->
        <div class="d-flex justify-content-center">
            <div style="max-width: 350px; width: 100%;">
                <h4 >📅 Filtrar por Fecha</h4>
                <form method="GET">
                    <div class="col-12">
                        <label for="fecha" class="form-label">Seleccionar Fecha:</label>
                        <input type="date" name="fecha" id="fecha" value="<?= $filtro_fecha ?>" class="form-control" required>
                    </div>
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-primary w-50">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resultados -->
        <div class="card p-4 shadow-sm">
            <h4 class="mb-4">📋 Resultado de Lavado</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Operador</th>
                            <th>Rol</th>
                            <th>Variedad</th>
                            <th>Total de Tuppers Lavados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado->num_rows > 0): ?>
                            <?php while ($fila = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($fila['Fecha']) ?></td>
                                    <td><?= htmlspecialchars($fila['Operador']) ?></td>
                                    <td><?= htmlspecialchars($fila['Rol']) ?></td>
                                    <td><?= htmlspecialchars($fila['Variedad']) ?></td>
                                    <td><?= htmlspecialchars($fila['Total_Tuppers']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">No hay registros para esta fecha.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="text-center mt-5 mb-3">
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
