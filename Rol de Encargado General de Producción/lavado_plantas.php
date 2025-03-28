<?php
include '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_operador = $_POST['id_operador'] ?? null;
    $id_variedad = $_POST['id_variedad'] ?? null;
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $rol = $_POST['rol'] ?? null;
    $cantidad = $_POST['cantidad'] ?? null;

    if ($id_operador && $id_variedad && $rol && $cantidad) {
        $sql = "INSERT INTO asignacion_lavado (ID_Operador, ID_Variedad, Fecha, Rol, Cantidad_Tuppers)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissi", $id_operador, $id_variedad, $fecha, $rol, $cantidad);

        if ($stmt->execute()) {
            echo "<script>alert('Asignación registrada correctamente.'); window.location.href='lavado_plantas.php';</script>";
        } else {
            echo "<script>alert('Error al registrar la asignación.');</script>";
        }
    } else {
        echo "<script>alert('Todos los campos son obligatorios.');</script>";
    }
}

// Obtener operadores activos que NO sean administradores
$operadores = $conn->query("
    SELECT ID_Operador, CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) AS NombreCompleto 
    FROM operadores 
    WHERE Activo = 1 AND ID_Rol = 2
    ORDER BY Nombre ASC
");


// Obtener variedades
$variedades = $conn->query("SELECT ID_Variedad, Nombre_Variedad FROM variedades ORDER BY Nombre_Variedad ASC");
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
              <label for="id_variedad">Variedad:</label>
              <select name="id_variedad" id="id_variedad" class="form-select" required>
                <option value="">-- Seleccionar Variedad --</option>
                <?php while ($var = $variedades->fetch_assoc()): ?>
                  <option value="<?= $var['ID_Variedad'] ?>"><?= $var['Nombre_Variedad'] ?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label for="fecha">Fecha:</label>
              <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-4">
              <label for="rol">Rol:</label>
              <select name="rol" id="rol" class="form-select" required>
                <option value="">-- Seleccionar Rol --</option>
                <option value="Supervisor">Supervisor</option>
                <option value="Lavador">Lavador</option>
              </select>
            </div>

            <div class="col-md-4">
              <label for="cantidad">Cantidad de Tuppers:</label>
              <input type="number" name="cantidad" class="form-control" min="1" required>
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
