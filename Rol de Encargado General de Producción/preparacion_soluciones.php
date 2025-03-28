<?php
include '../db.php';
session_start();

// AJAX para autocompletar código de medio nutritivo
if (isset($_GET['action']) && $_GET['action'] === 'buscar_medio') {
    $term = $_GET['term'] ?? '';

    $sql = "SELECT ID_MedioNM, Codigo_Medio 
            FROM mediosnutritivosmadre 
            WHERE Codigo_Medio LIKE ? 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $like = "%$term%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $res = [];
    while ($row = $result->fetch_assoc()) {
        $res[] = [
            'id' => $row['ID_MedioNM'],
            'label' => $row['Codigo_Medio'],
            'value' => $row['Codigo_Medio']
        ];
    }
    echo json_encode($res);
    exit;
}

// Procesamiento del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo = $_POST['codigo_medio'];
    $fecha = $_POST['fecha_preparacion'];
    $cantidad = $_POST['cantidad_preparada'];
    $operador = $_SESSION['ID_Operador'] ?? null;

    $sql = "INSERT INTO mediosnutritivosmadre 
            (Codigo_Medio, Fecha_Preparacion, Cantidad_Preparada, Estado, Operador_Responsable) 
            VALUES (?, ?, ?, 'Disponible', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdi", $codigo, $fecha, $cantidad, $operador);

    if ($stmt->execute()) {
        echo "<script>alert('Medio nutritivo registrado correctamente.'); window.location.href='preparacion_soluciones.php';</script>";
    } else {
        echo "<script>alert('Error al registrar el medio.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Preparación de Soluciones Madre</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
</head>
<body>
  <div class="contenedor-borde">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center">
        </a>
        <div>
          <h2>Preparación de Soluciones Madre</h2>
          <p>Registro de la preparación de medios nutritivos madre.</p>
        </div>
      </div>
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_egp.php'">
                🔄 Volver a la página principal
              </button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
      <div class="form-container">
        <h2>Soluciones Madre</h2>
        <div class="form-center">
          <div class="form-left">
            <form method="POST" action="preparacion_soluciones.php">
              <label for="codigo_medio">Código del Medio Nutritivo Madre:</label>
              <input type="text" id="codigo_medio" name="codigo_medio" required>

              <label for="fecha_preparacion">Fecha de Preparación:</label>
              <input type="date" id="fecha_preparacion" name="fecha_preparacion" required>

              <label for="cantidad_preparada">Cantidad Preparada (L):</label>
              <input type="number" id="cantidad_preparada" name="cantidad_preparada" required min="0.1" step="0.1">

              <button type="submit">Registrar Preparación</button>
            </form>
          </div>
        </div>
      </div>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script>
  $(function () {
    $("#codigo_medio").autocomplete({
      source: "preparacion_soluciones.php?action=buscar_medio",
      minLength: 1,
      select: function (event, ui) {
        $("#codigo_medio").val(ui.item.value);
      }
    });
  });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
          integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
