<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../db.php';
session_start();

// 1) Verificar sesión iniciada y rol = 9
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 9) {
    header('Location: ../login.php');
    exit();
}

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
        $stmt = $conn->prepare("INSERT IGNORE INTO materiales (nombre) VALUES (?)");
        foreach ($_POST['materiales_nuevos'] as $nombre) {
            $stmt->bind_param('s', $nombre);
            $stmt->execute();
        }
        $msg = '✅ Nuevos materiales registrados';
    }
    // Actualizar inventario
    if (isset($_POST['actualizar_inventario'])) {
        $id   = intval($_POST['id_material']);
        $cant = intval($_POST['cantidad']);
        $oper = $_SESSION['ID_Operador'];
        $stmt = $conn->prepare("
            INSERT INTO inventario_materiales
              (id_material, cantidad, fecha_act, id_operador_registro)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
              cantidad             = VALUES(cantidad),
              fecha_act            = NOW(),
              id_operador_registro = VALUES(id_operador_registro)
        ");
        $stmt->bind_param('iii', $id, $cant, $oper);
        $stmt->execute();
        $msg = '✅ Inventario actualizado';
    }
}

// 3) Cargar datos para dropdown y tabla
$materiales = $conn->query("SELECT * FROM materiales ORDER BY nombre");
$inv_res    = $conn->query("
    SELECT m.id_material, m.nombre, COALESCE(i.cantidad, 0) AS cantidad
      FROM materiales m
      LEFT JOIN inventario_materiales i ON m.id_material = i.id_material
    ORDER BY m.nombre
");
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
            <button onclick="location.href='dashboard_eism.php'">
              🔙 Volver al Dashboard
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

      <div class="row mb-4">
        <!-- CHECKLIST DE MATERIALES FIJOS -->
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

        <!-- Formulario: actualizar existencias -->
        <div class="col-md-6">
          <div class="card p-3 h-100">
            <h4>Actualizar existencias</h4>
            <form method="POST" class="row g-2 align-items-end">
              <div class="col-7">
                <label for="id_material" class="form-label">Material</label>
                <select id="id_material" name="id_material" class="form-select" required>
                  <option value="">Selecciona material…</option>
                  <?php
                  // recargar lista de materiales para el dropdown
                  $materiales->data_seek(0);
                  while ($m = $materiales->fetch_assoc()): ?>
                    <option value="<?= $m['id_material'] ?>">
                      <?= htmlspecialchars($m['nombre']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="col-3">
                <label for="cantidad" class="form-label">Cantidad</label>
                <input type="number" id="cantidad" name="cantidad"
                       class="form-control" placeholder="0" min="0" required>
              </div>
              <div class="col-2">
                <button name="actualizar_inventario" class="btn btn-success w-100">
                  Guardar
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Tabla de inventario actual -->
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Material</th>
              <th>Cantidad Disponible</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($r = $inv_res->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($r['nombre']) ?></td>
                <td><?= intval($r['cantidad']) ?></td>
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
</body>
</html>
