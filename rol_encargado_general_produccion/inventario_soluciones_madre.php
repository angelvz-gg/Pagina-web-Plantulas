<?php
// 0) Mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Mexico_City');

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


// Obtener valores de filtro desde GET
$codigoFiltro = $_GET['codigo_medio'] ?? '';
$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';
$cantidadMinima = $_GET['cantidad_minima'] ?? '';
$estadoFiltro = $_GET['estado'] ?? '';

// Armar la consulta dinámica
$sql = "SELECT Codigo_Medio, Fecha_Preparacion, Cantidad_Preparada, Cantidad_Disponible, Estado 
        FROM medios_nutritivos_madre 
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($codigoFiltro)) {
    $sql .= " AND Codigo_Medio = ?";
    $params[] = $codigoFiltro;
    $types .= "s";
}
if (!empty($fechaDesde)) {
    $sql .= " AND Fecha_Preparacion >= ?";
    $params[] = $fechaDesde;
    $types .= "s";
}
if (!empty($fechaHasta)) {
    $sql .= " AND Fecha_Preparacion <= ?";
    $params[] = $fechaHasta;
    $types .= "s";
}
if (is_numeric($cantidadMinima)) {
    $sql .= " AND Cantidad_Disponible >= ?";
    $params[] = $cantidadMinima;
    $types .= "i";
}
if (!empty($estadoFiltro)) {
    $sql .= " AND Estado = ?";
    $params[] = $estadoFiltro;
    $types .= "s";
}

$sql .= " ORDER BY Fecha_Preparacion DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$codigosQuery = $conn->query("SELECT DISTINCT Codigo_Medio FROM medios_nutritivos_madre ORDER BY Codigo_Medio ASC");
$codigos = $codigosQuery->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inventario de soluciones madre</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
          <h2>Inventario de soluciones madre</h2>
          <p>Consulta la cantidad restante de cada medio nutritivo madre.</p>
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
  <div class="d-flex flex-column" style="min-width:140px;">
    <label for="filtro-codigo" class="small mb-1">Código Medio</label>
    <select id="filtro-codigo" name="codigo_medio" form="filtrosForm"
            class="form-select form-select-sm">
      <option value="">— Todos —</option>
      <?php foreach ($codigos as $codigo): ?>
        <option value="<?= htmlspecialchars($codigo['Codigo_Medio'])?>"
          <?= $codigoFiltro === $codigo['Codigo_Medio'] ? 'selected':''?>>
          <?= htmlspecialchars($codigo['Codigo_Medio'])?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="d-flex flex-column" style="min-width:120px;">
    <label for="filtro-desde" class="small mb-1">Desde</label>
    <input id="filtro-desde" type="date" name="fecha_desde" form="filtrosForm"
           class="form-control form-control-sm"
           value="<?= htmlspecialchars($fechaDesde) ?>">
  </div>

  <div class="d-flex flex-column" style="min-width:120px;">
    <label for="filtro-hasta" class="small mb-1">Hasta</label>
    <input id="filtro-hasta" type="date" name="fecha_hasta" form="filtrosForm"
           class="form-control form-control-sm"
           value="<?= htmlspecialchars($fechaHasta) ?>">
  </div>

  <div class="d-flex flex-column" style="min-width:140px;">
    <label for="filtro-cantidad" class="small mb-1">Cantidad Mínima</label>
    <input id="filtro-cantidad" type="number" step="0.1" name="cantidad_minima" form="filtrosForm"
           class="form-control form-control-sm"
           value="<?= htmlspecialchars($cantidadMinima) ?>">
  </div>

  <div class="d-flex flex-column" style="min-width:120px;">
    <label for="filtro-estado" class="small mb-1">Estado</label>
    <select id="filtro-estado" name="estado" form="filtrosForm"
            class="form-select form-select-sm">
      <option value="">— Todos —</option>
      <option value="Disponible" <?= $estadoFiltro==='Disponible' ? 'selected':''?>>Disponible</option>
      <option value="Consumido"   <?= $estadoFiltro==='Consumido'   ? 'selected':''?>>Consumido</option>
    </select>
  </div>

  <button form="filtrosForm" type="submit"
          class="btn-inicio btn btn-success btn-sm ms-auto">
    Filtrar
  </button>
</nav>
    </header>

    <main class="flex-fill" style="flex:1; padding: 20px;">
      <div class="section">
        <h2>📊 Cantidad Disponible de Soluciones Madre</h2>
          <hr />
        </div>

        <!-- Tabla -->
        <table class="table mt-4">
          <thead>
            <tr>
              <th>Código del Medio</th>
              <th>Fecha de Preparación</th>
              <th>Cantidad Inicial (L)</th>
              <th>Cantidad Usada (L)</th>
              <th>Cantidad Restante (L)</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
             <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                 $preparada = (int)$row['Cantidad_Preparada'];
                  $disponible = is_null($row['Cantidad_Disponible']) ? $preparada : (int)$row['Cantidad_Disponible'];
                 $cantidadUsada = max(0, $preparada - $disponible);
                ?>
           <tr>
            <td><?= htmlspecialchars($row['Codigo_Medio']) ?></td>
            <td><?= htmlspecialchars($row['Fecha_Preparacion']) ?></td>
            <td><?= $preparada ?></td>
            <td>
              <?= $cantidadUsada > 0 ? $cantidadUsada : '<span style="color: gray;">— Aún no se ha usado —</span>' ?>
            </td>
            <td><?= $disponible ?></td>
            <td><?= $row['Estado'] ?></td>
          </tr>
        <?php endwhile; ?>
        <?php else: ?>
                <tr><td colspan="6">No hay registros que coincidan con los filtros.</td></tr>
              <?php endif; ?>
        </tbody>

        </table>
    </main>

    <footer class="text-center mt-5">
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script>
    function toggleFiltros() {
      const filtros = document.getElementById("filtros-contenedor");
      const boton = document.getElementById("toggleBtn");
      const visible = filtros.style.display === "block";
      filtros.style.display = visible ? "none" : "block";
      boton.innerHTML = visible ? "🔍 Mostrar filtros" : "❌ Ocultar filtros";
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

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
