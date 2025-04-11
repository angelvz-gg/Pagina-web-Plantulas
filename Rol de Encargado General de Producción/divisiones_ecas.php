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

// Obtener siembras disponibles
$sql = "SELECT S.*, V.Codigo_Variedad, V.Nombre_Variedad,
               (SELECT COUNT(*) FROM division_ecas D WHERE D.ID_Siembra = S.ID_Siembra) AS Total_Divisiones,
               (SELECT COALESCE(MAX(Generacion), 0) FROM division_ecas D WHERE D.ID_Siembra = S.ID_Siembra) AS Ultima_Generacion,
               (S.Cantidad_Sembrada - IFNULL((SELECT SUM(Cantidad_Dividida + Brotes_Contaminados) FROM division_ecas D WHERE D.ID_Siembra = S.ID_Siembra),0)) AS Disponibles
        FROM siembra_ecas S
        JOIN Variedades V ON V.ID_Variedad = S.ID_Variedad
        WHERE S.ID_Desinfeccion IS NOT NULL
          AND (S.Cantidad_Sembrada - IFNULL((SELECT SUM(Cantidad_Dividida + Brotes_Contaminados) FROM division_ecas D WHERE D.ID_Siembra = S.ID_Siembra),0)) > 0
        ORDER BY S.Fecha_Siembra DESC";
$res = $conn->query($sql);
$siembras = $res->fetch_all(MYSQLI_ASSOC);

// Guardar división
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_division"])) {
    $id_siembra = $_POST["id_siembra"];
    $fecha = $_POST["fecha_div"];
    $cantidad = $_POST["cantidad"];
    $brotes_contaminados = $_POST["brotes_contaminados"];
    $brotes_totales = $_POST["brotes_totales"];
    $tasa_multiplicacion = $_POST["tasa_multiplicacion"];
    $medio = $_POST["medio"];
    $observaciones = $_POST["observaciones"];
    $generacion = $_POST["generacion"];

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM medios_nutritivos WHERE Codigo_Medio = ? AND Etapa_Destinada = 'ECAS'");
    $stmt->bind_param("s", $medio);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result['total'] == 0) {
        $mensaje = "❌ El medio nutritivo no está registrado para ECAS.";
    } else {
        $stmt = $conn->prepare("INSERT INTO division_ecas 
            (ID_Siembra, Fecha_Division, Cantidad_Dividida, Medio_Nuevo, Generacion, Observaciones, Operador_Responsable, Brotes_Totales, Tasa_Multiplicacion, Brotes_Contaminados) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isisssiddi", 
            $id_siembra, 
            $fecha, 
            $cantidad, 
            $medio, 
            $generacion, 
            $observaciones, 
            $ID_Operador, 
            $brotes_totales, 
            $tasa_multiplicacion, 
            $brotes_contaminados
        );

        if ($stmt->execute()) {
            $id_division = $conn->insert_id;

            if ($brotes_contaminados > 0) {
                $motivo = "Contaminación en división";
                $sql_perdida = "INSERT INTO perdidas_laboratorio 
                    (ID_Entidad, Tipo_Entidad, Fecha_Perdida, Cantidad_Perdida, Motivo, Operador_Entidad, Operador_Chequeo)
                    VALUES (?, 'division_ecas', ?, ?, ?, ?, ?)";
                $stmt_perdida = $conn->prepare($sql_perdida);
                if ($stmt_perdida) {
                    $stmt_perdida->bind_param("isissi", 
                        $id_division, 
                        $fecha, 
                        $brotes_contaminados, 
                        $motivo, 
                        $ID_Operador, 
                        $ID_Operador
                    );
                    $stmt_perdida->execute();
                }
            }

            header("Location: divisiones_ecas.php?success=1");
            exit();
        } else {
            $mensaje = "❌ Error al registrar la división.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Registro de Divisiones - ECAS</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
      <h2>Registro de Divisiones - ECAS</h2>
      <div></div>
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

  <main class="container">
    <?php if ($mensaje): ?>
      <div class="alert alert-info mt-3"><?= $mensaje ?></div>
    <?php endif; ?>

    <h4 class="mt-4">🌱 Siembras disponibles para dividir:</h4>
    <div class="carrusel-desinfecciones">
      <?php foreach ($siembras as $s): ?>
        <div class="tarjeta-desinf"
             data-id="<?= $s['ID_Siembra'] ?>"
             data-variedad="<?= $s['Codigo_Variedad'] . ' - ' . $s['Nombre_Variedad'] ?>"
             data-generacion="<?= $s['Ultima_Generacion'] + 1 ?>"
             data-disponibles="<?= $s['Disponibles'] ?>">
          <strong><?= $s['Codigo_Variedad'] ?> - <?= $s['Nombre_Variedad'] ?></strong><br>
          Siembra: <?= $s['Cantidad_Sembrada'] ?><br>
          Disponibles: <?= $s['Disponibles'] ?><br>
          Última gen: <?= $s['Ultima_Generacion'] ?><br>
          Fecha: <?= date("d/m/Y", strtotime($s['Fecha_Siembra'])) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" class="form-doble-columna formulario-siembra" id="formulario-division" style="display:none">
      <input type="hidden" name="id_siembra" id="id_siembra">
      <input type="hidden" name="generacion" id="generacion">
      <div class="content">
        <div class="section">
          <p><strong>Variedad seleccionada:</strong> <span id="nombre_variedad"></span></p>

          <label>¿El explante está hinchado?</label>
          <select id="hinchado" required>
            <option value="">-- Selecciona --</option>
            <option value="si">Sí</option>
            <option value="no">No</option>
          </select>

          <div id="datos-division" style="display:none">
            <label>📅 Fecha de división:</label>
            <input type="date" name="fecha_div" required>

            <label>💥 Brotes contaminados:</label>
            <input type="number" name="brotes_contaminados" min="0">

            <label>🔢 Cantidad dividida:</label>
            <input type="number" name="cantidad" id="cantidad" min="1" required>

            <label>🌿 Brotes totales obtenidos:</label>
            <input type="number" name="brotes_totales" min="0" required>

            <label>📈 Tasa de multiplicación:</label>
            <input type="number" name="tasa_multiplicacion" step="0.01" min="0" required>

            <label>🧪 Código nuevo de Medio Nutritivo:</label>
            <input type="text" name="medio" id="medio_nutritivo" required>

            <label>📝 Observaciones:</label>
            <textarea name="observaciones" rows="3"></textarea>

            <button type="submit" name="guardar_division" class="save-button mt-3">Guardar División</button>
          </div>
        </div>
      </div>
    </form>
  </main>

  <footer class="mt-5">
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function () {
  let disponibles = 0;

  $(".tarjeta-desinf").on("click", function () {
    const id = $(this).data("id");
    const variedad = $(this).data("variedad");
    const generacion = $(this).data("generacion");
    disponibles = parseInt($(this).data("disponibles"));

    $("#id_siembra").val(id);
    $("#generacion").val(generacion);
    $("#nombre_variedad").text(variedad);
    $("#cantidad").attr("max", disponibles);

    $(".tarjeta-desinf").removeClass("selected blur");
    $(this).addClass("selected");
    $(".tarjeta-desinf").not(this).addClass("blur");

    $("#formulario-division").slideDown("fast", function () {
      window.scrollTo({ top: $("#formulario-division").offset().top - 100, behavior: 'smooth' });
    });
  });

  $("#hinchado").on("change", function () {
    const val = $(this).val();
    if (val === "si") {
      $("#datos-division").slideDown();
    } else {
      $("#datos-division").slideUp();
    }
  });

  $("form").on("submit", function (e) {
    const medio = $("#medio_nutritivo").val().trim();
    const cantidad = parseInt($("#cantidad").val());

    if (cantidad > disponibles) {
      alert("❌ No puedes dividir más de " + disponibles + " explantes disponibles.");
      $("#cantidad").focus();
      e.preventDefault();
      return;
    }

    if (medio === "") {
      alert("⚠️ Ingresa un código de medio nutritivo.");
      $("#medio_nutritivo").focus();
      e.preventDefault();
    }
  });

  $("#medio_nutritivo").autocomplete({
    source: function (request, response) {
      $.getJSON("divisiones_ecas.php?action=buscar_medio", { term: request.term }, response);
    },
    minLength: 0,
    select: function (event, ui) {
      $("#medio_nutritivo").val(ui.item.value);
    }
  }).focus(function () {
    $(this).autocomplete("search", "");
  });
});
</script>
</body>
</html>
