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

// Consulta para reportes rechazados de Multiplicación
$sql_multiplicacion = "SELECT M.ID_Multiplicacion, V.Codigo_Variedad, V.Nombre_Variedad, M.Fecha_Siembra, M.Observaciones_Revision
    FROM multiplicacion M
    LEFT JOIN variedades V ON M.ID_Variedad = V.ID_Variedad
    WHERE M.Operador_Responsable = ? AND M.Estado_Revision = 'Rechazado'";
$stmt_m = $conn->prepare($sql_multiplicacion);
$stmt_m->bind_param("i", $ID_Operador);
$stmt_m->execute();
$result_m = $stmt_m->get_result();

// Consulta para reportes rechazados de Enraizamiento
$sql_enraizamiento = "SELECT E.ID_Enraizamiento, V.Codigo_Variedad, V.Nombre_Variedad, E.Fecha_Siembra, E.Observaciones_Revision
    FROM enraizamiento E
    LEFT JOIN variedades V ON E.ID_Variedad = V.ID_Variedad
    WHERE E.Operador_Responsable = ? AND E.Estado_Revision = 'Rechazado'";
$stmt_e = $conn->prepare($sql_enraizamiento);
$stmt_e->bind_param("i", $ID_Operador);
$stmt_e->execute();
$result_e = $stmt_e->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notificaciones de Rechazo - Operador</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
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
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
      </a>
      <h2>Notificaciones de Rechazo</h2>
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
    <div class="container mt-4">
      <h3>Tienes notificaciones de rechazo pendientes</h3>
      <?php if($result_m->num_rows == 0 && $result_e->num_rows == 0): ?>
        <div class="alert alert-info">No tienes notificaciones de rechazo.</div>
      <?php else: ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Variedad</th>
              <th>Fecha de Siembra</th>
              <th>Observaciones</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result_m->fetch_assoc()): ?>
            <tr>
              <td>Multiplicación</td>
              <td><?= $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad'] ?></td>
              <td><?= $row['Fecha_Siembra'] ?></td>
              <td><?= $row['Observaciones_Revision'] ?></td>
              <td>
                <a href="corregir_reporte.php?tipo=multiplicacion&id=<?= $row['ID_Multiplicacion'] ?>" class="btn btn-warning btn-sm">Corregir</a>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php while($row = $result_e->fetch_assoc()): ?>
            <tr>
              <td>Enraizamiento</td>
              <td><?= $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad'] ?></td>
              <td><?= $row['Fecha_Siembra'] ?></td>
              <td><?= $row['Observaciones_Revision'] ?></td>
              <td>
                <a href="corregir_reporte.php?tipo=enraizamiento&id=<?= $row['ID_Enraizamiento'] ?>" class="btn btn-warning btn-sm">Corregir</a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>
  <footer class="mt-4">
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    
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
