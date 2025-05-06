<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>
            alert('Debes iniciar sesión primero.');
            window.location.href='../login.php';
          </script>";
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
    $like = "%{$term}%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $res = [];
    while ($row = $result->fetch_assoc()) {
        $res[] = ['label' => $row['Codigo_Medio'], 'value' => $row['Codigo_Medio']];
    }
    echo json_encode($res);
    exit;
}

// Obtener desinfecciones con explantes aún disponibles
$sql_des = "
    SELECT 
        D.ID_Desinfeccion,
        D.ID_Variedad,
        D.Explantes_Desinfectados,
        V.Codigo_Variedad,
        V.Nombre_Variedad,
        COALESCE(SUM(S.Cantidad_Sembrada), 0) AS Cantidad_Sembrada_Total
    FROM desinfeccion_explantes D
    JOIN variedades V ON D.ID_Variedad = V.ID_Variedad
    LEFT JOIN siembra_ecas S ON D.ID_Desinfeccion = S.ID_Desinfeccion
    WHERE D.Operador_Responsable = ?
      AND D.Estado_Desinfeccion = 'Completado'
    GROUP BY D.ID_Desinfeccion
    HAVING D.Explantes_Desinfectados > COALESCE(SUM(S.Cantidad_Sembrada), 0)
    ORDER BY D.FechaHr_Desinfeccion DESC
";
$stmt_des = $conn->prepare($sql_des);
$stmt_des->bind_param("i", $ID_Operador);
$stmt_des->execute();
$desinfecciones = $stmt_des->get_result()->fetch_all(MYSQLI_ASSOC);

// Manejo del formulario de siembra
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_siembra"])) {
    $id_desinfeccion = (int)$_POST["id_desinfeccion"];
    $id_variedad     = (int)$_POST["id_variedad"];
    $fecha_siembra   = $_POST["fecha_siembra"];
    $medio           = $_POST["medio"];
    $cantidad        = (int)$_POST["cantidad"];
    $tuppers_llenos  = (int)$_POST["tuppers_llenos"];
    $observaciones   = $_POST["observaciones"] ?? '';

    if ($tuppers_llenos > $cantidad) {
        $mensaje = "❌ Los tuppers llenos no pueden exceder la cantidad de explantes.";
    } else {
        // Validar medio nutritivo
        $ver = $conn->prepare("
            SELECT COUNT(*) AS total 
            FROM medios_nutritivos 
            WHERE Codigo_Medio = ? 
              AND Etapa_Destinada = 'ECAS'
        ");
        $ver->bind_param("s", $medio);
        $ver->execute();
        $res = $ver->get_result()->fetch_assoc();
        if ($res['total'] == 0) {
            $mensaje = "❌ El código del medio nutritivo no está registrado para ECAS.";
        } else {
            // Verificar o crear lote
            $sql_buscar_lote = "
                SELECT ID_Lote 
                FROM lotes 
                WHERE Fecha = ? 
                  AND ID_Variedad = ? 
                  AND ID_Operador = ? 
                  AND ID_Etapa = 1
            ";
            $stmt_buscar_lote = $conn->prepare($sql_buscar_lote);
            $stmt_buscar_lote->bind_param("sii", $fecha_siembra, $id_variedad, $ID_Operador);
            $stmt_buscar_lote->execute();
            $row = $stmt_buscar_lote->get_result()->fetch_assoc();

            if ($row) {
                $id_lote = $row['ID_Lote'];
            } else {
                $sql_lote = "
                    INSERT INTO lotes (Fecha, ID_Variedad, ID_Operador, ID_Etapa)
                    VALUES (?, ?, ?, 1)
                ";
                $stmt_lote = $conn->prepare($sql_lote);
                $stmt_lote->bind_param("sii", $fecha_siembra, $id_variedad, $ID_Operador);
                if ($stmt_lote->execute()) {
                    $id_lote = $conn->insert_id;
                } else {
                    $mensaje = "❌ Error al crear el lote.";
                }
            }

            if (empty($mensaje)) {
                // Insertar registro de siembra con Tuppers_Llenos
                $sql_siembra = "
                    INSERT INTO siembra_ecas
                      (ID_Desinfeccion, ID_Variedad, Fecha_Siembra, Medio_Nutritivo,
                       Cantidad_Sembrada, Tuppers_Llenos, Observaciones,
                       Operador_Responsable, ID_Lote)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt_siembra = $conn->prepare($sql_siembra);
                $stmt_siembra->bind_param(
                    "iissisiis",
                    $id_desinfeccion,
                    $id_variedad,
                    $fecha_siembra,
                    $medio,
                    $cantidad,
                    $tuppers_llenos,
                    $observaciones,
                    $ID_Operador,
                    $id_lote
                );
                if ($stmt_siembra->execute()) {
                    header("Location: registro_siembra_ecas.php?success=1");
                    exit();
                } else {
                    $mensaje = "❌ Error al registrar la siembra: " . $stmt_siembra->error;
                }
            }
        }
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
      <a class="navbar-brand" href="#"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
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

  <main class="container mt-4">
    <?php if ($mensaje): ?>
      <div class="alert alert-warning"><?= $mensaje ?></div>
    <?php elseif (isset($_GET['success'])): ?>
      <div class="alert alert-success">✅ Siembra registrada correctamente.</div>
    <?php endif; ?>

    <h4>🧼 Desinfecciones con explantes disponibles:</h4>
    <div class="carrusel-desinfecciones mb-4">
      <?php foreach ($desinfecciones as $d): 
          $disponibles = $d['Explantes_Desinfectados'] - $d['Cantidad_Sembrada_Total'];
      ?>
        <div class="tarjeta-desinf" 
             data-id="<?= $d['ID_Desinfeccion'] ?>" 
             data-variedad-id="<?= $d['ID_Variedad'] ?>"
             data-variedad-nombre="<?= htmlspecialchars($d['Codigo_Variedad'] . ' - ' . $d['Nombre_Variedad']) ?>"
             data-explantes="<?= $disponibles ?>">
          <strong><?= htmlspecialchars($d['Codigo_Variedad'] . ' - ' . $d['Nombre_Variedad']) ?></strong><br>
          Disponibles: <?= $disponibles ?><br>
          Fecha: <?= date("d/m/Y", strtotime($d['FechaHr_Desinfeccion'])) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" id="formulario-siembra" class="form-doble-columna" style="display:none;">
      <input type="hidden" name="id_variedad" id="id_variedad">
      <input type="hidden" name="id_desinfeccion" id="id_desinfeccion">

      <label>Variedad: <strong><span id="nombre_variedad"></span></strong></label>
      <label for="fecha_siembra">📅 Fecha de siembra:</label>
      <input type="date" name="fecha_siembra" class="form-control" required>

      <label for="medio">🧪 Código del medio nutritivo:</label>
      <input type="text" id="medio_nutritivo" name="medio" class="form-control" required>

      <label for="cantidad">🔢 Explantes a sembrar:</label>
      <input type="number" name="cantidad" class="form-control" min="1" required>

      <label for="tuppers_llenos">📦 Tuppers llenos:</label>
      <input type="number" name="tuppers_llenos" class="form-control" min="0" required>

      <label for="observaciones">📝 Observaciones:</label>
      <textarea name="observaciones" class="form-control" rows="3"></textarea>

      <button type="submit" name="guardar_siembra" class="btn btn-primary mt-3">Guardar Registro</button>
    </form>
  </main>

  <footer class="text-center mt-5">
    © 2025 PLANTAS AGRODEX. Todos los derechos reservados.
  </footer>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function(){
  let explantesDisponibles = 0;

  $("#medio_nutritivo").autocomplete({
    source(request, response) {
      $.getJSON("registro_siembra_ecas.php?action=buscar_medio", { term: request.term }, response);
    },
    minLength: 0
  }).focus(function(){
    $(this).autocomplete("search", "");
  });

  $(".tarjeta-desinf").click(function(){
    explantesDisponibles = +$(this).data("explantes");
    $("#formulario-siembra")[0].reset();
    $(".tarjeta-desinf").removeClass("selected blur");
    $(this).addClass("selected").siblings().addClass("blur");

    $("#id_desinfeccion").val($(this).data("id"));
    $("#id_variedad").val($(this).data("variedad-id"));
    $("#nombre_variedad").text($(this).data("variedad-nombre"));

    $("input[name='cantidad']").attr("max", explantesDisponibles);
    $("input[name='tuppers_llenos']").attr("max", explantesDisponibles);

    $("#formulario-siembra").slideDown("fast");
    window.scrollTo({ top: $("#formulario-siembra").offset().top - 100, behavior: 'smooth' });
  });

  $("#formulario-siembra").submit(function(e){
    const medio    = $("#medio_nutritivo").val().trim();
    const sembrados = +$("input[name='cantidad']").val();
    const llenos    = +$("input[name='tuppers_llenos']").val();

    if (!medio) {
      alert("⚠️ Debes ingresar un medio nutritivo.");
      e.preventDefault(); return;
    }
    if (sembrados > explantesDisponibles) {
      alert(`❌ Solo hay ${explantesDisponibles} explantes disponibles.`);
      e.preventDefault(); return;
    }
    if (llenos > sembrados) {
      alert("❌ Los tuppers llenos no pueden exceder la cantidad de explantes.");
      e.preventDefault(); return;
    }
  });
});
</script>
</body>
</html>
