<?php
include '../db.php';
session_start();

// Zona horaria de CDMX
date_default_timezone_set('America/Mexico_City');

// Verificar rol
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 10) {
    header('Location: ../login.php');
    exit();
}

$msg = "";

// Guardar limpieza
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_operador = $_SESSION['ID_Operador'];
    $anaquel     = $_POST['anaquel'] ?? '';
    $repisas     = intval($_POST['repisas'] ?? 0);
    $fecha       = date('Y-m-d');
    $hora        = date('Y-m-d H:i:s');

    if ($anaquel && $repisas > 0) {
        // Insertar en limpieza_incubadora
        $stmt = $conn->prepare("INSERT INTO limpieza_incubadora (ID_Operador, Fecha, Hora_Registro, Anaquel, Repisas_Limpias) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $id_operador, $fecha, $hora, $anaquel, $repisas);
        $stmt->execute();
        $id_limpieza = $conn->insert_id;
        $stmt->close();

        // Insertar en registro_limpieza
        $area = "7. Incubador";
        $estado = "Realizada";
        $stmt2 = $conn->prepare("INSERT INTO registro_limpieza (ID_Operador, Fecha, Hora_Registro, Area, Estado_Limpieza, ID_LimpiezaIncubadora) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("issssi", $id_operador, $fecha, $hora, $area, $estado, $id_limpieza);
        $stmt2->execute();
        $stmt2->close();

        $msg = "✅ Registro guardado correctamente.";
    } else {
        $msg = "❌ Debes completar todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Limpieza de Incubador</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#"><img src="../logoplantulas.png" width="130" height="124" /></a>
        <h2>🧽 Limpieza de Repisas del Incubador</h2>
      </div>
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_eol.php'">🔙 Regresar</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="container mt-4">
      <?php if ($msg): ?>
        <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header bg-success text-white">Registrar Limpieza</div>
        <div class="card-body">
          <form method="POST">
            <div class="mb-3">
              <label class="form-label">Anaquel</label>
              <select name="anaquel" class="form-select" required>
                <option value="">Selecciona…</option>
                <option value="Anaquel 1">Anaquel 1</option>
                <option value="Anaquel 2">Anaquel 2</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Cantidad de Repisas Limpias</label>
              <input type="number" name="repisas" class="form-control" min="1" required />
            </div>
            <div class="text-end">
              <button type="submit" class="btn btn-primary">Guardar Registro</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <footer class="text-center py-3">
      &copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.
    </footer>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
