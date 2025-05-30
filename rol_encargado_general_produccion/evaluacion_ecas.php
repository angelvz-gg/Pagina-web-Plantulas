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

$mensaje = '';

// 3) Cargar lotes ECAS con brotes disponibles
$sql = "
  SELECT
    L.ID_Lote,
    L.Fecha AS Fecha,
    V.Nombre_Variedad,
    V.Codigo_Variedad,
    COALESCE(NULLIF(V.Color,''), 'Sin datos') AS Color,
    COALESCE(S.Brotes_Disponibles, D.Brotes_Totales) AS Brotes,
    COALESCE(S.Tuppers_Llenos, D.Tuppers_Llenos) AS Tuppers,
    CASE
      WHEN D.ID_Division IS NOT NULL THEN 'División de brotes'
      ELSE 'Siembra de explantes'
    END AS SubEtapa
  FROM lotes L
  LEFT JOIN siembra_ecas S ON S.ID_Lote = L.ID_Lote
  LEFT JOIN division_ecas D ON D.ID_Siembra = S.ID_Siembra
  JOIN variedades V ON V.ID_Variedad = L.ID_Variedad
  WHERE L.ID_Etapa = 1
    AND COALESCE(S.Brotes_Disponibles, D.Brotes_Totales, 0) > 0
  ORDER BY L.Fecha DESC
";
$lotes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// 4) Procesar registro de pérdida
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_evaluacion"])) {
    $id_lote     = (int) $_POST["id_lote"];
    $cont        = $_POST["contaminacion"] ?? 'no';
    $fecha_perd  = date("Y-m-d");
    $tupp_perd   = (int) ($_POST["tuppers_desechados"] ?? 0);
    $bro_perd    = (int) ($_POST["brotes_desechados"] ?? 0);
    $motivo      = htmlspecialchars(strip_tags(trim($_POST["motivo_desecho"] ?? '')), ENT_QUOTES, 'UTF-8');
    $obs         = htmlspecialchars(strip_tags(trim($_POST["observaciones"] ?? '')), ENT_QUOTES, 'UTF-8');

    // Validar contra los datos del lote
    $tuppers_disponibles = 0;
    $brotes_disponibles  = 0;
    foreach ($lotes as $l) {
        if ($l['ID_Lote'] == $id_lote) {
            $tuppers_disponibles = (int) $l['Tuppers'];
            $brotes_disponibles  = (int) $l['Brotes'];
            break;
        }
    }

    if ($cont === "si") {
        if ($tupp_perd < 1 || $tupp_perd > $tuppers_disponibles) {
            $mensaje = "❌ La cantidad de tuppers desechados debe estar entre 1 y $tuppers_disponibles.";
        } elseif ($bro_perd < 1 || $bro_perd > $brotes_disponibles) {
            $mensaje = "❌ La cantidad de brotes desechados debe estar entre 1 y $brotes_disponibles.";
        }

        if (empty($mensaje) && ($tupp_perd > 0 || $bro_perd > 0)) {
            // Buscar ID de siembra asociado
            $id_siembra = null;
            $res_siembra = $conn->prepare("SELECT ID_Siembra FROM siembra_ecas WHERE ID_Lote = ? LIMIT 1");
            $res_siembra->bind_param("i", $id_lote);
            $res_siembra->execute();
            $res = $res_siembra->get_result()->fetch_assoc();
            if ($res) {
                $id_siembra = (int) $res['ID_Siembra'];
            }

            // Insertar pérdida
            $total = $tupp_perd + $bro_perd;
            $stmt = $conn->prepare("
                INSERT INTO perdidas_laboratorio
                  (ID_Entidad, Tipo_Entidad, Fecha_Perdida, Cantidad_Perdida,
                   Tuppers_Perdidos, Brotes_Perdidos, Motivo,
                   Operador_Entidad, Operador_Chequeo)
                VALUES (?, 'lotes', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isiiisii",
                $id_lote, $fecha_perd, $total,
                $tupp_perd, $bro_perd,
                $motivo, $ID_Operador, $ID_Operador
            );

            // Actualizar brotes en siembra_ecas
            if ($id_siembra !== null) {
                $upd = $conn->prepare("
                    UPDATE siembra_ecas
                       SET Brotes_Disponibles = GREATEST(Brotes_Disponibles - ?, 0)
                     WHERE ID_Siembra = ?
                ");
                $upd->bind_param("ii", $bro_perd, $id_siembra);
                $upd->execute();
            }

            $mensaje = $stmt->execute()
                ? "✅ Pérdida registrada correctamente."
                : "❌ Error al registrar pérdida: " . $stmt->error;
        }
    } elseif ($cont === "no") {
        $mensaje = "✅ Evaluación registrada sin pérdidas.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Evaluación de Lotes - ECAS</title>
  <link rel="stylesheet" href="../style.css?v=<?=time()?>">
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
      <a class="navbar-brand" href="#"><img src="../logoplantulas.png" width="130" height="124" alt="Logo"></a>
      <h2>Evaluación de Lotes - ECAS</h2>
      <div class="Opciones-barra"></div>
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
  </header>

  <main class="container mt-4">
    <?php if ($mensaje): ?>
      <div class="alert alert-info"><?= $mensaje ?></div>
    <?php endif; ?>

    <?php if (count($lotes) > 0): ?>
      <!-- Selección de lote ECAS -->
      <div class="mb-3">
        <label>Lote ECAS:</label>
        <select id="id_lote" class="form-select">
          <option value="">-- Elige lote --</option>
          <?php foreach ($lotes as $l): ?>
            <option value="<?= $l['ID_Lote'] ?>"
                    data-fecha="<?= $l['Fecha'] ?>"
                    data-variedad="<?= htmlspecialchars($l['Nombre_Variedad']) ?>"
                    data-codigo="<?= htmlspecialchars($l['Codigo_Variedad']) ?>"
                    data-color="<?= htmlspecialchars($l['Color']) ?>"
                    data-tuppers="<?= $l['Tuppers'] ?>"
                    data-brotes="<?= $l['Brotes'] ?>"
                    data-subetapa="<?= $l['SubEtapa'] ?>">
              <?= "ID {$l['ID_Lote']} – {$l['Codigo_Variedad']} – {$l['Nombre_Variedad']}" ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Información del lote -->
      <div id="info-lote" style="display:none; margin-bottom:20px;">
        <p><strong>📋 Información del Lote Seleccionado:</strong></p>
        <p><strong>Fecha:</strong> <span id="fecha_lote"></span></p>
        <p><strong>Variedad:</strong> <span id="variedad_lote"></span></p>
        <p><strong>Código:</strong> <span id="codigo_var"></span></p>
        <p><strong>Color:</strong> <span id="color_var"></span></p>
        <p><strong>Sub-etapa:</strong> <span id="subetapa_lote"></span></p>
        <p><strong>Tuppers disponibles:</strong> <span id="tuppers_lote"></span></p>
        <p><strong>Brotes disponibles:</strong> <span id="brotes_lote"></span></p>
      </div>

      <!-- Formulario de evaluación -->
      <form method="POST" class="form-doble-columna">
        <input type="hidden" name="id_lote" id="input_id_lote">

        <div class="mb-3">
          <label>¿Contaminación Detectada?</label>
          <select name="contaminacion" class="form-select" id="contaminacion_sel" required>
            <option value="">Selecciona...</option>
            <option value="no">No</option>
            <option value="si">Sí</option>
          </select>
        </div>

        <div id="cont_campos" style="display:none;">
          <div class="mb-3">
            <label>Tuppers desechados:</label>
            <input type="number" name="tuppers_desechados" class="form-control" min="1">
          </div>
          <div class="mb-3">
            <label>Brotes desechados:</label>
            <input type="number" name="brotes_desechados" class="form-control" min="1">
          </div>
          <div class="mb-3">
            <label>Motivo:</label>
            <input type="text" name="motivo_desecho" class="form-control">
          </div>
        </div>

        <div class="mb-3">
          <label>Observaciones:</label>
          <textarea name="observaciones" class="form-control" rows="3"></textarea>
        </div>

        <button type="submit" name="guardar_evaluacion" class="btn btn-primary">
          Registrar Evaluación
        </button>
      </form>

    <?php else: ?>
      <div class="alert alert-warning">
        ⚠️ No hay lotes con inventario ECAS disponibles.
      </div>
    <?php endif; ?>
  </main>

  <footer class="text-center mt-5">&copy; 2025 PLANTAS AGRODEX</footer>
</div>

<script>
  document.getElementById('id_lote')?.addEventListener('change', function(){
    const opt = this.options[this.selectedIndex];
    if (!opt.value) {
      document.getElementById('info-lote').style.display = 'none';
      return;
    }
    document.getElementById('input_id_lote').value = opt.value;
    document.getElementById('fecha_lote').innerText = opt.dataset.fecha;
    document.getElementById('variedad_lote').innerText = opt.dataset.variedad;
    document.getElementById('codigo_var').innerText = opt.dataset.codigo;
    document.getElementById('color_var').innerText = opt.dataset.color;
    document.getElementById('subetapa_lote').innerText = opt.dataset.subetapa;
    document.getElementById('tuppers_lote').innerText = opt.dataset.tuppers;
    document.getElementById('brotes_lote').innerText = opt.dataset.brotes;
    document.getElementById('info-lote').style.display = 'block';
  });

  document.getElementById('contaminacion_sel')?.addEventListener('change', function(){
    document.getElementById('cont_campos').style.display =
      this.value === 'si' ? 'block' : 'none';
  });
</script>

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
