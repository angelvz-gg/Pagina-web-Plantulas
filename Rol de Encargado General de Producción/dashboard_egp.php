<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Encargado General de Producción</title>
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />
  </head>
  <body>
    <div class="contenedor-pagina">
      <header>
        <div class="encabezado">
          <a class="navbar-brand" href="#">
            <img
              src="../logoplantulas.png"
              alt="Logo"
              width="130"
              height="124"
              class="d-inline-block align-text-center"
            />
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
                <button onclick="window.location.href='../Login/logout.php'">
                  Cerrar Sesión
                </button>
              </div>
            </div>
          </nav>
        </div>
      </header>

      <!-- Contenido principal -->
      <main>

        <!-- Sección específica de ECAS -->
        <h3 class="mt-5 mb-3">🌱 Producción - Etapa 1 (ECAS)</h3>
        <section class="dashboard-grid">

          <div class="card card-ecas">
            <h2>🧼 Desinfección de Explantes</h2>
            <p>Preparación inicial de explantes para el cultivo.</p>
            <a href="desinfeccion_explantes.php">Ir a Desinfección</a>
          </div>
          
          <div class="card">
            <h2>📄 Historial de Desinfección</h2>
            <p>Consulta todas las desinfecciones registradas por los operadores.</p>
            <a href="historial_desinfeccion_explantes.php">Ver Historial</a>
          </div>

          <div class="card card-ecas">
            <h2>📋 Registro de Siembra Inicial</h2>
            <p>Captura la siembra inicial de explantes tras la desinfección.</p>
            <a href="registro_siembra_ecas.php">Registrar Siembra</a>
          </div>

          <div class="card card-ecas">
            <h2>✂️ Divisiones de Explantes</h2>
            <p>Registra las divisiones hechas en ECAS y su generación correspondiente.</p>
            <a href="divisiones_ecas.php">Registrar División</a>
          </div>

          <div class="card card-ecas">
            <h2>🧪 Evaluación de Desarrollo</h2>
            <p>Clasifica los explantes: vivos, hinchados, con brote, infectados o muertos.</p>
            <a href="evaluacion_ecas.php">Evaluar Desarrollo</a>
          </div>

          <div class="card card-ecas">
            <h2>🌿 Disección de Brotes</h2>
            <p>Registra el número de hojas separadas por brote y su siguiente medio nutritivo.</p>
            <a href="diseccion_hojas_ecas.php">Registrar Disección</a>
          </div>

          <div class="card card-ecas">
            <h2>📈 Estadísticas de ECAS</h2>
            <p>Consulta métricas clave de desarrollo por variedad, generación y éxito.</p>
            <a href="estadisticas_ecas.php">Ver Estadísticas</a>
          </div>

          <div class="card card-ecas">
            <h2>📤 Envío a Multiplicación</h2>
            <p>Finaliza el proceso ECAS enviando brotes listos a multiplicación.</p>
            <a href="envio_multiplicacion.php">Registrar Envío</a>
          </div>
          
        </section>

        <!-- Sección general -->
        <h3 class="mt-5 mb-3">🔧 Funciones Generales</h3>
        <section class="dashboard-grid">

          <div class="card">
            <h2>🔬 Reportes de Producción</h2>
            <p>Consulta y revisa los reportes diarios de producción.</p>
            <a href="reportes_produccion.php">Ver Reportes</a>
          </div>

          <div class="card">
            <h2>🧪 Preparación de Soluciones Madre</h2>
            <p>Supervisa y controla la preparación de soluciones madre en el laboratorio.</p>
            <a href="preparacion_soluciones.php">Ir a Preparación</a>
          </div>

          <div class="card">
            <h2>📊 Inventario de Soluciones Madre</h2>
            <p>Consulta la cantidad restante de cada solución madre.</p>
            <a href="inventario_soluciones_madre.php">Ver Inventario</a>
          </div>

          <div class="card">
            <h2>🧹 Crear el Rol de Limpieza</h2>
            <p>Define las tareas de limpieza y asigna responsabilidades.</p>
            <a href="rol_limpieza.php">Crear Rol de Limpieza</a>
          </div>

          <div class="card">
            <h2>🌿 Relación del Material Vegetativo</h2>
            <p>Gestiona la relación del material vegetativo que se lavará.</p>
            <a href="lavado_plantas.php">Ver Relación</a>
          </div>

          <div class="card">
            <h2>📈 Historial de Lavado Parcial</h2>
            <p>Visualiza los reportes de avance de todos los operadores.</p>
            <a href="historial_lavado_parcial.php">Ver Historial</a>
          </div>
        </section>
      </main>

      <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
      </footer>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>
