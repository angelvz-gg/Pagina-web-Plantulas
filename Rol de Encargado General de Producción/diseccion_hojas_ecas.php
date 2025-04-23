<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];
$mensaje = "";

// Autocompletado para medios ECAS
if (isset($_GET['action']) && $_GET['action'] === 'buscar_medio') {
    $term = $_GET['term'] ?? '';
    $sql = "SELECT DISTINCT Codigo_Medio FROM medios_nutritivos 
            WHERE Codigo_Medio LIKE ? AND Etapa_Destinada = 'ECAS' AND Estado = 'Activo' LIMIT 10";
    $stmt = $conn->prepare($sql);
    $like = "%$term%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $res = [];
    while ($row = $result->fetch_assoc()) {
        $res[] = ['id' => $row['Codigo_Medio'], 'label' => $row['Codigo_Medio'], 'value' => $row['Codigo_Medio']];
    }
    echo json_encode($res);
    exit;
}

// Obtener siembras y divisiones disponibles
$sql = "
(
    SELECT 
        S.ID_Siembra,
        NULL AS ID_Division,
        S.ID_Lote,
        S.Brotes_Disponibles,
        V.Codigo_Variedad,
        V.Nombre_Variedad,
        S.Fecha_Siembra,
        'Siembra' AS Tipo,
        1 AS Generacion
    FROM siembra_ecas S
    JOIN variedades V ON V.ID_Variedad = S.ID_Variedad
    WHERE S.Brotes_Disponibles > 0
)
UNION ALL
(
    SELECT 
        D.ID_Siembra,
        D.ID_Division,
        NULL AS ID_Lote,
        D.Brotes_Totales AS Brotes_Disponibles,
        V.Codigo_Variedad,
        V.Nombre_Variedad,
        D.Fecha_Division AS Fecha_Siembra,
        'Division' AS Tipo,
        D.Generacion + 1 AS Generacion
    FROM division_ecas D
    JOIN siembra_ecas S ON S.ID_Siembra = D.ID_Siembra
    JOIN variedades V ON V.ID_Variedad = S.ID_Variedad
    WHERE D.Brotes_Totales > 0
)
ORDER BY Fecha_Siembra DESC
";

$res_siembras = $conn->query($sql);
$siembras = $res_siembras ? $res_siembras->fetch_all(MYSQLI_ASSOC) : [];

// Guardar disección
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_diseccion"])) {
    $id_siembra = $_POST["id_siembra"];
    $id_division = $_POST["id_division"] ?: null;
    $fecha_diseccion = $_POST["fecha_diseccion"];
    $cantidad_hojas = intval($_POST["cantidad_hojas"]);
    $medio_usado = $_POST["medio_usado"];
    $brotes_generados = isset($_POST["brotes_explante"]) ? intval($_POST["brotes_explante"]) : null;
    $observaciones = $_POST["observaciones"];
    $brotes_disponibles = intval($_POST["brotes_disponibles"]);
    $generacion = intval($_POST["generacion"]);

    if ($cantidad_hojas > $brotes_disponibles) {
        echo "<script>alert('❌ No puedes disecar más brotes de los disponibles. Brotes disponibles: {$brotes_disponibles}.'); window.history.back();</script>";
        exit();
    } else {
        $sql_insert = "INSERT INTO diseccion_hojas_ecas 
                        (ID_Siembra, ID_Lote, Fecha_Diseccion, N_Hojas_Diseccionadas, Medio_Usado, Generacion, Brotes_Generados, Observaciones, Operador_Responsable)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        $id_lote_real = $id_division ? NULL : null;

        $stmt_insert->bind_param(
            "iisisissi",
            $id_siembra,
            $id_lote_real,
            $fecha_diseccion,
            $cantidad_hojas,
            $medio_usado,
            $generacion,
            $brotes_generados,
            $observaciones,
            $ID_Operador
        );

        if ($stmt_insert->execute()) {
            // Actualizar brotes disponibles
            if ($id_division) {
                $update = $conn->prepare("UPDATE division_ecas SET Brotes_Totales = Brotes_Totales - ? WHERE ID_Division = ?");
                $update->bind_param("ii", $cantidad_hojas, $id_division);
            } else {
                $update = $conn->prepare("UPDATE siembra_ecas SET Brotes_Disponibles = Brotes_Disponibles - ? WHERE ID_Siembra = ?");
                $update->bind_param("ii", $cantidad_hojas, $id_siembra);
            }
            $update->execute();

            echo "<script>alert('✅ Disección registrada y brotes actualizados correctamente.'); window.location.href='dashboard_egp.php';</script>";
            exit();
        } else {
            echo "<script>alert('❌ Error al registrar la disección: " . $stmt_insert->error . "'); window.history.back();</script>";
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Disección de Hojas - ECAS</title>
  <link rel="stylesheet" href="../style.css?v=<?=time();?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#"><img src="../logoplantulas.png" alt="Logo" width="130" height="124"></a>
      <h2>Disección de Hojas - ECAS</h2>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_egp.php'">🏠 Volver al inicio</button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main class="container mt-4">
    <?php if (!empty($mensaje)): ?>
      <div class="alert alert-info"> <?= $mensaje ?> </div>
    <?php endif; ?>

    <form method="POST" class="form-doble-columna">
      <div class="mb-3">
        <label for="id_siembra" class="form-label">Selecciona una Fuente de Propágulos:</label>
        <select name="id_siembra" id="id_siembra" class="form-select" required>
          <option value="">-- Selecciona una opción --</option>
          <?php foreach ($siembras as $s): ?>
            <option value="<?= $s['ID_Siembra'] ?>"
                    data-division="<?= $s['ID_Division'] ?>"
                    data-variedad="<?= $s['Nombre_Variedad'] ?>"
                    data-codigo="<?= $s['Codigo_Variedad'] ?>"
                    data-fecha="<?= $s['Fecha_Siembra'] ?>"
                    data-brotes="<?= $s['Brotes_Disponibles'] ?>"
                    data-generacion="<?= $s['Generacion'] ?>">
              <?= "({$s['Tipo']}) {$s['Codigo_Variedad']} - {$s['Nombre_Variedad']} (Fecha: {$s['Fecha_Siembra']})" ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <input type="hidden" name="id_division" id="id_division">
      <input type="hidden" name="brotes_disponibles" id="brotes_disponibles">
      <input type="hidden" name="generacion" id="generacion">

      <div id="info-siembra" style="margin-bottom:20px;">
        <p><strong>📋 Información seleccionada:</strong></p>
        <p><strong>Fecha:</strong> <span id="fecha_siembra"></span></p>
        <p><strong>Variedad:</strong> <span id="variedad_siembra"></span></p>
        <p><strong>Código:</strong> <span id="codigo_variedad"></span></p>
        <p><strong>Explantes Disponibles:</strong> <span id="brotes_siembra"></span></p>
        <p><strong>Generación:</strong> <span id="generacion_siembra"></span></p>
      </div>

      <div class="mb-3">
        <label for="fecha_diseccion" class="form-label">Fecha de Disección:</label>
        <input type="date" name="fecha_diseccion" id="fecha_diseccion" class="form-control" required>
      </div>

      <div class="mb-3">
        <label for="cantidad_hojas" class="form-label">Cantidad de Explantes Diseccionadas:</label>
        <input type="number" name="cantidad_hojas" id="cantidad_hojas" class="form-control" min="1" required>
      </div>

      <div class="mb-3">
        <label for="brotes_explante" class="form-label">Brotes generados:</label>
        <input type="number" name="brotes_explante" id="brotes_explante" class="form-control" min="0">
      </div>

      <div class="mb-3">
        <label for="medio_usado" class="form-label">Código del Nuevo Medio Nutritivo:</label>
        <input type="text" name="medio_usado" id="medio_usado" class="form-control" required>
      </div>

      <div class="mb-3">
        <label for="observaciones" class="form-label">Observaciones:</label>
        <textarea name="observaciones" id="observaciones" class="form-control" rows="3"></textarea>
      </div>

      <button type="submit" name="guardar_diseccion" class="btn btn-primary">Guardar Disección</button>
    </form>
  </main>

  <footer class="text-center mt-5">
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
document.getElementById('id_siembra')?.addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  document.getElementById('fecha_siembra').innerText = opt.getAttribute('data-fecha') || '';
  document.getElementById('variedad_siembra').innerText = opt.getAttribute('data-variedad') || '';
  document.getElementById('codigo_variedad').innerText = opt.getAttribute('data-codigo') || '';
  document.getElementById('brotes_siembra').innerText = opt.getAttribute('data-brotes') || '0';

  document.getElementById('id_division').value = opt.getAttribute('data-division') || '';
  document.getElementById('brotes_disponibles').value = opt.getAttribute('data-brotes') || '0';
  document.getElementById('generacion').value = opt.getAttribute('data-generacion') || '1';
  document.getElementById('generacion_siembra').innerText = opt.getAttribute('data-generacion') || '1';
});

$(function () {
  $("#medio_usado").autocomplete({
    source: function (request, response) {
      $.getJSON("diseccion_hojas_ecas.php?action=buscar_medio", { term: request.term }, response);
    },
    minLength: 0,
    select: function (event, ui) {
      $("#medio_usado").val(ui.item.value);
    }
  }).focus(function () {
    $(this).autocomplete("search", "");
  });
});
</script>
</body>
</html>
