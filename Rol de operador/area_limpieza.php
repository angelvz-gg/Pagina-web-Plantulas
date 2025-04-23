<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];

// Marcar como realizada
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["marcar_realizada"])) {
    $fecha = $_POST["fecha"] ?? date('Y-m-d');
    $area = $_POST["area"] ?? '';

    $sql_update = "UPDATE registro_limpieza 
                   SET Estado_Limpieza = 'Realizada' 
                   WHERE ID_Operador = ? AND Fecha = ? AND Area = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("iss", $ID_Operador, $fecha, $area);
    $stmt->execute();

    echo "<script>alert('Área marcada como realizada.'); window.location.href='area_limpieza.php';</script>";
    exit();
}

// Obtener TODAS las asignaciones del día, sin filtrar estado
$sql = "SELECT Fecha, Area, Estado_Limpieza 
        FROM registro_limpieza 
        WHERE ID_Operador = ? 
          AND Fecha = CURDATE()
        ORDER BY Hora_Registro DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ID_Operador);
$stmt->execute();
$result = $stmt->get_result();
$asignaciones = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Área de Limpieza Asignada</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124" />
        PLÁNTULAS AGRODEX
      </a>
      <h2>Área de Limpieza Asignada</h2>
    </div>

    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_cultivo.php'">
              🔙 Volver al panel
            </button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main>
    <section class="section">
      <h3>🧹 Asignaciones de limpieza para hoy</h3>

      <?php if (count($asignaciones) > 0): ?>
        <table class="table">
          <thead>
            <tr>
              <th>📅 Fecha</th>
              <th>🧭 Área Asignada</th>
              <th>✅ Estado</th>
              <th>🛠 Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($asignaciones as $asignacion): ?>
              <tr>
                <td><?= htmlspecialchars($asignacion['Fecha']) ?></td>
                <td><?= htmlspecialchars($asignacion['Area']) ?></td>
                <td><?= htmlspecialchars($asignacion['Estado_Limpieza']) ?></td>
                <td>
                  <?php if (strtolower(trim($asignacion['Estado_Limpieza'])) !== 'realizada'): ?>
                    <form method="POST" class="form-inline">
                      <input type="hidden" name="fecha" value="<?= htmlspecialchars($asignacion['Fecha']) ?>">
                      <input type="hidden" name="area" value="<?= htmlspecialchars($asignacion['Area']) ?>">
                      <button type="submit" name="marcar_realizada" class="save-button verificar btn-sm">
                        Marcar como realizada
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-success">✔ Realizada</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color: red;">No tienes asignaciones de limpieza para hoy.</p>
      <?php endif; ?>
    </section>
  </main>

  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>
</html>
