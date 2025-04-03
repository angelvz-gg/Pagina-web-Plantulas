<?php
include '../db.php';
session_start();

// Autocompletado AJAX de operadores (solo operadores reales con ID_Rol = 3)
if (isset($_GET['action']) && $_GET['action'] === 'buscar_operador') {
  $term = $_GET['term'] ?? '';
  $sql = "SELECT ID_Operador, CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) AS NombreCompleto 
          FROM operadores 
          WHERE CONCAT(Nombre, ' ', Apellido_P, ' ', Apellido_M) LIKE ? 
          AND ID_Rol = 2 AND Activo = 1
          LIMIT 10";
  $stmt = $conn->prepare($sql);
  $like = "%$term%";
  $stmt->bind_param("s", $like);
  $stmt->execute();
  $result = $stmt->get_result();

  $res = [];
  while ($row = $result->fetch_assoc()) {
      $res[] = [
          'id' => $row['ID_Operador'],
          'label' => $row['NombreCompleto'],
          'value' => $row['NombreCompleto']
      ];
  }
  echo json_encode($res);
  exit;
}

// Procesar asignación
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $id_operador = $_POST['id_operador'];
  $fecha = $_POST['fecha_de_asignacion'];
  $area = $_POST['area'];
  $estado = 'Pendiente';

  // Validar que el operador existe, está activo y tiene el rol correcto
  $validar = $conn->prepare("SELECT COUNT(*) FROM operadores WHERE ID_Operador = ? AND Activo = 1 AND ID_Rol = 2");
  $validar->bind_param("i", $id_operador);
  $validar->execute();
  $validar->bind_result($existe);
  $validar->fetch();
  $validar->close();

  if ($existe == 0) {
      echo "<script>alert('El operador seleccionado no existe, no está activo o no es operador.');</script>";
  } else {
      $stmt = $conn->prepare("INSERT INTO registro_limpieza (ID_Operador, Fecha, Area, Estado_Limpieza) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("isss", $id_operador, $fecha, $area, $estado);

      if ($stmt->execute()) {
          echo "<script>alert('Limpieza asignada exitosamente.'); window.location.href='rol_limpieza.php';</script>";
          exit;
      } else {
          echo "<script>alert('Error al asignar limpieza.');</script>";
      }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Asignación de Limpieza</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
        </a>
        <div>
          <h2>Asignación de Limpieza</h2>
          <p>Registro de operadores responsables de la limpieza y áreas asignadas.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_egp.php'">
                🔄 Regresar
              </button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
      <div class="section">
        <h2>🧼 Asignar limpieza de áreas</h2>
        <form method="POST">
          <label for="operador_asignado">👤 Operador Asignado:</label>
          <input type="text" id="operador_asignado" name="operador_asignado" required placeholder="Buscar operador...">
          <input type="hidden" id="id_operador" name="id_operador" required>

          <label for="fecha_de_asignacion">📅 Fecha de Asignación:</label>
          <input type="date" id="fecha_de_asignacion" name="fecha_de_asignacion" required />

          <label for="menuarea">🧽 Área a limpiar:</label>
          <select id="menuarea" name="area" required>
            <option value="">-- Seleccione un área --</option>
          </select>

          <button type="submit" class="mt-3">✅ Asignar Limpieza</button>
        </form>
      </div>

      <div class="section">
        <h3>📂 Ver historial de asignaciones</h3>
        <button onclick="window.location.href='historial_limpieza.php'">📋 Ir al historial</button>
      </div>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script>
    // Autocompletado de operador
    $(function () {
      $("#operador_asignado").autocomplete({
        source: "rol_limpieza.php?action=buscar_operador",
        minLength: 1,
        select: function (event, ui) {
          $("#operador_asignado").val(ui.item.value);
          $("#id_operador").val(ui.item.id);
        }
      });
    });

    // Cargar lista de áreas
    const datosarea = {
      areas: [
        "1. Área común",
        "2. Baños",
        "3. Zona de secado de tupper",
        "4. Zona de almacenamiento de tupper",
        "5. Zona de tupper vacío",
        "6. Zona de cajas vacías y osmocis",
        "7. Incubador",
        "8. Zona de zapatos",
        "9. Área de preparación de medios",
        "10. Área de reactivos",
        "11. Siembras etapa 2",
        "12. Siembras etapa 3"
      ]
    };

    document.addEventListener("DOMContentLoaded", function () {
      const menuarea = document.getElementById("menuarea");
      datosarea.areas.forEach(area => {
        const option = document.createElement("option");
        option.value = area;
        option.textContent = area;
        menuarea.appendChild(option);
      });
    });
  </script>
</body>
</html>
