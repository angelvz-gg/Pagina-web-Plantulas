<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Responsable de Producción de Medios de Cultivo</title>
  <link rel="stylesheet" href="../style.css?v=<?=time();?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
</head>

<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
      </a>
      <div>
        <h2>Bienvenido, Responsable de Producción de Medios de Cultivo</h2>
        <p>Resumen de tus actividades y accesos rápidos.</p>
      </div>
    </div>

    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='../logout.php'">
              Cerrar Sesion
            </button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main>
    <section class="dashboard-grid">
      <div class="card">
        <h2>🔍 Chequeo de Ósmosis Inversa</h2>
        <p>Revisa y mantén el sistema de ósmosis inversa en óptimas condiciones para la producción.</p>
        <a href="chequeo_osmosis.php">Ver detalles</a>
      </div>

      <div class="card">
        <h2>🔥 🛡️ Esterilización en Autoclave</h2>
        <p>Asegura la esterilización de materiales y medios de cultivo mediante autoclave.</p>
        <a href="esterilizacion_autoclave.php">Ver detalles</a>
      </div>

      <div class="card">
        <h2>🧪 Homogeneización del Medio</h2>
        <p>Supervisa y controla la homogeneización del medio nutritivo madre.</p>
        <a href="h_medio_nutritivo.php">Ver detalles</a>
      </div>

      <div class="card">
        <h2>📋 Historial de Homogenizaciones</h2>
        <p>Consulta registros anteriores de diluciones realizadas y tuppers llenados.</p>
        <a href="historial_homogenizaciones.php">Ver historial</a>
      </div>

    </section>
  </main>

  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
