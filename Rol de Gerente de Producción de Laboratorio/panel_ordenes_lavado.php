<?php
include '../db.php';
session_start();

// Obtener órdenes
$ordenes_panel = $conn->query("
    SELECT ol.ID_Orden, v.Nombre_Variedad, v.Especie, ol.Fecha_Lavado, ol.Cantidad_Lavada, ol.Estado
    FROM orden_tuppers_lavado ol
    INNER JOIN lotes l ON ol.ID_Lote = l.ID_Lote
    INNER JOIN variedades v ON l.ID_Variedad = v.ID_Variedad
    ORDER BY ol.Fecha_Creacion DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel de Órdenes de Lavado</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
        </a>
        <div>
          <h2>📦 Panel de Órdenes de Lavado</h2>
          <p>Consulta y administra las órdenes enviadas a lavado de plantas.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_gpl.php'">🔄 Regresar</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="container mt-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-hover text-center">
              <thead class="table-light">
                <tr>
                  <th>ID Orden</th>
                  <th>Variedad</th>
                  <th>Especie</th>
                  <th>Fecha de Lavado</th>
                  <th>Cantidad de Tuppers</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($ordenes_panel->num_rows > 0): ?>
                  <?php while ($orden = $ordenes_panel->fetch_assoc()): ?>
                    <tr>
                      <td><?= $orden['ID_Orden'] ?></td>
                      <td><?= $orden['Nombre_Variedad'] ?></td>
                      <td><?= $orden['Especie'] ?></td>
                      <td><?= $orden['Fecha_Lavado'] ?></td>
                      <td><?= $orden['Cantidad_Lavada'] ?></td>
                      <td>
                        <?php if ($orden['Estado'] == 'Pendiente'): ?>
                          <span class="badge bg-warning text-dark">Pendiente</span>
                        <?php elseif ($orden['Estado'] == 'Asignado'): ?>
                          <span class="badge bg-info text-dark">Asignado</span>
                        <?php elseif ($orden['Estado'] == 'Completado'): ?>
                          <span class="badge bg-success">Completado</span>
                        <?php elseif ($orden['Estado'] == 'Caja Preparada'): ?>
                          <span class="badge bg-success">Caja Preparada</span>
                        <?php elseif ($orden['Estado'] == 'En Lavado'): ?>
                          <span class="badge bg-success">En Lavado</span>
                        <?php else: ?>
                          <span class="badge bg-secondary">Otro</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6">No hay órdenes registradas.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
