<?php
include '../db.php';
session_start();

if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 7) {
  header("Location: ../login.php");
  exit();
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_operador = $_SESSION['ID_Operador'];

  if (isset($_POST['guardar_diario'])) {
    $litros = $_POST['litros'];
    $ppm = $_POST['ppm'];
    $obs = $_POST['observaciones_diario'];
    $stmt = $conn->prepare("INSERT INTO osmosis_chequeo_diario (FechaHora, Litros_Tratados, PPM, Observaciones, ID_Operador) VALUES (NOW(), ?, ?, ?, ?)");
    $stmt->bind_param("ddsi", $litros, $ppm, $obs, $id_operador);
    $mensaje = $stmt->execute() ? "✅ Registro diario guardado correctamente." : "❌ Error al guardar el registro diario.";
  }

  if (isset($_POST['guardar_retrolavado'])) {
    $sal = $_POST['sal'];
    $nivel = $_POST['nivel'];
    $obs = $_POST['observaciones_retrolavado'];
    $stmt = $conn->prepare("INSERT INTO osmosis_retrolavado (FechaHora, Sal_Utilizada_Kg, Nivel_Agua_Porc, Observaciones, ID_Operador) VALUES (NOW(), ?, ?, ?, ?)");
    $stmt->bind_param("ddsi", $sal, $nivel, $obs, $id_operador);
    $mensaje = $stmt->execute() ? "✅ Retrolavado registrado correctamente." : "❌ Error al guardar el retrolavado.";
  }

  if (isset($_POST['guardar_mantenimiento'])) {
    $empresa = $_POST['empresa'];
    $lavados = isset($_POST['filtros_lavados']) ? 1 : 0;
    $reemplazados = isset($_POST['filtros_reemplazados']) ? 1 : 0;
    $obs = $_POST['observaciones_mantenimiento'];
    $stmt = $conn->prepare("INSERT INTO osmosis_mantenimiento (FechaHora, Empresa_Responsable, Filtros_Lavados, Filtros_Reemplazados, Observaciones, ID_Operador) VALUES (NOW(), ?, ?, ?, ?, ?)");
    $stmt->bind_param("sbbsi", $empresa, $lavados, $reemplazados, $obs, $id_operador);
    $mensaje = $stmt->execute() ? "✅ Mantenimiento registrado correctamente." : "❌ Error al guardar el mantenimiento.";
  }
}

function mostrarTabla($query, $encabezados, $campos) {
  global $conn;
  $result = $conn->query($query);

  if ($result->num_rows > 0) {
    echo "<div class='table-responsive'><table class='table table-bordered table-striped'>";
    echo "<thead><tr>";
    foreach ($encabezados as $encabezado) {
      echo "<th>{$encabezado}</th>";
    }
    echo "</tr></thead><tbody>";

    while ($row = $result->fetch_assoc()) {
      echo "<tr>";
      foreach ($campos as $campo) {
        echo "<td>" . (!empty($row[$campo]) ? htmlspecialchars($row[$campo]) : 'Sin información') . "</td>";
      }
      echo "</tr>";
    }

    echo "</tbody></table></div>";
  } else {
    echo "<p class='text-muted'>No hay registros disponibles.</p>";
  }
}

$tipo_historial = $_GET['tipo'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chequeo de Ósmosis Inversa</title>
  <link rel="stylesheet" href="../style.css?v=<?=time();?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
      </a>
      <div>
        <h2>Chequeo de Ósmosis Inversa</h2>
        <p>Registro de producción diaria, retrolavado y mantenimiento del sistema.</p>
      </div>
    </div>
    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_rpmc.php'">🔄 Regresar</button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main class="container py-4">
    <?php if (!empty($mensaje)): ?>
      <div class="alert alert-info text-center" role="alert">
        <?= $mensaje ?>
      </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="osmosisTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="diario-tab" data-bs-toggle="tab" data-bs-target="#diario" type="button" role="tab">Chequeo Diario</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="retrolavado-tab" data-bs-toggle="tab" data-bs-target="#retrolavado" type="button" role="tab">Retrolavado</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="mantenimiento-tab" data-bs-toggle="tab" data-bs-target="#mantenimiento" type="button" role="tab">Mantenimiento</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button" role="tab">Historial</button>
      </li>
    </ul>

    <div class="tab-content" id="osmosisTabContent">
      <div class="tab-pane fade show active" id="diario" role="tabpanel">
        <form method="POST" class="row g-3">
          <div class="col-md-6">
            <label for="litros" class="form-label">Litros Tratados</label>
            <input type="number" class="form-control" id="litros" name="litros" required>
          </div>
          <div class="col-md-6">
            <label for="ppm" class="form-label">PPM</label>
            <input type="number" class="form-control" id="ppm" name="ppm" required>
          </div>
          <div class="col-12">
            <label for="observaciones_diario" class="form-label">Observaciones</label>
            <textarea class="form-control" id="observaciones_diario" name="observaciones_diario" rows="2"></textarea>
          </div>
          <div class="col-12 text-end">
            <button type="submit" name="guardar_diario" class="btn btn-success">Guardar Registro Diario</button>
          </div>
        </form>
      </div>

      <div class="tab-pane fade" id="retrolavado" role="tabpanel">
        <form method="POST" class="row g-3">
          <div class="col-md-6">
            <label for="sal" class="form-label">Sal Utilizada (kg)</label>
            <input type="number" step="0.1" class="form-control" id="sal" name="sal" required>
          </div>
          <div class="col-md-6">
            <label for="nivel" class="form-label">Nivel de Agua Estimado (%)</label>
            <input type="number" min="0" max="100" class="form-control" id="nivel" name="nivel" required>
          </div>
          <div class="col-12">
            <label for="observaciones_retrolavado" class="form-label">Observaciones</label>
            <textarea class="form-control" id="observaciones_retrolavado" name="observaciones_retrolavado" rows="2"></textarea>
          </div>
          <div class="col-12 text-end">
            <button type="submit" name="guardar_retrolavado" class="btn btn-success">Guardar Retrolavado</button>
          </div>
        </form>
      </div>

      <div class="tab-pane fade" id="mantenimiento" role="tabpanel">
        <form method="POST" class="row g-3">
          <div class="col-md-6">
            <label for="empresa" class="form-label">Empresa Responsable</label>
            <input type="text" class="form-control" id="empresa" name="empresa">
          </div>
          <div class="col-md-6">
            <label class="form-label d-block">Filtros</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="lavados" name="filtros_lavados">
              <label class="form-check-label" for="lavados">Lavados</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="reemplazados" name="filtros_reemplazados">
              <label class="form-check-label" for="reemplazados">Reemplazados</label>
            </div>
          </div>
          <div class="col-12">
            <label for="observaciones_mantenimiento" class="form-label">Observaciones</label>
            <textarea class="form-control" id="observaciones_mantenimiento" name="observaciones_mantenimiento" rows="2"></textarea>
          </div>
          <div class="col-12 text-end">
            <button type="submit" name="guardar_mantenimiento" class="btn btn-success">Guardar Mantenimiento</button>
          </div>
        </form>
      </div>

      <div class="tab-pane fade" id="historial" role="tabpanel">
        <form method="GET" class="mb-3">
        <div class="row g-3 mb-3">
  <div class="col-md-8">
    <select id="selectorHistorial" class="form-select">
      <option disabled selected>-- Selecciona un historial --</option>
      <option value="diario" <?= ($tipo_historial === 'diario') ? 'selected' : '' ?>>Chequeo Diario</option>
      <option value="retrolavado" <?= ($tipo_historial === 'retrolavado') ? 'selected' : '' ?>>Retrolavado</option>
      <option value="mantenimiento" <?= ($tipo_historial === 'mantenimiento') ? 'selected' : '' ?>>Mantenimiento</option>
    </select>
  </div>
</div>


        <?php
        if ($tipo_historial === 'diario') {
          mostrarTabla("SELECT FechaHora, Litros_Tratados, PPM, Observaciones FROM osmosis_chequeo_diario ORDER BY FechaHora DESC", ['Fecha y Hora', 'Litros Tratados', 'PPM', 'Observaciones'], ['FechaHora', 'Litros_Tratados', 'PPM', 'Observaciones']);
        } elseif ($tipo_historial === 'retrolavado') {
          mostrarTabla("SELECT FechaHora, Sal_Utilizada_Kg, Nivel_Agua_Porc, Observaciones FROM osmosis_retrolavado ORDER BY FechaHora DESC", ['Fecha y Hora', 'Sal (kg)', 'Nivel de Agua (%)', 'Observaciones'], ['FechaHora', 'Sal_Utilizada_Kg', 'Nivel_Agua_Porc', 'Observaciones']);
        } elseif ($tipo_historial === 'mantenimiento') {
          mostrarTabla("SELECT FechaHora, Empresa_Responsable, Filtros_Lavados, Filtros_Reemplazados, Observaciones FROM osmosis_mantenimiento ORDER BY FechaHora DESC", ['Fecha y Hora', 'Empresa', 'Lavados', 'Reemplazados', 'Observaciones'], ['FechaHora', 'Empresa_Responsable', 'Filtros_Lavados', 'Filtros_Reemplazados', 'Observaciones']);
        }
        ?>
      </div>
    </div>
  </main>

  <footer class="text-center p-3">
    &copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    const select = document.getElementById("selectorHistorial");
    if (select) {
      select.addEventListener("change", () => {
        const tipo = select.value;
        const url = new URL(window.location.href);
        url.searchParams.set("tipo", tipo);
        history.replaceState(null, "", url.toString());
        location.reload();
      });
    }

    // Activar pestaña de historial si viene con ?tipo=
    const tipo = new URLSearchParams(window.location.search).get("tipo");
    if (tipo) {
      const historialTab = new bootstrap.Tab(document.querySelector('#historial-tab'));
      historialTab.show();
    }
  });
</script>


</body>
</html>