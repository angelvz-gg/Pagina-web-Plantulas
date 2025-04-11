<?php
include '../db.php';
session_start();

$ID_Operador = $_SESSION['ID_Operador'] ?? null;
$fecha_actual = date('Y-m-d');

// Buscar la asignacion activa de este operador
$sql = "SELECT * FROM asignaciones_multiplicacion WHERE Operador_Asignado = ? AND Estado = 'Asignado' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ID_Operador);
$stmt->execute();
$result = $stmt->get_result();
$asignacion = $result->fetch_assoc();

$ID_Diseccion = $asignacion['ID_Diseccion'] ?? null;
$Codigo_Variedad = $asignacion['Codigo_Variedad'] ?? '';
$Nombre_Variedad = $asignacion['Nombre_Variedad'] ?? '';
$Brotes_Asignados = $asignacion['Brotes_Asignados'] ?? 0;
$editable = true;

// Obtener especie de la variedad
$especie = '';
if (!empty($Codigo_Variedad)) {
    $stmt_especie = $conn->prepare("SELECT Especie FROM variedades WHERE Codigo_Variedad = ? LIMIT 1");
    $stmt_especie->bind_param("s", $Codigo_Variedad);
    $stmt_especie->execute();
    $result_especie = $stmt_especie->get_result();
    if ($row = $result_especie->fetch_assoc()) {
        $especie = $row['Especie'];
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'buscar_medio') {
    $term = $_GET['term'] ?? '';
    $etapa = $_GET['etapa'] ?? '';
    $especie_busqueda = $_GET['especie'] ?? '';

    $sql = "SELECT ID_MedioNutritivo, Codigo_Medio 
            FROM medios_nutritivos 
            WHERE Codigo_Medio LIKE ? 
              AND Etapa_Destinada = ? 
              AND Especie = ? 
              AND Estado = 'Activo'
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $like = "%$term%";
    $stmt->bind_param("sss", $like, $etapa, $especie_busqueda);
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && $ID_Diseccion) {
    $tasa = $_POST["tasa_multiplicacion"];
    $id_medio = $_POST["id_medio_nutritivo"];
    $num_brotes = $_POST["numero_brotes"];
    $tupper_lleno = $_POST["tupper_lleno"];
    $tupper_vacio = $_POST["tupper_vacios"];

    $sql_insert = "INSERT INTO Multiplicacion 
        (ID_Variedad, ID_MedioNutritivo, Cantidad_Dividida, Fecha_Siembra, Tasa_Multiplicacion, 
         Tuppers_Llenos, Tuppers_Desocupados, Operador_Responsable, Estado_Revision)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("iiissiii", 
        $asignacion['ID_Variedad'], 
        $id_medio, 
        $num_brotes, 
        $fecha_actual, 
        $tasa, 
        $tupper_lleno, 
        $tupper_vacio, 
        $ID_Operador
    );

    if ($stmt->execute()) {
        $stmt_update = $conn->prepare("UPDATE asignaciones_multiplicacion SET Estado = 'Trabajado' WHERE ID_Asignacion = ?");
        $stmt_update->bind_param("i", $asignacion['ID_Asignacion']);
        $stmt_update->execute();

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
  <title>Trabajo en Multiplicación</title>
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
      <h2>Trabajo en Multiplicación</h2>
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
    <?php if ($asignacion): ?>
    <form method="POST" class="form-doble-columna">
      <div class="content">
        <div class="section">
          <label>Fecha de Reporte:</label>
          <input type="text" value="<?= $fecha_actual ?>" readonly>

          <label>Etapa:</label>
          <input type="text" value="Multiplicación" readonly>

          <label>Variedad Asignada:</label>
          <input type="text" value="<?= htmlspecialchars($Codigo_Variedad . ' - ' . $Nombre_Variedad) ?>" readonly>

          <label>Brotes Totales Asignados:</label>
          <input type="text" value="<?= $Brotes_Asignados ?>" readonly>

          <label for="tasa_multiplicacion">Tasa de Multiplicación:</label>
          <input type="text" name="tasa_multiplicacion" required>

          <label for="medio_nutritivo">Medio Nutritivo:</label>
          <input type="text" id="medio_nutritivo" required>
          <input type="hidden" id="id_medio_nutritivo" name="id_medio_nutritivo">

          <label for="numero_brotes">Número de Brotes:</label>
          <input type="number" name="numero_brotes" required>

          <label for="tupper_lleno">Tuppers Llenos:</label>
          <input type="number" name="tupper_lleno" required>

          <label for="tupper_vacios">Tuppers Vacíos:</label>
          <input type="number" name="tupper_vacios" required>

          <button type="submit" class="save-button">Guardar información</button>
        </div>
      </div>
    </form>
    <?php else: ?>
      <div class="alert alert-warning m-4">No tienes asignaciones pendientes de multiplicación.</div>
    <?php endif; ?>
  </main>

  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function () {
  $("#medio_nutritivo").autocomplete({
    source: function (request, response) {
      $.getJSON("trabajo_multiplicacion.php?action=buscar_medio", {
        term: request.term,
        etapa: "Multiplicación",
        especie: "<?= $especie ?>"
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
