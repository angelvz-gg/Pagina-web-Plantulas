<?php
session_start();
require '../db.php';

if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
  header("Location: ../login.php");
  exit();
}

$mensaje = "";

// Obtener variedades para el select
$variedades = mysqli_query($conn, "SELECT ID_Variedad, Nombre_Variedad, Especie FROM Variedades ORDER BY Especie, Nombre_Variedad");

// Insertar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_tupper = $_POST['id_tupper'];
  $id_variedad = $_POST['id_variedad'];
  $fecha = $_POST['fecha_lavado'];
  $cantidad = intval($_POST['cantidad_lavada']);

  $stmt = $conn->prepare("INSERT INTO lavado_plantas 
    (ID_Tupper, ID_Variedad, Fecha_Lavado, Cantidad_Lavada, Operador_Responsable)
    VALUES (?, ?, ?, ?, ?)");

  $stmt->bind_param("iisii", $id_tupper, $id_variedad, $fecha, $cantidad, $_SESSION['ID_Operador']);

  if ($stmt->execute()) {
    $mensaje = "✅ Registro guardado correctamente.";
  } else {
    $mensaje = "❌ Error al guardar el registro.";
  }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Selección de Tuppers</title>
  <link rel="stylesheet" href="../style.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124" />
        </a>
        <div>
          <h2>📦 Selección de Tuppers</h2>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_gpl.php'" class="btn btn-secondary">🔙 REGRESAR</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="container mt-4">
      <?php if (!empty($mensaje)) : ?>
        <p style="color: <?= strpos($mensaje, '✅') !== false ? 'green' : 'red' ?>; text-align:center;">
          <?= $mensaje ?>
        </p>
      <?php endif; ?>

      <h4>🧼 Enviar Tuppers a Lavado</h4>
      <form method="POST" class="row g-3 mt-3">
        <div class="col-md-6">
          <label class="form-label">ID del Tupper:</label>
          <input type="number" name="id_tupper" class="form-control" required placeholder="Número o código del tupper">
        </div>

        <div class="col-md-6">
          <label class="form-label">Variedad:</label>
          <select name="id_variedad" class="form-select" required>
            <option value="">-- Seleccionar variedad --</option>
            <?php while ($v = mysqli_fetch_assoc($variedades)) : ?>
              <option value="<?= $v['ID_Variedad'] ?>">
                <?= $v['Nombre_Variedad'] ?> (<?= $v['Especie'] ?>)
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Cantidad de Tuppers Lavados:</label>
          <input type="number" name="cantidad_lavada" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Fecha de Lavado:</label>
          <input type="date" name="fecha_lavado" class="form-control" required>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-success">Registrar Lavado</button>
        </div>
      </form>
    </main>

    <footer class="text-center mt-4 mb-3">
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>
</body>
</html>
