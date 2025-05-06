<?php
// exportar_reportes.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include __DIR__ . '/../db.php';
session_start();
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 8) {
    header('Location: ../login.php');
    exit();
}

$operador   = trim($_GET['operador']   ?? '');
$fechaDesde = $_GET['desde']          ?? '';
$fechaHasta = $_GET['hasta']          ?? '';
$type       = $_GET['type']           ?? '';

$opEsc = $conn->real_escape_string($operador);

$where = "WHERE 1=1";
if ($operador   !== '') $where .= " AND r.Operador_Original = '{$opEsc}'";
if ($fechaDesde !== '') $where .= " AND r.Fecha >= '{$fechaDesde}'";
if ($fechaHasta !== '') $where .= " AND r.Fecha <= '{$fechaHasta}'";

$sql = "
SELECT * FROM (
  -- Multiplicación
  SELECT
    'Multiplicación' AS Etapa,
    M.ID_Multiplicacion AS ID,
    -- Nombre del operador responsable de siembra
    CONCAT(O.Nombre,' ',O.Apellido_P,' ',O.Apellido_M) AS Operador_Original,
    -- Nombre del que consolida
    CONCAT(Oc.Nombre,' ',Oc.Apellido_P,' ',Oc.Apellido_M) AS Operador_Consolida,
    CONCAT(V.Codigo_Variedad,' – ',V.Nombre_Variedad)    AS Variedad,
    COALESCE(NULLIF(V.Color, ''), 'S/D')                 AS Color,
    M.Fecha_Siembra                                     AS Fecha,
    M.Cantidad_Dividida                                 AS Cantidad
  FROM multiplicacion M
  JOIN operadores O       ON M.Operador_Responsable = O.ID_Operador
  JOIN consolidacion_log CL ON CL.ID_Multiplicacion = M.ID_Multiplicacion
  JOIN operadores Oc      ON CL.ID_Operador        = Oc.ID_Operador
  JOIN variedades V       ON M.ID_Variedad        = V.ID_Variedad
  WHERE M.Estado_Revision = 'Consolidado'

  UNION ALL

  -- Enraizamiento
  SELECT
    'Enraizamiento' AS Etapa,
    E.ID_Enraizamiento AS ID,
    CONCAT(O.Nombre,' ',O.Apellido_P,' ',O.Apellido_M) AS Operador_Original,
    CONCAT(Oc2.Nombre,' ',Oc2.Apellido_P,' ',Oc2.Apellido_M) AS Operador_Consolida,
    CONCAT(V2.Codigo_Variedad,' – ',V2.Nombre_Variedad)       AS Variedad,
    COALESCE(NULLIF(V2.Color, ''), 'S/D')                      AS Color,
    E.Fecha_Siembra                                           AS Fecha,
    E.Cantidad_Dividida                                       AS Cantidad
  FROM enraizamiento E
  JOIN operadores O       ON E.Operador_Responsable = O.ID_Operador
  JOIN consolidacion_log CL2 ON CL2.ID_Enraizamiento = E.ID_Enraizamiento
  JOIN operadores Oc2     ON CL2.ID_Operador        = Oc2.ID_Operador
  JOIN variedades V2      ON E.ID_Variedad         = V2.ID_Variedad
  WHERE E.Estado_Revision = 'Consolidado'
) AS r
{$where}
ORDER BY r.Fecha DESC
";

$result = $conn->query($sql);

// Excel
if ($type === 'excel') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reportes_consolidados.csv"');
    echo "\xEF\xBB\xBF";
    echo "Etapa,ID,Operador Original,Consolidado Por,Variedad,Color,Fecha de Siembra,Cantidad\n";
    while ($row = $result->fetch_assoc()) {
        $fields = array_map(fn($f) => '"'.str_replace('"','""',$f).'"', [
            $row['Etapa'],
            $row['ID'],
            $row['Operador_Original'],
            $row['Operador_Consolida'],
            $row['Variedad'],
            $row['Color'],
            $row['Fecha'],
            $row['Cantidad']
        ]);
        echo implode(',', $fields)."\n";
    }
    exit;
}

// PDF inline
if ($type === 'pdf') {
    $dompdf = new Dompdf();
    $html  = '<h2 style="text-align:center;">Reportes Consolidados</h2>'
           . '<table border="1" cellpadding="5" cellspacing="0" width="100%">'
           . '<thead><tr style="background:#45814d;color:white;">'
           . '<th>Etapa</th><th>ID</th>'
           . '<th>Operador Original</th><th>Consolidado Por</th>'
           . '<th>Variedad</th><th>Color</th>'
           . '<th>Fecha de Siembra</th><th>Cantidad</th>'
           . '</tr></thead><tbody>';
    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>'
               . '<td>'.$row['Etapa'].'</td>'
               . '<td>'.$row['ID'].'</td>'
               . '<td>'.htmlspecialchars($row['Operador_Original']).'</td>'
               . '<td>'.htmlspecialchars($row['Operador_Consolida']).'</td>'
               . '<td>'.htmlspecialchars($row['Variedad']).'</td>'
               . '<td>'.htmlspecialchars($row['Color']).'</td>'
               . '<td>'.$row['Fecha'].'</td>'
               . '<td>'.$row['Cantidad'].'</td>'
               . '</tr>';
    }
    $html .= '</tbody></table>';
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','landscape');
    $dompdf->render();
    $dompdf->stream("reportes_consolidados.pdf", ["Attachment" => false]);
    exit;
}

// Obtener operadores para filtro
$opsSql = "
  SELECT DISTINCT CONCAT(Nombre,' ',Apellido_P,' ',Apellido_M) AS Operador
    FROM operadores O
    JOIN (
      SELECT Operador_Responsable AS id FROM multiplicacion WHERE Estado_Revision='Consolidado'
      UNION ALL
      SELECT Operador_Responsable AS id FROM enraizamiento WHERE Estado_Revision='Consolidado'
    ) t ON O.ID_Operador = t.id
  ORDER BY Operador
";
$opsResult = $conn->query($opsSql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Exportar Reportes Consolidados</title>
  <link rel="stylesheet" href="../style.css?v=<?=time()?>"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body class="scrollable">
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand me-3" href="#">
          <img src="../logoplantulas.png" width="130" height="124" alt="Logo">
        </a>
        <div>
          <h2>Exportar Reportes Consolidados</h2>
          <p class="mb-0">Filtra antes de ver o descargar.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="location.href='dashboard_rrs.php'">
                🔙 Regresar al Dashboard
              </button>
            </div>
          </div>
        </nav>
        <nav class="filter-toolbar">
          <select name="operador" form="filtrosForm" class="form-select form-select-sm" style="min-width:160px;">
            <option value="">— Todos —</option>
            <?php while($o = $opsResult->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($o['Operador'])?>"
                <?= $o['Operador']=== $operador?'selected':''?>>
                <?= htmlspecialchars($o['Operador'])?>
              </option>
            <?php endwhile; ?>
          </select>
          <input form="filtrosForm" type="date" name="desde"
                 class="form-control form-control-sm" style="max-width:140px;"
                 value="<?= $fechaDesde ?>">
          <input form="filtrosForm" type="date" name="hasta"
                 class="form-control form-control-sm" style="max-width:140px;"
                 value="<?= $fechaHasta ?>">
          <button form="filtrosForm" type="submit"
                  class="btn-inicio btn btn-success btn-sm">
            Aplicar filtros
          </button>
        </nav>
      </div>
    </header>

    <main class="container mt-3">
      <form id="filtrosForm" method="GET" class="d-none"></form>

      <div class="table-responsive mb-4">
        <table class="table table-striped table-sm">
          <thead class="table-light">
            <tr>
              <th>Etapa</th><th>ID</th>
              <th>Operador Original</th><th>Consolidado Por</th>
              <th>Variedad</th><th>Color</th>
              <th>Fecha de Siembra</th><th>Cantidad</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $preview = $conn->query($sql);
            while($r = $preview->fetch_assoc()): ?>
              <tr>
                <td><?= $r['Etapa'] ?></td>
                <td><?= $r['ID'] ?></td>
                <td><?= htmlspecialchars($r['Operador_Original']) ?></td>
                <td><?= htmlspecialchars($r['Operador_Consolida']) ?></td>
                <td><?= htmlspecialchars($r['Variedad']) ?></td>
                <td><?= htmlspecialchars($r['Color']) ?></td>
                <td><?= $r['Fecha'] ?></td>
                <td><?= $r['Cantidad'] ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <div class="text-center">
        <a href="?<?= http_build_query([
              'operador'=>$operador,
              'desde'=>$fechaDesde,
              'hasta'=>$fechaHasta,
              'type'=>'excel'
            ]) ?>"
           class="btn btn-success me-2 btn-sm">
          📊 Descargar Excel
        </a>
        <a href="?<?= http_build_query([
              'operador'=>$operador,
              'desde'=>$fechaDesde,
              'hasta'=>$fechaHasta,
              'type'=>'pdf'
            ]) ?>"
           target="_blank"
           class="btn btn-danger btn-sm">
          📄 Ver/Imprimir PDF
        </a>
      </div>
    </main>

    <footer class="text-center py-3">&copy; 2025 PLANTAS AGRODEX</footer>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
