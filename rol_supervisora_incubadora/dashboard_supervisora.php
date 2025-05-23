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

if ((int) $_SESSION['Rol'] !== 4) {
    echo "<p class=\"error\">⚠️ Acceso denegado. Sólo Supervisora de Incubadora.</p>";
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Supervisor de Incubadora</title>
  <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  />
  <style>
    /* Resalta brevemente la tarjeta seleccionada */
    .highlight {
      animation: highlight 2s ease-out;
    }
    @keyframes highlight {
      from { background-color: #fffae6; }
      to   { background-color: transparent; }
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
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand me-3" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center"/>
        </a>
        <div>
          <h2>Bienvenida, Supervisora de Incubadora</h2>
          <p>Encargado de Suministro de material, limpieza de incubador e Inventarios</p>
        </div>
      </div>
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='../logout.php'">Cerrar sesión</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="container mt-4">
      <section class="dashboard-grid">
        <div class="card" data-card-id="inventario_materiales">
          <h2>📦 Inventario de Materiales</h2>
          <p>Agrega y actualiza existencias de pinzas, bisturíes, periódicos y trapos.</p>
          <a href="inventario_materiales.php">Ver detalles</a>
        </div>

        <div class="card" data-card-id="suministro_material">
          <h2>🚚 Suministro de Insumos</h2>
          <p>Asigna y despacha medio nutritivo y explantes según etapa.</p>
          <a href="suministro_material.php">Ver detalles</a>
        </div>
        <div class="card" id="card-vista-tuppers">
          <h2>📋 Existencias de Tuppers</h2>
          <p>Consulta todos los tuppers, sus estados y su trazabilidad completa.</p>
          <a href="existencias_tuppers.php" onclick="guardarScroll('card-vista-tuppers')">Ir a Vista</a>
        </div>
        <div class="card" id="card-seleccion-tuppers">
          <h2>🔬 Selección de Tuppers</h2>
          <p>Coordina la selección de tuppers para lavado.</p>
          <a href="seleccion_tuppers.php" onclick="guardarScroll('card-seleccion-tuppers')">Gestionar selección</a>
        </div>
        
        <div class="card" data-card-id="registro_datos_incubadora">
          <h2>📝 Registro Temperatura & Humedad 🌡</h2>
          <p>Captura diaria de condiciones del incubador.</p>
          <a href="registro_datos_incubadora.php">Ver detalles</a>
        </div>
        <div class="card" data-card-id="historial_completo_incubadora">
          <h2>📜 Historial de Parámetros</h2>
          <p>Consulta todos los registros de temperatura y humedad.</p>
          <a href="historial_completo_incubadora.php">Ver detalles</a>
        </div>
        <div class="card" data-card-id="inventario_etapa3">
          <h2>📦 Inventario Etapa 3</h2>
          <p>Control puntual del stock de material vegetativo</p>
          <a href="inventario_etapa3.php">Ver detalles</a>
        </div>

                  <div class="card card-ecas" id="card-desinfeccion">
            <h2>📋 Organización de material para clasificación</h2>
            <p>Organiza los materiales para clasificación.</p>
            <a
              href="organizacion_material_lavado.php"
              onclick="guardarScroll('card-desinfeccion')"
              >Ir a Registros</a
            >
          </div>
          <div class="card" id="card-limpieza-incubador">
            <h2>🧽 Registrar Limpieza de Incubador</h2>
            <p>Registrar repisas limpias por anaquel en el incubador.</p>
            <a
              href="limpieza_incubador.php"
              onclick="guardarScroll('card-limpieza-incubador')"
            >Ir al Registro</a>
        </div>
          <div class="card" id="card-historial-desinfeccion">
            <h2>🧼 Historial de Limpieza</h2>
            <p>Accede a las asignaciones de limpieza de repisas.</p>
            <a
              href="limpieza_repisas.php"
              onclick="guardarScroll('card-historial-desinfeccion')"
              >Ver Historial</a
            >
          </div>
      </section>
    </main>

    <footer class="text-center py-3">&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</footer>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const cards = document.querySelectorAll('.dashboard-grid .card');
      // Al hacer clic en cualquier enlace de tarjeta
      cards.forEach(card => {
        const link = card.querySelector('a');
        link.addEventListener('click', () => {
          const id = card.dataset.cardId;
          sessionStorage.setItem('lastCard', id);
        });
      });

      // Al cargar la página, leer y, si existe, hacer scroll y resaltar
      const last = sessionStorage.getItem('lastCard');
      if (last) {
        const target = document.querySelector(`.dashboard-grid .card[data-card-id="${last}"]`);
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          target.classList.add('highlight');
        }
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

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
  ></script>
</body>
</html>
