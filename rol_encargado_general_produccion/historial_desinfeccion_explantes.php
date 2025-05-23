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

// Parámetros de búsqueda
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$busqueda_variedad = $_GET['busqueda_variedad'] ?? '';

// Consulta base
$sql = "SELECT D.ID_Desinfeccion, D.ID_Variedad, D.Explantes_Iniciales, D.Explantes_Desinfectados,
               D.FechaHr_Desinfeccion, D.HrFn_Desinfeccion, D.Estado_Desinfeccion,
               O.Nombre, O.Apellido_P, O.Apellido_M,
               V.Codigo_Variedad, V.Nombre_Variedad
        FROM desinfeccion_explantes D
        LEFT JOIN operadores O ON D.Operador_Responsable = O.ID_Operador
        LEFT JOIN variedades V ON D.ID_Variedad = V.ID_Variedad
        WHERE 1";

// Filtros dinámicos
$params = [];
$types = "";

if (!empty($fecha_desde)) {
    $sql .= " AND D.FechaHr_Desinfeccion >= ?";
    $params[] = $fecha_desde . " 00:00:00";
    $types .= "s";
}
if (!empty($fecha_hasta)) {
    $sql .= " AND D.FechaHr_Desinfeccion <= ?";
    $params[] = $fecha_hasta . " 23:59:59";
    $types .= "s";
}
if (!empty($busqueda_variedad)) {
    $sql .= " AND (V.Codigo_Variedad LIKE ? OR V.Nombre_Variedad LIKE ?)";
    $term = '%' . $busqueda_variedad . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= "ss";
}

$sql .= " ORDER BY D.FechaHr_Desinfeccion DESC";
$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Historial de Desinfección</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script>
    const SESSION_LIFETIME = <?= $sessionLifetime * 1000 ?>;
    const WARNING_OFFSET   = <?= $warningOffset   * 1000 ?>;
    let START_TS         = <?= $nowTs           * 1000 ?>;
  </script>
</head>
<body>
<div class="contenedorpagina">

  <header>
    <div class="encabezado">
      <a class="navbar-brand"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
      <h2>Historial de Desinfección de Explantes</h2>
      <div></div>
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

      <!-- Filtros -->
<nav class="filter-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-2" style="overflow-x:auto;">
  <div class="d-flex flex-column" style="min-width:120px;">
    <label for="filtro-fecha-desde" class="small mb-1">Desde</label>
    <input id="filtro-fecha-desde" type="date" name="fecha_desde" form="filtrosForm"
           class="form-control form-control-sm"
           value="<?= htmlspecialchars($fecha_desde) ?>">
  </div>

  <div class="d-flex flex-column" style="min-width:120px;">
    <label for="filtro-fecha-hasta" class="small mb-1">Hasta</label>
    <input id="filtro-fecha-hasta" type="date" name="fecha_hasta" form="filtrosForm"
           class="form-control form-control-sm"
           value="<?= htmlspecialchars($fecha_hasta) ?>">
  </div>

  <div class="d-flex flex-column" style="min-width:140px;">
    <label for="filtro-variedad" class="small mb-1">Variedad</label>
    <input id="filtro-variedad" type="text" name="busqueda_variedad" form="filtrosForm"
           class="form-control form-control-sm"
           placeholder="Nombre o Código"
           value="<?= htmlspecialchars($busqueda_variedad) ?>">
  </div>

  <button form="filtrosForm" type="submit"
          class="btn-inicio btn btn-success btn-sm ms-auto">
    Filtrar
  </button>
</nav>
  </header>

  <main>
    <!-- Tabla -->
    <div class="table-responsive">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>Código Variedad</th>
            <th>Nombre Variedad</th>
            <th>Explantes Iniciales</th>
            <th>Desinfectados</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Estado</th>
            <th>Responsable</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
              <td><?= $row['ID_Desinfeccion'] ?></td>
              <td><?= $row['Codigo_Variedad'] ?? '-' ?></td>
              <td><?= $row['Nombre_Variedad'] ?? '-' ?></td>
              <td><?= $row['Explantes_Iniciales'] ?></td>
              <td><?= $row['Explantes_Desinfectados'] ?? '-' ?></td>
              <td><?= $row['FechaHr_Desinfeccion'] ?></td>
              <td><?= $row['HrFn_Desinfeccion'] ?? '-' ?></td>
              <td><?= $row['Estado_Desinfeccion'] ?></td>
              <td><?= $row['Nombre'] . ' ' . $row['Apellido_P'] . ' ' . $row['Apellido_M'] ?></td>
            </tr>
          <?php } ?>
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
    filtros.style.display = filtros.style.display === "none" ? "block" : "none";
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
