<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];

// Obtener asignación de lavado activa para hoy
$sql_asignacion = "SELECT AL.ID_Variedad, AL.Fecha, V.Nombre_Variedad, AL.Rol, AL.Cantidad_Tuppers
                   FROM asignacion_lavado AL
                   JOIN Variedades V ON AL.ID_Variedad = V.ID_Variedad
                   WHERE AL.ID_Operador = ? AND AL.Fecha = CURDATE()
                   LIMIT 1";
$stmt_asignacion = $conn->prepare($sql_asignacion);
$stmt_asignacion->bind_param("i", $ID_Operador);
$stmt_asignacion->execute();
$result_asignacion = $stmt_asignacion->get_result();
$asignacion = $result_asignacion->fetch_assoc();

// Registro de avance parcial
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["tuppers_lavados"])) {
    $id_variedad = $_POST["id_variedad"];
    $tuppers_lavados = $_POST["tuppers_lavados"];
    $observaciones = $_POST["observaciones"] ?? null;
    $fecha = date('Y-m-d');

    $sql_insert = "INSERT INTO reporte_lavado_parcial (ID_Operador, ID_Variedad, Fecha, Tuppers_Lavados, Observaciones)
                   VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("iisis", $ID_Operador, $id_variedad, $fecha, $tuppers_lavados, $observaciones);
    
    if ($stmt_insert->execute()) {
        echo "<script>alert('Reporte de avance registrado exitosamente.'); window.location.href='relacion_lavado.php';</script>";
    } else {
        echo "<script>alert('Error al guardar el reporte.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Relación de Lavado - Plántulas Agrodex</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
    <div class="encabezado">
        <a class="navbar-brand">
            <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
            PLÁNTULAS AGRODEX
        </a>
        <h2>RELACIÓN DE LAVADO</h2>
    </div>

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
        <?php if ($asignacion): ?>
            <h2>Asignación de Lavado para Hoy</h2>
            <div class="section">
                <p><strong>📅 Fecha:</strong> <?= htmlspecialchars($asignacion['Fecha']) ?></p>
                <p><strong>🌱 Variedad:</strong> <?= htmlspecialchars($asignacion['Nombre_Variedad']) ?></p>
                <p><strong>👤 Rol asignado:</strong> <?= htmlspecialchars($asignacion['Rol']) ?></p>
                <p><strong>🧴 Tuppers a lavar:</strong> <?= htmlspecialchars($asignacion['Cantidad_Tuppers']) ?></p>
            </div>

            <h2>Reporte de Avance (Media Jornada)</h2>
            <form method="POST" action="relacion_lavado.php" class="form-doble-columna">
                <div class="content">
                    <div class="section">
                        <input type="hidden" name="id_variedad" value="<?= $asignacion['ID_Variedad'] ?>">

                        <label for="tuppers_lavados">Tuppers lavados hasta ahora:</label>
                        <input type="number" name="tuppers_lavados" required placeholder="Ej. 15">

                        <label for="observaciones">Observaciones (opcional):</label>
                        <textarea name="observaciones" rows="3" placeholder="Escribe algo si es necesario..."></textarea>

                        <button type="submit" class="save-button">Guardar avance</button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="section">
                <p style="color: red;"><strong>No tienes una asignación activa de lavado para hoy.</strong></p>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
</div>
</body>
</html>
