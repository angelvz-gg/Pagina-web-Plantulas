<?php
include '../db.php';
session_start();

// Verificar sesión y rol
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 8) {
    header('Location: ../login.php');
    exit();
}

// Procesar consolidación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo'], $_POST['id'])) {
    $tipo = $_POST['tipo'];
    $id   = intval($_POST['id']);
    if ($tipo === 'multiplicacion') {
        $stmt = $conn->prepare("UPDATE multiplicacion SET Estado_Revision = 'Consolidado' WHERE ID_Multiplicacion = ?");
    } else {
        $stmt = $conn->prepare("UPDATE enraizamiento SET Estado_Revision = 'Consolidado' WHERE ID_Enraizamiento = ?");
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: historial_reportes.php');
    exit();
}

// Capturar filtros
$filterOp     = trim($_GET['operador']   ?? '');
$filterEstado = $_GET['estado']          ?? '';
$filterOpEsc  = $conn->real_escape_string($filterOp);

$whereOp = $filterOp
    ? " AND O.Nombre LIKE '%{$filterOpEsc}%'"
    : '';

$whereMul = $whereEnr = '';
if ($filterEstado === 'Pendiente') {
    $whereMul = " AND M.Estado_Revision = 'Verificado'";
    $whereEnr = " AND E.Estado_Revision = 'Verificado'";
} elseif ($filterEstado === 'Consolidado') {
    $whereMul = " AND M.Estado_Revision = 'Consolidado'";
    $whereEnr = " AND E.Estado_Revision = 'Consolidado'";
}

// Consultas
$sql_mul = "
  SELECT M.ID_Multiplicacion AS id, O.Nombre AS operador,
         V.Codigo_Variedad, V.Nombre_Variedad,
         M.Fecha_Siembra, M.Tasa_Multiplicacion,
         M.Cantidad_Dividida, M.Tuppers_Llenos, M.Tuppers_Desocupados,
         M.Estado_Revision
    FROM multiplicacion M
    JOIN operadores O ON M.Operador_Responsable = O.ID_Operador
    JOIN variedades V ON M.ID_Variedad         = V.ID_Variedad
   WHERE 1=1
     {$whereOp}
     {$whereMul}
   ORDER BY M.Fecha_Siembra DESC
";
$sql_enr = "
  SELECT E.ID_Enraizamiento AS id, O.Nombre AS operador,
         V.Codigo_Variedad, V.Nombre_Variedad,
         E.Fecha_Siembra, E.Tasa_Multiplicacion,
         E.Cantidad_Dividida, E.Tuppers_Llenos, E.Tuppers_Desocupados,
         E.Estado_Revision
    FROM enraizamiento E
    JOIN operadores O ON E.Operador_Responsable = O.ID_Operador
    JOIN variedades V ON E.ID_Variedad         = V.ID_Variedad
   WHERE 1=1
     {$whereOp}
     {$whereEnr}
   ORDER BY E.Fecha_Siembra DESC
";

$hist_mul = $conn->query($sql_mul);
$hist_enr = $conn->query($sql_enr);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Historial de Reportes</title>
  <link rel="stylesheet" href="../style.css?v=<?=time()?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="scrollable">
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand me-3" href="#"><img src="../logoplantulas.png" width="130" height="124"></a>
        <div>
          <h2>Historial de Reportes</h2>
          <p class="mb-0">Filtra por operador o estado.</p>
        </div>
      </div>
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="location.href='dashboard_rrs.php'">🔙 Regresar al Dashboard</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="container mt-4">
      <button class="btn btn-sm btn-secondary mb-2" type="button"
              data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
        🔍 Mostrar filtros
      </button>
      <div class="collapse mb-4" id="filtrosCollapse">
        <form method="GET" class="row g-2 align-items-center">
          <div class="col-auto">
            <input type="text" name="operador" value="<?=htmlspecialchars($filterOp)?>"
                   class="form-control form-control-sm" style="width:140px;" placeholder="Operador">
          </div>
          <div class="col-auto">
            <select name="estado" class="form-select form-select-sm" style="width:160px;">
              <option value="">Todos</option>
              <option value="Pendiente"   <?=$filterEstado==='Pendiente'   ? 'selected':''?>>Pendiente</option>
              <option value="Consolidado" <?=$filterEstado==='Consolidado' ? 'selected':''?>>Consolidado</option>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn-inicio btn-sm">Aplicar</button>
            <a href="historial_reportes.php" class="btn-anular btn-sm ms-1">Limpiar</a>
          </div>
        </form>
      </div>

      <h4>Multiplicación</h4>
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th><th>Operador</th><th>Variedad</th><th>Fecha</th>
              <th>Tasa</th><th>Cant.</th><th>LLenos</th><th>Vacíos</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php while($r=$hist_mul->fetch_assoc()):
              $estado = $r['Estado_Revision']==='Consolidado'
                ? '<span class="badge bg-success">Consolidado</span>'
                : '<span class="badge bg-warning text-dark">Pendiente</span>';
            ?>
            <tr>
              <td data-label="ID"><?=$r['id']?></td>
              <td data-label="Operador"><?=htmlspecialchars($r['operador'])?></td>
              <td data-label="Variedad"><?=htmlspecialchars("{$r['Codigo_Variedad']} – {$r['Nombre_Variedad']}")?></td>
              <td data-label="Fecha"><?=$r['Fecha_Siembra']?></td>
              <td data-label="Tasa"><?=$r['Tasa_Multiplicacion']?></td>
              <td data-label="Cant."><?=$r['Cantidad_Dividida']?></td>
              <td data-label="LLenos"><?=$r['Tuppers_Llenos']?></td>
              <td data-label="Vacíos"><?=$r['Tuppers_Desocupados']?></td>
              <td data-label="Estado"><?=$estado?></td>
                <?php if($r['Estado_Revision']==='Verificado'): ?>
                <?php else: ?>

                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <h4 class="mt-5">Enraizamiento</h4>
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th><th>Operador</th><th>Variedad</th><th>Fecha</th>
              <th>Tasa</th><th>Cant.</th><th>LLenos</th><th>Vacíos</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php while($r=$hist_enr->fetch_assoc()):
              $estado = $r['Estado_Revision']==='Consolidado'
                ? '<span class="badge bg-success">Consolidado</span>'
                : '<span class="badge bg-warning text-dark">Pendiente</span>';
            ?>
            <tr>
              <td data-label="ID"><?=$r['id']?></td>
              <td data-label="Operador"><?=htmlspecialchars($r['operador'])?></td>
              <td data-label="Variedad"><?=htmlspecialchars("{$r['Codigo_Variedad']} – {$r['Nombre_Variedad']}")?></td>
              <td data-label="Fecha"><?=$r['Fecha_Siembra']?></td>
              <td data-label="Tasa"><?=$r['Tasa_Multiplicacion']?></td>
              <td data-label="Cant."><?=$r['Cantidad_Dividida']?></td>
              <td data-label="LLenos"><?=$r['Tuppers_Llenos']?></td>
              <td data-label="Vacíos"><?=$r['Tuppers_Desocupados']?></td>
              <td data-label="Estado"><?=$estado?></td>
                <?php if($r['Estado_Revision']==='Verificado'): ?>
                <?php else: ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </main>

    <footer class="text-center py-3">&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</footer>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
