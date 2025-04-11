<?php
include '../db.php';
session_start();

// 1. Asignar una orden de lavado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_orden'], $_POST['id_operador'], $_POST['rol'])) {
    $id_orden = intval($_POST['id_orden']);
    $id_operador = intval($_POST['id_operador']);
    $rol = $_POST['rol'];

    // Obtener datos de la orden
    $stmt = $conn->prepare("
        SELECT ol.ID_Lote, l.ID_Variedad, ol.Fecha_Lavado, ol.Cantidad_Lavada
        FROM orden_tuppers_lavado ol
        INNER JOIN lotes l ON ol.ID_Lote = l.ID_Lote
        WHERE ol.ID_Orden = ?
    ");
    $stmt->bind_param("i", $id_orden);
    $stmt->execute();
    $res = $stmt->get_result();
    $orden = $res->fetch_assoc();

    if ($orden) {
        $id_lote = $orden['ID_Lote'];
        $id_variedad = $orden['ID_Variedad'];
        $fecha = $orden['Fecha_Lavado'];
        $cantidad = $orden['Cantidad_Lavada'];

        // Insertar en asignacion_lavado
        $insert = $conn->prepare("
            INSERT INTO asignacion_lavado (ID_Operador, ID_Variedad, Fecha, Rol, Cantidad_Tuppers)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert->bind_param("iissi", $id_operador, $id_variedad, $fecha, $rol, $cantidad);

        if ($insert->execute()) {
            // Actualizar estado de la orden a "Asignado"
            $update = $conn->prepare("UPDATE orden_tuppers_lavado SET Estado = 'Asignado' WHERE ID_Orden = ?");
            $update->bind_param("i", $id_orden);
            $update->execute();

            // Registrar en movimientos_lote como "Asignación de Lavado"
            $movimiento = $conn->prepare("
                INSERT INTO movimientos_lote (ID_Lote, Fecha_Movimiento, Tipo_Movimiento, Cantidad_Tuppers, ID_Operador, Observaciones)
                VALUES (?, NOW(), 'Asignación de Lavado', ?, ?, 'Operador asignado para realizar lavado')
            ");
            $movimiento->bind_param("iii", $id_lote, $cantidad, $id_operador);
            $movimiento->execute();

            echo "<script>alert('✅ Asignación de lavado registrada correctamente.'); window.location.href='lavado_plantas.php';</script>";
            exit();
        } else {
            echo "<script>alert('❌ Error al registrar la asignación.');</script>";
        }
    } else {
        echo "<script>alert('❌ Error: Orden no encontrada.');</script>";
    }
}

// 2. Obtener operadores activos que NO sean administradores
$operadores = $conn->query("
    SELECT ID_Operador, CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) AS NombreCompleto 
    FROM operadores 
    WHERE Activo = 1 AND ID_Rol = 2
    ORDER BY Nombre ASC
");

// 3. Obtener órdenes pendientes
$ordenes = $conn->query("
    SELECT ol.ID_Orden, v.Nombre_Variedad, v.Especie, ol.Fecha_Lavado, ol.Cantidad_Lavada
    FROM orden_tuppers_lavado ol
    INNER JOIN lotes l ON ol.ID_Lote = l.ID_Lote
    INNER JOIN variedades v ON l.ID_Variedad = v.ID_Variedad
    WHERE ol.Estado = 'Pendiente'
    ORDER BY ol.Fecha_Creacion ASC
");
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Asignación Lavado de Plantas</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
        </a>
        <div>
          <h2>Asignación de Lavado de Plantas</h2>
          <p>Registra los tuppers a lavar por operador.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_egp.php'">🔄 Regresar</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
      <div class="section">
        <h2>🌿 Registrar Asignación de Lavado</h2>
        <form method="POST" class="form-doble-columna">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="id_operador">Operador:</label>
              <select name="id_operador" id="id_operador" class="form-select" required>
                <option value="">-- Seleccionar Operador --</option>
                <?php while ($op = $operadores->fetch_assoc()): ?>
                  <option value="<?= $op['ID_Operador'] ?>"><?= $op['NombreCompleto'] ?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="id_orden">Orden de Lavado (Pendiente):</label>
              <select name="id_orden" id="id_orden" class="form-select" required>
                <option value="">-- Seleccionar Orden --</option>
                <?php while ($orden = $ordenes->fetch_assoc()): ?>
                  <option value="<?= $orden['ID_Orden'] ?>">
                    <?= $orden['Nombre_Variedad'] ?> (<?= $orden['Especie'] ?>) - <?= $orden['Fecha_Lavado'] ?> - <?= $orden['Cantidad_Lavada'] ?> tuppers
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="rol">Rol:</label>
              <select name="rol" id="rol" class="form-select" required>
                <option value="">-- Seleccionar Rol --</option>
                <option value="Supervisor">Supervisor</option>
                <option value="Lavador">Lavador</option>
              </select>
            </div>

            <div class="col-md-12 d-flex justify-content-center">
              <button type="submit" class="save-button">Registrar Asignación</button>
            </div>
          </div>
        </form>
      </div>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
