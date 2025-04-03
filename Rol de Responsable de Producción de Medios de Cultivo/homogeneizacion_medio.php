<?php
include '../db.php';
session_start();

// Procesamiento del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fecha = $_POST['fechaRegistro'];
    $hora = $_POST['horaRegistro'];
    $codigoMedio = $_POST['codigoMedio'];
    $cantidadCreada = $_POST['cantidadCreada'];
    $cantidadOcupada = $_POST['cantidadOcupada'];
    $observaciones = $_POST['observaciones'];
    $operador = $_SESSION['ID_Operador'] ?? null;

    // Buscar el ID_MedioNM disponible más reciente con ese código
    $stmt = $conn->prepare("SELECT ID_MedioNM FROM medios_nutritivos_madre 
                            WHERE Codigo_Medio = ? AND Cantidad_Disponible >= ? 
                            ORDER BY Fecha_Preparacion DESC LIMIT 1");
    $stmt->bind_param("sd", $codigoMedio, $cantidadOcupada);
    $stmt->execute();
    $stmt->bind_result($id_medio_nm);
    $stmt->fetch();
    $stmt->close();

    if ($id_medio_nm) {
        // Generar un ID para la dilución (por ejemplo DIL20250328-01)
        $id_dilucion = 'DIL' . date("YmdHis");

        // Insertar en la tabla dilucion_llenado_tuppers
        $insert = $conn->prepare("INSERT INTO dilucion_llenado_tuppers 
            (ID_Dilucion, ID_MedioNM, Fecha_Preparacion, Cantidad_MedioMadre, Volumen_Final, Tuppers_Llenos, Operador_Responsable) 
            VALUES (?, ?, ?, ?, ?, 0, ?)");
        $insert->bind_param("sisddi", $id_dilucion, $id_medio_nm, $fecha, $cantidadOcupada, $cantidadCreada, $operador);
        $insert->execute();

        // Actualizar la cantidad disponible del medio madre
        $update = $conn->prepare("UPDATE medios_nutritivos_madre 
                                  SET Cantidad_Disponible = Cantidad_Disponible - ? 
                                  WHERE ID_MedioNM = ?");
        $update->bind_param("di", $cantidadOcupada, $id_medio_nm);
        $update->execute();

        echo "<script>alert('Homogeneización registrada exitosamente.'); window.location.href='homogeneizacion.php';</script>";
        exit;
    } else {
        echo "<script>alert('No se encontró un medio madre disponible con ese código o cantidad insuficiente.');</script>";
    }
}

// Obtener historial
$historial = $conn->query("SELECT dl.Fecha_Preparacion, 
                                  mm.Codigo_Medio, 
                                  dl.Cantidad_MedioMadre, 
                                  dl.Volumen_Final 
                           FROM dilucion_llenado_tuppers dl
                           JOIN medios_nutritivos_madre mm ON dl.ID_MedioNM = mm.ID_MedioNM
                           ORDER BY dl.Fecha_Preparacion DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homogeneización del Medio</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
</head>
<body>
  <header>
    <h1>Homogeneización del Medio</h1>
    <p>Registra el proceso de homogeneización del medio nutritivo madre.</p>
    <button onclick="window.location.href='dashboard_medios.php'">🔙 Volver al Dashboard</button>
  </header>

  <main>
    <section class="form-container">
      <h2>Registrar Homogeneización</h2>
      <form method="POST">
        <div>
          <label for="fechaRegistro">Fecha:</label>
          <input type="date" id="fechaRegistro" name="fechaRegistro" required>
        </div>
        <div>
          <label for="horaRegistro">Hora:</label>
          <input type="time" id="horaRegistro" name="horaRegistro" required>
        </div>
        <div>
          <label for="codigoMedio">Código de Medio Nutritivo Madre:</label>
          <input type="text" id="codigoMedio" name="codigoMedio" placeholder="Ej. MS" required>
        </div>
        <div>
          <label for="cantidadCreada">Cantidad Creada (L):</label>
          <input type="number" step="0.01" id="cantidadCreada" name="cantidadCreada" placeholder="Ej. 50" required>
        </div>
        <div>
          <label for="cantidadOcupada">Cantidad Ocupada (L):</label>
          <input type="number" step="0.01" id="cantidadOcupada" name="cantidadOcupada" placeholder="Ej. 10" required>
        </div>
        <div>
          <label for="observaciones">Observaciones:</label>
          <input type="text" id="observaciones" name="observaciones" placeholder="Comentarios adicionales">
        </div>
        <button type="submit">Guardar Registro</button>
      </form>
    </section>

    <section>
      <h2>Historial de Homogeneización</h2>
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Código Medio</th>
            <th>Cantidad Ocupada (L)</th>
            <th>Volumen Final (L)</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($historial && $historial->num_rows > 0): ?>
            <?php while ($row = $historial->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['Fecha_Preparacion']) ?></td>
                <td><?= htmlspecialchars($row['Codigo_Medio']) ?></td>
                <td><?= htmlspecialchars($row['Cantidad_MedioMadre']) ?></td>
                <td><?= htmlspecialchars($row['Volumen_Final']) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="4">No hay registros disponibles.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>
</body>
</html>
