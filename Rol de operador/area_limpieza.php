<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];

// Obtener el área asignada al operador
$sql_area = "SELECT Area_Produccion FROM Operadores WHERE ID_Operador = ?";
$stmt_area = $conn->prepare($sql_area);
$stmt_area->bind_param("i", $ID_Operador);
$stmt_area->execute();
$result_area = $stmt_area->get_result();
$row_area = $result_area->fetch_assoc();
$area_asignada = $row_area["Area_Produccion"] ?? "Área no asignada";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fecha = date('Y-m-d');
    $estado = $_POST["estado"];

    $sql = "INSERT INTO Registro_Limpieza (ID_Operador, Fecha, Area, Estado_Limpieza) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $ID_Operador, $fecha, $area_asignada, $estado);

    if ($stmt->execute()) {
        echo "<script>alert('Registro de limpieza guardado correctamente.'); window.location.href='area_limpieza.php';</script>";
    } else {
        echo "<script>alert('Error al registrar la limpieza.');</script>";
    }
}

$sql_registros = "SELECT * FROM Registro_Limpieza WHERE ID_Operador = ? ORDER BY Fecha DESC";
$stmt = $conn->prepare($sql_registros);
$stmt->bind_param("i", $ID_Operador);
$stmt->execute();
$result_registros = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Limpieza - Plántulas Agrodex</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
    <!-- Encabezado -->
     <header>
    <div class="encabezado">
        <a class="navbar-brand">
            <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
            PLÁNTULAS AGRODEX
        </a>
        <h2>🌿 LIMPIEZA DE ÁREAS</h2>
        <div></div>
    </div>
    </header>

    <!-- Barra de navegación con botón -->
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

    <!-- Contenido principal -->
    <main>
        <h2>Registro de Limpieza</h2>
        <form method="POST" action="area_limpieza.php" class="form-doble-columna">
            <div class="content">
                <div class="section">
                    <h2>Área Designada</h2>
                    <h3>Tu área asignada es:</h3>
                    <input type="text" value="<?= htmlspecialchars($area_asignada); ?>" readonly>
                </div>

                <div class="section">
                    <h2>Estado de limpieza</h2>
                    <h3>¿Se completó la limpieza?</h3>
                    <select name="estado" required>
                        <option value="">-- Selecciona una opción --</option>
                        <option value="Área lavada">✅ Área lavada</option>
                        <option value="Área no lavada">❌ Área no lavada</option>
                    </select>
                    <button class="save-button" type="submit">Guardar Registro</button>
                </div>
            </div>
        </form>

        <h2>Historial de Limpieza</h2>
        <div class="tabla-responsive">
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Área</th>
                    <th>Estado</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = $result_registros->fetch_assoc()) { ?>
                    <tr>
                        <td><?= $row['Fecha']; ?></td>
                        <td><?= $row['Area']; ?></td>
                        <td><?= $row['Estado_Limpieza']; ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Footer corregido -->
    <footer>
        <p>&copy; 2025 PLÁNTULAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
</div>
</body>
</html>
