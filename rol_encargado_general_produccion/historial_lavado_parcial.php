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

if ((int) $_SESSION['Rol'] !== 5) {
    echo "<p class=\"error\">⚠️ Acceso denegado. Sólo Encargado General de Producción.</p>";
    exit;
}

// 2) Variables para el modal de sesión (3 min inactividad, aviso 1 min antes)
$sessionLifetime = 60 * 3;   // 180 s
$warningOffset   = 60 * 1;   // 60 s
$nowTs           = time();


$fechaFiltro = $_GET['fecha'] ?? date('Y-m-d');
$operadorFiltro = $_GET['operador'] ?? '';

$sql = "SELECT R.Fecha, R.Hora_Registro, R.Tuppers_Lavados, R.Observaciones,
               CONCAT(O.Nombre, ' ', O.Apellido_P, ' ', O.Apellido_M) AS Operador
        FROM reporte_lavado_parcial R
        JOIN operadores O ON R.ID_Operador = O.ID_Operador
        WHERE R.Fecha = ?
";

$params = [$fechaFiltro];
$types = 's';

if (!empty($operadorFiltro)) {
    $sql .= " AND R.ID_Operador = ?";
    $params[] = $operadorFiltro;
    $types .= 'i';
}

$sql .= " ORDER BY R.Hora_Registro DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$operadores = $conn->query("SELECT ID_Operador, CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) AS NombreCompleto FROM operadores WHERE Activo = 1 ORDER BY Nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial de Lavado Parcial</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
        </a>
        <div>
          <h2>Historial de Lavado Parcial</h2>
          <p>Consulta los avances registrados durante media jornada.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_egp.php'">
              🏠 Volver al Inicio
              </button>
            </div>
          </div>
        </nav>
      </div>
      <nav class="filter-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-2 mb-4" style="overflow-x:auto;">
  <div class="d-flex flex-column" style="min-width:140px;">
    <label for="filtro-fecha" class="small mb-1">Fecha</label>
    <input id="filtro-fecha" type="date" name="fecha" form="filtrosForm"
           class="form-control form-control-sm"
           value="<?= htmlspecialchars($fechaFiltro) ?>">
  </div>

  <div class="d-flex flex-column" style="min-width:160px;">
    <label for="filtro-operador" class="small mb-1">Operador</label>
    <select id="filtro-operador" name="operador" form="filtrosForm"
            class="form-select form-select-sm">
      <option value="">— Todos —</option>
      <?php while ($op = $operadores->fetch_assoc()): ?>
        <option value="<?= $op['ID_Operador'] ?>"
          <?= ($op['ID_Operador'] == $operadorFiltro) ? 'selected':''?>>
          <?= htmlspecialchars($op['NombreCompleto'])?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>

  <button form="filtrosForm" type="submit"
          class="btn-inicio btn btn-success btn-sm ms-auto">
    Filtrar
  </button>
</nav>
<!-- Formulario oculto para filtros -->
<form id="filtrosForm" method="GET" class="d-none"></form>
    </header>

    <main>
      <div class="section">
        <h2>📋 Reportes Registrados</h2>

        <!-- Tabla de resultados -->
        <table class="table">
          <thead>
            <tr>
              <th>📅 Fecha</th>
              <th>⏰ Hora</th>
              <th>👤 Operador</th>
              <th>🧴 Tuppers Lavados</th>
              <th>🗒 Observaciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['Fecha']) ?></td>
                  <td><?= date('H:i', strtotime($row['Hora_Registro'])) ?></td>
                  <td><?= htmlspecialchars($row['Operador']) ?></td>
                  <td><?= (int)$row['Tuppers_Lavados'] ?></td>
                  <td><?= htmlspecialchars($row['Observaciones'] ?? '-') ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5">No hay registros disponibles.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
<!-- Modal de advertencia de sesión + Ping por interacción que reinicia timers -->
<script>
(function(){
  let modalShown = false,
      warningTimer,
      expireTimer;

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
    document.getElementById('keepalive-btn').addEventListener('click', () => {
      cerrarModalYReiniciar(); // 🔥 Aquí aplicamos el cambio
    });
  }

  function cerrarModalYReiniciar() {
    // 🔥 Cerrar modal inmediatamente
    const modal = document.getElementById('session-warning');
    if (modal) modal.remove();
    reiniciarTimers(); // Reinicia el temporizador visual

    // 🔄 Enviar ping a la base de datos en segundo plano
    fetch('../keepalive.php', { credentials: 'same-origin' })
      .then(res => res.json())
      .then(data => {
        if (data.status !== 'OK') {
          alert('No se pudo extender la sesión');
        }
      })
      .catch(() => {}); // Silenciar errores de red
  }

  function reiniciarTimers() {
    START_TS   = Date.now();
    modalShown = false;
    clearTimeout(warningTimer);
    clearTimeout(expireTimer);
    scheduleTimers();
  }

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

  ['click', 'keydown'].forEach(event => {
    document.addEventListener(event, () => {
      reiniciarTimers();
      fetch('../keepalive.php', { credentials: 'same-origin' }).catch(() => {});
    });
  });

  scheduleTimers();
})();
</script>

</body>
</html>
