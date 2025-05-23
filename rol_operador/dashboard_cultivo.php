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

$ID_Operador = $_SESSION["ID_Operador"] ?? null;
$isSupervisor = false;
$isResponsableCajas = false;
$tieneAsignacionesMultiplicacion = false;

if ($ID_Operador) {
    // Verificar si el operador es Supervisor en la asignación de lavado
    $sql_rol = "SELECT Rol FROM asignacion_lavado 
                WHERE ID_Operador = ? AND Fecha = CURDATE() AND Rol = 'Supervisor'";
    $stmt_rol = $conn->prepare($sql_rol);
    $stmt_rol->bind_param("i", $ID_Operador);
    $stmt_rol->execute();
    $result_rol = $stmt_rol->get_result();
    $isSupervisor = $result_rol->num_rows > 0;

    // Consultar reportes rechazados en Multiplicación
    $stmt_m = $conn->prepare("SELECT COUNT(*) as total FROM multiplicacion WHERE Operador_Responsable = ? AND Estado_Revision = 'Rechazado'");
    $stmt_m->bind_param("i", $ID_Operador);
    $stmt_m->execute();
    $result_m = $stmt_m->get_result();
    $countMultiplicacion = 0;
    if ($row = $result_m->fetch_assoc()) {
        $countMultiplicacion = $row['total'];
    }

    // Consultar reportes rechazados en Enraizamiento
    $stmt_e = $conn->prepare("SELECT COUNT(*) as total FROM enraizamiento WHERE Operador_Responsable = ? AND Estado_Revision = 'Rechazado'");
    $stmt_e->bind_param("i", $ID_Operador);
    $stmt_e->execute();
    $result_e = $stmt_e->get_result();
    $countEnraizamiento = 0;
    if ($row = $result_e->fetch_assoc()) {
        $countEnraizamiento = $row['total'];
    }

    // Total de reportes rechazados pendientes de corrección
    $correccionesPendientes = $countMultiplicacion + $countEnraizamiento;

    // Verificar si es Responsable de Cajas Negras
    $stmt_cajas = $conn->prepare("SELECT COUNT(*) as total FROM responsables_cajas WHERE ID_Operador = ?");
    $stmt_cajas->bind_param("i", $ID_Operador);
    $stmt_cajas->execute();
    $result_cajas = $stmt_cajas->get_result();
    if ($row = $result_cajas->fetch_assoc()) {
        $isResponsableCajas = $row['total'] > 0;
    }

    // Verificar si el operador tiene asignaciones de multiplicación pendientes
    $stmt_multiplicacion = $conn->prepare("SELECT COUNT(*) as total FROM asignaciones_multiplicacion WHERE Operador_Asignado = ? AND Estado = 'Asignado'");
    $stmt_multiplicacion->bind_param("i", $ID_Operador);
    $stmt_multiplicacion->execute();
    $result_multiplicacion = $stmt_multiplicacion->get_result();
    if ($row = $result_multiplicacion->fetch_assoc()) {
        $tieneAsignacionesMultiplicacion = $row['total'] > 0;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel Operador</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
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
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
        </a>
        <div>
          <h2>Panel de Operador</h2>
          <p>Mantén el registro de actividades</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='../logout.php'">
                Cerrar Sesión
              </button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
      <section class="dashboard-grid">
        <div class="card">
          <h2>🌿 Trabajo en Disección</h2>
          <p>Revisa tus etapas asignadas.</p>
          <a href="reporte_diseccion.php">Trabajo en Disección</a>
        </div>
        <div class="card">
          <h2>📝 Asignación de Limpieza</h2>
          <p>Revisa qué área tienes asignada para limpieza.</p>
          <a href="area_limpieza.php">Ver detalles</a>
        </div>
        <div class="card">
          <h2>🗂 Asignación para Clasificación</h2>
          <p>Revisa tu rol para la clasificación de plantas.</p>
          <a href="relacion_lavado.php">Ver detalles</a>
        </div>

        <?php if (isset($correccionesPendientes) && $correccionesPendientes > 0): ?>
          <div class="card">
            <h2>⚠️ Correcciones Pendientes</h2>
            <p>Tienes <?= $correccionesPendientes ?> reporte(s) rechazado(s) que requieren corrección.</p>
            <a href="notificaciones_operador.php">Corregir Reporte</a>
          </div>
        <?php endif; ?>

        <?php if ($isSupervisor): ?>
          <div class="card">
            <h2>📊 Historial de Lavado Parcial</h2>
            <p>Supervisa los avances registrados por el equipo.</p>
            <a href="historial_lavado_parcial.php">Ver Historial</a>
          </div>
        <?php endif; ?>

        <?php if ($isResponsableCajas): ?>
          <div class="card">
            <h2>📦 Preparación de Cajas Negras</h2>
            <p>Accede a las órdenes asignadas y organiza tuppers.</p>
            <a href="preparacion_cajas.php">Preparar Cajas</a>
          </div>
        <?php endif; ?>

        <?php if ($tieneAsignacionesMultiplicacion): ?>
          <div class="card">
            <h2>🧬 Trabajo en Multiplicación</h2>
            <p>Tienes asignaciones pendientes de multiplicación para trabajar.</p>
            <a href="trabajo_multiplicacion.php">Ver mis Asignaciones</a>
          </div>
        <?php endif; ?>

      </section>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
