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

// Lista fija de materiales permitidos
$lista_materiales = [
    'Pinza grande',
    'Pinza mediana',
    'Bisturí',
    'Bolsa de periódico',
    'Trapos'
];

// 2) Procesar formularios
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Registrar nuevos tipos desde checklist
    if (isset($_POST['registrar_materiales']) && !empty($_POST['materiales_nuevos'])) {
    // Preparamos las sentencias
    $stmtCheck = $conn->prepare("
      SELECT COUNT(*) 
        FROM materiales 
       WHERE nombre = ? AND reutilizable = 1
    ");
    $stmtInsert = $conn->prepare("
      INSERT INTO materiales (nombre, reutilizable) 
      VALUES (?, 1)
    ");

    foreach ($_POST['materiales_nuevos'] as $nombre) {
        // 1) Comprobamos si ya existe ese nombre como reutilizable
        $stmtCheck->bind_param('s', $nombre);
        $stmtCheck->execute();
        $stmtCheck->bind_result($count);
        $stmtCheck->fetch();

        // 2) Si no existe, insertamos
        if ($count === 0) {
            $stmtInsert->bind_param('s', $nombre);
            $stmtInsert->execute();
        }
    }

    $msg = '✅ Nuevos materiales registrados';
}


// Actualizar inventario
if (isset($_POST['actualizar_inventario'])) {
    $id   = intval($_POST['id_material']);
    $cant = intval($_POST['cantidad']);
    $oper = $_SESSION['ID_Operador'];

    // 1) Comprobar disponibilidad real en BD (cantidad – en_uso)
    $chk = $conn->prepare("
      SELECT 
        COALESCE(SUM(cantidad),0) - COALESCE(SUM(en_uso),0) AS disponible
        FROM inventario_materiales
       WHERE id_material = ?
    ");
    $chk->bind_param('i', $id);
    $chk->execute();
    $chk->bind_result($disponible);
    $chk->fetch();
    $chk->close();

    // 2) Validaciones en cascada
    if ($disponible < 1) {
        $msg = "🚫 No hay stock disponible (disponible = $disponible)";
    }
    elseif ($disponible >= 5) {
        $msg = "🚫 No puedes actualizar este material (disponible $disponible ≥ 5)";
    }
    else {
        // Solo entramos aquí si 1 ≤ $disponible < 5

        // 3) Validación de rango 1–100 para la cantidad a ingresar
        if ($cant < 1 || $cant > 100) {
            $msg = '⚠️ La cantidad debe estar entre 1 y 100';
        } else {
// 4) Si todo OK, actualizamos la tabla
$stmt = $conn->prepare("
    INSERT INTO inventario_materiales
      (id_material, cantidad, fecha_act, id_operador_registro)
    VALUES (?, ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE
      cantidad             = cantidad + VALUES(cantidad),
      fecha_act            = NOW(),
      id_operador_registro = VALUES(id_operador_registro)
");
$stmt->bind_param('iii', $id, $cant, $oper);
$stmt->execute();

            $msg = '✅ Inventario actualizado';
        }
    }
}
}

// 3) Cargar datos para dropdown y tabla
$materiales = $conn->query("SELECT * FROM materiales ORDER BY nombre");

$inv_res = $conn->query("
  SELECT
    m.id_material,
    m.nombre,
    m.reutilizable,
    CASE
      WHEN m.reutilizable = 1
        THEN COALESCE(i.total,0) - COALESCE(i.uso,0)
      ELSE COALESCE(i.total,0)
    END AS cantidad_total
  FROM materiales m
  LEFT JOIN (
    -- sumar todas las filas de inventario por material
    SELECT
      id_material,
      SUM(cantidad) AS total,
      SUM(en_uso)   AS uso
    FROM inventario_materiales
    GROUP BY id_material
  ) i ON m.id_material = i.id_material
  ORDER BY m.nombre, m.reutilizable DESC
");

// Construir mapa de id_material → existencia actual
$qtyMap = [];
$inv_qty = $conn->query("
  SELECT 
    id_material,
    COALESCE(SUM(cantidad),0)      AS total,
    COALESCE(SUM(en_uso),0)        AS en_uso
  FROM inventario_materiales
  GROUP BY id_material
");
while ($row = $inv_qty->fetch_assoc()) {
    $avail = intval($row['total']) - intval($row['en_uso']);
    $qtyMap[$row['id_material']] = max($avail, 0);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Inventario de Materiales</title>
  <link rel="stylesheet" href="../style.css?v=<?=time()?>"/>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet" crossorigin="anonymous"
  />
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
        <a class="navbar-brand me-3" href="dashboard_eism.php">
          <img src="../logoplantulas.png" width="130" height="124" alt="Logo">
        </a>
        <div>
          <h2>Inventario de Materiales</h2>
          <p class="mb-0">Selecciona materiales y actualiza sus existencias.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_supervisora.php'">
              🏠 Volver al Inicio
              </button>
            </div>
          </div>
        </nav>
      </div>
    </header>

<main class="container mt-4">
  <?php if ($msg): ?>
    <div class="alert alert-success"><?= $msg ?></div>
  <?php endif; ?>

  <div class="row mb-4">

    <!-- CHECKLIST DE MATERIALES FIJOS 
    <div class="col-md-6">
      <div class="card p-3 h-100">
        <h4>Registrar tipos de material</h4>
        <form method="POST">
          <?php foreach ($lista_materiales as $mat): ?>
            <div class="form-check">
              <input class="form-check-input"
                     type="checkbox"
                     name="materiales_nuevos[]"
                     value="<?= htmlspecialchars($mat) ?>"
                     id="<?= str_replace(' ', '_', $mat) ?>">
              <label class="form-check-label" for="<?= str_replace(' ', '_', $mat) ?>">
                <?= htmlspecialchars($mat) ?>
              </label>
            </div>
          <?php endforeach; ?>
          <button name="registrar_materiales" class="btn btn-primary mt-3">
            Registrar seleccionados
          </button>
        </form>
      </div>
    </div>
          -->

    <!-- FORMULARIO: ACTUALIZAR EXISTENCIAS -->
     <div class="row mb-4 justify-content-center">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
      <div class="card p-6">
        <h4>Actualizar existencias</h4>
        <form method="POST" class="row g-3 align-items-end">
          <div class="col-7">
            <label for="id_material" class="form-label">Material</label>
            <select id="id_material" name="id_material" class="form-select" required>
              <option value="" data-cantidad="0">Selecciona material…</option>
              <?php
              $materiales->data_seek(0);
              while ($m = $materiales->fetch_assoc()):
                $qty = $qtyMap[$m['id_material']] ?? 0;
              ?>
                <option 
                  value="<?= $m['id_material'] ?>"
                  data-cantidad="<?= $qty ?>"
                >
                  <?= htmlspecialchars($m['nombre']) ?> (<?= $qty ?>)
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-3">
            <label for="cantidad" class="form-label">Cantidad</label>
            <input type="number" id="cantidad" name="cantidad"
                   class="form-control" placeholder="0" min="1" max="100" required>
          </div>
          <div class="col-2">
            <button name="actualizar_inventario" class="btn btn-success w-100">
              Guardar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  </div>

  <!-- Tabla de inventario actual -->
  <div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>Material</th>
          <th>Cantidad Disponible</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($r = $inv_res->fetch_assoc()): ?>
          <tr
            data-id-material-id="<?= $r['id_material'] ?>"
            data-id-material="<?= htmlspecialchars($r['nombre']) ?>"
            data-reutilizable="<?= $r['reutilizable'] ?>"
          >
            <td>
              <?= htmlspecialchars($r['nombre']) ?>
              <?php if ($r['reutilizable']): ?>
                <span class="badge bg-success">reutilizable</span>
              <?php else: ?>
                <span class="badge bg-secondary">desechable</span>
              <?php endif; ?>
            </td>
            <td><?= max(0, intval($r['cantidad_total'])) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</main>


    <footer class="text-center py-3">
      &copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.
    </footer>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    crossorigin="anonymous"
  ></script>
  
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

// -------------------------
// Resaltar fila seleccionada
document.getElementById('id_material')
  .addEventListener('change', function(){
    const selectedText = this.options[this.selectedIndex].text;
    document.querySelectorAll('tbody tr').forEach(tr => {
      tr.classList.toggle('table-warning',
        tr.getAttribute('data-id-material') === selectedText
      );
    });
  });

  // — Bloque para bloquear por existencia individual —
const selectMat = document.getElementById('id_material');
const cantInput = document.getElementById('cantidad');
const guardarBtn = document.querySelector('button[name="actualizar_inventario"]');

function checkCantidadActual() {
  const opt   = selectMat.selectedOptions[0];
  const exist = parseInt(opt.getAttribute('data-cantidad'), 10);

  // Si no hay material seleccionado, limpiar alerta y deshabilitar
  if (!opt.value) {
    cantInput.disabled = true;
    guardarBtn.disabled = true;
    document.getElementById('alert-stock')?.remove();
    return;
  }

  // Determinar si debe bloquearse
  const shouldBlock = exist >= 5;
  cantInput.disabled = shouldBlock;
  guardarBtn.disabled = shouldBlock;

  // Gestionar la alerta
  let alertEl = document.getElementById('alert-stock');
  if (shouldBlock) {
    const msg = `🚫 No puedes actualizar “${opt.textContent.trim()}” porque la existencia actual es ${exist} (debe ser < 5)`;
    if (!alertEl) {
      alertEl = document.createElement('div');
      alertEl.id = 'alert-stock';
      alertEl.className = 'alert alert-warning mt-3';
      guardarBtn.closest('.card')?.append(alertEl);
    }
    alertEl.textContent = msg;
  } else if (alertEl) {
    // Si ya no debe bloquearse, quitar alerta
    alertEl.remove();
  }
}

// Ejecutar al cambiar selección y al cargar la página
selectMat.addEventListener('change', checkCantidadActual);
document.addEventListener('DOMContentLoaded', checkCantidadActual);

</script>

</body>
</html>
