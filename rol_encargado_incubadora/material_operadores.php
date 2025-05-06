<?php
// Activar detección de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../db.php';
session_start();

// 1) Verificar sesión iniciada
if (!isset($_SESSION['ID_Operador'])) {
    header('Location: ../login.php');
    exit();
}

// 2) Verificar rol = 9 (Encargado de Incubadora y Suministro de Material)
if ($_SESSION['Rol'] != 9) {
    header('Location: ../login.php');
    exit();
}

// 3) Obtener todas las operadoras (Rol = 2)
$ops = $conn->query("
  SELECT 
    ID_Operador,
    CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) AS nombre
  FROM operadores
  WHERE `ID_Rol` = 2
  ORDER BY nombre
");

// 4) Procesar POST de revisión
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op     = intval($_POST['id_operador']);
    $enc    = $_SESSION['ID_Operador'];
    $e2     = intval($_POST['explantes_etapa2']);
    $e3     = intval($_POST['explantes_etapa3']);
    $otros  = trim($_POST['otros_articulos']);
    $ok     = intval($_POST['correcto']);
    $obs    = trim($_POST['observaciones']);

    $stmt = $conn->prepare("
      INSERT INTO asignacion_material
        (id_operador, fecha_revision, id_encargado,
         explantes_etapa2, explantes_etapa3,
         otros_articulos, correcto, observaciones)
      VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        fecha_revision    = NOW(),
        id_encargado      = VALUES(id_encargado),
        explantes_etapa2  = VALUES(explantes_etapa2),
        explantes_etapa3  = VALUES(explantes_etapa3),
        otros_articulos   = VALUES(otros_articulos),
        correcto          = VALUES(correcto),
        observaciones     = VALUES(observaciones)
    ");
    $stmt->bind_param(
        'iiiiisss',
        $op, $enc, $e2, $e3, $otros, $ok, $obs
    );
    if ($stmt->execute()) {
        $msg = '✅ Revisión guardada';
    } else {
        $msg = '❌ Error: ' . $stmt->error;
    }
}

// 5) Preparar SELECT para cargar última asignación de cada operadora
$asigStmt = $conn->prepare("
  SELECT explantes_etapa2,
         explantes_etapa3,
         otros_articulos,
         correcto,
         observaciones
    FROM asignacion_material
   WHERE id_operador = ?
   LIMIT 1
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Revisión de Material Operadoras</title>
  <link rel="stylesheet" href="../style.css?v=<?=time()?>"/>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  />
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand me-3" href="dashboard_eism.php">
          <img src="../logoplantulas.png" width="130" height="124" alt="Logo">
        </a>
        <div>
          <h2>Revisión de Material para Operadoras</h2>
          <p class="mb-0">Marca si tienen suficientes explantes y otros artículos</p>
        </div>
      </div>
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="location.href='dashboard_eism.php'">🔙 Volver al Dashboard</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="container mt-4">
      <?php if ($msg): ?>
        <div class="alert alert-info"><?= $msg ?></div>
      <?php endif; ?>

      <?php while ($op = $ops->fetch_assoc()): ?>
        <?php
          // Cargar últimos valores
          $asigStmt->bind_param('i', $op['ID_Operador']);
          $asigStmt->execute();
          $prev = $asigStmt->get_result()->fetch_assoc() ?: [];
        ?>
        <form method="POST" class="row g-3 mb-4">
          <input type="hidden" name="id_operador" value="<?= $op['ID_Operador'] ?>">
          <div class="col-12">
            <h3><?= htmlspecialchars($op['nombre']) ?></h3>
          </div>

          <div class="col-md-3">
            <label for="e2_<?= $op['ID_Operador'] ?>" class="form-label">Explantes Etapa 2</label>
            <input type="number"
                   class="form-control"
                   id="e2_<?= $op['ID_Operador'] ?>"
                   name="explantes_etapa2"
                   value="<?= $prev['explantes_etapa2'] ?? 0 ?>"
                   min="0">
          </div>

          <div class="col-md-3">
            <label for="e3_<?= $op['ID_Operador'] ?>" class="form-label">Explantes Etapa 3</label>
            <input type="number"
                   class="form-control"
                   id="e3_<?= $op['ID_Operador'] ?>"
                   name="explantes_etapa3"
                   value="<?= $prev['explantes_etapa3'] ?? 0 ?>"
                   min="0">
          </div>

          <div class="col-md-6">
            <label for="otros_<?= $op['ID_Operador'] ?>" class="form-label">Otros Artículos</label>
            <textarea id="otros_<?= $op['ID_Operador'] ?>"
                      class="form-control"
                      name="otros_articulos"
                      placeholder="Ej: pinzas, espátulas..."
                      rows="1"><?= htmlspecialchars($prev['otros_articulos'] ?? '') ?></textarea>
          </div>

          <div class="col-md-3">
            <label for="ok_<?= $op['ID_Operador'] ?>" class="form-label">¿Correcto?</label>
            <select id="ok_<?= $op['ID_Operador'] ?>"
                    class="form-select"
                    name="correcto">
              <option value="1" <?= ($prev['correcto'] ?? 1) == 1 ? 'selected' : '' ?>>Sí</option>
              <option value="0" <?= ($prev['correcto'] ?? 1) == 0 ? 'selected' : '' ?>>No</option>
            </select>
          </div>

          <div class="col-md-9">
            <label for="obs_<?= $op['ID_Operador'] ?>" class="form-label">Observaciones</label>
            <textarea id="obs_<?= $op['ID_Operador'] ?>"
                      class="form-control"
                      name="observaciones"
                      rows="1"><?= htmlspecialchars($prev['observaciones'] ?? '') ?></textarea>
          </div>

          <div class="col-12">
            <button type="submit" class="btn btn-primary">Guardar Revisión</button>
          </div>
        </form>
      <?php endwhile; ?>
    </main>

    <footer class="text-center py-3">&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</footer>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
  ></script>
</body>
</html>
