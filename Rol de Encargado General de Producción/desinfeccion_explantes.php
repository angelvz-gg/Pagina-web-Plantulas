<?php
include '../db.php';
session_start();

// Autocompletado AJAX para buscar variedades por Código o Nombre
if (isset($_GET['action']) && $_GET['action'] === 'buscar_variedad') {
    $term = $_GET['term'] ?? '';
    $sql = "SELECT ID_Variedad, Codigo_Variedad, Nombre_Variedad FROM Variedades 
            WHERE Codigo_Variedad LIKE ? OR Nombre_Variedad LIKE ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    $like = "%$term%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $sugerencias = [];
    while ($row = $result->fetch_assoc()) {
        $sugerencias[] = [
            'id' => $row['ID_Variedad'],
            'label' => $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad'],
            'value' => $row['Codigo_Variedad'] . " - " . $row['Nombre_Variedad']
        ];
    }
    echo json_encode($sugerencias);
    exit;
}

if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='../login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];
$mensaje = "";

// Verificar si hay desinfección activa
$sql_activa = "SELECT * FROM desinfeccion_explantes 
               WHERE Operador_Responsable = ? AND Estado_Desinfeccion = 'En proceso' 
               ORDER BY FechaHr_Desinfeccion DESC LIMIT 1";
$stmt_activa = $conn->prepare($sql_activa);
$stmt_activa->bind_param("i", $ID_Operador);
$stmt_activa->execute();
$desinfeccion_activa = $stmt_activa->get_result()->fetch_assoc();

// Obtener información de la variedad activa
$info_variedad = null;
if ($desinfeccion_activa) {
    $id_variedad_activa = $desinfeccion_activa['ID_Variedad'];
    $sql_var = "SELECT Codigo_Variedad, Nombre_Variedad FROM Variedades WHERE ID_Variedad = ?";
    $stmt_var = $conn->prepare($sql_var);
    $stmt_var->bind_param("i", $id_variedad_activa);
    $stmt_var->execute();
    $info_variedad = $stmt_var->get_result()->fetch_assoc();
}

// Iniciar desinfección
if (isset($_POST["iniciar"])) {
    $id_variedad = $_POST["id_variedad"];
    $explantes_iniciales = $_POST["explantes_iniciales"];
    $fecha_inicio = $_POST["fecha_inicio"];

    $sql_check = "SELECT COUNT(*) AS existe FROM Variedades WHERE ID_Variedad = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_variedad);
    $stmt_check->execute();
    $resultado_check = $stmt_check->get_result()->fetch_assoc();

    if ($resultado_check['existe'] == 0) {
        $mensaje = "❌ Error: La variedad seleccionada no existe.";
    } else {
        $sql_insert = "INSERT INTO desinfeccion_explantes 
                       (ID_Variedad, Explantes_Iniciales, FechaHr_Desinfeccion, Estado_Desinfeccion, Operador_Responsable)
                       VALUES (?, ?, ?, 'En proceso', ?)";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("iisi", $id_variedad, $explantes_iniciales, $fecha_inicio, $ID_Operador);

        if ($stmt->execute()) {
            header("Location: desinfeccion_explantes.php");
            exit();
        } else {
            $mensaje = "❌ Error al iniciar la desinfección.";
        }
    }
}

// Finalizar desinfección
if (isset($_POST["finalizar"])) {
    $id = $_POST["id_desinfeccion"];
    $desinfectados = $_POST["explantes_desinfectados"];
    $fecha_fin = $_POST["fecha_fin"];
    $estado_final = $_POST["estado_final"];

    $sql_finalizar = "UPDATE desinfeccion_explantes 
                      SET Explantes_Desinfectados = ?, HrFn_Desinfeccion = ?, Estado_Desinfeccion = ? 
                      WHERE ID_Desinfeccion = ? AND Operador_Responsable = ?";
    $stmt = $conn->prepare($sql_finalizar);
    $stmt->bind_param("sssii", $desinfectados, $fecha_fin, $estado_final, $id, $ID_Operador);

    if ($stmt->execute()) {
        header("Location: desinfeccion_explantes.php");
        exit();
    } else {
        $mensaje = "❌ Error al finalizar la desinfección.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Desinfección de Explantes</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" />
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand"><img src="../logoplantulas.png" alt="Logo" width="130" height="124" /></a>
      <h2>Registro de Desinfección</h2>
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

  <main>
    <?php if ($mensaje): ?>
      <div class="alert alert-info"><?= $mensaje ?></div>
    <?php endif; ?>

    <h3>🧪 Iniciar nueva desinfección</h3>
    <form method="POST" class="form-doble-columna">
      <div class="content">
        <div class="section">
          <label for="nombre_variedad">Buscar Variedad:</label>
          <input type="text" id="nombre_variedad" name="nombre_variedad" required>
          <input type="hidden" id="id_variedad" name="id_variedad">

          <label for="explantes_iniciales">Explantes Iniciales:</label>
          <input type="number" name="explantes_iniciales" required min="1" />

          <label for="fecha_inicio">Fecha y hora de inicio:</label>
          <input type="datetime-local" name="fecha_inicio" required />

          <button type="submit" name="iniciar" class="btn-inicio">Iniciar Desinfección</button>
        </div>
      </div>
    </form>

    <?php if ($desinfeccion_activa): ?>
      <h3>✅ Finalizar desinfección activa</h3>
      <form method="POST" class="form-doble-columna">
        <input type="hidden" name="id_desinfeccion" value="<?= $desinfeccion_activa['ID_Desinfeccion'] ?>">
        <div class="content">
          <div class="section">
            <p><strong>Variedad:</strong> <?= $info_variedad['Codigo_Variedad'] . " - " . $info_variedad['Nombre_Variedad'] ?> (ID: <?= $desinfeccion_activa['ID_Variedad'] ?>)</p>
            <p><strong>Explantes Iniciales:</strong> <?= $desinfeccion_activa['Explantes_Iniciales'] ?></p>
            <p><strong>Fecha de inicio:</strong> <?= $desinfeccion_activa['FechaHr_Desinfeccion'] ?></p>

            <label for="explantes_desinfectados">Explantes Desinfectados:</label>
            <input type="number" name="explantes_desinfectados" required min="1" />

            <label for="fecha_fin">Fecha y hora de finalización:</label>
            <input type="datetime-local" name="fecha_fin" required />

            <label for="estado_final">Estado final:</label>
            <select name="estado_final" required>
              <option value="">-- Selecciona --</option>
              <option value="Completado">✅ Completado</option>
              <option value="Fallido">❌ Fallido</option>
            </select>

            <button type="submit" name="finalizar" class="btn-final">Finalizar Desinfección</button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </main>

  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function() {
  $("#nombre_variedad").autocomplete({
    source: "desinfeccion_explantes.php?action=buscar_variedad",
    minLength: 2,
    select: function(event, ui) {
      $("#id_variedad").val(ui.item.id);
    }
  });
});
</script>
</body>
</html>
