<?php
include '../db.php';
session_start();

// 1) Verificar sesión
if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>
            alert('Debes iniciar sesión primero.');
            window.location.href='../login.php';
          </script>";
    exit();
}
$ID_Operador = $_SESSION["ID_Operador"];
$mensaje = "";

// 2) Autocompletado para medios ECAS
if (isset($_GET['action']) && $_GET['action'] === 'buscar_medio') {
    $term = "%".($_GET['term'] ?? '')."%";
    $stmt = $conn->prepare("
        SELECT DISTINCT Codigo_Medio
          FROM medios_nutritivos
         WHERE Codigo_Medio LIKE ?
           AND Etapa_Destinada = 'ECAS'
           AND Estado = 'Activo'
         LIMIT 10
    ");
    $stmt->bind_param("s", $term);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $out = array_map(fn($r)=>['label'=>$r['Codigo_Medio'],'value'=>$r['Codigo_Medio']], $rows);
    echo json_encode($out);
    exit;
}

// 3) Cargar siembras disponibles, calculando dinámicamente los brotes restantes
$sql = "
    SELECT
      S.ID_Siembra,
      V.Codigo_Variedad,
      V.Nombre_Variedad,
      S.Tuppers_Llenos      AS Tuppers_Sembrados,
      S.Cantidad_Sembrada
        - IFNULL((
            SELECT SUM(D.Cantidad_Dividida + COALESCE(D.Brotes_Contaminados,0))
              FROM division_ecas D
             WHERE D.ID_Siembra = S.ID_Siembra
          ),0)            AS Disponibles,
      COALESCE((
        SELECT MAX(Generacion)
          FROM division_ecas D
         WHERE D.ID_Siembra = S.ID_Siembra
      ),0)                AS Ultima_Generacion,
      S.Cantidad_Sembrada,
      S.Fecha_Siembra
    FROM siembra_ecas S
    JOIN variedades V ON V.ID_Variedad = S.ID_Variedad
    WHERE S.ID_Desinfeccion IS NOT NULL
      AND (
        S.Cantidad_Sembrada
        - IFNULL((
            SELECT SUM(D.Cantidad_Dividida + COALESCE(D.Brotes_Contaminados,0))
              FROM division_ecas D
             WHERE D.ID_Siembra = S.ID_Siembra
          ),0)
      ) > 0
    ORDER BY S.Fecha_Siembra DESC
";
$siembras = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// 4) Procesar formulario de división
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_division"])) {
    $id_siembra          = (int) $_POST["id_siembra"];
    $fecha               = $_POST["fecha_div"];
    $cantidad_div        = (int) $_POST["cantidad"];
    $tuppers_llenos      = (int) $_POST["tuppers_llenos"];
    $tuppers_vacios      = (int) $_POST["tuppers_desocupados"];
    $brotes_cont         = (int) ($_POST["brotes_contaminados"] ?? 0);
    $brotes_totales      = (int) $_POST["brotes_totales"];
    $tasa                = (float) $_POST["tasa_multiplicacion"];
    $medio               = $_POST["medio"];
    $obs                 = $_POST["observaciones"] ?? '';
    $gen                 = (int) $_POST["generacion"];

    // Validar medio nutritivo
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
          FROM medios_nutritivos
         WHERE Codigo_Medio = ?
           AND Etapa_Destinada = 'ECAS'
    ");
    $stmt->bind_param("s", $medio);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['total'] === 0) {
        $mensaje = "❌ Medio nutritivo no registrado para ECAS.";
    } else {
        // Insertar división
        $ins = $conn->prepare("
            INSERT INTO division_ecas
              (ID_Siembra, Fecha_Division, Cantidad_Dividida,
               Tuppers_Llenos, Tuppers_Desocupados, Tuppers_Disponibles,
               Medio_Nuevo, Generacion, Observaciones, Operador_Responsable,
               Brotes_Totales, Tasa_Multiplicacion, Brotes_Contaminados)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // Tuppers_Disponibles = Tuppers_Llenos
        $ins->bind_param(
            "isiiiisisiidi",
            $id_siembra,
            $fecha,
            $cantidad_div,
            $tuppers_llenos,
            $tuppers_vacios,
            $tuppers_llenos,
            $medio,
            $gen,
            $obs,
            $ID_Operador,
            $brotes_totales,
            $tasa,
            $brotes_cont
        );
        if ($ins->execute()) {
            $id_div = $conn->insert_id;
            // Actualizar brotes sembrados en siembra_ecas
            $upd = $conn->prepare("
                UPDATE siembra_ecas
                   SET Brotes_Disponibles = Brotes_Disponibles - ?
                 WHERE ID_Siembra = ?
            ");
            $upd->bind_param("ii", $cantidad_div, $id_siembra);
            $upd->execute();
            // Registrar pérdidas por contaminación
            if ($brotes_cont > 0) {
                $perd = $conn->prepare("
                    INSERT INTO perdidas_laboratorio
                      (ID_Entidad, Tipo_Entidad, Fecha_Perdida,
                       Cantidad_Perdida, Motivo, Operador_Entidad, Operador_Chequeo)
                    VALUES (?, 'division_ecas', ?, ?, 'Contaminación en división', ?, ?)
                ");
                $perd->bind_param("isiii", $id_div, $fecha, $brotes_cont, $ID_Operador, $ID_Operador);
                $perd->execute();
            }
            header("Location: divisiones_ecas.php?success=1");
            exit;
        } else {
            $mensaje = "❌ Error al guardar división: " . $ins->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Divisiones - ECAS</title>
  <link rel="stylesheet" href="../style.css?v=<?=time()?>">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#"><img src="../logoplantulas.png" width="130" height="124" alt="Logo"></a>
      <h2>Registro de Divisiones - ECAS</h2>
      <div></div>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="location.href='dashboard_egp.php'">🏠 Volver al inicio</button>
          </div>
        </div>
      </nav>
    </div>
  </header>
  <main class="container mt-4">
    <?php if ($mensaje): ?>
      <div class="alert alert-warning"><?= $mensaje ?></div>
    <?php elseif (isset($_GET['success'])): ?>
      <div class="alert alert-success">✅ División registrada correctamente.</div>
    <?php endif; ?>

    <h4>🌱 Siembras disponibles para dividir:</h4>
    <div class="carrusel-desinfecciones mb-4">
      <?php foreach ($siembras as $s): ?>
        <div class="tarjeta-desinf"
             data-id="<?= $s['ID_Siembra'] ?>"
             data-variedad="<?= htmlspecialchars("{$s['Codigo_Variedad']} - {$s['Nombre_Variedad']}") ?>"
             data-generacion="<?= $s['Ultima_Generacion']+1 ?>"
             data-disponibles="<?= $s['Disponibles'] ?>"
             data-tuppers-iniciales="<?= $s['Tuppers_Sembrados'] ?>">
          <strong><?= htmlspecialchars("{$s['Codigo_Variedad']} - {$s['Nombre_Variedad']}") ?></strong><br>
          Sembrados: <?= $s['Cantidad_Sembrada'] ?><br>
          Disponibles: <?= $s['Disponibles'] ?><br>
          Tuppers iniciales: <?= $s['Tuppers_Sembrados'] ?><br>
          Última gen: <?= $s['Ultima_Generacion'] ?><br>
          Fecha: <?= date("d/m/Y",strtotime($s['Fecha_Siembra'])) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" id="formulario-division" class="form-doble-columna" style="display:none;">
      <input type="hidden" name="id_siembra" id="id_siembra">
      <input type="hidden" name="generacion" id="generacion">

      <p><strong>Variedad:</strong> <span id="nombre_variedad"></span></p>
      <p>Disponibles: <span id="span_disponibles"></span></p>

      <label>📅 Fecha de división:</label>
      <input type="date" name="fecha_div" class="form-control" required>

      <label>🔢 Cantidad dividida:</label>
      <input type="number" name="cantidad" id="cantidad" class="form-control" min="1" required>

      <label>📦 Tuppers llenos:</label>
      <input type="number" name="tuppers_llenos" id="tuppers_llenos" class="form-control" min="0" required>

      <label>📦 Tuppers desocupados:</label>
      <input type="number" name="tuppers_desocupados" id="tuppers_desocupados" class="form-control" min="0" required>

      <label>💥 Brotes contaminados:</label>
      <input type="number" name="brotes_contaminados" class="form-control" min="0">

      <label>🌿 Brotes totales:</label>
      <input type="number" name="brotes_totales" class="form-control" min="0" required>

      <label>📈 Tasa multiplicación:</label>
      <input type="number" name="tasa_multiplicacion" step="0.01" class="form-control" min="0" required>

      <label>🧪 Medio nutritivo:</label>
      <input type="text" name="medio" id="medio_nutritivo" class="form-control" required>

      <label>📝 Observaciones:</label>
      <textarea name="observaciones" class="form-control" rows="3"></textarea>

      <button type="submit" name="guardar_division" class="btn btn-primary mt-3">Guardar División</button>
    </form>
  </main>
  <footer class="text-center mt-5">&copy; 2025 PLANTAS AGRODEX</footer>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function(){
  let tuppersInit = 0;
  $(".tarjeta-desinf").click(function(){
    const $t = $(this);
    tuppersInit = +$t.data("tuppers-iniciales");
    $("#id_siembra").val($t.data("id"));
    $("#generacion").val($t.data("generacion"));
    $("#nombre_variedad").text($t.data("variedad"));
    $("#span_disponibles").text($t.data("disponibles"));
    $("#cantidad").val($t.data("disponibles")).attr("max", $t.data("disponibles"));
    $("#tuppers_desocupados").attr("max", tuppersInit);
    $(".tarjeta-desinf").removeClass("selected blur");
    $t.addClass("selected").siblings().addClass("blur");
    $("#formulario-division").slideDown();
    window.scrollTo({ top: $("#formulario-division").offset().top - 80, behavior:'smooth' });
  });

  $("#formulario-division").submit(function(e){
    if (+$("#tuppers_desocupados").val() > tuppersInit) {
      alert(`❌ Desocupados no pueden superar los ${tuppersInit} iniciales.`);
      e.preventDefault();
    }
  });

  $("#medio_nutritivo").autocomplete({
    source(request,response){ 
      $.getJSON("divisiones_ecas.php?action=buscar_medio",{term:request.term},response);
    },
    minLength:0
  }).focus(function(){ $(this).autocomplete("search"); });
});
</script>
</body>
</html>
