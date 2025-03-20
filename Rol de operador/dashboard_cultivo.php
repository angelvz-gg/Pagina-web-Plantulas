<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel Operador</title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />
    <style>
      /* Estilos generales */
      html,
      body {
        height: 100%;
        margin: 0; /* Elimina el margen predeterminado */
        display: flex;
        flex-direction: column; /* Organiza los elementos en columna */
        border: 4px solid #45814d;
      }

      /* Estilos para el encabezado */
      .encabezado {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background-color: #45814d;
        color: white;
        font-size: 22px;
        padding: 10px 20px;
      }
      .encabezado .navbar-brand {
        display: flex;
        align-items: center;
      }
      .encabezado h2 {
        margin: 0;
        flex-grow: 1;
        text-align: center;
      }

      /* Estilos para el botón */
      button {
        background-color: #d9b310;
        color: #2a2a2a;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
      }

      /* Estilos para la barra de navegación */
      .barra-navegacion .navbar {
        background-color: #6faf71 !important;
      }
      .Opciones-barra {
        display: flex;
        list-style: none;
        padding: 0;
        margin-left: auto;
      }
      .Opciones-barra .nav-item {
        display: flex;
        align-items: center;
        list-style: none;
        margin-right: 20px;
        font-size: 22px;
      }
      .Opciones-barra .nav-link {
        color: white !important;
      }

      /* Estilos para el main */
      main {
        flex-grow: 1;
        padding: 20px;
        background-color: #f8f9fa;
      }

      .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        width: 100%;
        margin-top: 20px;
      }

      .card {
        background-color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        transition: transform 0.3s ease-in-out;
        text-align: center;
      }

      .card h2 {
        font-size: 1.5em;
        color: #333;
        margin-bottom: 10px;
      }
      .card p {
        font-size: 1em;
        color: #666;
        margin-bottom: 10px;
      }

      .card a {
        display: inline-block;
        background-color: #45814d;
        color: white;
        text-decoration: none;
        padding: 10px 15px;
        margin-top: 10px;
        border-radius: 5px;
        transition: background-color 0.3s ease-in-out;
      }

      /* Estilos para el footer */
      footer {
        background-color: #45814d;
        color: white;
        text-align: center;
        padding: 10px 0;
      }
    </style>
  </head>
  <body>
    <header>
      <!-- Encabezado con logo y título -->
      <div class="encabezado">
        <a class="navbar-brand" href="#">
          <!-- Logo de la empresa -->
          <img
            src="logoplantulas.png"  
            alt="Logo"
            width="130"
            height="124"
            class="d-inline-block align-text-center"
          />
        </a>
        <div>
          <h2>Panel de Operador</h2>
          <p>Mantén el registro de actividades</p>
        </div>
      </div>

      <!-- Barra de navegación -->
      <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='index.php'">
                Volver a la página principal
              </button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <!-- Contenido principal -->
    <main>
      <section class="dashboard-grid">
        <div class="card">
          <h2>🌿 Trabajo en Disección</h2>
          <p>Revisa tus etapas asignadas.</p>
          <a href="reporte_diseccion.php">Trabajo en Disección</a>
        </div>
        <div class="card">
          <h2>📝 Asignación de Limpieza</h2>
          <p>Revisa qué área tienes asignada para limpieza.</p>
          <a href="area_limpieza.php">Ver detalles</a>
        </div>
        <div class="card">
          <h2>🗂 Asignación de Lavado</h2>
          <p>Revisa tu rol para el lavado de plantas.</p>
          <a href="relacion_lavado.php">Ver detalles</a>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer>
      <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>
