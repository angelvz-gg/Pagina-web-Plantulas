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

if ((int) $_SESSION['Rol'] !== 6) {
    echo "<p class=\"error\">⚠️ Acceso denegado. Sólo Gerente de Producción de Laboratorio.</p>";
    exit;
}
// 2) Variables para el modal de sesión (3 min inactividad, aviso 1 min antes)
$sessionLifetime = 60 * 3;   // 180 s
$warningOffset   = 60 * 1;   // 60 s
$nowTs           = time();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Gerente de Producción de Laboratorio</title>
  <link rel="stylesheet" href="../style.css?v=<?= time() ?>" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
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
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124" />
        </a>
        <div>
          <h2>Gerente de Producción de Laboratorio</h2>
          <p>Resumen de tus actividades y accesos rápidos.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='../logout.php'">Cerrar Sesión</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
    <!-- 🔷 PLANIFICACIÓN 
      <h3 style="margin-left: 2rem; margin-top: 1rem;">📋 Planificación</h3>
      <section class="dashboard-grid">
        <div class="card" id="card-planificar">
          <h2>📝 Crear Planificación</h2>
          <p>Planifica nuevas metas de producción.</p>
          <a href="planificacion_produccion.php" onclick="guardarScroll('card-planificar')">Planificar</a>
        </div>
        <div class="card" id="card-seguimiento">
          <h2>📊 Seguimiento de Planificaciones</h2>
          <p>Consulta el estado, responsables y avances.</p>
          <a href="seguimiento_planificacion.php" onclick="guardarScroll('card-seguimiento')">Ver seguimiento</a>
        </div>
        <div class="card" id="card-comparativa">
          <h2>📈 Producción vs Planificación</h2>
          <p>Compara lo producido con lo planificado.</p>
          <a href="comparar_produccion.php" onclick="guardarScroll('card-comparativa')">Ver comparativa</a>
        </div>
        <div class="card" id="card-auditoria">
          <h2>🕵️ Auditoría de Planificaciones</h2>
          <p>Consulta los cambios hechos a cada planificación.</p>
          <a href="auditoria_planificaciones.php" onclick="guardarScroll('card-auditoria')">Ver auditoría</a>
        </div>
      </section>
-->

      <!-- 🔷 GESTIÓN OPERATIVA -->
      <h3 style="margin-left: 2rem; margin-top: 2rem;">🔧 Gestión Operativa</h3>
      <section class="dashboard-grid">
        <div class="card" id="card-vista-tuppers">
          <h2>📋 Vista General de Tuppers</h2>
          <p>Consulta todos los tuppers, sus estados y su trazabilidad completa.</p>
          <a href="vista_general_tuppers.php" onclick="guardarScroll('card-vista-tuppers')">Ir a Vista</a>
        </div>

        <div class="card" id="card-distribucion">
          <h2>🗂 Asignación para orden de cajas</h2>
          <p>Organiza el trabajo del personal.</p>
          <a href="distribucion_trabajo.php" onclick="guardarScroll('card-distribucion')">Organizar tareas</a>
        </div>

        <div class="card" id="card-ordenes-lavado">
          <h2>📦 Estatus de ordenes para clasificación </h2>
          <p>Consulta y administra las órdenes enviadas para clasificación de plantas.</p>
          <a href="panel_ordenes_lavado.php" onclick="guardarScroll('card-ordenes-lavado')">Revisar estado de órdenes</a>
        </div>

        <div class="card" id="card-ver-asignaciones">
          <h2>📝 Acciones para ordenes de clasificación</h2>
          <p>Consulta y administra asignaciones pasadas y activas para clasficación.</p>
          <a href="verificar_asignaciones.php" onclick="guardarScroll('card-ver-asignaciones')">Ver asignaciones</a>
        </div>
<!--
        <div class="card" id="card-rendimiento">
          <h2>👷 Control de Rendimiento</h2>
          <p>Revisa la productividad de cada operario.</p>
          <a href="rendimiento_personal.php" onclick="guardarScroll('card-rendimiento')">Ver rendimiento</a>
        </div>
-->
      </section>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

  <script>
    // Guardar el ID de la tarjeta clickeada
    function guardarScroll(cardId) {
      localStorage.setItem('ultima_tarjeta_click', cardId);
    }

    // Al cargar la página, movernos a la última tarjeta
    document.addEventListener('DOMContentLoaded', function() {
      const cardId = localStorage.getItem('ultima_tarjeta_click');
      if (cardId) {
        const card = document.getElementById(cardId);
        if (card) {
          card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        localStorage.removeItem('ultima_tarjeta_click');
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
