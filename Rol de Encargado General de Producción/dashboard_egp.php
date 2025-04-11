<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel Encargado General de Producción</title>
  <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
      </a>
      <div>
        <h2>Encargado General de Producción</h2>
        <p>Panel de gestión y supervisión</p>
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

  <!-- Contenido principal -->
  <main>

    <h3 class="mt-5 mb-3">🌱 Producción - Etapa 1 (ECAS)</h3>
    <section class="dashboard-grid">

      <div class="card card-ecas" id="card-desinfeccion">
        <h2>🧼 Desinfección de Explantes</h2>
        <p>Preparación inicial de explantes para el cultivo.</p>
        <a href="desinfeccion_explantes.php" onclick="guardarScroll('card-desinfeccion')">Ir a Desinfección</a>
      </div>

      <div class="card" id="card-historial-desinfeccion">
        <h2>📄 Historial de Desinfección</h2>
        <p>Consulta todas las desinfecciones registradas por los operadores.</p>
        <a href="historial_desinfeccion_explantes.php" onclick="guardarScroll('card-historial-desinfeccion')">Ver Historial</a>
      </div>

      <div class="card card-ecas" id="card-siembra-inicial">
        <h2>📋 Registro de Siembra Inicial</h2>
        <p>Captura la siembra inicial de explantes tras la desinfección.</p>
        <a href="registro_siembra_ecas.php" onclick="guardarScroll('card-siembra-inicial')">Registrar Siembra</a>
      </div>

      <div class="card card-ecas" id="card-divisiones">
        <h2>✂️ Divisiones de Explantes</h2>
        <p>Registra las divisiones hechas en ECAS y su generación correspondiente.</p>
        <a href="divisiones_ecas.php" onclick="guardarScroll('card-divisiones')">Registrar División</a>
      </div>

      <div class="card card-ecas" id="card-evaluacion">
        <h2>🧪 Evaluación de Desarrollo</h2>
        <p>Clasifica los explantes: vivos, hinchados, con brote, infectados o muertos.</p>
        <a href="evaluacion_ecas.php" onclick="guardarScroll('card-evaluacion')">Evaluar Desarrollo</a>
      </div>

      <div class="card card-ecas" id="card-diseccion">
        <h2>🌿 Disección de Brotes</h2>
        <p>Registra el número de hojas separadas por brote y su siguiente medio nutritivo.</p>
        <a href="diseccion_hojas_ecas.php" onclick="guardarScroll('card-diseccion')">Registrar Disección</a>
      </div>

      <div class="card card-ecas" id="card-envio-multiplicacion">
        <h2>📤 Envío a Multiplicación</h2>
        <p>Finaliza el proceso ECAS enviando brotes listos a multiplicación.</p>
        <a href="envio_multiplicacion.php" onclick="guardarScroll('card-envio-multiplicacion')">Registrar Envío</a>
      </div>

      <div class="card card-ecas" id="card-estadisticas-ecas">
        <h2>📈 Estadísticas de ECAS</h2>
        <p>Consulta métricas clave de desarrollo por variedad, generación y éxito.</p>
        <a href="estadisticas_ecas.php" onclick="guardarScroll('card-estadisticas-ecas')">Ver Estadísticas</a>
      </div>

    </section>

    <h3 class="mt-5 mb-3">🔧 Funciones Generales</h3>
    <section class="dashboard-grid">

      <div class="card" id="card-reportes-produccion">
        <h2>🔬 Reportes de Producción</h2>
        <p>Consulta y revisa los reportes diarios de producción.</p>
        <a href="reportes_produccion.php" onclick="guardarScroll('card-reportes-produccion')">Ver Reportes</a>
      </div>

      <div class="card" id="card-preparacion-soluciones">
        <h2>🧪 Preparación de Soluciones Madre</h2>
        <p>Supervisa y controla la preparación de soluciones madre en el laboratorio.</p>
        <a href="preparacion_soluciones.php" onclick="guardarScroll('card-preparacion-soluciones')">Ir a Preparación</a>
      </div>

      <div class="card" id="card-inventario-soluciones">
        <h2>📈 Inventario de Soluciones Madre</h2>
        <p>Consulta la cantidad restante de cada solución madre.</p>
        <a href="inventario_soluciones_madre.php" onclick="guardarScroll('card-inventario-soluciones')">Ver Inventario</a>
      </div>

      <div class="card" id="card-rol-limpieza">
        <h2>🧹 Crear el Rol de Limpieza</h2>
        <p>Define las tareas de limpieza y asigna responsabilidades.</p>
        <a href="rol_limpieza.php" onclick="guardarScroll('card-rol-limpieza')">Crear Rol de Limpieza</a>
      </div>

      <div class="card" id="card-historial-lavado">
        <h2>📈 Historial de Lavado Parcial</h2>
        <p>Visualiza los reportes de avance de todos los operadores.</p>
        <a href="historial_lavado_parcial.php" onclick="guardarScroll('card-historial-lavado')">Ver Historial</a>
      </div>

    </section>
  </main>

  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<!-- Scripts Bootstrap y Scroll -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
