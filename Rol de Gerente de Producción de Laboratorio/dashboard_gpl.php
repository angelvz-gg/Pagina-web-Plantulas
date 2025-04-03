<?php
session_start();
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 6) {
  header("Location: ../login.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Gerente de Producción de Laboratorio</title>
  <link rel="stylesheet" href="../style.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
</head>
<body>
  <div class="contenedor-pagina">
    <header>
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <img src="../logoplantulas.png" alt="Logo" width="130" height="124" />
        </a>
        <div>
          <h2>Gerente de Producción de Laboratorio</h2>
          <p>Resumen de tus actividades y accesos rápidos.</p>
        </div>
      </div>

      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='../Login/logout.php'" class="btn btn-danger">Cerrar Sesión</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
      <!-- 🔷 PLANIFICACIÓN -->
      <h3 style="margin-left: 2rem; margin-top: 1rem;">📋 Planificación</h3>
      <section class="dashboard-grid">
        <div class="card">
          <h2>📝 Crear Planificación</h2>
          <p>Planifica nuevas metas de producción.</p>
          <a href="planificacion_produccion.php">Planificar</a>
        </div>
        <div class="card">
          <h2>📊 Seguimiento de Planificaciones</h2>
          <p>Consulta el estado, responsables y avances.</p>
          <a href="seguimiento_planificacion.php">Ver seguimiento</a>
        </div>
        <div class="card">
          <h2>📈 Producción vs Planificación</h2>
          <p>Compara lo producido con lo planificado.</p>
          <a href="comparar_produccion.php">Ver comparativa</a>
        </div>
        <div class="card">
          <h2>🕵️ Auditoría de Planificaciones</h2>
          <p>Consulta los cambios hechos a cada planificación.</p>
          <a href="auditoria_planificaciones.php">Ver auditoría</a>
        </div>
      </section>

      <!-- 🔷 GESTIÓN OPERATIVA -->
      <h3 style="margin-left: 2rem; margin-top: 2rem;">🔧 Gestión Operativa</h3>
      <section class="dashboard-grid">
        <div class="card">
          <h2>🔬 Selección de Tuppers</h2>
          <p>Coordina la selección de tuppers para lavado.</p>
          <a href="seleccion_tuppers.php">Gestionar selección</a>
        </div>
        <div class="card">
          <h2>🗂 Distribución de Trabajo</h2>
          <p>Organiza el trabajo del personal.</p>
          <a href="distribucion_trabajo.php">Organizar tareas</a>
        </div>
        <div class="card">
          <h2>📝 Ver Asignaciones</h2>
          <p>Consulta asignaciones pasadas y activas.</p>
          <a href="verificar_asignaciones.php">Ver asignaciones</a>
        </div>
        <div class="card">
          <h2>👷 Control de Rendimiento</h2>
          <p>Revisa la productividad de cada operario.</p>
          <a href="rendimiento_personal.php">Ver rendimiento</a>
        </div>
        <div class="card">
          <h2>⚠️ Alertas de Producción</h2>
          <p>Consulta atrasos, pérdidas y riesgos críticos.</p>
          <a href="alertas_produccion.php">Ver alertas</a>
        </div>
      </section>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
