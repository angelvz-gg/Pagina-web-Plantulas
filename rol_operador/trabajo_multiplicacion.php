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
$fecha_actual = date('Y-m-d');

// Buscar la asignacion activa de este operador
$sql = "SELECT * FROM asignaciones_multiplicacion 
        WHERE Operador_Asignado = ? AND Estado = 'Asignado'
        ORDER BY Fecha_Registro DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ID_Operador);
$stmt->execute();
$result = $stmt->get_result();
$asignaciones = $result->fetch_all(MYSQLI_ASSOC);

// Buscar asignación seleccionada por GET
$asignacion = null;
if (isset($_GET['asignacion'])) {
    $idSeleccionada = intval($_GET['asignacion']);
    foreach ($asignaciones as $a) {
        if ($a['ID_Asignacion'] == $idSeleccionada) {
            $asignacion = $a;
            break;
        }
    }
}

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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["asignacion_a_trabajar"]) && isset($_POST["numero_brotes"])) {
    $id_asignacion = intval($_POST["asignacion_a_trabajar"]);
    $num_brotes = intval($_POST["numero_brotes"]);
    $tasa = floatval($_POST["tasa_multiplicacion"]);
    $id_medio = intval($_POST["id_medio_nutritivo"]);
    $tupper_lleno = intval($_POST["tupper_lleno"]);
    $tupper_vacio = intval($_POST["tupper_vacios"]);

    // Obtener la asignación para validar
    $stmt = $conn->prepare("SELECT * FROM asignaciones_multiplicacion WHERE ID_Asignacion = ? AND Operador_Asignado = ?");
    $stmt->bind_param("ii", $id_asignacion, $ID_Operador);
    $stmt->execute();
    $res = $stmt->get_result();
    $asignacion = $res->fetch_assoc();

    if (!$asignacion) {
        echo "<script>alert('❌ Asignación no válida.');</script>";
    } elseif ($num_brotes < 1 || $num_brotes > $asignacion['Brotes_Asignados']) {
        echo "<script>alert('❌ Los brotes deben ser entre 1 y {$asignacion['Brotes_Asignados']}.');</script>";
    } elseif ($tasa < 1.00 || $tasa > 50.00) {
        echo "<script>alert('❌ La tasa debe estar entre 1.00 y 50.00.');</script>";
    } else {
        // Obtener ID_Variedad desde Código_Variedad
        $id_variedad = null;
        $stmt_var = $conn->prepare("SELECT ID_Variedad FROM variedades WHERE Codigo_Variedad = ? LIMIT 1");
        $stmt_var->bind_param("s", $asignacion['Codigo_Variedad']);
        $stmt_var->execute();
        $res_var = $stmt_var->get_result();
        if ($row = $res_var->fetch_assoc()) {
            $id_variedad = intval($row['ID_Variedad']);
        }

        if (!$id_variedad) {
            echo "<script>alert('❌ Variedad no encontrada.');</script>";
        } else {
            // Insertar registro
            $sql_insert = "INSERT INTO multiplicacion 
                (ID_Variedad, ID_MedioNutritivo, Cantidad_Dividida, Fecha_Siembra, Tasa_Multiplicacion, 
                 Tuppers_Llenos, Tuppers_Desocupados, Operador_Responsable, Estado_Revision)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("iiissiii", 
                $id_variedad, 
                $id_medio, 
                $num_brotes, 
                $fecha_actual, 
                $tasa, 
                $tupper_lleno, 
                $tupper_vacio, 
                $ID_Operador
            );

            if ($stmt->execute()) {
                // Marcar asignación como trabajada
                $stmt_update = $conn->prepare("UPDATE asignaciones_multiplicacion SET Estado = 'Trabajado' WHERE ID_Asignacion = ?");
                $stmt_update->bind_param("i", $id_asignacion);
                $stmt_update->execute();

                header("Location: trabajo_multiplicacion.php?success=1");
                exit;
            } else {
                echo "<script>alert('❌ Error al guardar el registro.');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trabajo en Multiplicación</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card-asignacion {
  background-color: #f8f9fa;
  border: 1px solid #ced4da;
  border-radius: 10px;
  padding: 0.8rem;
  height: 115px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  display: flex;
  justify-content: center;
  text-align: center;
}
.card-asignacion:hover,
.card-asignacion.selected {
  background-color: #d6eaff;
  box-shadow: 0 3px 6px rgba(0,0,0,0.1);
}
.card-asignacion h6 {
  font-size: 1.05rem;
  font-weight: 700;
  margin-bottom: 0.4rem;
}
.card-asignacion p {
  font-size: 0.95rem;
  margin: 0.2rem 0;
}
</style>

<style>
  #formulario-trabajo {
    opacity: 0;
    transform: translateY(20px);
    animation: slideFadeIn 0.6s ease forwards;
  }

  @keyframes slideFadeIn {
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>

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
      <h2>Trabajo en Multiplicación</h2>
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

  <main class="container">
<?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success mt-3">✅ Registro guardado correctamente.</div>
<?php endif; ?>

<?php if (!empty($asignaciones)): ?>
  <div class="container mt-4">
  <h4 class="mb-3">📦 Asignaciones pendientes</h4>
  <div class="row row-cols-1 row-cols-md-3 g-3">
    <?php foreach ($asignaciones as $asignacion): ?>
      <div class="col">
        <div class="card card-asignacion <?= (isset($_POST['asignacion_a_trabajar']) && $_POST['asignacion_a_trabajar'] == $asignacion['ID_Asignacion']) ? 'selected' : '' ?>" data-asignacion="<?= $asignacion['ID_Asignacion'] ?>">
          <h6><?= htmlspecialchars($asignacion['Codigo_Variedad']) ?> – <?= htmlspecialchars($asignacion['Nombre_Variedad']) ?></h6>
          <p><strong>Brotes:</strong> <?= $asignacion['Brotes_Asignados'] ?></p>
          <p><strong>Fecha:</strong> <?= $asignacion['Fecha_Registro'] ?></p>
          <p><strong>Estado:</strong> <?= $asignacion['Estado'] ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

  <?php if (isset($asignacion) && $asignacion): ?>
  <div id="formulario-trabajo" class="container mt-5 border-top pt-4">
    <h5 class="mb-3">✍️ Registro de trabajo – <?= htmlspecialchars($asignacion['Codigo_Variedad'] . ' – ' . $asignacion['Nombre_Variedad']) ?></h5>
    <form method="POST" class="row g-3 border p-4 bg-light rounded shadow-sm">
      <input type="hidden" name="asignacion_a_trabajar" value="<?= $asignacion['ID_Asignacion'] ?>">

      <div class="col-md-4">
        <label class="form-label">Fecha de Reporte:</label>
        <input type="text" class="form-control" value="<?= $fecha_actual ?>" readonly>
      </div>

      <div class="col-md-4">
        <label class="form-label">Tasa de Multiplicación:</label>
        <input type="text" class="form-control" name="tasa_multiplicacion" required>
      </div>

      <div class="col-md-4">
        <label class="form-label">Número de Brotes:</label>
        <input type="number" class="form-control" name="numero_brotes" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Medio Nutritivo:</label>
        <input type="text" id="medio_nutritivo" class="form-control" placeholder="Selecciona el código sugerido automáticamente" required>
        <input type="hidden" id="id_medio_nutritivo" name="id_medio_nutritivo">
        <small class="text-muted">🔍 Escribe para ver los medios nutritivos recomendados para esta especie.</small>
      </div>

      <div class="col-md-3">
        <label class="form-label">Tuppers Llenos:</label>
        <input type="number" class="form-control" name="tupper_lleno" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Tuppers Vacíos:</label>
        <input type="number" class="form-control" name="tupper_vacios" required>
      </div>

      <div class="col-12 text-end">
        <button type="submit" class="btn btn-success">✅ Guardar información</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php else: ?>
  <div class="alert alert-warning">No tienes asignaciones pendientes de multiplicación.</div>
<?php endif; ?>
  </main>

  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const formBlock = document.getElementById("formulario-trabajo");
    if (formBlock) {
      setTimeout(() => {
        formBlock.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 200); // pequeño retraso para asegurar que el DOM esté cargado
    }
  });

document.querySelectorAll(".card-asignacion").forEach(card => {
  card.addEventListener("click", () => {
    const id = card.dataset.asignacion;
    window.location.href = `trabajo_multiplicacion.php?asignacion=${id}`;
  });
});

</script>

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
