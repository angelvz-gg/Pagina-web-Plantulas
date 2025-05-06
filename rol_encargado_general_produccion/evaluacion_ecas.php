<?php
// Mostrar errores en pantalla (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// 2) Procesar registro de pérdida
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_evaluacion"])) {
    $id_lote       = (int) $_POST["id_lote"];
    $cont          = $_POST["contaminacion"] ?? 'no';
    $fecha_perd    = date("Y-m-d");
    $tupp_perd     = (int) ($_POST["tuppers_desechados"] ?? 0);
    $bro_perd      = (int) ($_POST["brotes_desechados"] ?? 0);
    $motivo        = $_POST["motivo_desecho"] ?? '';
    $obs           = $_POST["observaciones"] ?? '';

    if ($cont === "si" && ($tupp_perd > 0 || $bro_perd > 0)) {
        $total = $tupp_perd + $bro_perd;
        $stmt = $conn->prepare("
            INSERT INTO perdidas_laboratorio
              (ID_Entidad, Tipo_Entidad, Fecha_Perdida, Cantidad_Perdida,
               Tuppers_Perdidos, Brotes_Perdidos, Motivo,
               Operador_Entidad, Operador_Chequeo)
            VALUES (?, 'lotes', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isiiisii",
            $id_lote, $fecha_perd, $total,
            $tupp_perd, $bro_perd,
            $motivo, $ID_Operador, $ID_Operador
        );
        $mensaje = $stmt->execute()
            ? "✅ Pérdida registrada correctamente."
            : "❌ Error al registrar pérdida: ".$stmt->error;
    } else {
        $mensaje = "✅ Evaluación registrada sin pérdidas.";
    }
}

// 3) Cargar automáticamente lotes de ECAS
$sql = "
  SELECT
    L.ID_Lote,
    L.Fecha AS Fecha,
    V.Nombre_Variedad,
    V.Codigo_Variedad,
    COALESCE(NULLIF(V.Color,''),'Sin datos') AS Color,
    COALESCE(S.Tuppers_Llenos,0)           AS Tuppers,
    COALESCE(S.Brotes_Disponibles,0)       AS Brotes,
    CASE
      WHEN EXISTS (
        SELECT 1
          FROM division_ecas D
          JOIN siembra_ecas S2 ON D.ID_Siembra = S2.ID_Siembra
         WHERE S2.ID_Lote = L.ID_Lote
      ) THEN 'División de brotes'
      ELSE 'Siembra de explantes'
    END                                    AS SubEtapa
  FROM lotes L
  JOIN siembra_ecas S ON S.ID_Lote    = L.ID_Lote
  JOIN variedades V   ON V.ID_Variedad = L.ID_Variedad
  WHERE L.ID_Etapa = 1
    AND COALESCE(S.Brotes_Disponibles,0) > 0
  ORDER BY L.Fecha DESC
";
$lotes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Evaluación de Lotes - ECAS</title>
  <link rel="stylesheet" href="../style.css?v=<?=time()?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#"><img src="../logoplantulas.png" width="130" height="124" alt="Logo"></a>
      <h2>Evaluación de Lotes - ECAS</h2>
      <div class="Opciones-barra"></div>
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
      <div class="alert alert-info"><?= $mensaje ?></div>
    <?php endif; ?>

    <?php if (count($lotes) > 0): ?>
      <!-- Selección de lote ECAS -->
      <div class="mb-3">
        <label>Lote ECAS:</label>
        <select id="id_lote" class="form-select">
          <option value="">-- Elige lote --</option>
          <?php foreach ($lotes as $l): ?>
            <option value="<?= $l['ID_Lote'] ?>"
                    data-fecha="<?= $l['Fecha'] ?>"
                    data-variedad="<?= htmlspecialchars($l['Nombre_Variedad']) ?>"
                    data-codigo="<?= htmlspecialchars($l['Codigo_Variedad']) ?>"
                    data-color="<?= htmlspecialchars($l['Color']) ?>"
                    data-tuppers="<?= $l['Tuppers'] ?>"
                    data-brotes="<?= $l['Brotes'] ?>"
                    data-subetapa="<?= $l['SubEtapa'] ?>">
              <?= "ID {$l['ID_Lote']} – {$l['Codigo_Variedad']} – {$l['Nombre_Variedad']}" ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Información del lote -->
      <div id="info-lote" style="display:none; margin-bottom:20px;">
        <p><strong>📋 Información del Lote Seleccionado:</strong></p>
        <p><strong>Fecha:</strong> <span id="fecha_lote"></span></p>
        <p><strong>Variedad:</strong> <span id="variedad_lote"></span></p>
        <p><strong>Código:</strong> <span id="codigo_var"></span></p>
        <p><strong>Color:</strong> <span id="color_var"></span></p>
        <p><strong>Sub-etapa:</strong> <span id="subetapa_lote"></span></p>
        <p><strong>Tuppers disponibles:</strong> <span id="tuppers_lote"></span></p>
        <p><strong>Brotes disponibles:</strong> <span id="brotes_lote"></span></p>
      </div>

      <!-- Formulario de evaluación -->
      <form method="POST" class="form-doble-columna">
        <input type="hidden" name="id_lote" id="input_id_lote">

        <div class="mb-3">
          <label>¿Contaminación Detectada?</label>
          <select name="contaminacion" class="form-select" id="contaminacion_sel" required>
            <option value="">Selecciona...</option>
            <option value="no">No</option>
            <option value="si">Sí</option>
          </select>
        </div>

        <div id="cont_campos" style="display:none;">
          <div class="mb-3">
            <label>Tuppers desechados:</label>
            <input type="number" name="tuppers_desechados" class="form-control" min="0">
          </div>
          <div class="mb-3">
            <label>Brotes desechados:</label>
            <input type="number" name="brotes_desechados" class="form-control" min="0">
          </div>
          <div class="mb-3">
            <label>Motivo:</label>
            <input type="text" name="motivo_desecho" class="form-control">
          </div>
        </div>

        <div class="mb-3">
          <label>Observaciones:</label>
          <textarea name="observaciones" class="form-control" rows="3"></textarea>
        </div>

        <button type="submit" name="guardar_evaluacion" class="btn btn-primary">
          Registrar Evaluación
        </button>
      </form>

    <?php else: ?>
      <div class="alert alert-warning">
        ⚠️ No hay lotes con inventario ECAS disponibles.
      </div>
    <?php endif; ?>
  </main>

  <footer class="text-center mt-5">&copy; 2025 PLANTAS AGRODEX</footer>
</div>

<script>
  document.getElementById('id_lote')?.addEventListener('change', function(){
    const opt = this.options[this.selectedIndex];
    if (!opt.value) {
      document.getElementById('info-lote').style.display = 'none';
      return;
    }
    document.getElementById('input_id_lote').value = opt.value;
    document.getElementById('fecha_lote').innerText = opt.dataset.fecha;
    document.getElementById('variedad_lote').innerText = opt.dataset.variedad;
    document.getElementById('codigo_var').innerText = opt.dataset.codigo;
    document.getElementById('color_var').innerText = opt.dataset.color;
    document.getElementById('subetapa_lote').innerText = opt.dataset.subetapa;
    document.getElementById('tuppers_lote').innerText = opt.dataset.tuppers;
    document.getElementById('brotes_lote').innerText = opt.dataset.brotes;
    document.getElementById('info-lote').style.display = 'block';
  });

  document.getElementById('contaminacion_sel')?.addEventListener('change', function(){
    document.getElementById('cont_campos').style.display =
      this.value === 'si' ? 'block' : 'none';
  });
</script>
</body>
</html>
