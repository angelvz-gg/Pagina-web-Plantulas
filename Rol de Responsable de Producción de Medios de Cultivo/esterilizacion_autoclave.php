<?php
include '../db.php';
session_start();

// 1) Verificación de sesión y rol
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 7) {
  header("Location: ../login.php");
  exit();
}

// 2) Mensaje desde GET (después del redirect)
$mensaje = $_GET['mensaje'] ?? '';
$procesoSeleccionado = null;

// 3) Handler AJAX para actualizar info de dilución
if (isset($_POST['ajax_dilucion_info'])) {
  $id = $_POST['ajax_dilucion_info'];
  $data = [
    'Tuppers_Llenos'    => 0,
    'Usados'            => 0,
    'Disponibles'       => 0,
    'Veces_Esterilizado'=> 0,
    'Finalizados'       => 0
  ];

  if ($id) {
    $stmt = $conn->prepare("
      SELECT d.Tuppers_Llenos,
             COALESCE(SUM(ea.Tuppers_Esterilizados),0) AS usados
      FROM dilucion_llenado_tuppers d
      LEFT JOIN esterilizacion_autoclave ea
        ON d.ID_Dilucion = ea.ID_Dilucion
      WHERE d.ID_Dilucion = ?
      GROUP BY d.ID_Dilucion
    ");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
      $data['Tuppers_Llenos'] = (int)$res['Tuppers_Llenos'];
      $data['Usados']         = (int)$res['usados'];
      $data['Disponibles']    = $data['Tuppers_Llenos'] - $data['Usados'];
    }

    $res1 = $conn->query("SELECT COUNT(*) AS total FROM esterilizacion_autoclave WHERE ID_Dilucion = '$id'");
    $data['Veces_Esterilizado'] = (int)$res1->fetch_assoc()['total'];

    $res2 = $conn->query("SELECT COUNT(*) AS finalizados FROM esterilizacion_autoclave WHERE ID_Dilucion = '$id' AND Estado = 'Finalizado'");
    $data['Finalizados'] = (int)$res2->fetch_assoc()['finalizados'];
  }

  header('Content-Type: application/json');
  echo json_encode($data);
  exit();
}

// 4) Abrir modal de finalización via GET
if (isset($_GET['abrir_modal_finalizar'])) {
  $id = (int)$_GET['abrir_modal_finalizar'];
  $stmt = $conn->prepare("SELECT * FROM esterilizacion_autoclave WHERE ID_Esterilizacion = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $procesoSeleccionado = $stmt->get_result()->fetch_assoc();
}

// 5) Procesamiento de formularios (POST) con Post/Redirect/Get
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_operador = $_SESSION['ID_Operador'];
  $self = basename(__FILE__);

  // a) Iniciar esterilización
  if (isset($_POST['iniciar_esterilizacion'])) {
    $id_dilucion           = $_POST['id_dilucion'];
    $tipo_articulo         = isset($_POST['tipo_articulo'])
                            ? implode(', ', $_POST['tipo_articulo'])
                            : '';
    $tuppers_esterilizados = (int)$_POST['tuppers_esterilizados'];
    $con_tapa              = isset($_POST['con_tapa']) ? 1 : 0;

    $checkLote = $conn->prepare("
      SELECT d.Tuppers_Llenos,
             COALESCE(SUM(ea.Tuppers_Esterilizados),0) AS usados
      FROM dilucion_llenado_tuppers d
      LEFT JOIN esterilizacion_autoclave ea
        ON d.ID_Dilucion = ea.ID_Dilucion
      WHERE d.ID_Dilucion = ?
      GROUP BY d.ID_Dilucion
    ");
    $checkLote->bind_param("s", $id_dilucion);
    $checkLote->execute();
    $datos = $checkLote->get_result()->fetch_assoc();
    $restantes = $datos ? ($datos['Tuppers_Llenos'] - $datos['usados']) : 0;

    if ($tuppers_esterilizados <= $restantes) {
      $stmt = $conn->prepare("
        INSERT INTO esterilizacion_autoclave
          (ID_Dilucion, Tipo_Articulo, FechaInicio, Estado, ID_Operador, Tuppers_Esterilizados, Con_Tapa)
        VALUES (?, ?, NOW(), 'En proceso', ?, ?, ?)
      ");
      $stmt->bind_param("ssiii", $id_dilucion, $tipo_articulo, $id_operador, $tuppers_esterilizados, $con_tapa);
      $ok = $stmt->execute();
      $mensaje = $ok
                 ? "✅ Proceso de esterilización iniciado."
                 : "❌ Error al iniciar el proceso.";
    } else {
      $mensaje = "⚠️ No puedes esterilizar más tuppers de los que hay disponibles.";
    }

    header("Location: $self?mensaje=" . urlencode($mensaje));
    exit();
  }

  // b) Finalizar esterilización
  if (isset($_POST['finalizar_esterilizacion'])) {
    $id_esterilizacion = $_POST['id_esterilizacion'];
    $resultado         = $_POST['resultado'];
    $observaciones     = $_POST['observaciones'];

    $stmt = $conn->prepare("
      UPDATE esterilizacion_autoclave
      SET FechaFin = NOW(),
          Resultado = ?,
          Observaciones = ?,
          Estado = 'Finalizado'
      WHERE ID_Esterilizacion = ?
    ");
    $stmt->bind_param("ssi", $resultado, $observaciones, $id_esterilizacion);
    $success = $stmt->execute();

    $mensaje = $success
               ? "✅ Proceso finalizado."
               : "❌ Error al finalizar el proceso.";

    header("Location: $self?mensaje=" . urlencode($mensaje));
    exit();
  }
}

// 6) Consultas para mostrar estado actual
$procesos = $conn
  ->query("SELECT * FROM esterilizacion_autoclave WHERE Estado = 'En proceso'")
  ->fetch_all(MYSQLI_ASSOC);

// Ahora obtenemos sólo las esterilizaciones terminadas del mismo table
$finalizados = $conn
  ->query("
    SELECT ID_Esterilizacion, ID_Dilucion, FechaInicio, FechaFin
    FROM esterilizacion_autoclave
    WHERE Estado = 'Finalizado'
    ORDER BY FechaFin DESC
  ")
  ->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Esterilización en Autoclave</title>
  <link rel="stylesheet" href="../style.css?v=<?=time();?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .seccion-interna { display: none; }
    .seccion-activa { display: block; }
  </style>
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
      </a>
      <div>
        <h2>Esterilización en Autoclave</h2>
        <p>Registro del proceso de esterilización con inicio, seguimiento y cierre.</p>
      </div>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="Opciones-barra">
        <div class="container-fluid">
          <button onclick="window.location.href='dashboard_rpmc.php'">
            🔄 Regresar
          </button>
        </div>
        </div>
      </nav>
    </div>
    <div class="container my-3 text-center">
      <button class="btn btn-outline-primary mx-1" onclick="mostrarSeccion('inicio')">➕ Iniciar</button>
      <button class="btn btn-outline-warning mx-1" onclick="mostrarSeccion('proceso')">🧪 En proceso</button>
      <button class="btn btn-outline-secondary mx-1" onclick="mostrarSeccion('historial')">📜 Finalizadas</button>
    </div>
  </header>

  <main class="container py-4">
    <?php if ($mensaje): ?>
      <div class="alert alert-info text-center"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <!-- SECCIÓN: INICIO -->
    <div id="inicio" class="seccion-interna seccion-activa">
      <div class="card mb-4">
        <div class="card-header bg-primary text-white">Iniciar nueva esterilización</div>
        <div class="card-body">
          <form method="POST" class="row g-3">
            <div class="col-md-6">
              <label for="id_dilucion" class="form-label">Lote (ID_Dilución)</label>
              <select name="id_dilucion" class="form-select" required>
                <option value="" disabled selected>-- Selecciona --</option>
                <?php
                $res = $conn->query("SELECT ID_Dilucion FROM dilucion_llenado_tuppers ORDER BY ID_Dilucion DESC");
                while ($row = $res->fetch_assoc()): ?>
                  <option value="<?= $row['ID_Dilucion'] ?>"><?= $row['ID_Dilucion'] ?></option>
                <?php endwhile; ?>
              </select>
              <div class="mt-2 text-muted" id="info_lote"></div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Cantidad de tuppers</label>
              <input type="number" name="tuppers_esterilizados" class="form-control" required>
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="con_tapa" id="con_tapa">
                <label class="form-check-label" for="con_tapa">¿Incluye tapas?</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Artículos a esterilizar:</label><br>
              <?php foreach (["Periódico", "Pinzas", "Bisturí", "Trapos"] as $item): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="tipo_articulo[]" value="<?= $item ?>">
                  <label class="form-check-label"><?= $item ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="col-12 text-end">
              <button type="submit" name="iniciar_esterilizacion" class="btn btn-primary">Iniciar proceso</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- SECCIÓN: EN PROCESO -->
    <div id="proceso" class="seccion-interna">
      <div class="card mb-4">
        <div class="card-header bg-warning">Esterilizaciones en proceso</div>
        <div class="card-body">
          <table class="table table-bordered">
            <thead class="table-dark">
              <tr>
                <th>ID</th>
                <th>Lote</th>
                <th>Inicio</th>
                <th>Artículos</th>
                <th>Tuppers</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($procesos as $p): ?>
                <tr>
                  <td><?= $p['ID_Esterilizacion'] ?></td>
                  <td><?= $p['ID_Dilucion'] ?></td>
                  <td><?= $p['FechaInicio'] ?></td>
                  <td><?= $p['Tipo_Articulo'] ?></td>
                  <td><?= $p['Tuppers_Esterilizados'] ?></td>
                  <td>
                    <a href="?abrir_modal_finalizar=<?= $p['ID_Esterilizacion'] ?>"
                       class="btn btn-sm btn-success">Finalizar</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- SECCIÓN: FINALIZADAS -->
    <div id="historial" class="seccion-interna">
      <div class="card mb-4">
        <div class="card-header bg-secondary text-white">Procesos Finalizados</div>
        <div class="card-body">
          <table class="table table-bordered table-striped">
            <thead class="table-dark">
              <tr>
                <th>ID</th>
                <th>Lote</th>
                <th>Fecha y hora inicio</th>
                <th>Fecha y hora fin</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($finalizados)): ?>
                <tr>
                  <td colspan="4" class="text-center">No hay procesos finalizados.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($finalizados as $f): ?>
                  <tr>
                    <td><?= htmlspecialchars($f['ID_Esterilizacion']) ?></td>
                    <td><?= htmlspecialchars($f['ID_Dilucion']) ?></td>
                    <td><?= htmlspecialchars($f['FechaInicio']) ?></td>
                    <td><?= htmlspecialchars($f['FechaFin']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL DE FINALIZACIÓN -->
    <?php if ($procesoSeleccionado): ?>
    <div class="modal fade show" tabindex="-1" style="display:block; background-color:rgba(0,0,0,0.5);">
      <div class="modal-dialog">
        <form method="POST" class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title">
              Finalizar Proceso #<?= $procesoSeleccionado['ID_Esterilizacion'] ?>
            </h5>
            <a href="esterilizacion_autoclave.php" class="btn-close"></a>
          </div>
          <div class="modal-body">
            <input type="hidden" name="id_esterilizacion"
                   value="<?= $procesoSeleccionado['ID_Esterilizacion'] ?>">
            <div class="mb-3">
              <label class="form-label">Resultado</label>
              <select name="resultado" class="form-select" required>
                <option value="Exitoso">Exitoso</option>
                <option value="Fallido">Fallido</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Observaciones</label>
              <textarea name="observaciones" class="form-control" rows="3"
                        placeholder="¿Qué ocurrió en la esterilización?"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="finalizar_esterilizacion"
                    class="btn btn-success">Finalizar</button>
            <a href="esterilizacion_autoclave.php" class="btn btn-secondary">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<script>
  function mostrarSeccion(id) {
    document.querySelectorAll('.seccion-interna')
            .forEach(div => div.classList.remove('seccion-activa'));
    document.getElementById(id).classList.add('seccion-activa');
  }

  document.querySelector('[name="id_dilucion"]')
    .addEventListener('change', function() {
      const id = this.value;
      fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_dilucion_info=' + encodeURIComponent(id)
      })
      .then(res => res.json())
      .then(data => {
        const info = `Lote: ${id} | Llenos: ${data.Tuppers_Llenos} | Usados: ${data.Usados}
                      | Disponibles: ${data.Disponibles} | Ester.: ${data.Veces_Esterilizado}
                      | Final.: ${data.Finalizados}`;
        document.getElementById('info_lote').textContent = info;
      });
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
