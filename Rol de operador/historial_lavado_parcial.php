<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"]) || !isset($_SESSION["Rol"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];
$RolUsuario = $_SESSION["Rol"];
$reportes = [];

if ($RolUsuario === 'Encargado General de Producción') {
    // Encargado ve todo
    $sql = "SELECT RP.Fecha, RP.Hora_Registro, RP.Tuppers_Lavados, RP.Observaciones,
                   O.Nombre AS NombreOperador, V.Nombre_Variedad
            FROM reporte_lavado_parcial RP
            JOIN Operadores O ON RP.ID_Operador = O.ID_Operador
            JOIN Variedades V ON RP.ID_Variedad = V.ID_Variedad
            ORDER BY RP.Fecha DESC, RP.Hora_Registro DESC";
    $stmt = $conn->prepare($sql);
} else {
    // Supervisor solo ve su grupo, en su misma variedad y fecha
    $sql_supervisor = "SELECT Fecha, ID_Variedad FROM asignacion_lavado 
                       WHERE ID_Operador = ? AND Rol = 'Supervisor' AND Fecha = CURDATE()";
    $stmt_sup = $conn->prepare($sql_supervisor);
    $stmt_sup->bind_param("i", $ID_Operador);
    $stmt_sup->execute();
    $res = $stmt_sup->get_result();
    $datos = $res->fetch_assoc();

    if ($datos) {
        $fecha = $datos["Fecha"];
        $id_variedad = $datos["ID_Variedad"];

        $sql = "SELECT RP.Fecha, RP.Hora_Registro, RP.Tuppers_Lavados, RP.Observaciones,
                       O.Nombre AS NombreOperador, V.Nombre_Variedad
                FROM reporte_lavado_parcial RP
                JOIN Operadores O ON RP.ID_Operador = O.ID_Operador
                JOIN Variedades V ON RP.ID_Variedad = V.ID_Variedad
                WHERE RP.Fecha = ? AND RP.ID_Variedad = ?
                ORDER BY RP.Hora_Registro DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $fecha, $id_variedad);
    } else {
        $stmt = null;
    }
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $reportes = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial Lavado Parcial</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
</head>
<body>
<div class="contenedor-pagina">
    <div class="encabezado">
        <a class="navbar-brand">
            <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
            PLÁNTULAS AGRODEX
        </a>
        <h2>📊 HISTORIAL DE LAVADO PARCIAL</h2>
    </div>

    <!-- Botón volver -->
    <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
            <div class="container-fluid">
                <div class="Opciones-barra">
                    <button onclick="window.location.href='dashboard_cultivo.php'">
                        🏠 Volver al inicio
                    </button>
                </div>
            </div>
        </nav>
    </div>

    <main>
        <h2>Reportes registrados</h2>
        <?php if (empty($reportes)): ?>
            <p style="color: red;">No hay reportes disponibles para mostrar.</p>
        <?php else: ?>
        <div class="tabla-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Operador</th>
                        <th>Variedad</th>
                        <th>Tuppers Lavados</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportes as $row): ?>
                        <tr>
                            <td><?= $row['Fecha'] ?></td>
                            <td><?= $row['Hora_Registro'] ?></td>
                            <td><?= htmlspecialchars($row['NombreOperador']) ?></td>
                            <td><?= htmlspecialchars($row['Nombre_Variedad']) ?></td>
                            <td><?= $row['Tuppers_Lavados'] ?></td>
                            <td><?= htmlspecialchars($row['Observaciones']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
</div>
</body>
</html>
