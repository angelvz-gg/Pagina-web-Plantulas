<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];

// Consultar asignaciones
$sql_asignacion = "SELECT AL.ID, AL.ID_Variedad, AL.Fecha, V.Nombre_Variedad, AL.Rol, AL.Cantidad_Tuppers, L.ID_Lote, AL.Estado_Final
                   FROM asignacion_lavado AL
                   JOIN variedades V ON AL.ID_Variedad = V.ID_Variedad
                   JOIN lotes L ON V.ID_Variedad = L.ID_Variedad
                   WHERE AL.ID_Operador = ? AND AL.Fecha = CURDATE()";
$stmt_asignacion = $conn->prepare($sql_asignacion);
$stmt_asignacion->bind_param("i", $ID_Operador);
$stmt_asignacion->execute();
$result_asignacion = $stmt_asignacion->get_result();
$asignaciones = $result_asignacion->fetch_all(MYSQLI_ASSOC);

// Avances registrados
$avances_realizados = [];
$sql_check_avances = "SELECT ID_Variedad, SUM(Tuppers_Lavados) AS Tuppers_Lavados FROM reporte_lavado_parcial WHERE ID_Operador = ? AND Fecha = CURDATE() GROUP BY ID_Variedad";
$stmt_check = $conn->prepare($sql_check_avances);
$stmt_check->bind_param("i", $ID_Operador);
$stmt_check->execute();
$res_check = $stmt_check->get_result();
while ($row = $res_check->fetch_assoc()) {
    $avances_realizados[$row['ID_Variedad']] = $row['Tuppers_Lavados'];
}

// Guardar avance o cierre
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion"])) {
    $accion = $_POST["accion"];
    $id_variedad = $_POST["id_variedad"];
    $id_lote = $_POST["id_lote"];
    $id_asignacion = $_POST["id_asignacion"];
    $fecha = date('Y-m-d');

    if ($accion == "avance") {
        $tuppers_lavados = $_POST["tuppers_lavados"];
        $observaciones = $_POST["observaciones"] ?? null;

        $stmt = $conn->prepare("INSERT INTO reporte_lavado_parcial (ID_Operador, ID_Variedad, Fecha, Tuppers_Lavados, Observaciones) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisis", $ID_Operador, $id_variedad, $fecha, $tuppers_lavados, $observaciones);
        $stmt->execute();

        echo "<script>alert('✅ Avance registrado correctamente.'); window.location.href='relacion_lavado.php';</script>";
        exit();
    }

    if ($accion == "final") {
        $tuppers_finales = $_POST["tuppers_finales"];
        $observaciones_finales = $_POST["observaciones_finales"] ?? null;

        $stmt = $conn->prepare("INSERT INTO reporte_lavado_final (ID_Operador, ID_Variedad, Fecha, Tuppers_Lavados_Final, Observaciones) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisis", $ID_Operador, $id_variedad, $fecha, $tuppers_finales, $observaciones_finales);
        $stmt->execute();

        $stmt_update = $conn->prepare("UPDATE asignacion_lavado SET Estado_Final = 'Completada' WHERE ID = ?");
        $stmt_update->bind_param("i", $id_asignacion);
        $stmt_update->execute();

        echo "<script>alert('✅ Lavado final registrado correctamente.'); window.location.href='relacion_lavado.php';</script>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Relación de Lavado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand"><img src="../logoplantulas.png" alt="Logo" width="130" height="124"></a>
      <h2>RELACIÓN DE LAVADO</h2>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_cultivo.php'">🏠 Volver al inicio</button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main class="container mt-4">
    <?php if (!empty($asignaciones)): ?>
        <h3 class="mb-4">🧽 Mis Asignaciones de Hoy</h3>
        <div class="carrusel-desinfecciones">
          <?php foreach ($asignaciones as $asignacion): ?>
            <?php
              $avance = $avances_realizados[$asignacion['ID_Variedad']] ?? 0;
              $restante = max(0, $asignacion['Cantidad_Tuppers'] - $avance);
            ?>
            <div class="tarjeta-desinf" onclick="mostrarFormulario(<?= $asignacion['ID'] ?>)" id="card-<?= $asignacion['ID'] ?>">
              <strong><?= htmlspecialchars($asignacion['Nombre_Variedad']) ?></strong><br>
              Tuppers asignados: <?= $asignacion['Cantidad_Tuppers'] ?><br>
              Lavados avance: <?= $avance ?><br>
              Tuppers restantes: <?= $restante ?><br>
              Rol: <?= $asignacion['Rol'] ?><br>
              Fecha: <?= $asignacion['Fecha'] ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php foreach ($asignaciones as $asignacion): ?>
          <form method="POST" id="formulario-<?= $asignacion['ID'] ?>" class="formulario-siembra mt-4" style="display:none;">
            <input type="hidden" name="id_variedad" value="<?= $asignacion['ID_Variedad'] ?>">
            <input type="hidden" name="id_lote" value="<?= $asignacion['ID_Lote'] ?>">
            <input type="hidden" name="id_asignacion" value="<?= $asignacion['ID'] ?>">

            <h4 class="text-center mb-3">🌱 <?= htmlspecialchars($asignacion['Nombre_Variedad']) ?></h4>

            <?php if (($avances_realizados[$asignacion['ID_Variedad']] ?? 0) == 0 && !$asignacion['Estado_Final']): ?>
              <input type="hidden" name="accion" value="avance">
              <label>🧼 Tuppers lavados hasta ahora:</label>
              <input type="number" name="tuppers_lavados" min="0" max="<?= $asignacion['Cantidad_Tuppers'] ?>" required>
              <label>📝 Observaciones:</label>
              <textarea name="observaciones" rows="3"></textarea>
            <?php elseif (!$asignacion['Estado_Final']): ?>
              <input type="hidden" name="accion" value="final">
              <label>✅ Tuppers lavados al final:</label>
              <input type="number" name="tuppers_finales" min="0" max="<?= $restante ?>" required>
              <label>📝 Observaciones Finales:</label>
              <textarea name="observaciones_finales" rows="3"></textarea>
            <?php else: ?>
              <div class="alert alert-success text-center">Esta asignación está completada ✅</div>
            <?php endif; ?>

            <?php if (!$asignacion['Estado_Final']): ?>
            <div class="text-center mt-3">
              <button type="submit" class="save-button">Guardar Registro</button>
            </div>
            <?php endif; ?>
          </form>
        <?php endforeach; ?>
    <?php else: ?>
      <div class="alert alert-warning text-center">⚠️ No tienes asignaciones activas hoy.</div>
    <?php endif; ?>
  </main>

  <footer >
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<script>
function mostrarFormulario(id) {
  document.querySelectorAll('.formulario-siembra').forEach(f => f.style.display = 'none');
  document.getElementById('formulario-' + id).style.display = 'block';
  window.scrollTo({ top: document.getElementById('formulario-' + id).offsetTop - 100, behavior: 'smooth' });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
