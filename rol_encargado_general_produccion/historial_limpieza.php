<?php
// 0) Mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1) Validar sesión y rol
require_once __DIR__ . '/../session_manager.php';
require_once __DIR__ . '/../db.php';

date_default_timezone_set('America/Mexico_City');
$conn->query("SET time_zone = '-06:00'");


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


// Fecha actual para filtros
$fechaHoy = date('Y-m-d');
$fechaDesde = $_GET['fecha_desde'] ?? $fechaHoy;
$fechaHasta = $_GET['fecha_hasta'] ?? $fechaHoy;
$estadoFiltro = $_GET['estado'] ?? '';

// Consulta SQL
$sql = "SELECT rl.ID_Limpieza, rl.Fecha, rl.Area, rl.Estado_Limpieza, 
               CONCAT(o.Nombre, ' ', o.Apellido_P, ' ', o.Apellido_M) AS NombreCompleto
        FROM registro_limpieza rl
        JOIN operadores o ON rl.ID_Operador = o.ID_Operador
        WHERE rl.Fecha BETWEEN ? AND ?";
$params = [$fechaDesde, $fechaHasta];
$types = 'ss';

if (!empty($estadoFiltro)) {
    $sql .= " AND rl.Estado_Limpieza = ?";
    $params[] = $estadoFiltro;
    $types .= 's';
}

$sql .= " ORDER BY rl.Fecha DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $fecha, $area, $estado, $nombreCompleto);

// Anular asignación
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["anular_id"])) {
    $idAnular = $_POST["anular_id"];
    $anularStmt = $conn->prepare("UPDATE registro_limpieza SET Estado_Limpieza = 'Anulado' WHERE ID_Limpieza = ?");
    $anularStmt->bind_param("i", $idAnular);
    $anularStmt->execute();
    header("Location: historial_limpieza.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial de Limpieza</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
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
        <a class="navbar-brand">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
        </a>
        <div>
          <h2>Historial de Limpieza</h2>
          <p>Consulta de asignaciones realizadas o anuladas.</p>
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

      <nav class="filter-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-2" style="overflow-x:auto;">
  <div class="d-flex flex-column" style="min-width:120px;">
    <label for="filtro-fecha-desde" class="small mb-1">Desde</label>
    <input id="filtro-fecha-desde" type="date" name="fecha_desde" form="filtrosForm"
           class="form-control form-control-sm"
           value="<?= htmlspecialchars($fechaDesde) ?>">
  </div>

  <div class="d-flex flex-column" style="min-width:120px;">
    <label for="filtro-fecha-hasta" class="small mb-1">Hasta</label>
    <input id="filtro-fecha-hasta" type="date" name="fecha_hasta" form="filtrosForm"
           class="form-control form-control-sm"
           value="<?= htmlspecialchars($fechaHasta) ?>">
  </div>

  <div class="d-flex flex-column" style="min-width:140px;">
    <label for="filtro-estado" class="small mb-1">Estado</label>
    <select id="filtro-estado" name="estado" form="filtrosForm"
            class="form-select form-select-sm">
      <option value="">— Todos —</option>
      <option value="Pendiente" <?= $estadoFiltro==='Pendiente'  ? 'selected':''?>>Pendiente</option>
      <option value="Realizada" <?= $estadoFiltro==='Realizada'  ? 'selected':''?>>Realizada</option>
      <option value="Anulado"   <?= $estadoFiltro==='Anulado'    ? 'selected':''?>>Anulado</option>
    </select>
  </div>

  <button form="filtrosForm" type="submit"
          class="btn-inicio btn btn-success btn-sm ms-auto">
    Filtrar
  </button>
    </header>

    <main>
        <table class="table">
          <thead>
            <tr>
              <th>🆔 ID</th>
              <th>👤 Operador</th>
              <th>📅 Fecha</th>
              <th>🧽 Área</th>
              <th>📌 Estado</th>
              <th>⚙️ Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($stmt->num_rows > 0): ?>
              <?php while ($stmt->fetch()): ?>
                <?php $esHoy = ($fecha === $fechaHoy); ?>
                <tr>
                  <td><?= $id ?></td>
                  <td><?= htmlspecialchars($nombreCompleto) ?></td>
                  <td><?= $fecha ?></td>
                  <td><?= htmlspecialchars($area) ?></td>
                  <td><?= $estado ?></td>
                  <td>
                    <?php if ($estado !== 'Anulado' && $esHoy): ?>
                      <form method="POST" onsubmit="return confirm('¿Estás seguro de anular esta asignación?');">
                        <input type="hidden" name="anular_id" value="<?= $id ?>">
                        <button type="submit" class="btn-anular">🗑 Anular</button>
                      </form>
                    <?php elseif ($estado !== 'Anulado'): ?>
                      <span class="text-muted-small">Solo hoy</span>
                    <?php else: ?>
                      <span class="text-muted">N/A</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center">No hay asignaciones para hoy.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script>
    function toggleFiltros() {
      const filtros = document.getElementById("filtros");
      const boton = document.getElementById("btnFiltros");
      const visible = filtros.style.display === "block";
      filtros.style.display = visible ? "none" : "block";
      boton.innerText = visible ? "🔍 Mostrar filtros" : "❌ Ocultar filtros";
    }
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
