<?php
// suministro_material.php

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

include '../db.php';
session_start();

// 1) Verificar sesión y rol = Encargado (Rol = 9)
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 9) {
    header('Location: ../login.php');
    exit();
}

// 2) Procesar POST de asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignar_materiales'])) {
    $conn->begin_transaction();

    try {
        $id_enc   = $_SESSION['ID_Operador']; // Encargado que asigna
        $id_op    = intval($_POST['id_operador']); // Operadora que recibe
        $mats     = $_POST['material']  ?? [];
        $cants    = $_POST['cantidad']  ?? [];

        // Construir detalles
        $detalles = [];
        foreach ($mats as $i => $id_mat) {
            $cantidad = intval($cants[$i] ?? 0);
            if ($cantidad > 0) {
                // Obtener nombre
                $res = $conn->prepare("SELECT nombre FROM materiales WHERE id_material = ?");
                $res->bind_param('i', $id_mat);
                $res->execute();
                $nombre = $res->get_result()->fetch_assoc()['nombre'];
                $res->close();

                $detalles[$id_mat] = [
                    'nombre'   => $nombre,
                    'cantidad' => $cantidad
                ];
            }
        }

        if (empty($detalles)) {
            throw new Exception('⚠️ Debes asignar al menos un material.');
        }

        // Insertar en resumen (suministro_material)
        $stmt = $conn->prepare("
            INSERT INTO suministro_material
              (id_operador, id_encargado, detalles)
            VALUES (?, ?, ?)
        ");
        $json = json_encode(array_column($detalles, 'cantidad', 'nombre'), JSON_UNESCAPED_UNICODE);
        $stmt->bind_param('iis', $id_op, $id_enc, $json);
        $stmt->execute();
        $stmt->close();

        // Registrar en movimientos y actualizar inventario
        $mov = $conn->prepare("
            INSERT INTO movimientos_materiales 
              (id_material, tipo_movimiento, cantidad, id_operador_asignado, id_encargado, observaciones)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($detalles as $id_mat => $info) {
            $cant = $info['cantidad'];

            // Obtener si es reutilizable
            $q = $conn->prepare("SELECT reutilizable FROM materiales WHERE id_material = ?");
            $q->bind_param("i", $id_mat);
            $q->execute();
            $res = $q->get_result();
            $reutilizable = $res->fetch_assoc()['reutilizable'] ?? 0;
            $q->close();

            if ($reutilizable) {
                // Aumentar en_uso
                $u = $conn->prepare("
                    INSERT INTO inventario_materiales (id_material, cantidad, en_uso)
                    VALUES (?, 0, ?)
                    ON DUPLICATE KEY UPDATE en_uso = en_uso + VALUES(en_uso)
                ");
                $u->bind_param("ii", $id_mat, $cant);
                $u->execute();
                $u->close();
            } else {
                // Disminuir cantidad
                $u = $conn->prepare("
                    INSERT INTO inventario_materiales (id_material, cantidad)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE cantidad = cantidad - VALUES(cantidad)
                ");
                $u->bind_param("ii", $id_mat, $cant);
                $u->execute();
                $u->close();
            }

            // Registrar el movimiento
            $tipo = 'asignacion';
            $obs  = "Asignado desde suministro_material.php";
            $mov->bind_param("isiiss", $id_mat, $tipo, $cant, $id_op, $id_enc, $obs);
            $mov->execute();
        }
        $mov->close();

        $conn->commit();
        $msg = '✅ Asignación registrada y stock actualizado exitosamente.';

    } catch (Exception $e) {
        $conn->rollback();
        $msg = '❌ Error: ' . $e->getMessage();
    }

    header('Location: suministro_material.php?msg=' . urlencode($msg));
    exit();
}

// 3) Leer mensaje GET
$msg = $_GET['msg'] ?? '';

// 4) Cargar inventario disponible (mostrando solo lo útil)
$inventario = $conn->query("
    SELECT 
      m.id_material, 
      m.nombre, 
      CASE 
        WHEN m.reutilizable = 1 THEN (COALESCE(i.cantidad, 0) - COALESCE(i.en_uso, 0))
        ELSE COALESCE(i.cantidad, 0)
      END AS disponibles
    FROM materiales m
    LEFT JOIN inventario_materiales i USING(id_material)
    HAVING disponibles > 0
    ORDER BY m.nombre
");

// 5) Cargar operadoras y materiales
$ops  = $conn->query("
    SELECT ID_Operador, CONCAT(Nombre,' ',Apellido_P,' ',Apellido_M) AS nombre
      FROM operadores
     WHERE ID_Rol = 2
     ORDER BY nombre
");
$mats = $conn->query("
    SELECT id_material, nombre
      FROM materiales
     ORDER BY nombre
");

// 6) Traer últimas asignaciones
$asigs = $conn->query("
    SELECT s.fecha_entrega,
           CONCAT(o.Nombre,' ',o.Apellido_P) AS operadora,
           s.detalles
      FROM suministro_material s
      JOIN operadores o ON s.id_operador = o.ID_Operador
     ORDER BY s.fecha_entrega DESC
     LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Suministro de Material</title>
  <link rel="stylesheet" href="../style.css?v=<?=time();?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .table-responsive > table {
      width: 100%;
      table-layout: auto;
    }
    .table th, .table td {
      white-space: normal;
      word-break: break-word;
    }
  </style>
</head>
<body>
  <div class="contenedor-pagina">
    <header class="mb-4">
      <div class="encabezado d-flex align-items-center">
        <a class="navbar-brand me-3" href="#"><img src="../logoplantulas.png" width="130" height="124"></a>
        <div>
          <h2>Suministro de Material</h2>
          <p>Asigna materiales y cantidades a cada operadora.</p>
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

    <main class="container">
      <?php if ($msg): ?>
        <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <div class="row g-4">
        <!-- Inventario Disponible -->
        <div class="col-lg-4">
          <div class="card">
            <div class="card-header bg-info text-white text-center">
              Inventario Disponible
            </div>
            <ul class="list-group list-group-flush">
              <?php while ($inv = $inventario->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between">
                  <span><?= htmlspecialchars($inv['nombre']) ?></span>
                  <span><?= intval($inv['disponibles']) ?></span>
                </li>
              <?php endwhile; ?>
            </ul>
          </div>
        </div>

        <!-- Formulario de Asignación -->
        <div class="col-lg-8">
          <div class="card h-100">
            <div class="card-header bg-primary text-white">Asignar Material</div>
            <div class="card-body">
              <form method="POST">
                <div class="mb-3">
                  <label class="form-label">Operadora</label>
                  <select name="id_operador" class="form-select" required>
                    <option value="">Selecciona…</option>
                    <?php while ($op = $ops->fetch_assoc()): ?>
                      <option value="<?= $op['ID_Operador'] ?>">
                        <?= htmlspecialchars($op['nombre']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="row g-3">
                  <?php while ($m = $mats->fetch_assoc()): ?>
                    <div class="col-6 col-md-4 d-flex align-items-center">
                      <input type="hidden" name="material[]" value="<?= $m['id_material'] ?>">
                      <label class="form-label flex-grow-1 mb-0"><?= htmlspecialchars($m['nombre']) ?></label>
                      <input type="number"
                             name="cantidad[]"
                             class="form-control"
                             style="width:80px;"
                             min="0"
                             placeholder="0">
                    </div>
                  <?php endwhile; ?>
                </div>
                <div class="text-end mt-4">
                  <button name="asignar_materiales" class="btn btn-success">Guardar Asignación</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Últimas Asignaciones -->
      <div class="card mt-4">
        <div class="card-header bg-secondary text-white">Últimas Asignaciones</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
              <thead class="table-dark">
                <tr>
                  <th>Fecha & Hora</th>
                  <th>Operadora</th>
                  <th>Materiales</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($a = $asigs->fetch_assoc()): ?>
                  <?php $items = json_decode($a['detalles'], true); ?>
                  <tr>
                    <td><?= htmlspecialchars($a['fecha_entrega']) ?></td>
                    <td><?= htmlspecialchars($a['operadora']) ?></td>
                    <td>
                      <?php foreach ($items as $nombre => $cant): ?>
                        <div><?= htmlspecialchars($nombre) ?>: <?= intval($cant) ?></div>
                      <?php endforeach; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>

    <footer class="text-center py-3">&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</footer>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
