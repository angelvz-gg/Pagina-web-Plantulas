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

if (isset($_POST['sumar_esterilizacion'])) {
    $idMat = intval($_POST['id_material_ester']);
    $cant  = intval($_POST['cantidad_ester']);
    $oper  = $_SESSION['ID_Operador'];

    // 1) Verificar cantidad válida
    if ($cant < 1 || $cant > 50) {
        $msg = '⚠️ Solo puedes agregar entre 1 y 50 unidades por vez.';
    } else {
        // 2) Consultar cantidad actual - esterilizados
        $stmt = $conn->prepare("
          SELECT 
            im.cantidad,
            COALESCE(SUM(mm.cantidad),0) AS esterilizados
          FROM inventario_materiales im
          LEFT JOIN movimientos_materiales mm
            ON im.id_material = mm.id_material AND mm.tipo_movimiento = 'esterilizacion'
          WHERE im.id_material = ?
          GROUP BY im.id_material
        ");
        $stmt->bind_param('i', $idMat);
        $stmt->execute();
        $stmt->bind_result($total, $esterilizados);
        if ($stmt->fetch()) {
            $disponible = max($total - $esterilizados, 0);
            if ($disponible > 10) {
                $msg = "🚫 Este material ya tiene suficiente stock para esterilización ($disponible disponibles). Solo se permite agregar si es 10 o menos.";
            } else {
                // OK, procede a insertar
                $stmt->close();
                $stmt = $conn->prepare("
                  INSERT INTO inventario_materiales (id_material, cantidad, en_uso, fecha_act, id_operador_registro)
                  VALUES (?, ?, 0, NOW(), ?)
                  ON DUPLICATE KEY UPDATE
                    cantidad = cantidad + VALUES(cantidad),
                    fecha_act = NOW(),
                    id_operador_registro = VALUES(id_operador_registro)
                ");
                $stmt->bind_param('iii', $idMat, $cant, $oper);
                $stmt->execute();
                $msg = '✅ Material disponible para esterilización actualizado.';
            }
        } else {
            $msg = '⚠️ Material no encontrado.';
        }
    }
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
<div class="row justify-content-center">
  <div class="col-12">
    <div class="card card-formulario p-4 mx-auto" style="max-width: 700px;">
      <h4>Actualizar existencias</h4>
      <form method="POST" class="row g-3 align-items-end">
        <div class="col-12 col-md-7">
          <label for="id_material" class="form-label">Material</label>
          <select id="id_material" name="id_material" class="form-select" required>
            <small id="disponible_info" class="text-muted">Selecciona un material para ver su disponibilidad.</small>
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
        <div class="col-6 col-md-3">
          <label for="cantidad" class="form-label">Cantidad</label>
          <input type="number" id="cantidad" name="cantidad"
                 class="form-control" placeholder="0" min="1" max="100" required>
        </div>
<div class="col-12 col-md-2 d-grid">
  <button name="..." class="btn btn-success w-100">
    Guardar
  </button>
</div>
      </form>
    </div>
  </div>
</div>

  <!-- FORMULARIO: SUMAR cantidad para esterilización -->
<div class="row justify-content-center">
  <div class="col-12">
    <div class="card card-formulario p-4 mx-auto" style="max-width: 700px;">
      <h4>Sumar materiales para esterilización</h4>
      <form method="POST" class="row g-3 align-items-end">
        <div class="col-12 col-md-7">
          <label for="id_material_ester" class="form-label">Material</label>
          <select id="id_material_ester" name="id_material_ester" class="form-select" required>
            <option value="">Selecciona material…</option>
            <?php
            $materiales->data_seek(0);
            while ($m = $materiales->fetch_assoc()):
              $id = $m['id_material'];
              $stmt = $conn->prepare("
                SELECT 
                  COALESCE(SUM(im.cantidad),0) AS total,
                  COALESCE(SUM(im.en_uso),0)   AS en_uso,
                  (
                    SELECT COALESCE(SUM(mm.cantidad),0)
                    FROM movimientos_materiales mm
                    WHERE mm.id_material = im.id_material AND mm.tipo_movimiento = 'esterilizacion'
                  ) AS esterilizados
                FROM inventario_materiales im
                WHERE im.id_material = ?
              ");
              $stmt->bind_param('i', $id);
              $stmt->execute();
              $stmt->bind_result($total, $en_uso, $esterilizados);
              $stmt->fetch();
              $stmt->close();

              $disp = max($total - $en_uso - $esterilizados, 0);
            ?>
              <option value="<?= $id ?>">
                <?= htmlspecialchars($m['nombre']) ?> (<?= $disp ?> disponibles para esterilización)
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label for="cantidad_ester" class="form-label">Cantidad</label>
          <input type="number" id="cantidad_ester" name="cantidad_ester"
                 class="form-control" min="1" max="500" required>
        </div>
<div class="col-12 col-md-2 d-grid">
  <button name="sumar_esterilizacion" class="btn btn-outline-primary w-100">
    Sumar
  </button>
</div>
      </form>
    </div>
  </div>
</div>

  <!-- Tabla de inventario actual -->
  <div class="table-responsive">
    <table class="table table-bordered table-sm text-center align-middle" style="max-width: 600px; margin: 0 auto;">
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
