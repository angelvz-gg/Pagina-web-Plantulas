<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];
$mensaje = "";

// Registrar pérdida en perdidas_laboratorio
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_evaluacion"])) {
    $id_lote = $_POST["id_lote"];
    $contaminacion = $_POST["contaminacion"] ?? 'no';
    $fecha_perdida = date("Y-m-d");
    $tuppers_perdidos = $_POST["tuppers_desechados"] ?? 0;
    $brotes_perdidos = $_POST["brotes_desechados"] ?? 0;
    $motivo = $_POST["motivo_desecho"] ?? '';
    $observaciones = $_POST["observaciones"] ?? '';

    if ($contaminacion === "si" && ($tuppers_perdidos > 0 || $brotes_perdidos > 0)) {
        $sql = "INSERT INTO perdidas_laboratorio 
                (ID_Entidad, Tipo_Entidad, Fecha_Perdida, Cantidad_Perdida, Tuppers_Perdidos, Brotes_Perdidos, Motivo, Operador_Entidad, Operador_Chequeo)
                VALUES (?, 'lotes', ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $cantidad_total = $tuppers_perdidos + $brotes_perdidos;
        $stmt->bind_param("isiiisii", $id_lote, $fecha_perdida, $cantidad_total, $tuppers_perdidos, $brotes_perdidos, $motivo, $ID_Operador, $ID_Operador);

        if ($stmt->execute()) {
            $mensaje = "✅ Pérdida parcial registrada correctamente.";
        } else {
            $mensaje = "❌ Error al registrar la pérdida: " . $stmt->error;
        }
    } else {
        $mensaje = "✅ Evaluación registrada sin pérdidas.";
    }
}

// Obtener lotes y calcular tuppers y brotes descontando pérdidas
$lotes = [];
$sql_lotes = "SELECT L.ID_Lote, L.Fecha, V.Nombre_Variedad, V.Codigo_Variedad
              FROM lotes L
              JOIN variedades V ON L.ID_Variedad = V.ID_Variedad
              ORDER BY L.Fecha DESC";
$res_lotes = $conn->query($sql_lotes);

if ($res_lotes) {
    while ($l = $res_lotes->fetch_assoc()) {
        $id_lote = $l['ID_Lote'];
        $total_tuppers = 0;
        $total_brotes = 0;

        // siembra_ecas
        $stmt = $conn->prepare("SELECT COUNT(*) AS tupper_count, SUM(Cantidad_Sembrada) AS brote_sum FROM siembra_ecas WHERE ID_Lote = ?");
        $stmt->bind_param("i", $id_lote);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $total_tuppers += $res['tupper_count'] ?? 0;
        $total_brotes += $res['brote_sum'] ?? 0;

        // division_ecas
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS tupper_count, SUM(D.Cantidad_Dividida) AS brote_sum
            FROM division_ecas D
            INNER JOIN siembra_ecas S ON D.ID_Siembra = S.ID_Siembra
            WHERE S.ID_Lote = ?
        ");
        $stmt->bind_param("i", $id_lote);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $total_tuppers += $res['tupper_count'] ?? 0;
        $total_brotes += $res['brote_sum'] ?? 0;

        // multiplicacion
        $stmt = $conn->prepare("
            SELECT SUM(Tuppers_Llenos) AS tupper_count, SUM(Cantidad_Dividida) AS brote_sum 
            FROM multiplicacion 
            WHERE ID_Lote = ?
        ");
        $stmt->bind_param("i", $id_lote);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $total_tuppers += $res['tupper_count'] ?? 0;
        $total_brotes += $res['brote_sum'] ?? 0;

        // enraizamiento
        $stmt = $conn->prepare("
            SELECT SUM(Tuppers_Llenos) AS tupper_count, SUM(Cantidad_Dividida) AS brote_sum 
            FROM enraizamiento 
            WHERE ID_Lote = ?
        ");
        $stmt->bind_param("i", $id_lote);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $total_tuppers += $res['tupper_count'] ?? 0;
        $total_brotes += $res['brote_sum'] ?? 0;

        // 🔥 Descontar pérdidas registradas
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(Tuppers_Perdidos), 0) AS tupper_perdidos,
                   COALESCE(SUM(Brotes_Perdidos), 0) AS brote_perdidos
            FROM perdidas_laboratorio
            WHERE ID_Entidad = ?
              AND Tipo_Entidad = 'lotes'
        ");
        $stmt->bind_param("i", $id_lote);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $tuppers_perdidos = $res['tupper_perdidos'] ?? 0;
        $brotes_perdidos = $res['brote_perdidos'] ?? 0;

        $total_tuppers = max(0, $total_tuppers - $tuppers_perdidos);
        $total_brotes = max(0, $total_brotes - $brotes_perdidos);

        // 🔥 Determinar en qué etapa está el lote
        $etapa = "Sin producción registrada";

        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM enraizamiento WHERE ID_Lote = ?");
        $stmt->bind_param("i", $id_lote);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (($res['total'] ?? 0) > 0) {
            $etapa = "Enraizamiento";
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM multiplicacion WHERE ID_Lote = ?");
            $stmt->bind_param("i", $id_lote);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if (($res['total'] ?? 0) > 0) {
                $etapa = "Multiplicación";
            } else {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) AS total
                    FROM division_ecas D
                    INNER JOIN siembra_ecas S ON D.ID_Siembra = S.ID_Siembra
                    WHERE S.ID_Lote = ?
                ");
                $stmt->bind_param("i", $id_lote);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                if (($res['total'] ?? 0) > 0) {
                    $etapa = "División de Brotes";
                } else {
                    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM siembra_ecas WHERE ID_Lote = ?");
                    $stmt->bind_param("i", $id_lote);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    if (($res['total'] ?? 0) > 0) {
                        $etapa = "Siembra de Explantes";
                    }
                }
            }
        }

        $l['Total_Tuppers'] = $total_tuppers;
        $l['Total_Brotes'] = $total_brotes;
        $l['Etapa'] = $etapa;
        $lotes[] = $l;
    }
}
?>



<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Evaluación de Lotes - ECAS</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
      <h2>Evaluación de Lotes - ECAS</h2>
      <div></div>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_egp.php'" >🏠 Volver al inicio</button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main class="container mt-4">
    <?php if ($mensaje): ?>
      <div class="alert alert-info"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="POST" class="form-doble-columna">
      <div class="mb-3">
        <label>Lote a Evaluar:</label>
        <select name="id_lote" class="form-select" id="id_lote_select" required>
          <option value="">Selecciona un lote</option>
          <?php foreach ($lotes as $l): ?>
            <option value="<?= $l['ID_Lote'] ?>"
                    data-variedad="<?= $l['Nombre_Variedad'] ?>"
                    data-codigo="<?= $l['Codigo_Variedad'] ?>"
                    data-fecha="<?= $l['Fecha'] ?>"
                    data-tuppers="<?= $l['Total_Tuppers'] ?>"
                    data-brotes="<?= $l['Total_Brotes'] ?>"
                    data-etapa="<?= $l['Etapa'] ?>">
              <?= "ID Lote: {$l['ID_Lote']} - {$l['Codigo_Variedad']} - {$l['Nombre_Variedad']}" ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="info-lote" style="margin-bottom:20px;">
        <p><strong>📋 Información del Lote Seleccionado:</strong></p>
        <p><strong>Fecha:</strong> <span id="fecha_lote"></span></p>
        <p><strong>Variedad:</strong> <span id="variedad_lote"></span></p>
        <p><strong>Código Variedad:</strong> <span id="codigo_variedad"></span></p>
        <p><strong>Tuppers:</strong> <span id="tuppers_lote"></span></p>
        <p><strong>Brotes:</strong> <span id="brotes_lote"></span></p>
        <p><strong>Etapa Actual:</strong> <span id="etapa_lote"></span></p>
      </div>

      <div class="mb-3">
        <label>¿Contaminación Detectada?</label>
        <select name="contaminacion" class="form-select" id="contaminacion_select" required>
          <option value="">Selecciona...</option>
          <option value="no">No</option>
          <option value="si">Sí</option>
        </select>
      </div>

      <div id="contaminacion-campos" style="display:none;">
        <div class="mb-3">
          <label>Número de Tuppers Desechados:</label>
          <input type="number" name="tuppers_desechados" class="form-control" min="0">
        </div>

        <div class="mb-3">
          <label>Número de Brotes Desechados:</label>
          <input type="number" name="brotes_desechados" class="form-control" min="0">
        </div>

        <div class="mb-3">
          <label>Motivo del Desecho:</label>
          <input type="text" name="motivo_desecho" class="form-control" maxlength="100">
        </div>
      </div>

      <div class="mb-3">
        <label>Observaciones generales:</label>
        <textarea name="observaciones" class="form-control" rows="3"></textarea>
      </div>

      <button type="submit" name="guardar_evaluacion" class="btn btn-primary">Registrar Evaluación</button>
    </form>
  </main>

  <footer class="text-center mt-5">
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<script>
// Mostrar datos del lote al seleccionar
document.getElementById('id_lote_select')?.addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  document.getElementById('fecha_lote').innerText = opt.getAttribute("data-fecha") || '';
  document.getElementById('variedad_lote').innerText = opt.getAttribute("data-variedad") || '';
  document.getElementById('codigo_variedad').innerText = opt.getAttribute("data-codigo") || '';
  document.getElementById('tuppers_lote').innerText = opt.getAttribute("data-tuppers") || '0';
  document.getElementById('brotes_lote').innerText = opt.getAttribute("data-brotes") || '0';
  document.getElementById('etapa_lote').innerText = opt.getAttribute("data-etapa") || '';
});

// Mostrar campos si hay contaminación
document.getElementById('contaminacion_select')?.addEventListener('change', function () {
  document.getElementById('contaminacion-campos').style.display = (this.value === 'si') ? 'block' : 'none';
});
</script>
</body>
</html>
