<?php
// 0) Mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1) Validar sesión y rol
require_once __DIR__ . '/../session_manager.php';
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['ID_Operador'])) {
    header('Location: ../login.php?mensaje=Debe iniciar sesión');
    exit;
}
$ID_Operador = (int) $_SESSION['ID_Operador'];

if ((int) $_SESSION['Rol'] !== 2) {
    echo "<p class=\"error\">⚠️ Acceso denegado. Solo Operador.</p>";
    exit;
}
// 2) Variables para el modal de sesión (3 min inactividad, aviso 1 min antes)
$sessionLifetime = 60 * 3;   // 180 s
$warningOffset   = 60 * 1;   // 60 s
$nowTs           = time();


$ID_Operador = $_SESSION['ID_Operador'] ?? null;
date_default_timezone_set('America/Mexico_City');
$fecha_actual = date('Y-m-d H:i:s');


// --- NUEVO BLOQUE: cargar etapas Multiplicación y Enraizamiento ---
$etapas = [];
$query = "SELECT ID_Etapa, Descripcion FROM catalogo_etapas WHERE ID_Etapa IN (2, 3)";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $etapas[] = $row;
}

// Autocompletado AJAX para variedad
if (isset($_GET['action']) && $_GET['action'] === 'buscar_variedad') {
    $term = $_GET['term'] ?? '';
    $sql = "SELECT ID_Variedad, Codigo_Variedad, Nombre_Variedad, Especie 
            FROM variedades 
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
$sql = "SELECT * FROM asignaciones WHERE ID_Operador = ? AND Fecha = ? LIMIT 1";
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
    $tabla = ($ID_Etapa == 1) ? "multiplicacion" : "enraizamiento";
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
    $tasa = floatval($_POST["tasa_multiplicacion"]);
    $id_medio = $_POST["id_medio_nutritivo"];
    $num_brotes = $_POST["numero_brotes"];
    $tupper_lleno = $_POST["tupper_lleno"];
    $tupper_vacio = $_POST["tupper_vacios"];
    $etapa = $_POST["etapa"] ?? $ID_Etapa;
    $variedad = $_POST["id_variedad"] ?? $ID_Variedad;

    $tabla = ($etapa == 1) ? "multiplicacion" : "enraizamiento";

if ($num_brotes < 1 || $num_brotes > 1000) {
    echo "<script>alert('❌ Número de brotes debe ser entre 1 y 1000'); window.history.back();</script>";
    exit;
}
if ($tupper_lleno < 1 || $tupper_lleno > 500 || $tupper_vacio < 1 || $tupper_vacio > 500) {
    echo "<script>alert('❌ Tuppers deben estar entre 1 y 500'); window.history.back();</script>";
    exit;
}


    if ($reporteExistente) {
        $sql = "UPDATE $tabla 
                SET Tasa_Multiplicacion = ?, ID_MedioNutritivo = ?, Cantidad_Dividida = ?, 
                    Tuppers_Llenos = ?, Tuppers_Desocupados = ?, Estado_Revision = 'Pendiente' 
                WHERE ID_Asignacion = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("diiiii", $tasa, $id_medio, $num_brotes, $tupper_lleno, $tupper_vacio, $ID_Asignacion);
    } else {
        $sql = "INSERT INTO $tabla 
                (ID_Asignacion, Fecha_Siembra, ID_Variedad, Tasa_Multiplicacion, ID_MedioNutritivo, 
                 Cantidad_Dividida, Tuppers_Llenos, Tuppers_Desocupados, Estado_Revision, Operador_Responsable) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issdiiiis", $ID_Asignacion, $fecha_actual, $variedad, $tasa, $id_medio, $num_brotes, $tupper_lleno, $tupper_vacio, $ID_Operador);
    }

    if ($stmt->execute()) {
        if ($ID_Asignacion) {
            $stmt = $conn->prepare("UPDATE asignaciones SET Estado = 'Completado' WHERE ID_Asignacion = ?");
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reporte de Siembra</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script>
    const SESSION_LIFETIME = <?= $sessionLifetime * 1000 ?>;
    const WARNING_OFFSET   = <?= $warningOffset   * 1000 ?>;
    let START_TS         = <?= $nowTs           * 1000 ?>;
  </script>
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
              🏠 Volver al Inicio
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
              <option value="">-- Selecciona una etapa --</option>
              <?php foreach ($etapas as $etapa): ?>
              <option value="<?= $etapa['ID_Etapa'] ?>"><?= htmlspecialchars($etapa['Descripcion']) ?></option>
          <?php endforeach; ?>
          </select>

            <label for="nombre_variedad">Buscar Variedad:</label>
            <input type="text" id="nombre_variedad" required>
            <input type="hidden" id="id_variedad" name="id_variedad">
            <input type="hidden" id="especie_variedad">
          <?php endif; ?>

          <label for="tasa_multiplicacion">Tasa de Multiplicación:</label>
          <input type="number" name="tasa_multiplicacion" step="0.01" required <?= $editable ? '' : 'readonly' ?>>

          <label for="medio_nutritivo">Medio Nutritivo:</label>
          <input type="text" id="medio_nutritivo" <?= $editable ? '' : 'readonly' ?> required>
          <input type="hidden" id="id_medio_nutritivo" name="id_medio_nutritivo">

          <label for="numero_brotes">Número de Brotes:</label>
          <input type="number" name="numero_brotes" min="1" max="1000" required <?= $editable ? '' : 'readonly' ?>>

          <label for="tupper_lleno">Tuppers Llenos:</label>
          <input type="number" name="tupper_lleno" min="1" max="500" required <?= $editable ? '' : 'readonly' ?>>

          <label for="tupper_vacios">Tuppers Vacíos:</label>
          <input type="number" name="tupper_vacios" min="1" max="500" required <?= $editable ? '' : 'readonly' ?>>

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

$('form').on('submit', function () {
  if (!$('#id_variedad').val()) {
    alert('❌ Por favor selecciona una variedad válida desde la lista sugerida.');
    $('#nombre_variedad').addClass('is-invalid').focus(); // ⬅️ aquí aplicamos el borde rojo
    return false;
  } else {
    $('#nombre_variedad').removeClass('is-invalid'); // Quita el borde si sí es válida
  }
});
</script>


 <!-- Modal de advertencia de sesión -->
 <script>
 (function(){
  // Estado y referencias a los temporizadores
  let modalShown = false,
      warningTimer,
      expireTimer;

  // Función para mostrar el modal de aviso
  function showModal() {
    modalShown = true;
    const modalHtml = `
      <div id="session-warning" class="modal-overlay">
        <div class="modal-box">
          <p>Tu sesión va a expirar pronto. ¿Deseas mantenerla activa?</p>
          <button id="keepalive-btn" class="btn-keepalive">Seguir activo</button>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document
      .getElementById('keepalive-btn')
      .addEventListener('click', keepSessionAlive);
  }

  // Función para llamar a keepalive.php y, si es OK, reiniciar los timers
  function keepSessionAlive() {
    fetch('../keepalive.php', { credentials: 'same-origin' })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'OK') {
          // Quitar el modal
          const modal = document.getElementById('session-warning');
          if (modal) modal.remove();

          // Reiniciar tiempo de inicio
          START_TS   = Date.now();
          modalShown = false;

          // Reprogramar los timers
          clearTimeout(warningTimer);
          clearTimeout(expireTimer);
          scheduleTimers();
        } else {
          alert('No se pudo extender la sesión');
        }
      })
      .catch(() => alert('Error al mantener viva la sesión'));
  }

  // Configura los timeouts para mostrar el aviso y para la expiración real
  function scheduleTimers() {
    const elapsed     = Date.now() - START_TS;
    const warnAfter   = SESSION_LIFETIME - WARNING_OFFSET;
    const expireAfter = SESSION_LIFETIME;

    warningTimer = setTimeout(showModal, Math.max(warnAfter - elapsed, 0));

    expireTimer = setTimeout(() => {
      if (!modalShown) {
        showModal();
      } else {
        window.location.href = '/plantulas/login.php?mensaje='
          + encodeURIComponent('Sesión caducada por inactividad');
      }
    }, Math.max(expireAfter - elapsed, 0));
  }

  // Inicia la lógica al cargar el script
  scheduleTimers();
})();
  </script>
</body>
</html>
