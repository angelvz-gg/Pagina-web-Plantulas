<?php
include '../db.php';
session_start();

// Verificar sesión y rol
if (!isset($_SESSION['ID_Operador'])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}
if ($_SESSION['Rol'] != 8) {
    echo "<script>alert('No tienes permiso para acceder a esta página.'); window.location.href='../login.php';</script>";
    exit();
}

// Procesar consolidación y auditoría
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'];    // 'multiplicacion' o 'enraizamiento'
    $id   = intval($_POST['id']);

    // Determinar tabla, PK y obtener estado previo
    if ($tipo === 'multiplicacion') {
        $tabla   = 'multiplicacion';
        $pkCampo = 'ID_Multiplicacion';
    } else {
        $tabla   = 'enraizamiento';
        $pkCampo = 'ID_Enraizamiento';
    }

    // 1) Leer estado anterior
    $stmtOld = $conn->prepare(
        "SELECT Estado_Revision 
           FROM $tabla 
          WHERE $pkCampo = ?"
    );
    $stmtOld->bind_param('i', $id);
    $stmtOld->execute();
    $old = $stmtOld->get_result()->fetch_assoc()['Estado_Revision'];

    // 2) Actualizar a 'Consolidado'
    $nuevo = 'Consolidado';
    $stmtUpd = $conn->prepare(
        "UPDATE $tabla
            SET Estado_Revision = ?
          WHERE $pkCampo = ?"
    );
    $stmtUpd->bind_param('si', $nuevo, $id);
    $stmtUpd->execute();

    // 3) Insertar en consolidacion_log
    //    Solo especificamos la columna correspondiente; la otra queda NULL
    if ($tipo === 'multiplicacion') {
      $sqlLog = "
        INSERT INTO consolidacion_log
          (ID_Multiplicacion, ID_Operador, Fecha_Hora, Estado_Anterior, Estado_Nuevo)
        VALUES (?, ?, ?, ?, ?)
      ";
      $stmtLog = $conn->prepare($sqlLog);
  
      // Guardamos en variables para pasarlas por referencia
      $operadorId = $_SESSION['ID_Operador'];
      $fechaHora  = date('Y-m-d H:i:s');
  
      $stmtLog->bind_param(
          'iisss',
          $id,
          $operadorId,
          $fechaHora,
          $old,
          $nuevo
      );
  } else {
      $sqlLog = "
        INSERT INTO consolidacion_log
          (ID_Enraizamiento, ID_Operador, Fecha_Hora, Estado_Anterior, Estado_Nuevo)
        VALUES (?, ?, ?, ?, ?)
      ";
      $stmtLog = $conn->prepare($sqlLog);
  
      // Nuevamente variables intermedias
      $operadorId = $_SESSION['ID_Operador'];
      $fechaHora  = date('Y-m-d H:i:s');
  
      $stmtLog->bind_param(
          'iisss',
          $id,
          $operadorId,
          $fechaHora,
          $old,
          $nuevo
      );
  }
  
  $stmtLog->execute();
  

    header('Location: consolidar_trabajo.php');
    exit();
}

// Consultas para mostrar pendientes
$sql_mul = "
  SELECT 
    M.ID_Multiplicacion AS id,
    O.Nombre            AS operador,
    V.Codigo_Variedad,
    V.Nombre_Variedad,
    M.Fecha_Siembra,
    M.Cantidad_Dividida AS cantidad
  FROM multiplicacion M
  JOIN operadores O ON M.Operador_Responsable = O.ID_Operador
  JOIN variedades V ON M.ID_Variedad         = V.ID_Variedad
  WHERE M.Estado_Revision = 'Verificado'
  ORDER BY M.Fecha_Siembra DESC
";
$sql_enr = "
  SELECT 
    E.ID_Enraizamiento    AS id,
    O.Nombre             AS operador,
    V.Codigo_Variedad,
    V.Nombre_Variedad,
    E.Fecha_Siembra,
    E.Cantidad_Dividida   AS cantidad
  FROM enraizamiento E
  JOIN operadores O ON E.Operador_Responsable = O.ID_Operador
  JOIN variedades V ON E.ID_Variedad         = V.ID_Variedad
  WHERE E.Estado_Revision = 'Verificado'
  ORDER BY E.Fecha_Siembra DESC
";

$result_mul = $conn->query($sql_mul);
$result_enr = $conn->query($sql_enr);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Consolidar Trabajo</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="scrollable">
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand me-3" href="#">
          <img src="../logoplantulas.png" width="130" height="124" alt="Logo">
        </a>
        <div>
          <h2>Consolidar Trabajo</h2>
          <p class="mb-0">Marca como “Consolidado” los reportes verificados.</p>
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
      </div>
    </header>

    <main class="container mt-4">
      <h4>Multiplicación</h4>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>Operador</th>
              <th>Variedad</th>
              <th>Fecha Siembra</th>
              <th>Cantidad</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result_mul->fetch_assoc()): ?>
            <tr>
              <td data-label="Operador"><?= htmlspecialchars($row['operador']) ?></td>
              <td data-label="Variedad"><?= htmlspecialchars($row['Codigo_Variedad'].' – '.$row['Nombre_Variedad']) ?></td>
              <td data-label="Fecha Siembra"><?= htmlspecialchars($row['Fecha_Siembra']) ?></td>
              <td data-label="Cantidad"><?= htmlspecialchars($row['cantidad']) ?></td>
              <td data-label="Acción">
                <form method="POST" class="form-boton">
                  <input type="hidden" name="tipo" value="multiplicacion">
                  <input type="hidden" name="id"   value="<?= $row['id'] ?>">
                  <button type="submit" class="btn-consolidar btn-sm px-2 mb-2">✔ Consolidar</button>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <h4 class="mt-5">Enraizamiento</h4>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>Operador</th>
              <th>Variedad</th>
              <th>Fecha Siembra</th>
              <th>Cantidad</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result_enr->fetch_assoc()): ?>
            <tr>
              <td data-label="Operador"><?= htmlspecialchars($row['operador']) ?></td>
              <td data-label="Variedad"><?= htmlspecialchars($row['Codigo_Variedad'].' – '.$row['Nombre_Variedad']) ?></td>
              <td data-label="Fecha Siembra"><?= htmlspecialchars($row['Fecha_Siembra']) ?></td>
              <td data-label="Cantidad"><?= htmlspecialchars($row['cantidad']) ?></td>
              <td data-label="Acción">
                <form method="POST" class="form-boton">
                  <input type="hidden" name="tipo" value="enraizamiento">
                  <input type="hidden" name="id"   value="<?= $row['id'] ?>">
                  <button type="submit" class="btn-consolidar">✔ Consolidar</button>
                </form>
              </td>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
