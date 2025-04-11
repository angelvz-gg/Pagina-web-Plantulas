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
              <button onclick="window.location.href='../Login/logout.php'">Cerrar Sesión</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
      <!-- 🔷 PLANIFICACIÓN -->
      <h3 style="margin-left: 2rem; margin-top: 1rem;">📋 Planificación</h3>
      <section class="dashboard-grid">
        <div class="card" id="card-planificar">
          <h2>📝 Crear Planificación</h2>
          <p>Planifica nuevas metas de producción.</p>
          <a href="planificacion_produccion.php" onclick="guardarScroll('card-planificar')">Planificar</a>
        </div>
        <div class="card" id="card-seguimiento">
          <h2>📊 Seguimiento de Planificaciones</h2>
          <p>Consulta el estado, responsables y avances.</p>
          <a href="seguimiento_planificacion.php" onclick="guardarScroll('card-seguimiento')">Ver seguimiento</a>
        </div>
        <div class="card" id="card-comparativa">
          <h2>📈 Producción vs Planificación</h2>
          <p>Compara lo producido con lo planificado.</p>
          <a href="comparar_produccion.php" onclick="guardarScroll('card-comparativa')">Ver comparativa</a>
        </div>
        <div class="card" id="card-auditoria">
          <h2>🕵️ Auditoría de Planificaciones</h2>
          <p>Consulta los cambios hechos a cada planificación.</p>
          <a href="auditoria_planificaciones.php" onclick="guardarScroll('card-auditoria')">Ver auditoría</a>
        </div>
      </section>

      <!-- 🔷 GESTIÓN OPERATIVA -->
      <h3 style="margin-left: 2rem; margin-top: 2rem;">🔧 Gestión Operativa</h3>
      <section class="dashboard-grid">
        <div class="card" id="card-vista-tuppers">
          <h2>📋 Vista General de Tuppers</h2>
          <p>Consulta todos los tuppers, sus estados y su trazabilidad completa.</p>
          <a href="vista_general_tuppers.php" onclick="guardarScroll('card-vista-tuppers')">Ir a Vista</a>
        </div>

        <div class="card" id="card-seleccion-tuppers">
          <h2>🔬 Selección de Tuppers</h2>
          <p>Coordina la selección de tuppers para lavado.</p>
          <a href="seleccion_tuppers.php" onclick="guardarScroll('card-seleccion-tuppers')">Gestionar selección</a>
        </div>

        <div class="card" id="card-ordenes-lavado">
          <h2>📦 Órdenes para organización del Lavado</h2>
          <p>Consulta y administra las órdenes enviadas para lavado de plantas.</p>
          <a href="panel_ordenes_lavado.php" onclick="guardarScroll('card-ordenes-lavado')">Revisar estado de órdenes</a>
        </div>

        <div class="card" id="card-distribucion">
          <h2>🗂 Distribución de Trabajo</h2>
          <p>Organiza el trabajo del personal.</p>
          <a href="distribucion_trabajo.php" onclick="guardarScroll('card-distribucion')">Organizar tareas</a>
        </div>

        <div class="card" id="card-ver-asignaciones">
          <h2>📝 Ver Asignaciones</h2>
          <p>Consulta asignaciones pasadas y activas.</p>
          <a href="verificar_asignaciones.php" onclick="guardarScroll('card-ver-asignaciones')">Ver asignaciones</a>
        </div>

        <div class="card" id="card-rendimiento">
          <h2>👷 Control de Rendimiento</h2>
          <p>Revisa la productividad de cada operario.</p>
          <a href="rendimiento_personal.php" onclick="guardarScroll('card-rendimiento')">Ver rendimiento</a>
        </div>
      </section>
    </main>

    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

  <script>
    // Guardar el ID de la tarjeta clickeada
    function guardarScroll(cardId) {
      localStorage.setItem('ultima_tarjeta_click', cardId);
    }

    // Al cargar la página, movernos a la última tarjeta
    document.addEventListener('DOMContentLoaded', function() {
      const cardId = localStorage.getItem('ultima_tarjeta_click');
      if (cardId) {
        const card = document.getElementById(cardId);
        if (card) {
          card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        localStorage.removeItem('ultima_tarjeta_click');
      }
    });
  </script>

</body>
</html>
