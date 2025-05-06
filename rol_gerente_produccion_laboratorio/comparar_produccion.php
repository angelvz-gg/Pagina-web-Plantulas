<?php
session_start();
require '../db.php';

if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
  header("Location: ../login.php");
  exit();
}

$query = "
SELECT 
  p.ID_Planificacion,
  p.Fecha_Planificacion,
  v.Especie,
  v.Nombre_Variedad,
  p.Cantidad_Proyectada,
  
  COALESCE(m.Total_Multiplicacion, 0) +
  COALESCE(e.Total_Enraizamiento, 0) +
  COALESCE(d.Total_Division, 0) AS Total_Producido,

  ROUND((
    COALESCE(m.Total_Multiplicacion, 0) +
    COALESCE(e.Total_Enraizamiento, 0) +
    COALESCE(d.Total_Division, 0)
  ) / p.Cantidad_Proyectada * 100, 2) AS Porcentaje_Cumplido

FROM planificacion_Produccion p

JOIN variedades v ON p.ID_Variedad = v.ID_Variedad

-- Multiplicación
LEFT JOIN (
  SELECT ID_Variedad, SUM(Cantidad_Dividida) AS Total_Multiplicacion
  FROM multiplicacion
  GROUP BY ID_Variedad
) m ON m.ID_Variedad = p.ID_Variedad

-- Enraizamiento
LEFT JOIN (
  SELECT ID_Variedad, SUM(Cantidad_Dividida) AS Total_Enraizamiento
  FROM enraizamiento
  GROUP BY ID_Variedad
) e ON e.ID_Variedad = p.ID_Variedad

-- División ECAS + Siembra_ECAS
LEFT JOIN (
  SELECT se.ID_Variedad, SUM(de.Cantidad_Dividida) AS Total_Division
  FROM division_ecas de
  JOIN siembra_ecas se ON de.ID_Siembra = se.ID_Siembra
  GROUP BY se.ID_Variedad
) d ON d.ID_Variedad = p.ID_Variedad

ORDER BY p.Fecha_Planificacion DESC
";

$result = mysqli_query($conn, $query);
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Planificación de Producción</title>
  <link rel="stylesheet" href="../style.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124" />
        </a>
        <div>
          <h2>📋 Planificación de Producción</h2>
        </div>
      </div>
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_gpl.php'">🔙 REGRESAR</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="container mt-4">
      <?php if (!empty($mensaje)) : ?>
        <p style="text-align:center; color:<?= strpos($mensaje, '✅') !== false ? 'green' : 'red' ?>;">
          <?= $mensaje ?>
        </p>
      <?php endif; ?>

      <form method="POST" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Fecha de Planificación:</label>
          <input type="date" name="fecha_plan" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Variedad:</label>
          <select name="id_variedad" class="form-select" required>
            <option value="">-- Seleccionar variedad --</option>
            <?php while ($v = mysqli_fetch_assoc($variedades)) : ?>
              <option value="<?= $v['ID_Variedad'] ?>">
                <?= $v['Variedad'] ?> (<?= $v['Especie'] ?>)
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Cantidad a Producir:</label>
          <input type="number" name="cantidad" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Fecha Estimada de Siembra:</label>
          <input type="date" name="fecha_siembra" class="form-control">
        </div>

        <div class="col-md-6">
          <label class="form-label">Etapa Destino:</label>
          <select name="etapa" class="form-select" required>
            <option value="Multiplicación">Multiplicación</option>
            <option value="Enraizamiento">Enraizamiento</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Tasa de Multiplicación Promedio:</label>
          <input type="number" step="0.01" name="tasa" class="form-control">
        </div>

        <div class="col-md-6">
          <label class="form-label">Días entre Resiembra:</label>
          <input type="number" name="dias" class="form-control" value="30">
        </div>

        <?php
        function crearSelect($name, $label, $operadores) {
          echo "<div class='col-md-6'>";
          echo "<label class='form-label'>$label:</label>";
          echo "<select name='$name' class='form-select'>";
          echo "<option value=''>-- Seleccionar --</option>";
          mysqli_data_seek($operadores, 0);
          while ($op = mysqli_fetch_assoc($operadores)) {
            echo "<option value='{$op['ID_Operador']}'>{$op['Nombre']}</option>";
          }
          echo "</select></div>";
        }

        crearSelect('responsable_ejecucion', 'Responsable de Ejecución', $operadores);
        crearSelect('responsable_supervision', 'Responsable de Supervisión', $operadores);
        crearSelect('responsable_medio', 'Responsable de Medio Nutritivo', $operadores);
        crearSelect('responsable_acomodo', 'Responsable de Acomodo de Planta', $operadores);
        ?>

        <div class="col-12">
          <label class="form-label">Observaciones:</label>
          <textarea name="observaciones" class="form-control" rows="3"></textarea>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-success">Guardar Planificación</button>
        </div>
      </form>
    </main>

    <footer class="text-center mt-4 mb-3">
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>
</body>
</html>
