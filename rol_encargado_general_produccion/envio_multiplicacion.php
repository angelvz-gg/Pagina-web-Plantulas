<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];
$mensaje = "";

// Procesar asignación si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["asignar_variedad"])) {
    $id_diseccion = intval($_POST['id_diseccion']);
    $codigo_variedad = $_POST['codigo_variedad'];
    $nombre_variedad = $_POST['nombre_variedad'];
    $brotes_asignados = intval($_POST['brotes_asignados']);
    $fecha_asignacion = $_POST['fecha_asignacion'];
    $operador_asignado = intval($_POST['operador_asignado']);
    $observaciones = $_POST['observaciones'] ?? NULL;

    $sql_insert = "INSERT INTO asignaciones_multiplicacion 
        (ID_Diseccion, Codigo_Variedad, Nombre_Variedad, Brotes_Asignados, Fecha_Asignacion, Operador_Asignado, Estado, Observaciones)
        VALUES (?, ?, ?, ?, ?, ?, 'Asignado', ?)";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("isssiss", $id_diseccion, $codigo_variedad, $nombre_variedad, $brotes_asignados, $fecha_asignacion, $operador_asignado, $observaciones);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Asignación registrada correctamente.'); window.location.href='envio_multiplicacion.php';</script>";
        exit();
    } else {
        echo "<script>alert('❌ Error al registrar la asignación.'); window.history.back();</script>";
        exit();
    }
}

$min_brotes_multiplicacion = 80;

$sql = "
    SELECT 
        V.Codigo_Variedad,
        V.Nombre_Variedad,
        (SUM(DH.Brotes_Generados) - IFNULL(SUM(AM.Brotes_Asignados), 0)) AS Total_Brotes_Disponibles,
        MAX(DH.Fecha_Diseccion) AS Ultima_Fecha,
        MAX(DH.ID_Diseccion) AS ID_Diseccion,
        O.Nombre AS Nombre_Operador,
        O.Apellido_P AS ApellidoP_Operador,
        O.Apellido_M AS ApellidoM_Operador
    FROM diseccion_hojas_ecas DH
    JOIN siembra_ecas S ON DH.ID_Siembra = S.ID_Siembra
    JOIN variedades V ON S.ID_Variedad = V.ID_Variedad
    LEFT JOIN asignaciones_multiplicacion AM ON DH.ID_Diseccion = AM.ID_Diseccion
    LEFT JOIN operadores O ON DH.Operador_Responsable = O.ID_Operador
    GROUP BY V.ID_Variedad
    HAVING Total_Brotes_Disponibles >= ?
    ORDER BY Total_Brotes_Disponibles DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $min_brotes_multiplicacion);
$stmt->execute();
$result = $stmt->get_result();
$variedades = $result->fetch_all(MYSQLI_ASSOC);

$operadores = [];
$res_operadores = $conn->query("SELECT ID_Operador, CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) AS NombreCompleto FROM operadores WHERE Activo = 1 AND ID_Rol = 2");
if ($res_operadores) {
    $operadores = $res_operadores->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Envío a Multiplicación - ECAS</title>
    <link rel="stylesheet" href="../style.css?v=<?=time();?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
    <header class="encabezado">
        <a class="navbar-brand" href="#"><img src="../logoplantulas.png" alt="Logo" width="130" height="124"></a>
        <h2>🌿 Envío de Variedades a Multiplicación</h2>
    </header>

    <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
            <div class="container-fluid">
                <div class="Opciones-barra">
                <button onclick="window.location.href='dashboard_egp.php'" >🏠 Volver al Dashboard</button>
                </div>
            </div>
        </nav>
    </div>

    <main class="container mt-4">
        <?php if (count($variedades) > 0): ?>
            <div id="formulario-asignacion" style="display:none;" class="mb-4">
                <h4>Asignar variedad a operador</h4>
                <form method="POST" class="border p-3">
                    <input type="hidden" name="id_diseccion" id="id_diseccion">
                    <input type="hidden" name="codigo_variedad" id="codigo_variedad">
                    <input type="hidden" name="nombre_variedad" id="nombre_variedad">

                    <div class="mb-3">
                        <label>Variedad Seleccionada:</label>
                        <input type="text" id="variedad_mostrada" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Brotes a asignar:</label>
                        <input type="number" name="brotes_asignados" id="brotes_asignados" class="form-control" required min="1">
                    </div>
                    <div class="mb-3">
                        <label>Fecha de asignación:</label>
                        <input type="date" name="fecha_asignacion" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Asignar a operador:</label>
                        <select name="operador_asignado" class="form-select" required>
                            <option value="">-- Seleccionar operador --</option>
                            <?php foreach ($operadores as $op): ?>
                                <option value="<?= $op['ID_Operador'] ?>"><?= htmlspecialchars($op['NombreCompleto']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Observaciones (opcional):</label>
                        <textarea name="observaciones" class="form-control"></textarea>
                    </div>
                    <button type="submit" name="asignar_variedad" class="btn btn-success">Confirmar Asignación</button>
                </form>
            </div>

            <div class="alert alert-success">
                Variedades con más de <?= $min_brotes_multiplicacion ?> brotes disponibles para enviar a multiplicación:
            </div>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Código Variedad</th>
                        <th>Nombre Variedad</th>
                        <th>Brotes Disponibles</th>
                        <th>Fecha de Última Disección</th>
                        <th>Responsable</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variedades as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['Codigo_Variedad']) ?></td>
                            <td><?= htmlspecialchars($v['Nombre_Variedad']) ?></td>
                            <td><strong><?= $v['Total_Brotes_Disponibles'] ?></strong></td>
                            <td><?= htmlspecialchars($v['Ultima_Fecha']) ?></td>
                            <td><?= htmlspecialchars($v['Nombre_Operador'] . " " . $v['ApellidoP_Operador'] . " " . $v['ApellidoM_Operador']) ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm"
                                    onclick="mostrarFormulario('<?= $v['ID_Diseccion'] ?>', '<?= $v['Codigo_Variedad'] ?>', '<?= $v['Nombre_Variedad'] ?>', '<?= $v['Total_Brotes_Disponibles'] ?>')">
                                    Asignar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">
                No hay variedades con suficientes brotes disponibles para enviar a multiplicación.
            </div>
        <?php endif; ?>
    </main>

    <footer class="text-center mt-5">
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mostrar el formulario al dar clic en "Asignar"
function mostrarFormulario(idDiseccion, codigo, nombre, brotes) {
    document.getElementById('formulario-asignacion').style.display = 'block';
    document.getElementById('id_diseccion').value = idDiseccion;
    document.getElementById('codigo_variedad').value = codigo;
    document.getElementById('nombre_variedad').value = nombre;
    document.getElementById('variedad_mostrada').value = codigo + ' - ' + nombre;
    document.getElementById('brotes_asignados').value = brotes;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>

