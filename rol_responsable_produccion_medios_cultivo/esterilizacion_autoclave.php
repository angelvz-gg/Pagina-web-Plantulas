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

if ((int) $_SESSION['Rol'] !== 7) {
    echo "<p class=\"error\">⚠️ Acceso denegado. Solo Responsable de Producción de Medios de Cultivo.</p>";
    exit;
}

// 2) Variables para el modal de sesión (3 min inactividad, aviso 1 min antes)
$sessionLifetime = 60 * 3;
$warningOffset   = 60 * 1;
$nowTs           = time();

$mensaje = $_GET['mensaje'] ?? '';
$procesoSeleccionado = null;

// AJAX: info de dilución + medio
if (isset($_POST['ajax_dilucion_info'])) {
    if (ob_get_level()) ob_end_clean();
    $stmt = $conn->prepare("
        SELECT 
          d.Tuppers_Llenos,
          m.Codigo_Medio AS Medio,
          COALESCE(SUM(ea.Tuppers_Esterilizados),0) AS usados
        FROM dilucion_llenado_tuppers d
        LEFT JOIN medios_nutritivos_madre m 
          ON d.ID_MedioNM = m.ID_MedioNM
        LEFT JOIN esterilizacion_autoclave ea 
          ON d.ID_Dilucion = ea.ID_Dilucion
        WHERE d.ID_Dilucion = ?
        GROUP BY d.ID_Dilucion, m.Codigo_Medio
    ");
    $stmt->bind_param("s", $_POST['ajax_dilucion_info']);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) {
        $data = [
            'Tuppers_Llenos' => (int)$r['Tuppers_Llenos'],
            'Medio'          => $r['Medio'],
            'Usados'         => (int)$r['usados'],
        ];
    } else {
        $data = ['Tuppers_Llenos'=>0,'Medio'=>'—','Usados'=>0];
    }
    $data['Disponibles'] = $data['Tuppers_Llenos'] - $data['Usados'];
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// AJAX: lista de tuppers contaminados
if (isset($_POST['ajax_contaminados'])) {
    if (ob_get_level()) ob_end_clean();

    $stmt = $conn->prepare("
      SELECT 
        ID_Perdida,
        Tipo_Entidad,
        Fecha_Perdida,
        COALESCE(Tuppers_Perdidos,0)  AS Tuppers_Perdidos,
        COALESCE(Motivo,'—')          AS Motivo
      FROM perdidas_laboratorio
      WHERE COALESCE(Tuppers_Perdidos,0) > 0
      ORDER BY Fecha_Perdida DESC
    ");
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($row = $res->fetch_assoc()) {
      $out[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

// AJAX: info de material
if (isset($_POST['ajax_material_info'])) {
    $idm = intval($_POST['ajax_material_info']);
    $stmt1 = $conn->prepare("
        SELECT GREATEST(cantidad - en_uso,0)
          - COALESCE((SELECT SUM(cantidad) 
                      FROM movimientos_materiales 
                      WHERE id_material = ? 
                        AND tipo_movimiento IN ('asignacion','esterilizacion')),0)
        AS Disponibles, en_uso
        FROM inventario_materiales 
        WHERE id_material = ?
    ");
    $stmt1->bind_param('ii', $idm, $idm);
    $stmt1->execute();
    $row = $stmt1->get_result()->fetch_assoc();
    $disp = max((int)$row['Disponibles'],0);

    $stmt2 = $conn->prepare("
        SELECT COALESCE(SUM(Tuppers_Esterilizados),0) AS Usados 
        FROM esterilizacion_autoclave 
        WHERE id_material = ?
    ");
    $stmt2->bind_param('i', $idm);
    $stmt2->execute();
    $used = (int)$stmt2->get_result()->fetch_assoc()['Usados'];

    header('Content-Type: application/json');
    echo json_encode(['Disponibles'=>$disp,'Usados'=>$used]);
    exit();
}

// Modal de finalización
if (isset($_GET['abrir_modal_finalizar'])) {
    $paquete = (int)$_GET['abrir_modal_finalizar'];
    $stmt = $conn->prepare("
        SELECT 
          ID_Esterilizacion,
          id_paquete, 
          FechaInicio, 
          Tipo_Articulo, 
          ID_Operador
        FROM esterilizacion_autoclave
        WHERE id_paquete = ?
        ORDER BY ID_Esterilizacion DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $paquete);
    $stmt->execute();
    $procesoSeleccionado = $stmt->get_result()->fetch_assoc();
}


// Procesar inicio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iniciar_esterilizacion'])) {
    $self  = basename(__FILE__);
    $id_op = $_SESSION['ID_Operador'];

// 0.5) Si seleccionó un registro contaminado, leer ID y cantidad
$id_perdida_cont = intval($_POST['id_perdida'] ?? 0);
$tupCont        = intval($_POST['tuppers_contaminados'][$id_perdida_cont] ?? 0);

// calcular vacíos y tapas SOLO si marcó el checkbox esterilizar_vacios
if (!empty($_POST['esterilizar_vacios'])) {
    $tupVac = intval($_POST['tuppers_vacios'] ?? 0);
    // calculamos tapas
    switch ($_POST['con_tapas_vacios'] ?? '') {
        case 'mismo':
            $tapVac = $tupVac;
            break;
        case 'otro':
            $tapVac = intval($_POST['tapas_vacios_otras'] ?? 0);
            break;
        default:
            $tapVac = 0;
    }
    // Con tapa solo si no es "ninguno"
    $con_tapa = ($_POST['con_tapas_vacios'] ?? '') !== 'ninguno' ? 1 : 0;
} else {
    $tupVac = 0;
    $tapVac = 0;
    $con_tapa = 0;
}

    // 0) Crear un nuevo paquete y obtener su ID
    $stmtP = $conn->prepare("
        INSERT INTO paquete_esterilizacion (operador_id)
        VALUES (?)
    ");
    $stmtP->bind_param("i", $id_op);
    $stmtP->execute();
    $id_paquete = $conn->insert_id;

    // 1) Movimientos para materiales seleccionados
if (!empty($_POST['articulo_ids'])) {
    foreach ($_POST['articulo_ids'] as $mid) {
        // ahora cantidad_articulo es un array asociativo [id_material => cantidad]
        $cnt = intval($_POST['cantidad_articulo'][$mid] ?? 0);  
        if ($cnt > 0) {
            $mvMat = $conn->prepare("
                INSERT INTO movimientos_materiales
                  (id_material, tipo_movimiento, cantidad, id_operador_asignado, id_encargado)
                VALUES
                  (?, 'esterilizacion', ?, ?, ?)
            ");
            $mvMat->bind_param("iiii", $mid, $cnt, $id_op, $id_op);
            $mvMat->execute();
        }
    }
}


    // 2) Movimientos para tuppers vacíos y tapas
    if ($tupVac > 0) {
        $mv = $conn->prepare("
            INSERT INTO movimientos_materiales
              (tipo_movimiento, cantidad, id_operador_asignado, id_encargado)
            VALUES
              ('esterilizacion', ?, ?, ?)
        ");
        $mv->bind_param("iii", $tupVac, $id_op, $id_op);
        $mv->execute();
    }
    if ($tapVac > 0) {
        $mv = $conn->prepare("
            INSERT INTO movimientos_materiales
              (tipo_movimiento, cantidad, id_operador_asignado, id_encargado)
            VALUES
              ('esterilizacion', ?, ?, ?)
        ");
        $mv->bind_param("iii", $tapVac, $id_op, $id_op);
        $mv->execute();
    }

// 3) Descripción de artículos para guardar en esterilizacion_autoclave
$parts = [];
if (!empty($_POST['articulo_ids'])) {
    foreach ($_POST['articulo_ids'] as $mid) {
        // lee la cantidad usando el ID como clave
        $cnt = intval($_POST['cantidad_articulo'][$mid] ?? 0);
        if ($cnt > 0) {
            // obtén el nombre del material
            $stmtMat = $conn->prepare(
                "SELECT nombre FROM materiales WHERE id_material = ?"
            );
            $stmtMat->bind_param("i", $mid);
            $stmtMat->execute();
            $resMat = $stmtMat->get_result()->fetch_assoc();
            $nom = $resMat
                ? $resMat['nombre']
                : "Material #$mid";
            // arma la descripción
            $parts[] = "$nom ($cnt)";
        }
    }
}

    $tipo_art = implode(', ', $parts);

// 3.5) Si marcó “esterilizar contaminados”, insertar ese proceso y descontar en la tabla de pérdidas
if (!empty($_POST['contaminados_toggle'])) {
    // Solo si hay ID de pérdida y cantidad válida
    if ($id_perdida_cont > 0 && $tupCont > 0) {
        // 3.5.a) Inserta el proceso de tuppers contaminados
        $insCont = $conn->prepare("
            INSERT INTO esterilizacion_autoclave
              (id_paquete, ID_Dilucion, Tipo_Articulo, FechaInicio, Estado,
               ID_Operador, Tuppers_Esterilizados, Con_Tapa, Tuppers_Vacios, Tapas_Vacias,
               ID_Perdida)
            VALUES
              (?, NULL, 'Tuppers contaminados', NOW(), 'En proceso',
               ?, ?, 0, 0, 0, ?)
        ");
        $insCont->bind_param(
            "iiii",
            $id_paquete,        // 1) este paquete
            $id_op,             // 2) operador
            $tupCont,           // 3) tuppers contaminados a esterilizar
            $id_perdida_cont    // 4) ID_Perdida (trazabilidad)
        );
        $insCont->execute();

        // 3.5.b) Descontar esos tuppers de la tabla de pérdidas
        $upd = $conn->prepare("
            UPDATE perdidas_laboratorio
               SET Tuppers_Perdidos = GREATEST(Tuppers_Perdidos - ?, 0)
             WHERE ID_Perdida = ?
        ");
        $upd->bind_param("ii", $tupCont, $id_perdida_cont);
        $upd->execute();
    }
}

  // 4) Insertar un proceso por cada lote, incluyendo id_paquete, vacíos y tapas
if (!empty($_POST['id_dilucion'])) {
    foreach ($_POST['id_dilucion'] as $idx => $id_dil) {
        $tuppers = intval($_POST['tuppers_esterilizados'][$idx] ?? 0);

        // validación de existencias
        $chk = $conn->prepare("
            SELECT d.Tuppers_Llenos,
                   COALESCE(SUM(ea.Tuppers_Esterilizados),0) AS usados
            FROM dilucion_llenado_tuppers d
            LEFT JOIN esterilizacion_autoclave ea 
              ON d.ID_Dilucion = ea.ID_Dilucion
            WHERE d.ID_Dilucion = ?
            GROUP BY d.ID_Dilucion
        ");
        $chk->bind_param("s", $id_dil);
        $chk->execute();
        $d = $chk->get_result()->fetch_assoc();
        $restL = $d ? ($d['Tuppers_Llenos'] - $d['usados']) : 0;

        if ($tuppers > 0 && $tuppers <= $restL) {
            $ins = $conn->prepare("
              INSERT INTO esterilizacion_autoclave
                (id_paquete, ID_Dilucion, Tipo_Articulo, FechaInicio, Estado,
                 ID_Operador, Tuppers_Esterilizados, Con_Tapa, Tuppers_Vacios, Tapas_Vacias)
              VALUES
                (?, ?, ?, NOW(), 'En proceso', ?, ?, ?, ?, ?)
            ");
            $ins->bind_param(
              "issiiiii",
              $id_paquete,
              $id_dil,
              $tipo_art,
              $id_op,
              $tuppers,
              $con_tapa,
              $tupVac,
              $tapVac
            );
            $ins->execute();
        }
    }
}


    header("Location: $self?mensaje=" . urlencode("✅ Procesos iniciados."));
    exit;
}

// Procesar finalizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_esterilizacion'])) {
    $self = basename(__FILE__);
    // 1) Leer el paquete a cerrar
    $id_paquete = intval($_POST['id_paquete'] ?? 0);

    if ($id_paquete) {
        // 2) Actualizar todas las filas de ese paquete
        $up = $conn->prepare("
          UPDATE esterilizacion_autoclave
             SET FechaFin      = NOW(),
                 Resultado     = ?,
                 Observaciones = ?,
                 Estado        = 'Finalizado'
           WHERE id_paquete   = ?
        ");
        $up->bind_param(
          "ssi",
          $_POST['resultado'],
          $_POST['observaciones'],
          $id_paquete
        );
        $up->execute();
    }

    header("Location: $self?mensaje=" . urlencode("✅ Proceso finalizado."));
    exit;
}

// “En proceso”
$procesos = $conn->query("
  SELECT
    e.id_paquete                            AS ID,
    GROUP_CONCAT(COALESCE(e.ID_Dilucion,'—') SEPARATOR ', ') AS Lotes,
    MIN(e.FechaInicio)                      AS FechaInicio,
    MAX(e.Tipo_Articulo)                    AS Articulos,
    -- suma solo los tuppers de contaminados
    SUM(CASE WHEN e.ID_Perdida IS NOT NULL THEN e.Tuppers_Esterilizados ELSE 0 END)
      AS TotalContaminados,
    -- totales generales
    SUM(e.Tuppers_Esterilizados)            AS TotalTuppers,
    SUM(e.Tuppers_Vacios)                   AS TotalVacios,
    SUM(e.Tapas_Vacias)                     AS TotalTapas
  FROM esterilizacion_autoclave e
  WHERE e.Estado = 'En proceso'
  GROUP BY e.id_paquete
  ORDER BY FechaInicio
")->fetch_all(MYSQLI_ASSOC);


// “Finalizadas”
$finalizados = $conn->query("
  SELECT
    e.id_paquete                            AS ID,
    GROUP_CONCAT(e.ID_Dilucion SEPARATOR ', ') AS Lotes,
    MIN(e.FechaInicio)                      AS FechaInicio,
    MAX(e.FechaFin)                         AS FechaFin,
    MAX(e.Tipo_Articulo)                    AS Articulos,              -- <— y aquí
    SUM(e.Tuppers_Esterilizados)            AS TotalTuppers,
    SUM(e.Tuppers_Vacios)                   AS TotalVacios,
    SUM(e.Tapas_Vacias)                     AS TotalTapas,
    MAX(e.Resultado)                        AS Resultado,
    MAX(e.Observaciones)                    AS Observaciones
  FROM esterilizacion_autoclave e
  WHERE e.Estado = 'Finalizado'
  GROUP BY e.id_paquete
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .seccion-interna { display:none; }
    .seccion-activa  { display:block; }
    .form-check-input { width:1.5em; height:1.5em; }
    .cantidad-art { display:none; width:80px; }
    .custom-checkbox:checked { background-color:#0d6efd33; border-color:#0d6efd; }
  </style>
  <script>
    const SESSION_LIFETIME=<?= $sessionLifetime*1000 ?>;
    const WARNING_OFFSET  =<?= $warningOffset*1000   ?>;
    let START_TS         =<?= $nowTs*1000           ?>;
  </script>
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado d-flex align-items-center">
      <a class="navbar-brand me-3" href="#"><img src="../logoplantulas.png" width="130" height="124"></a>
      <div>
        <h2>Esterilización en Autoclave</h2>
        <p>Inicio, seguimiento y cierre de procesos.</p>
      </div>
    </div>
        <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_rpmc.php'">
              🏠 Volver al Inicio
              </button>
            </div>
          </div>
        </nav>
      </div>
    <nav class="navbar navbar-expand bg-white border-bottom mb-3">
      <div class="container-fluid justify-content-center">
        <button class="btn btn-outline-primary me-2" onclick="mostrarSeccion('inicio')">➕ Iniciar</button>
        <button class="btn btn-outline-warning me-2" onclick="mostrarSeccion('proceso')">🧪 En proceso</button>
        <button class="btn btn-outline-secondary" onclick="mostrarSeccion('historial')">📜 Finalizadas</button>
      </div>
    </nav>
    <?php if($mensaje): ?><div class="alert alert-info text-center"><?=htmlspecialchars($mensaje)?></div><?php endif; ?>
  </header>
  
<main class="container py-4">
  <!-- INICIO -->
  <div id="inicio" class="seccion-interna <?= $procesoSeleccionado ? '' : 'seccion-activa' ?>">
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">Iniciar nueva esterilización</div>
      <div class="card-body">
        <form method="POST" class="row g-3">
        
 <!-- Esterilizar tuppers vacíos -->
<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">¿Desea esterilizar tuppers vacíos?</h5>
  </div>
  <div class="card-body d-flex flex-wrap align-items-center gap-3">
    <div class="form-check">
      <!-- 1) name y value para el checkbox -->
      <input class="form-check-input" 
             type="checkbox" 
             id="vacios_toggle"
             name="esterilizar_vacios"
             value="1">
    </div>
    <label class="form-check-label" for="vacios_toggle">Sí</label>

    <div class="vacios-options d-none d-flex align-items-center gap-2">
      <label class="mb-0">¿Cuántos tuppers?</label>
      <!-- 2) deshabilitado por defecto -->
      <input type="number" 
             id="num_vacios" 
             name="tuppers_vacios"
             class="form-control" 
             min="1" value="1" 
             style="width:80px;"
             disabled>

      <label class="mb-0">¿Con tapas?</label>
      <select id="vacios_tapas" 
              name="con_tapas_vacios" 
              class="form-select" 
              style="width:150px;">
        <option value="">Seleccione una opción</option>
        <option value="mismo">Mismo número</option>
        <option value="ninguno">Sin tapas</option>
        <option value="otro">Otro</option>
      </select>

      <div id="otro_tapas_group" class="d-none align-items-center gap-2">
        <label class="mb-0"># tapas</label>
        <input type="number" 
               id="otro_tapas" 
               name="tapas_vacios_otras"
               class="form-control" 
               min="1" 
               style="width:80px;">
      </div>
    </div>
  </div>
</div>

<!-- Esterilizar tuppers contaminados -->
<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">¿Desea esterilizar tuppers contaminados?</h5>
  </div>
  <div class="card-body d-flex align-items-center gap-3">
    <div class="form-check">
      <input class="form-check-input" 
             type="checkbox" 
             id="contaminados_toggle"
             name="contaminados_toggle" 
             value="1">
    </div>
    <label class="form-check-label" for="contaminados_toggle">Sí</label>
  </div>

  <div id="contaminados_list" class="card-body table-responsive d-none">
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tipo</th>
          <th>Fecha</th>
          <th>Tuppers Disponibles</th>
          <th>Motivo</th>
          <th>Cantidad de tuppers a esterilizar</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>
    <!-- ¿Cuántos lotes? -->
<div id="lotes_section" class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">¿Cuántos lotes?</h5>
  </div>
  <div class="card-body d-flex align-items-center gap-2">
    <input type="number" id="num_lotes" class="form-control" min="1" max="5" value="" placeholder="0" style="width:80px;">
    <button type="button" id="confirm_lotes" class="btn btn-outline-secondary">Confirmar</button>
  </div>
  <div id="lotes_container" class="card-body pt-0"></div>
</div>

          <!-- Sección Artículos -->
<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">Artículos a esterilizar</h5>
  </div>
  <div class="card-body d-flex flex-wrap gap-4">
    <?php
    $sql = "
      WITH asignados AS (
        SELECT id_material, SUM(cantidad) AS total_asig
        FROM movimientos_materiales
        WHERE tipo_movimiento='asignacion'
        GROUP BY id_material
      ), ester AS (
        SELECT id_material, SUM(cantidad) AS total_est
        FROM movimientos_materiales
        WHERE tipo_movimiento='esterilizacion'
        GROUP BY id_material
      ), materiales_disponibles AS (
        SELECT 
          im.id_material AS id,
          m.nombre            AS descripcion,
          GREATEST(
            im.cantidad - im.en_uso
            - COALESCE(asignados.total_asig,0)
            - COALESCE(ester.total_est,0),
          0) AS disponible
        FROM inventario_materiales im
        JOIN materiales m ON m.id_material = im.id_material
        LEFT JOIN asignados ON asignados.id_material = im.id_material
        LEFT JOIN ester     ON ester.id_material     = im.id_material

        UNION ALL

        SELECT
          p.ID_Entidad      AS id,
          CONCAT('Contaminado #',p.ID_Perdida) AS descripcion,
          p.Tuppers_Perdidos               AS disponible
        FROM perdidas_laboratorio p
        WHERE p.Tipo_Entidad='esterilizacion_autoclave'
          AND p.Tuppers_Perdidos>0
      )
      SELECT *
      FROM materiales_disponibles
      WHERE disponible > 0
      ORDER BY descripcion
    ";
    $artRes = $conn->query($sql);

    while($art = $artRes->fetch_assoc()):
      $i    = $art['id'];
      $disp = $art['disponible'];
    ?>
      <div class="form-check d-flex align-items-center mb-2">
  <input
    class="form-check-input custom-checkbox me-2"
    type="checkbox"
    name="articulo_ids[]"
    id="art<?= $i ?>"
    data-id="<?= $i ?>"
    value="<?= $i ?>">
  <label class="form-check-label me-3" for="art<?= $i ?>">
    <?= htmlspecialchars($art['descripcion']) ?>
  </label>
  <input
    type="number"
    class="form-control cantidad-art me-3"
    data-id="<?= $i ?>"
    min="0"
    placeholder="0"
    style="width: 80px;"
    disabled>
  <small id="info_art_<?= $i ?>" class="text-muted">
    Disponibles: <?= $disp ?> | Usados: –
  </small>
</div>

    <?php endwhile; ?>
  </div>
</div>

          <!-- Botón enviar -->
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
<div id="proceso" class="seccion-interna  <?= $procesoSeleccionado ? 'seccion-activa' : '' ?>"> 
  <div class="card mb-4">
    <div class="card-header bg-warning">Esterilizaciones en proceso</div>
    <div class="card-body table-responsive">
      <table class="table table-bordered">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Detalles por Lote</th>
            <th>Vacíos / Tapas</th>
            <th>Contaminados</th>
            <th>Inicio</th>
            <th>Artículos</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($procesos as $p): ?>
            <tr>
              <td><?= $p['ID'] ?></td>
              <td>
                <ul class="list-unstyled mb-0">
                  <?php
                  // Por cada lote dentro del paquete, contamos sus tuppers
                  $stmtCnt = $conn->prepare("
                    SELECT COALESCE(SUM(Tuppers_Esterilizados),0) AS cnt
                    FROM esterilizacion_autoclave
                    WHERE id_paquete = ?
                      AND ID_Dilucion = ?
                  ");
                  foreach (explode(', ', $p['Lotes']) as $lote):
                    $stmtCnt->bind_param("is", $p['ID'], $lote);
                    $stmtCnt->execute();
                    $cnt = $stmtCnt->get_result()->fetch_assoc()['cnt'];
                  ?>
                    <li>
                      <strong><?= htmlspecialchars($lote) ?></strong>:
                      <?= $cnt ?> tuppers
                    </li>
                  <?php endforeach; ?>
                </ul>
              </td>
              <td>
                <small>
                  Vacíos: <?= $p['TotalVacios'] ?><br>
                  Tapas: <?= $p['TotalTapas'] ?>
                </small>
              </td>
              <td>
                <?= $p['TotalContaminados'] ?>
              </td>
              <td><?= $p['FechaInicio'] ?></td>
              <td class="text-wrap"><?= htmlspecialchars($p['Articulos']) ?></td>
              <td>
                <a href="?abrir_modal_finalizar=<?= $p['ID'] ?>"
                   class="btn btn-sm btn-success">
                  Finalizar
                </a>
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
            <th>ID</th>
            <th>Detalles por Lote</th>
            <th>Vacíos / Tapas</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Artículos</th>
            <th>Resultado</th>
            <th>Observaciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($finalizados)): ?>
            <tr><td colspan="8" class="text-center">No hay procesos finalizados.</td></tr>
          <?php else: ?>
            <?php
            // Preparar contador para finalizados
            $stmtCntF = $conn->prepare("
              SELECT COALESCE(SUM(Tuppers_Esterilizados),0) AS cnt
              FROM esterilizacion_autoclave
              WHERE id_paquete = ?
                AND ID_Dilucion = ?
                AND Estado = 'Finalizado'
            ");
            ?>
            <?php foreach ($finalizados as $p): ?>
              <tr>
                <td><?= $p['ID'] ?></td>
                <td>
                  <ul class="list-unstyled mb-0">
    <?php 
    // Si no hay lotes, mostramos directamente el total de tuppers (contaminados u otro tipo)
    if (trim((string)$p['Lotes']) === ''): ?>
      <li>
        <strong>Total</strong>: <?= $p['TotalTuppers'] ?> tuppers
      </li>
    <?php 
    else:
      // Si sí hay lotes, iteramos como antes
      foreach (explode(', ', $p['Lotes']) as $lote):
        $stmtCntF->bind_param("is", $p['ID'], $lote);
        $stmtCntF->execute();
        $cntF = $stmtCntF->get_result()->fetch_assoc()['cnt'];
    ?>
      <li>
        <strong><?= htmlspecialchars($lote) ?></strong>: <?= $cntF ?> tuppers
      </li>
    <?php 
      endforeach;
    endif;
    ?>
  </ul>
</td>
                <td>
                  <small>
                    Vacíos: <?= $p['TotalVacios'] ?><br>
                    Tapas: <?= $p['TotalTapas'] ?>
                  </small>
                </td>
                <td><?= $p['FechaInicio'] ?></td>
                <td><?= $p['FechaFin'] ?></td>
                <td class="text-wrap"><?= htmlspecialchars($p['Articulos']) ?></td>
                <td><?= htmlspecialchars($p['Resultado']) ?></td>
                <td><?= nl2br(htmlspecialchars($p['Observaciones'])) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<!-- MODAL FINALIZAR -->
<?php if($procesoSeleccionado): ?>
  <div class="modal fade" id="modalFinalizar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="POST" class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">
            Finalizar Proceso #<?=htmlspecialchars($procesoSeleccionado['ID_Esterilizacion'])?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id_paquete" value="<?=htmlspecialchars($procesoSeleccionado['id_paquete'])?>">
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
          <button name="finalizar_esterilizacion" class="btn btn-success">Finalizar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

</main>
</div>



<script>
  // Navegación de secciones
  function mostrarSeccion(id) {
    document.querySelectorAll('.seccion-interna')
      .forEach(el => el.classList.remove('seccion-activa'));
    document.getElementById(id).classList.add('seccion-activa');
  }

  // Render dinámico de lotes
  function renderLotes(n) {
    const cont = document.getElementById('lotes_container');
    cont.innerHTML = '';
    for (let i = 0; i < n; i++) {
      cont.insertAdjacentHTML('beforeend', `
        <div class="row mb-3 lote-block">
          <div class="col-md-6">
            <label class="form-label">Lote ID_Dilución</label>
            <select name="id_dilucion[]" class="form-select lote-select" required>
              <option value="" disabled selected>-- Selecciona --</option>
              <?php
                $r = $conn->query("SELECT ID_Dilucion FROM dilucion_llenado_tuppers ORDER BY ID_Dilucion DESC");
                while($x = $r->fetch_assoc()):
              ?>
                <option><?= $x['ID_Dilucion'] ?></option>
              <?php endwhile;?>
            </select>
            <small class="info-lote text-muted small">
              Medio: – | Llenos: – | Usados: – | Disponibles: –  
            </small>
          </div>
          <div class="col-md-3">
            <label class="form-label"># Tuppers</label>
            <input type="number" name="tuppers_esterilizados[]"
                   class="form-control lote-qty" min="1" required>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check">
              <input type="checkbox" name="todos_tuppers[]"
                     class="form-check-input" id="todos_${i}">
              <label class="form-check-label" for="todos_${i}">Todos</label>
            </div>
          </div>
        </div>
      `);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {

    // Confirmar cantidad de lotes
    document.getElementById('confirm_lotes')
      .addEventListener('click', () => {
        const n = parseInt(document.getElementById('num_lotes').value, 10) || 0;
        renderLotes(n);
      });

    // AJAX de lotes y validaciones
    document.getElementById('lotes_container')
      .addEventListener('change', e => {
        if (e.target.classList.contains('lote-select')) {
          const vals = Array.from(document.querySelectorAll('.lote-select'))
            .map(s => s.value).filter(v => v);
          if (vals.some((v,i,a) => a.indexOf(v)!==i)) {
            alert('No puedes seleccionar el mismo lote dos veces.');
            e.target.value = '';
            return;
          }
          const sel  = e.target;
          const info = sel.closest('.lote-block').querySelector('.info-lote');
          fetch(window.location.pathname, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `ajax_dilucion_info=${encodeURIComponent(sel.value)}`
          })
          .then(r => r.json())
          .then(d => {
            info.textContent = 
              `Medio: ${d.Medio} | Llenos: ${d.Tuppers_Llenos}` +
              ` | Usados: ${d.Usados} | Disponibles: ${d.Disponibles}`;
            sel.closest('.lote-block')
               .querySelector('.lote-qty').max = d.Disponibles;
          })
          .catch(err => console.error('Fetch error:', err));
        }
        if (e.target.name === 'todos_tuppers[]') {
          const blo = e.target.closest('.lote-block');
          const inp = blo.querySelector('input[name="tuppers_esterilizados[]"]');
          const disp = parseInt(
            blo.querySelector('.info-lote').textContent.match(/Disponibles:\s*(\d+)/)[1],
            10
          );
          if (e.target.checked) {
            inp.value = disp; inp.readOnly = true;
          } else {
            inp.value = ''; inp.readOnly = false;
          }
        }
        if (e.target.classList.contains('lote-qty')) {
          const val = parseInt(e.target.value,10);
          const max = parseInt(e.target.max,10);
          if (val < 1 || val > max) {
            alert(`La cantidad debe estar entre 1 y ${max}.`);
            e.target.value = '';
          }
        }
      });

    // Lógica de artículos
    document.querySelectorAll('input[name="articulo_ids[]"]').forEach(chk => {
      const id    = chk.value;
      const numIn = document.querySelector(`.cantidad-art[data-id="${id}"]`);
      const info  = document.getElementById(`info_art_${id}`);

      chk.addEventListener('change', () => {
        if (chk.checked) {
          numIn.style.display = 'inline-block';
          numIn.disabled      = false;
          numIn.name          = `cantidad_articulo[${id}]`;
          numIn.required      = true;

          fetch(window.location.pathname, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `ajax_material_info=${encodeURIComponent(id)}`
          })
          .then(r => r.json())
          .then(data => {
            info.textContent = `Disponibles: ${data.Disponibles} | Usados: ${data.Usados}`;
          })
          .catch(err => console.error('Fetch error:', err));
        } else {
          numIn.style.display = 'none';
          numIn.disabled      = true;
          numIn.removeAttribute('name');
          numIn.required      = false;
          numIn.value         = '';
          info.textContent    = 'Disponibles: – | Usados: –';
        }
      });
    });

    // ───────── BLOQUE CONTAMINADOS ─────────
    const contToggle = document.getElementById('contaminados_toggle');
    const contList   = document.getElementById('contaminados_list');
    const contBody   = contList.querySelector('tbody');

    contList.classList.add('d-none');

    contToggle.addEventListener('change', () => {
      const show = contToggle.checked;
      contList.classList.toggle('d-none', !show);

      if (show) {
        contBody.innerHTML = '<tr><td colspan="6">Cargando…</td></tr>';
        fetch(window.location.pathname, {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: 'ajax_contaminados=1'
        })
        .then(r => r.json())
        .then(datos => {
          if (!datos.length) {
            contBody.innerHTML = '<tr><td colspan="6">No hay tuppers contaminados.</td></tr>';
          } else {
            contBody.innerHTML = datos.map(p => `
              <tr>
                <td><input type="radio" name="id_perdida" value="${p.ID_Perdida}"></td>
                <td>${p.Tipo_Entidad}</td>
                <td>${p.Fecha_Perdida}</td>
                <td>${p.Tuppers_Perdidos}</td>
                <td>${p.Motivo}</td>
                <td>
                  <input type="number"
                         name="tuppers_contaminados[${p.ID_Perdida}]"
                         class="form-control"
                         min="1" max="${p.Tuppers_Perdidos}"
                         disabled>
                </td>
              </tr>
            `).join('');
            document.querySelectorAll('input[name="id_perdida"]').forEach(radio => {
              radio.addEventListener('change', () => {
                document.querySelectorAll('input[name^="tuppers_contaminados"]').forEach(i => {
                  i.disabled = true; i.value = '';
                });
                const sel = document.querySelector(
                  `input[name="tuppers_contaminados[${radio.value}]"]`
                );
                sel.disabled = false;
                sel.required = true;
              });
            });
          }
        })
        .catch(() => {
          contBody.innerHTML = '<tr><td colspan="6" class="text-danger">Error cargando datos.</td></tr>';
        });
      }
    });
    // ────────────────────────────────────────

    // Vacíos
    const vaciosToggle = document.getElementById('vacios_toggle');
    const vaciosOpts   = document.querySelectorAll('.vacios-options');
    const numVacios    = document.getElementById('num_vacios');
    const vaciosTapas  = document.getElementById('vacios_tapas');
    const otroTapasGrp = document.getElementById('otro_tapas_group');
    const otroTapas    = document.getElementById('otro_tapas');

    numVacios.disabled = !vaciosToggle.checked;

    vaciosToggle.addEventListener('change', e => {
      const chk = e.target.checked;
      vaciosOpts.forEach(el => el.classList.toggle('d-none', !chk));
      numVacios.disabled = !chk;
      if (!chk) {
        numVacios.value = '';
        vaciosTapas.value = '';
        otroTapasGrp.classList.add('d-none');
        otroTapas.value = '';
      }
    });

    vaciosTapas.addEventListener('change', e => {
      otroTapasGrp.classList.toggle('d-none', e.target.value !== 'otro');
      if (e.target.value !== 'otro') otroTapas.value = '';
    });

    numVacios.addEventListener('input', () => {
      otroTapas.max = numVacios.value;
      if (vaciosTapas.value === 'otro') {
        vaciosTapas.value = '';
        vaciosTapas.dispatchEvent(new Event('change'));
      }
    });
    otroTapas.addEventListener('input', () => {
      if (parseInt(otroTapas.value,10) > parseInt(numVacios.value,10)) {
        alert('Las tapas no pueden exceder el número de tuppers.');
        otroTapas.value = numVacios.value;
      }
    });
  });

  
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if($procesoSeleccionado): ?>
<script>
  // 1) Cambia a la sección “En proceso”
  mostrarSeccion('proceso');

  // 2) Lanza el modal de Bootstrap
  const modalEl = document.getElementById('modalFinalizar');
  const bsModal = new bootstrap.Modal(modalEl);
  bsModal.show();
</script>
<?php endif; ?>

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
