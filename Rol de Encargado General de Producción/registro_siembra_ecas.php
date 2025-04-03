<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];
$mensaje = "";

// Autocompletado para medios ECAS únicos
if (isset($_GET['action']) && $_GET['action'] === 'buscar_medio') {
    $term = $_GET['term'] ?? '';
    $sql = "SELECT DISTINCT Codigo_Medio 
            FROM medios_nutritivos 
            WHERE Codigo_Medio LIKE ? 
              AND Etapa_Destinada = 'ECAS' 
              AND Estado = 'Activo' 
            LIMIT 10";
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

// Obtener todas las desinfecciones completadas
$sql_des = "SELECT D.*, V.Codigo_Variedad, V.Nombre_Variedad 
            FROM desinfeccion_explantes D
            JOIN Variedades V ON D.ID_Variedad = V.ID_Variedad
            WHERE D.Operador_Responsable = ? 
              AND D.Estado_Desinfeccion = 'Completado'
              AND D.ID_Desinfeccion NOT IN (
                  SELECT ID_Desinfeccion FROM siembra_ecas
              )
            ORDER BY D.FechaHr_Desinfeccion DESC";

$stmt_des = $conn->prepare($sql_des);
$stmt_des->bind_param("i", $ID_Operador);
$stmt_des->execute();
$desinfecciones = $stmt_des->get_result()->fetch_all(MYSQLI_ASSOC);

// Guardar siembra
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_siembra"])) {
    $id_desinfeccion = $_POST["id_desinfeccion"];
    $id_variedad = $_POST["id_variedad"];
    $fecha_siembra = $_POST["fecha_siembra"];
    $medio = $_POST["medio"];
    $cantidad = $_POST["cantidad"];
    $observaciones = $_POST["observaciones"];

    $ver = $conn->prepare("SELECT COUNT(*) as total FROM medios_nutritivos WHERE Codigo_Medio = ? AND Etapa_Destinada = 'ECAS'");
    $ver->bind_param("s", $medio);
    $ver->execute();
    $res = $ver->get_result()->fetch_assoc();

    if ($res['total'] == 0) {
        $mensaje = "❌ El código del medio nutritivo no está registrado para ECAS.";
    } else {
        $sql = "INSERT INTO siembra_ecas 
                (ID_Desinfeccion, ID_Variedad, Fecha_Siembra, Medio_Nutritivo, Cantidad_Sembrada, Observaciones, Operador_Responsable)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissisi", $id_desinfeccion, $id_variedad, $fecha_siembra, $medio, $cantidad, $observaciones, $ID_Operador);
        $mensaje = $stmt->execute() ? "✅ Siembra registrada correctamente." : "❌ Error al registrar la siembra.";
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Registro de Siembra Inicial - ECAS</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" />
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
      <h2>Registro de Siembra Inicial - ECAS</h2>
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

    <h4 class="mt-4">🧼 Desinfecciones listas para sembrar:</h4>
    <div class="carrusel-desinfecciones">
      <?php foreach ($desinfecciones as $d): ?>
        <div class="tarjeta-desinf" 
             data-id="<?= $d['ID_Desinfeccion'] ?>" 
             data-variedad-id="<?= $d['ID_Variedad'] ?>"
             data-variedad-nombre="<?= $d['Codigo_Variedad'] . ' - ' . $d['Nombre_Variedad'] ?>"
             data-explantes="<?= $d['Explantes_Desinfectados'] ?>"
             data-num-desinf="<?= $d['Num_Desinfecciones'] ?? 1 ?>">
          <strong><?= $d['Codigo_Variedad'] ?> - <?= $d['Nombre_Variedad'] ?></strong><br>
          Explantes: <?= $d['Explantes_Desinfectados'] ?><br>
          Desinfecciones: <?= $d['Num_Desinfecciones'] ?? 1 ?><br>
          Fecha: <?= date("d/m/Y", strtotime($d['FechaHr_Desinfeccion'])) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" class="form-doble-columna formulario-siembra" id="formulario-siembra">
      <input type="hidden" name="id_variedad" id="id_variedad">
      <input type="hidden" name="id_desinfeccion" id="id_desinfeccion">
      <div class="content">
        <div class="section">
          <p><strong>Variedad seleccionada:</strong> <span id="nombre_variedad"></span></p>

          <label for="fecha_siembra">📅 Fecha de siembra:</label>
          <input type="date" name="fecha_siembra" required>

          <label for="medio_nutritivo">🧪 Código del Medio Nutritivo:</label>
          <input type="text" id="medio_nutritivo" name="medio" required>

          <label for="cantidad">🔢 Cantidad de explantes sembrados:</label>
          <input type="number" name="cantidad" min="1" required>

          <label for="observaciones">📝 Observaciones:</label>
          <textarea name="observaciones" rows="3"></textarea>

          <button type="submit" name="guardar_siembra" class="save-button mt-3">Guardar Registro</button>
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
  let explantesDesinfectados = 0;

  // Autocompletado para medios ECAS
  $("#medio_nutritivo").autocomplete({
    source: function (request, response) {
      $.getJSON("registro_siembra_ecas.php?action=buscar_medio", {
        term: request.term
      }, response);
    },
    minLength: 0,
    select: function (event, ui) {
      $("#medio_nutritivo").val(ui.item.value);
    }
  }).focus(function () {
    $(this).autocomplete("search", "");
  });

  // Selección de tarjeta
  $(".tarjeta-desinf").on("click", function () {
    const id = $(this).data("id");
    const idVariedad = $(this).data("variedad-id");
    const nombre = $(this).data("variedad-nombre");
    const numDesinf = $(this).data("num-desinf");
    explantesDesinfectados = parseInt($(this).data("explantes"));

    $("#formulario-siembra")[0].reset();
    $(".tarjeta-desinf").removeClass("selected blur");
    $(this).addClass("selected");
    $(".tarjeta-desinf").not(this).addClass("blur");

    $("#id_desinfeccion").val(id);
    $("#id_variedad").val(idVariedad);
    $("#nombre_variedad").text(nombre);
    $("#num_desinf").val(numDesinf);
    $("input[name='cantidad']").attr("max", explantesDesinfectados);

    if (!$(".formulario-siembra").is(":visible")) {
      $(".formulario-siembra").slideDown("fast", function () {
        window.scrollTo({ top: $("#formulario-siembra").offset().top - 100, behavior: 'smooth' });
      });
    } else {
      window.scrollTo({ top: $("#formulario-siembra").offset().top - 100, behavior: 'smooth' });
    }
  });

  // Validación al enviar
  $("form").on("submit", function (e) {
    const medio = $("#medio_nutritivo").val().trim();
    const sembrados = parseInt($("input[name='cantidad']").val());

    if (medio === "") {
      alert("⚠️ Debes ingresar un código de medio nutritivo.");
      $("#medio_nutritivo").focus();
      e.preventDefault();
      return;
    }

    if (sembrados > explantesDesinfectados) {
      alert(`❌ No puedes sembrar más de ${explantesDesinfectados} explantes desinfectados.`);
      $("input[name='cantidad']").focus();
      e.preventDefault();
      return;
    }

    $.ajax({
      url: "registro_siembra_ecas.php",
      data: { action: "buscar_medio", term: medio },
      dataType: "json",
      async: false,
      success: function (resultados) {
        const encontrado = resultados.some(r => r.value === medio);
        if (!encontrado) {
          alert("❌ El código del medio nutritivo no es válido para ECAS o está inactivo.");
          $("#medio_nutritivo").focus();
          e.preventDefault();
        }
      }
    });
  });
});
</script>
</body>
</html>
