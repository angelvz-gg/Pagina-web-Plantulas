<?php
include '../db.php';
session_start();

$ID_Operador = $_SESSION['ID_Operador'] ?? null;
$fecha_actual = date('Y-m-d');

// Autocompletado AJAX para variedad
if (isset($_GET['action']) && $_GET['action'] === 'buscar_variedad') {
    $term = $_GET['term'] ?? '';
    $sql = "SELECT ID_Variedad, Codigo_Variedad, Nombre_Variedad, Especie 
            FROM Variedades 
            WHERE Codigo_Variedad LIKE ? OR Nombre_Variedad LIKE ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    $like = "%$term%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $sugerencias = [];
    while ($row = $result->fetch_assoc()) {
        $sugerencias[] = [
            'id' => $row['ID_Variedad'],
            'especie' => $row['Especie'],
            'label' => $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad'],
            'value' => $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad']
        ];
    }
    echo json_encode($sugerencias);
    exit;
}

// Autocompletado AJAX para medio nutritivo
if (isset($_GET['action']) && $_GET['action'] === 'buscar_medio') {
    $term = $_GET['term'] ?? '';
    $especie = $_GET['especie'] ?? '';
    $etapa = $_GET['etapa'] ?? '';

    $sql = "SELECT ID_MedioNutritivo, Codigo_Medio 
            FROM medios_nutritivos 
            WHERE Codigo_Medio LIKE ? 
              AND Etapa_Destinada = ? 
              AND Especie = ? 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $like = "%$term%";
    $stmt->bind_param("sss", $like, $etapa, $especie);
    $stmt->execute();
    $result = $stmt->get_result();

    $res = [];
    while ($row = $result->fetch_assoc()) {
        $res[] = [
            'id' => $row['ID_MedioNutritivo'],
            'label' => $row['Codigo_Medio'],
            'value' => $row['Codigo_Medio']
        ];
    }
    echo json_encode($res);
    exit;
}

// Validar asignación
$sql = "SELECT * FROM Asignaciones WHERE ID_Operador = ? AND Fecha = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $ID_Operador, $fecha_actual);
$stmt->execute();
$result = $stmt->get_result();
$asignacion = $result->fetch_assoc();

$ID_Asignacion = $asignacion['ID_Asignacion'] ?? null;
$ID_Variedad = $asignacion['ID_Variedad'] ?? '';
$ID_Etapa = $asignacion['ID_Etapa'] ?? '';
$reporteExistente = null;
$editable = true;

if ($asignacion) {
    // Según la etapa se guarda en Multiplicacion o Enraizamiento
    $tabla = ($ID_Etapa == 1) ? "Multiplicacion" : "Enraizamiento";
    $sql_check = "SELECT * FROM $tabla WHERE ID_Asignacion = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("i", $ID_Asignacion);
    $stmt->execute();
    $result = $stmt->get_result();
    $reporteExistente = $result->fetch_assoc();

    if ($reporteExistente && $reporteExistente['Estado_Revision'] !== 'Rechazado') {
        $editable = false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $editable) {
    $tasa = $_POST["tasa_multiplicacion"];
    $id_medio = $_POST["id_medio_nutritivo"];
    $num_brotes = $_POST["numero_brotes"];
    $tupper_lleno = $_POST["tupper_lleno"];
    $tupper_vacio = $_POST["tupper_vacios"];
    $etapa = $_POST["etapa"] ?? $ID_Etapa;
    $variedad = $_POST["id_variedad"] ?? $ID_Variedad;

    $tabla = ($etapa == 1) ? "Multiplicacion" : "Enraizamiento";

    if ($reporteExistente) {
        $sql = "UPDATE $tabla 
                SET Tasa_Multiplicacion = ?, ID_MedioNutritivo = ?, Cantidad_Dividida = ?, 
                    Tuppers_Llenos = ?, Tuppers_Desocupados = ?, Estado_Revision = 'Pendiente' 
                WHERE ID_Asignacion = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiisi", $tasa, $id_medio, $num_brotes, $tupper_lleno, $tupper_vacio, $ID_Asignacion);
    } else {
        $sql = "INSERT INTO $tabla 
                (ID_Asignacion, Fecha_Siembra, ID_Variedad, Tasa_Multiplicacion, ID_MedioNutritivo, 
                 Cantidad_Dividida, Tuppers_Llenos, Tuppers_Desocupados, Estado_Revision, Operador_Responsable) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issiiiisi", $ID_Asignacion, $fecha_actual, $variedad, $tasa, $id_medio, $num_brotes, $tupper_lleno, $tupper_vacio, $ID_Operador);
    }

    if ($stmt->execute()) {
        if ($ID_Asignacion) {
            $stmt = $conn->prepare("UPDATE Asignaciones SET Estado = 'Completado' WHERE ID_Asignacion = ?");
            $stmt->bind_param("i", $ID_Asignacion);
            $stmt->execute();
        }
        echo "<script>alert('Registro guardado correctamente.'); window.location.href='dashboard_cultivo.php';</script>";
    } else {
        echo "<script>alert('Error al guardar el registro.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Reporte de Siembra</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand">
        <img src="../logoplantulas.png" width="130" height="124" alt="Logo" />
      </a>
      <h2>Reporte de Siembra</h2>
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
  </header>

  <main>
    <form method="POST" class="form-doble-columna">
      <div class="content">
        <div class="section">
          <label>Fecha de Reporte:</label>
          <input type="text" value="<?= $fecha_actual ?>" readonly>

          <?php if (!$asignacion): ?>
            <label for="etapa">Etapa:</label>
            <select id="etapa" name="etapa" required>
              <option value="">-- Selecciona --</option>
              <option value="1">Multiplicación</option>
              <option value="2">Enraizamiento</option>
            </select>

            <label for="nombre_variedad">Buscar Variedad:</label>
            <input type="text" id="nombre_variedad" required>
            <input type="hidden" id="id_variedad" name="id_variedad">
            <input type="hidden" id="especie_variedad">
          <?php endif; ?>

          <label for="tasa_multiplicacion">Tasa de Multiplicación:</label>
          <input type="text" name="tasa_multiplicacion" required <?= $editable ? '' : 'readonly' ?>>

          <label for="medio_nutritivo">Medio Nutritivo:</label>
          <input type="text" id="medio_nutritivo" <?= $editable ? '' : 'readonly' ?> required>
          <input type="hidden" id="id_medio_nutritivo" name="id_medio_nutritivo">

          <label for="numero_brotes">Número de Brotes:</label>
          <input type="number" name="numero_brotes" required <?= $editable ? '' : 'readonly' ?>>

          <label for="tupper_lleno">Tuppers Llenos:</label>
          <input type="number" name="tupper_lleno" required <?= $editable ? '' : 'readonly' ?>>

          <label for="tupper_vacios">Tuppers Vacíos:</label>
          <input type="number" name="tupper_vacios" required <?= $editable ? '' : 'readonly' ?>>

          <?php if ($editable): ?>
            <button type="submit" class="save-button">Guardar información</button>
          <?php else: ?>
            <p><strong>Este reporte ya fue enviado y está en revisión o aprobado.</strong></p>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </main>

  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function () {
  // Autocompletar variedad
  $("#nombre_variedad").autocomplete({
    source: "reporte_diseccion.php?action=buscar_variedad",
    minLength: 2,
    select: function (event, ui) {
      $("#id_variedad").val(ui.item.id);
      $("#especie_variedad").val(ui.item.especie);
      $("#medio_nutritivo").val("");
      $("#id_medio_nutritivo").val("");
    }
  });

  // Autocompletar medio nutritivo
  $("#medio_nutritivo").autocomplete({
    source: function (request, response) {
      const etapa = $("#etapa").val() == "1" ? "Multiplicación" : "Enraizamiento";
      const especie = $("#especie_variedad").val();
      $.getJSON("reporte_diseccion.php?action=buscar_medio", {
        term: request.term,
        etapa: etapa,
        especie: especie
      }, response);
    },
    minLength: 0,
    select: function (event, ui) {
      $("#id_medio_nutritivo").val(ui.item.id);
    }
  }).focus(function () {
    $(this).autocomplete("search", "");
  });
});
</script>
</body>
</html>
