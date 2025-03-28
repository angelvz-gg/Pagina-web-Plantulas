<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Verificación de Reportes de Producción</title>
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
        margin: 0;
        display: flex;
        flex-direction: column;
      }

      .contenedor-borde {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        border: 4px solid #45814d;
        min-height: 100vh;
        box-sizing: border-box;
        overflow: auto;
      }

      /* Encabezado */
      .encabezado {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background-color: #45814d;
        color: white;
        font-size: 22px;
        padding: 10px 20px;
      }
      .encabezado h2 {
        margin: 0;
        flex-grow: 1;
        text-align: center;
      }

      /* Barra de navegación */
      .barra-navegacion .navbar {
        background-color: #6faf71 !important;
      }
      .Opciones-barra {
        display: flex;
        list-style: none;
        padding: 0;
        margin-left: auto;
      }

      /* Contenido principal */
      main {
        flex-grow: 1;
        padding: 20px;
        background-color: #f8f9fa;
      }

      /* Contenedor de la tabla */
      .form-container {
        overflow-x: auto;
        margin: 20px auto;
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 10px;
        background: #fff;
      }

      /* Estilos para la tabla */
      table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
      }

      table,
      th,
      td {
        border: 1px solid #ddd;
      }

      th,
      td {
        padding: 12px;
        text-align: left;
      }

      th {
        background-color: #f4f4f4;
        font-weight: bold;
      }

      tr:nth-child(even) {
        background-color: #f9f9f9;
      }

      tr:hover {
        background-color: #f1f1f1;
      }

      /* Botones */
      button {
        background-color: #d9b310;
        color: #2a2a2a;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        margin: 2px;
      }

      /* Footer */
      footer {
        background-color: #45814d;
        color: white;
        text-align: center;
        padding: 10px 0;
        width: 100%;
        flex-shrink: 0;
      }
    </style>
  </head>
  <body>
    <!-- Contenedor principal con borde -->
    <div class="contenedor-borde">
      <header>
        <!-- Encabezado -->
        <div class="encabezado">
          <a class="navbar-brand" href="#">
            <img
              src="logo.png"
              alt="Logo"
              width="130"
              height="124"
              class="d-inline-block align-text-center"
            />
          </a>
          <div>
            <h2>Inventario de soluciones madre </h2>
            <p>Consulta la cantidad restante de cada medio nutritivo madre..</p>
          </div>
        </div>

        <!-- Barra de navegación -->
        <div class="barra-navegacion">
          <nav class="navbar bg-body-tertiary">
            <div class="container-fluid">
              <div class="Opciones-barra">
                <button
                  onclick="window.location.href='dashboard_encargado_general_produccion.php'"
                >
                 <h3>🔄 Regresar</h3> 
                </button>
              </div>
            </div>
          </nav>
        </div>
      </header>

      <!-- Contenido principal con la tabla -->
      <main>
        <div class="form-container">
          <h2>📊 Cantidad Disponible de Soluciones Madre</h2>
          <table>
            <thead>
              <tr>
                <th>Código del Medio</th>
                <th>Fecha de Preparación</th>
                <th>Cantidad Inicial (L)</th>
                <th>Cantidad Usada (L)</th>
                <th>Cantidad Restante (L)</th>
                
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>dato</td>
                <td>dato</td>
                <td>dato</td>
                <td>dato</td>
                <td>dato</td>
               
            
              </tr>
            </tbody>
          </table>
        </div>
      </main>

      <!-- Footer -->
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