<?php
include '../db.php';
session_start();

// 1) Verificación de sesión y rol
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 7) {
  header("Location: ../login.php");
  exit();
}

$mensaje = $_GET['mensaje'] ?? '';
$procesoSeleccionado = null;

// AJAX: info de dilución
if (isset($_POST['ajax_dilucion_info'])) {
  $id = $_POST['ajax_dilucion_info'];
  $data = ['Tuppers_Llenos'=>0,'Usados'=>0,'Disponibles'=>0,'Veces_Esterilizado'=>0,'Finalizados'=>0];
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
    if ($r = $stmt->get_result()->fetch_assoc()) {
      $data['Tuppers_Llenos']  = (int)$r['Tuppers_Llenos'];
      $data['Usados']          = (int)$r['usados'];
      $data['Disponibles']     = $data['Tuppers_Llenos'] - $data['Usados'];
    }
    $data['Veces_Esterilizado'] = (int)$conn
      ->query("SELECT COUNT(*) AS c FROM esterilizacion_autoclave WHERE ID_Dilucion='$id'")
      ->fetch_assoc()['c'];
    $data['Finalizados'] = (int)$conn
      ->query("SELECT COUNT(*) AS c FROM esterilizacion_autoclave WHERE ID_Dilucion='$id' AND Estado='Finalizado'")
      ->fetch_assoc()['c'];
  }
  header('Content-Type: application/json');
  echo json_encode($data);
  exit();
}

// AJAX: info de material
if (isset($_POST['ajax_material_info'])) {
  $idm = intval($_POST['ajax_material_info']);
  $stmt1 = $conn->prepare("
    SELECT COALESCE(cantidad,0) AS disponibles
      FROM inventario_materiales
     WHERE id_material=?
  ");
  $stmt1->bind_param('i', $idm);
  $stmt1->execute();
  $disp = $stmt1->get_result()->fetch_assoc()['disponibles'];

  $stmt2 = $conn->prepare("
    SELECT COALESCE(SUM(ea.Tuppers_Esterilizados),0) AS usados
      FROM esterilizacion_autoclave ea
     WHERE ea.id_material=?
  ");
  $stmt2->bind_param('i', $idm);
  $stmt2->execute();
  $used = $stmt2->get_result()->fetch_assoc()['usados'];

  header('Content-Type: application/json');
  echo json_encode(['Disponibles'=>$disp, 'Usados'=>$used]);
  exit();
}

// Modal de finalización
if (isset($_GET['abrir_modal_finalizar'])) {
  $id = (int)$_GET['abrir_modal_finalizar'];
  $stmt = $conn->prepare("
    SELECT * 
      FROM esterilizacion_autoclave 
     WHERE ID_Esterilizacion=?
  ");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $procesoSeleccionado = $stmt->get_result()->fetch_assoc();
}

// Procesar inicio
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['iniciar_esterilizacion'])) {
  $self    = basename(__FILE__);
  $id_op   = $_SESSION['ID_Operador'];
  $id_dil  = $_POST['id_dilucion'];
  $tuppers = intval($_POST['tuppers_esterilizados']);
  $con_tapa= isset($_POST['con_tapa']) ? 1 : 0;

  // armar descripción
  $parts = [];
  foreach ($_POST['articulo'] as $i=>$art) {
    $cnt = intval($_POST['cantidad_articulo'][$i] ?? 0);
    if ($cnt>0) $parts[] = "$art ($cnt)";
  }
  $tipo_art = implode(', ', $parts);

  // validar lote
  $chk = $conn->prepare("
    SELECT d.Tuppers_Llenos,
           COALESCE(SUM(ea.Tuppers_Esterilizados),0) AS usados
      FROM dilucion_llenado_tuppers d
 LEFT JOIN esterilizacion_autoclave ea
        ON d.ID_Dilucion=ea.ID_Dilucion
     WHERE d.ID_Dilucion=?
  GROUP BY d.ID_Dilucion
  ");
  $chk->bind_param("s", $id_dil);
  $chk->execute();
  $d = $chk->get_result()->fetch_assoc();
  $restL = $d ? ($d['Tuppers_Llenos'] - $d['usados']) : 0;

  if ($tuppers <= $restL) {
    $ins = $conn->prepare("
      INSERT INTO esterilizacion_autoclave
        (ID_Dilucion,Tipo_Articulo,FechaInicio,Estado,
         ID_Operador,Tuppers_Esterilizados,Con_Tapa)
      VALUES(?, ?, NOW(), 'En proceso', ?, ?, ?)
    ");
    $ins->bind_param("ssiii", $id_dil, $tipo_art, $id_op, $tuppers, $con_tapa);
    $ok = $ins->execute();
    $mensaje = $ok ? "✅ Proceso iniciado." : "❌ Error al iniciar.";
  } else {
    $mensaje = "⚠️ No puedes esterilizar más tuppers de los que hay disponibles.";
  }
  header("Location:$self?mensaje=".urlencode($mensaje));
  exit();
}

// Procesar finalizar
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['finalizar_esterilizacion'])) {
  $self = basename(__FILE__);
  $id_e = intval($_POST['id_esterilizacion']);
  $resu = $_POST['resultado'];
  $obs  = $_POST['observaciones'];

  $up = $conn->prepare("
    UPDATE esterilizacion_autoclave
       SET FechaFin=NOW(),
           Resultado=?,
           Observaciones=?,
           Estado='Finalizado'
     WHERE ID_Esterilizacion=?
  ");
  $up->bind_param("ssi", $resu, $obs, $id_e);
  $suc = $up->execute();
  $mensaje = $suc ? "✅ Proceso finalizado." : "❌ Error al finalizar.";
  header("Location:$self?mensaje=".urlencode($mensaje));
  exit();
}

// Consultas
$procesos    = $conn->query("SELECT * FROM esterilizacion_autoclave WHERE Estado='En proceso'")
                   ->fetch_all(MYSQLI_ASSOC);
$finalizados = $conn->query("
  SELECT * 
    FROM esterilizacion_autoclave 
   WHERE Estado='Finalizado' 
ORDER BY FechaFin DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Esterilización en Autoclave</title>
  <link rel="stylesheet" href="../style.css?v=<?=time();?>">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">
  <style>
    .seccion-interna { display:none; }
    .seccion-activa  { display:block; }
    .form-check-input { width:1.5em; height:1.5em; }
    .d-flex.flex-wrap > .form-check { padding:0.5rem; border-radius:0.25rem; }
    .custom-checkbox:checked { background-color:#0d6efd33; border-color:#0d6efd; }
  </style>
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado d-flex align-items-center">
      <a class="navbar-brand me-3" href="#">
        <img src="../logoplantulas.png" width="130" height="124">
      </a>
      <div>
        <h2>Esterilización en Autoclave</h2>
        <p>Inicio, seguimiento y cierre de procesos.</p>
      </div>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="location.href='dashboard_rpmc.php'">🔄 Regresar</button>
          </div>
        </div>
      </nav>
    </div>
    <nav class="navbar navbar-expand bg-white border-bottom mb-3">
      <div class="container-fluid justify-content-center">
        <button class="btn btn-outline-primary me-2"  onclick="mostrarSeccion('inicio')">➕ Iniciar</button>
        <button class="btn btn-outline-warning me-2" onclick="mostrarSeccion('proceso')">🧪 En proceso</button>
        <button class="btn btn-outline-secondary"     onclick="mostrarSeccion('historial')">📜 Finalizadas</button>
      </div>
    </nav>
    <?php if($mensaje): ?>
      <div class="alert alert-info text-center"><?=htmlspecialchars($mensaje)?></div>
    <?php endif; ?>
  </header>

  <main class="container py-4">
    <!-- INICIO -->
    <div id="inicio" class="seccion-interna seccion-activa">
      <div class="card mb-4">
        <div class="card-header bg-primary text-white">
          Iniciar nueva esterilización
        </div>
        <div class="card-body">
          <form method="POST" class="row g-3">
            <!-- Lote -->
            <div class="col-md-6">
              <label class="form-label">Lote (ID_Dilución)</label>
              <select name="id_dilucion" class="form-select" required>
                <option value="" disabled selected>-- Selecciona --</option>
                <?php
                  $r = $conn->query("
                    SELECT ID_Dilucion 
                      FROM dilucion_llenado_tuppers 
                   ORDER BY ID_Dilucion DESC
                  ");
                  while($x = $r->fetch_assoc()):
                ?>
                  <option><?= $x['ID_Dilucion'] ?></option>
                <?php endwhile; ?>
              </select>
              <div id="info_lote" class="mt-2 text-muted small"></div>
            </div>
            <!-- Tuppers y tapas -->
            <div class="col-md-3">
              <label class="form-label"># Tuppers</label>
              <input type="number" name="tuppers_esterilizados"
                     class="form-control" min="1" required>
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       name="con_tapa" id="con_tapa">
                <label class="form-check-label" for="con_tapa">
                  Incluye tapas
                </label>
              </div>
            </div>

            <!-- Artículos -->
            <div class="col-12">
              <label class="form-label">Artículos a esterilizar</label>
              <div class="d-flex flex-wrap gap-4">
                <?php
                  $artRes = $conn->query("
                    SELECT id_material, nombre 
                      FROM materiales 
                     WHERE nombre IN 
                       ('Pinza grande','Pinza mediana','Bisturí',
                        'Bolsa de periódico','Trapos')
                     ORDER BY nombre
                  ");
                  while($art = $artRes->fetch_assoc()):
                    $i = $art['id_material'];
                ?>
                <div class="form-check d-flex align-items-center mb-2">
                  <input 
                    class="form-check-input me-2 custom-checkbox"
                    type="checkbox"
                    name="articulo[]"
                    value="<?=htmlspecialchars($art['nombre'])?>"
                    id="art<?=$i?>"
                    data-material-id="<?=$i?>">
                  <label class="form-check-label me-3" for="art<?=$i?>">
                    <?=htmlspecialchars($art['nombre'])?>
                  </label>
                  <input 
                    type="number"
                    name="cantidad_articulo[]"
                    class="form-control cantidad-art me-3"
                    style="width:80px; display:none;"
                    min="0"
                    placeholder="0">
                  <small id="info_art_<?=$i?>" class="text-muted">
                    Disponibles: – | Usados: –
                  </small>
                </div>
                <?php endwhile; ?>
              </div>
            </div>

            <div class="col-12 text-end">
              <button name="iniciar_esterilizacion" class="btn btn-primary">
                Iniciar proceso
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- EN PROCESO -->
    <div id="proceso" class="seccion-interna">
      <div class="card mb-4">
        <div class="card-header bg-warning">Esterilizaciones en proceso</div>
        <div class="card-body table-responsive">
          <table class="table table-bordered">
            <thead class="table-dark">
              <tr>
                <th>ID</th><th>Lote</th><th>Inicio</th>
                <th>Artículos</th><th>Tuppers</th><th>Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($procesos as $p): ?>
              <tr>
                <td><?=$p['ID_Esterilizacion']?></td>
                <td><?=$p['ID_Dilucion']?></td>
                <td><?=$p['FechaInicio']?></td>
                <td class="text-wrap"><?=$p['Tipo_Articulo']?></td>
                <td><?=$p['Tuppers_Esterilizados']?></td>
                <td>
                  <a href="?abrir_modal_finalizar=<?=$p['ID_Esterilizacion']?>"
                     class="btn btn-sm btn-success">Finalizar</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- FINALIZADAS -->
    <div id="historial" class="seccion-interna">
      <div class="card mb-4">
        <div class="card-header bg-secondary text-white">Procesos Finalizados</div>
        <div class="card-body table-responsive">
          <table class="table table-bordered table-striped">
            <thead class="table-dark">
              <tr>
                <th>ID</th><th>Lote</th><th>Inicio</th>
                <th>Fin</th><th># Tuppers</th><th>Artículos</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($finalizados)): ?>
              <tr>
                <td colspan="6" class="text-center">
                  No hay procesos finalizados.
                </td>
              </tr>
              <?php else: foreach($finalizados as $f): ?>
              <tr>
                <td><?=$f['ID_Esterilizacion']?></td>
                <td><?=$f['ID_Dilucion']?></td>
                <td><?=$f['FechaInicio']?></td>
                <td><?=$f['FechaFin']?></td>
                <td><?=$f['Tuppers_Esterilizados']?></td>
                <td class="text-wrap">
                  <?=htmlspecialchars($f['Tipo_Articulo'])?>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL FINALIZAR -->
    <?php if($procesoSeleccionado): ?>
    <div class="modal fade show" style="display:block; background:rgba(0,0,0,0.5);">
      <div class="modal-dialog">
        <form method="POST" class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title">
              Finalizar Proceso #<?=$procesoSeleccionado['ID_Esterilizacion']?>
            </h5>
            <a href="esterilizacion_autoclave.php" class="btn-close"></a>
          </div>
          <div class="modal-body">
            <input type="hidden" name="id_esterilizacion"
                   value="<?=$procesoSeleccionado['ID_Esterilizacion']?>">
            <div class="mb-3">
              <label class="form-label">Resultado</label>
              <select name="resultado" class="form-select">
                <option>Exitoso</option>
                <option>Fallido</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Observaciones</label>
              <textarea name="observaciones" class="form-control" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button name="finalizar_esterilizacion" class="btn btn-success">
              Finalizar
            </button>
            <a href="esterilizacion_autoclave.php" class="btn btn-secondary">
              Cancelar
            </a>
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
      .forEach(el => el.classList.remove('seccion-activa'));
    document.getElementById(id).classList.add('seccion-activa');
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Lote info AJAX
    document.querySelector('select[name="id_dilucion"]')
      .addEventListener('change', function() {
        fetch(location.href, {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'ajax_dilucion_info='+encodeURIComponent(this.value)
        })
        .then(r => r.json())
        .then(d => {
          document.getElementById('info_lote').textContent =
            `Llenos: ${d.Tuppers_Llenos} | Usados: ${d.Usados} | Disponibles: ${d.Disponibles}`;
        });
      });

    // Artículo info + toggle cantidad
    document.querySelectorAll('.form-check-input.custom-checkbox')
      .forEach(chk => {
        chk.addEventListener('change', () => {
          const matId   = chk.dataset.materialId;
          const infoEl  = document.getElementById('info_art_' + matId);
          const numIn   = chk.closest('.form-check')
                            .querySelector('.cantidad-art');
          if (chk.checked) {
            numIn.style.display = 'inline-block';
            numIn.required      = true;
            // AJAX materiales
            fetch(location.href, {
              method:'POST',
              headers:{'Content-Type':'application/x-www-form-urlencoded'},
              body:'ajax_material_info='+encodeURIComponent(matId)
            })
            .then(r => r.json())
            .then(data => {
              infoEl.textContent =
                `Disponibles: ${data.Disponibles} | Usados: ${data.Usados}`;
            });
          } else {
            numIn.style.display = 'none';
            numIn.required      = false;
            numIn.value         = '';
            infoEl.textContent  = 'Disponibles: – | Usados: –';
          }
        });
      });
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
