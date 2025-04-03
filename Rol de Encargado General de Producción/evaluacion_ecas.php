<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];
$mensaje = "";

// Registrar evaluación
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {
    $id_tupper = $_POST["id_tupper"];
    $estado = $_POST["estado"];
    $desechar = isset($_POST["desechar"]) ? 1 : 0;
    $motivo = $desechar ? ($_POST["motivo"] ?? '') : null;
    $observaciones = $_POST["observaciones"] ?? '';
    $fecha_revision = date("Y-m-d H:i:s");
    $etapa_desecho = $_GET['etapa'] ?? null;

    $sql = "INSERT INTO estado_tuppers 
            (ID_Tupper, Fecha_Revision, Estado, Desechar, Motivo_Desecho, Observaciones, ID_Operador_Chequeo, Etapa_Desecho)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("❌ Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("ississis", $id_tupper, $fecha_revision, $estado, $desechar, $motivo, $observaciones, $ID_Operador, $etapa_desecho);

    if ($stmt->execute()) {
        $mensaje = "✅ Evaluación registrada correctamente.";
    } else {
        $mensaje = "❌ Error al registrar la evaluación: " . $stmt->error;
    }
}

// Tabla por etapa
$etapas = [
    'Siembra de Explantes' => 'siembra_ecas',
    'División de Brotes' => 'division_ecas',
    'Disecado de Hojas' => 'diseccion_hojas_ecas'
];

$etapa_seleccionada = $_GET['etapa'] ?? '';
$tuppers = [];

if ($etapa_seleccionada && isset($etapas[$etapa_seleccionada])) {
    $tabla = $etapas[$etapa_seleccionada];

    $sql_tuppers = "SELECT T.ID_Tupper, T.Etiqueta_Tupper, T.Fecha_Etiquetado, V.Nombre_Variedad
    FROM tuppers T
    JOIN variedades V ON T.ID_Variedad = V.ID_Variedad
    JOIN $tabla E ON E.ID_Tupper = T.ID_Tupper
    WHERE E.ID_Tupper IS NOT NULL
    ORDER BY T.Fecha_Etiquetado DESC";

    $result_tuppers = $conn->query($sql_tuppers);
    if ($result_tuppers) {
        $tuppers = $result_tuppers->fetch_all(MYSQLI_ASSOC);

        if (empty($tuppers)) {
            $mensaje = "⚠️ No hay tuppers con ID asignado registrados en esta etapa. Verifica que el campo ID_Tupper no esté vacío en la tabla '$tabla'.";
        }
    } else {
        $mensaje = "❌ Error al cargar tuppers: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Evaluación de ECAS</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
      <h2>Evaluación de Tuppers - ECAS</h2>
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

  <main class="container mt-4">
    <?php if ($mensaje): ?>
      <div class="alert alert-info"> <?= $mensaje ?> </div>
    <?php endif; ?>

    <form method="GET" class="mb-4">
      <label for="etapa" class="form-label">Selecciona una etapa:</label>
      <select name="etapa" id="etapa" class="form-select" onchange="this.form.submit()">
        <option value="">-- Selecciona una etapa --</option>
        <?php foreach ($etapas as $nombre => $tabla): ?>
          <option value="<?= $nombre ?>" <?= $etapa_seleccionada === $nombre ? 'selected' : '' ?>><?= $nombre ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if ($etapa_seleccionada): ?>
      <?php if (!empty($tuppers)): ?>
        <form method="POST" class="form-doble-columna">
          <div class="mb-3">
            <label class="form-label">Tupper a Evaluar:</label>
            <select name="id_tupper" class="form-select" id="id_tupper_select" required>
              <option value="">Selecciona un tupper</option>
              <?php foreach ($tuppers as $t): ?>
                <option value="<?= $t['ID_Tupper'] ?>"
                        data-variedad="<?= $t['Nombre_Variedad'] ?>"
                        data-fecha="<?= $t['Fecha_Etiquetado'] ?>">
                  <?= "ID: {$t['ID_Tupper']} - Variedad: {$t['Nombre_Variedad']} - Fecha: {$t['Fecha_Etiquetado']}" ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">📌 Variedad:</label>
            <input type="text" id="variedad_mostrada" class="form-control" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label">📅 Fecha de Etiquetado:</label>
            <input type="text" id="fecha_mostrada" class="form-control" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label">Estado Observado:</label>
            <select name="estado" class="form-select" required>
              <option value="Intacto">Intacto</option>
              <option value="Contaminado">Contaminado</option>
              <option value="Dañado">Dañado</option>
            </select>
          </div>

          <div class="mb-3 form-check">
            <input type="checkbox" name="desechar" class="form-check-input" value="1">
            <label class="form-check-label">¿Desechar?</label>
          </div>

          <div class="mb-3">
            <label class="form-label">Motivo del Desecho (si aplica):</label>
            <input type="text" name="motivo" class="form-control" placeholder="Contaminado por hongo, etc.">
          </div>

          <div class="mb-3">
            <label class="form-label">Observaciones:</label>
            <textarea name="observaciones" class="form-control" rows="3"></textarea>
          </div>

          <button type="submit" name="guardar" class="btn btn-primary">Registrar Evaluación</button>
        </form>
      <?php else: ?>
        <div class="alert alert-warning">No hay tuppers registrados en esta etapa.</div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <footer class="mt-5">
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<script>
document.getElementById('id_tupper_select')?.addEventListener('change', function () {
  const selectedOption = this.options[this.selectedIndex];
  const variedad = selectedOption.getAttribute("data-variedad");
  const fecha = selectedOption.getAttribute("data-fecha");

  document.getElementById("variedad_mostrada").value = variedad || '';
  document.getElementById("fecha_mostrada").value = fecha || '';
});
</script>
</body>
</html>
