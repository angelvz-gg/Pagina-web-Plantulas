<?php
include '../db.php';
session_start();

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tipo = $_POST["tipo"];
    $id = intval($_POST["id"]);
    $accion = $_POST["accion"];
    $observacion = $_POST["observacion"] ?? null;
    $campos = isset($_POST["campos_rechazados"]) ? json_encode($_POST["campos_rechazados"]) : null;

    if ($tipo === "multiplicacion") {
        if ($accion === "verificar") {
            $stmt = $conn->prepare("UPDATE Multiplicacion SET Estado_Revision = 'Verificado' WHERE ID_Multiplicacion = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            // Obtener datos del reporte
            $sql_datos = "SELECT ID_Variedad, Operador_Responsable, Fecha_Siembra, Tuppers_Llenos FROM Multiplicacion WHERE ID_Multiplicacion = ?";
            $stmt_datos = $conn->prepare($sql_datos);
            $stmt_datos->bind_param("i", $id);
            $stmt_datos->execute();
            $datos = $stmt_datos->get_result()->fetch_assoc();

            if ($datos) {
                $hoy = date('Y-m-d'); // Fecha actual de validación
                $etapa = 2; // Multiplicación

                // Comprobar si ya existe lote
                $sql_check = "SELECT COUNT(*) AS existe FROM lotes WHERE Fecha = ? AND ID_Variedad = ? AND ID_Operador = ? AND ID_Etapa = ?";
                $check = $conn->prepare($sql_check);
                $check->bind_param("siii", $hoy, $datos['ID_Variedad'], $datos['Operador_Responsable'], $etapa);
                $check->execute();
                $existe = $check->get_result()->fetch_assoc();

                if ($existe['existe'] == 0 && $datos['Tuppers_Llenos'] > 0) {
                    $insert = $conn->prepare("INSERT INTO lotes (Fecha, ID_Variedad, ID_Operador, ID_Etapa) VALUES (?, ?, ?, ?)");
                    $insert->bind_param("siii", $hoy, $datos['ID_Variedad'], $datos['Operador_Responsable'], $etapa);
                    $insert->execute();

                    $id_lote_creado = $conn->insert_id;

                    // Relacionar reporte con ID_Lote
                    $update_lote = $conn->prepare("UPDATE Multiplicacion SET ID_Lote = ? WHERE ID_Multiplicacion = ?");
                    $update_lote->bind_param("ii", $id_lote_creado, $id);
                    $update_lote->execute();
                }
            }
        } else {
            $stmt = $conn->prepare("UPDATE Multiplicacion SET Estado_Revision = 'Rechazado', Observaciones_Revision = ?, Campos_Rechazados = ? WHERE ID_Multiplicacion = ?");
            $stmt->bind_param("ssi", $observacion, $campos, $id);
            $stmt->execute();
        }
    }

    if ($tipo === "enraizamiento") {
        if ($accion === "verificar") {
            $stmt = $conn->prepare("UPDATE Enraizamiento SET Estado_Revision = 'Verificado' WHERE ID_Enraizamiento = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            // Obtener datos del reporte
            $sql_datos = "SELECT ID_Variedad, Operador_Responsable, Fecha_Siembra, Tuppers_Llenos FROM Enraizamiento WHERE ID_Enraizamiento = ?";
            $stmt_datos = $conn->prepare($sql_datos);
            $stmt_datos->bind_param("i", $id);
            $stmt_datos->execute();
            $datos = $stmt_datos->get_result()->fetch_assoc();

            if ($datos) {
                $hoy = date('Y-m-d'); // Fecha actual de validación
                $etapa = 3; // Enraizamiento

                // Comprobar si ya existe lote
                $sql_check = "SELECT COUNT(*) AS existe FROM lotes WHERE Fecha = ? AND ID_Variedad = ? AND ID_Operador = ? AND ID_Etapa = ?";
                $check = $conn->prepare($sql_check);
                $check->bind_param("siii", $hoy, $datos['ID_Variedad'], $datos['Operador_Responsable'], $etapa);
                $check->execute();
                $existe = $check->get_result()->fetch_assoc();

                if ($existe['existe'] == 0 && $datos['Tuppers_Llenos'] > 0) {
                    $insert = $conn->prepare("INSERT INTO lotes (Fecha, ID_Variedad, ID_Operador, ID_Etapa) VALUES (?, ?, ?, ?)");
                    $insert->bind_param("siii", $hoy, $datos['ID_Variedad'], $datos['Operador_Responsable'], $etapa);
                    $insert->execute();

                    $id_lote_creado = $conn->insert_id;

                    // Relacionar reporte con ID_Lote
                    $update_lote = $conn->prepare("UPDATE Enraizamiento SET ID_Lote = ? WHERE ID_Enraizamiento = ?");
                    $update_lote->bind_param("ii", $id_lote_creado, $id);
                    $update_lote->execute();
                }
            }
        } else {
            $stmt = $conn->prepare("UPDATE Enraizamiento SET Estado_Revision = 'Rechazado', Observaciones_Revision = ?, Campos_Rechazados = ? WHERE ID_Enraizamiento = ?");
            $stmt->bind_param("ssi", $observacion, $campos, $id);
            $stmt->execute();
        }
    }

    echo "<script>window.location.href='reportes_produccion.php';</script>";
    exit();
}

// CONSULTAS para mostrar reportes pendientes
$sql_multiplicacion = "SELECT M.ID_Multiplicacion, V.Codigo_Variedad, V.Nombre_Variedad, M.Fecha_Siembra, M.Tasa_Multiplicacion,
           M.Cantidad_Dividida, M.Tuppers_Llenos, M.Tuppers_Desocupados, M.Estado_Revision,
           O.Nombre AS Nombre_Operador
    FROM Multiplicacion M
    LEFT JOIN Variedades V ON M.ID_Variedad = V.ID_Variedad
    LEFT JOIN Operadores O ON M.Operador_Responsable = O.ID_Operador
    WHERE M.Estado_Revision = 'Pendiente'";

$sql_enraizamiento = "SELECT E.ID_Enraizamiento, V.Codigo_Variedad, V.Nombre_Variedad, E.Fecha_Siembra, E.Tasa_Multiplicacion,
           E.Cantidad_Dividida, E.Tuppers_Llenos, E.Tuppers_Desocupados, E.Estado_Revision,
           O.Nombre AS Nombre_Operador
    FROM Enraizamiento E
    LEFT JOIN Variedades V ON E.ID_Variedad = V.ID_Variedad
    LEFT JOIN Operadores O ON E.Operador_Responsable = O.ID_Operador
    WHERE E.Estado_Revision = 'Pendiente'";

$result_multiplicacion = $conn->query($sql_multiplicacion);
$result_enraizamiento = $conn->query($sql_enraizamiento);
?>




<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Verificación de Reportes de Producción</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
      <h2>Verificación de Reportes de Producción</h2>
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

  <main>
    <div class="form-container">
      <div class="form-center">
        <h2>Reportes Pendientes de Verificación</h2>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Operador</th>
              <th>Variedad</th>
              <th>Cantidad</th>
              <th>Fecha de Siembra</th>
              <th>Tasa de Multiplicación</th>
              <th>Tuppers Llenos</th>
              <th>Tuppers Vacíos</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($row = $result_multiplicacion->fetch_assoc()): ?>
            <tr>
              <td><?= $row['Nombre_Operador'] ?></td>
              <td><?= $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad'] ?></td>
              <td><?= $row['Cantidad_Dividida'] ?></td>
              <td><?= $row['Fecha_Siembra'] ?></td>
              <td><?= $row['Tasa_Multiplicacion'] ?></td>
              <td><?= $row['Tuppers_Llenos'] ?></td>
              <td><?= $row['Tuppers_Desocupados'] ?></td>
              <td><?= $row['Estado_Revision'] ?></td>
              <td>
                <div class="botones-contenedor">
                  <form method="POST" class="form-boton">
                    <input type="hidden" name="tipo" value="multiplicacion">
                    <input type="hidden" name="id" value="<?= $row['ID_Multiplicacion'] ?>">
                    <input type="hidden" name="accion" value="verificar">
                    <button type="submit" class="save-button verificar">✔ Verificar</button>
                  </form>
                  <button type="button" class="save-button incorrecto" 
                          data-tipo="multiplicacion" 
                          data-id="<?= $row['ID_Multiplicacion'] ?>" 
                          onclick="mostrarRechazoModal(this)">✖ Incorrecto</button>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>

          <?php while ($row = $result_enraizamiento->fetch_assoc()): ?>
            <tr>
              <td><?= $row['Nombre_Operador'] ?></td>
              <td><?= $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad'] ?></td>
              <td><?= $row['Cantidad_Dividida'] ?></td>
              <td><?= $row['Fecha_Siembra'] ?></td>
              <td><?= $row['Tasa_Multiplicacion'] ?></td>
              <td><?= $row['Tuppers_Llenos'] ?></td>
              <td><?= $row['Tuppers_Desocupados'] ?></td>
              <td><?= $row['Estado_Revision'] ?></td>
              <td>
                <div class="botones-contenedor">
                  <form method="POST" class="form-boton">
                    <input type="hidden" name="tipo" value="enraizamiento">
                    <input type="hidden" name="id" value="<?= $row['ID_Enraizamiento'] ?>">
                    <input type="hidden" name="accion" value="verificar">
                    <button type="submit" class="save-button verificar">✔ Verificar</button>
                  </form>
                  <button type="button" class="save-button incorrecto" 
                          data-tipo="enraizamiento" 
                          data-id="<?= $row['ID_Enraizamiento'] ?>" 
                          onclick="mostrarRechazoModal(this)">✖ Incorrecto</button>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<!-- Modal para rechazo -->
<div class="modal fade" id="rechazoModal" tabindex="-1" aria-labelledby="rechazoModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="rechazoForm" onsubmit="return confirmarRechazo(this);">
        <div class="modal-header">
          <h5 class="modal-title" id="rechazoModalLabel">Rechazo de Reporte</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="tipo" id="rechazoTipo" value="">
          <input type="hidden" name="id" id="rechazoId" value="">
          <input type="hidden" name="accion" value="rechazar">
          <div class="mb-3">
            <label for="campos_rechazados_select" class="form-label">¿Qué es lo que se encuentra incorrecto?</label>
            <select name="campos_rechazados[]" id="campos_rechazados_select" class="form-control" multiple>
              <option value="Tasa_Multiplicacion">Tasa de multiplicación</option>
              <option value="Cantidad_Dividida">Cantidad dividida</option>
              <option value="Tuppers_Llenos">Tuppers llenos</option>
              <option value="Tuppers_Desocupados">Tuppers vacíos</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="observacion" class="form-label">Motivo del rechazo</label>
            <textarea name="observacion" id="observacion" class="form-control" placeholder="Motivo del rechazo" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Enviar rechazo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS Bundle (incluye Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
function mostrarRechazoModal(btn) {
  // Establecer los valores ocultos según el botón clickeado
  document.getElementById('rechazoTipo').value = btn.getAttribute('data-tipo');
  document.getElementById('rechazoId').value = btn.getAttribute('data-id');
  // Limpiar campos anteriores
  document.getElementById('observacion').value = "";
  let select = document.getElementById('campos_rechazados_select');
  for (let i = 0; i < select.options.length; i++) {
    select.options[i].selected = false;
  }
  // Mostrar el modal usando la API de Bootstrap
  var modalEl = document.getElementById('rechazoModal');
  var modal = new bootstrap.Modal(modalEl);
  modal.show();
}

function confirmarRechazo(form) {
  const motivo = form.querySelector("textarea[name='observacion']").value.trim();
  const camposSelect = form.querySelector("select[name='campos_rechazados[]']");
  
  if (!motivo) {
    alert("Debes ingresar una observación antes de rechazar.");
    return false;
  }
  
  if (camposSelect.selectedOptions.length === 0) {
    alert("Debes seleccionar al menos un campo que está incorrecto.");
    return false;
  }
  
  return true;
}
</script>
</body>
</html>
