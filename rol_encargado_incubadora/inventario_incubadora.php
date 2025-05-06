<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inventario de incubadora</title>
  <link rel="stylesheet" href="../style.css?v=<?=time();?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
</head>

<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="logoplantulas.png"alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
      </a>
      <div>
        <h2>Inventario de incubadora</h2>
        <p>Vista del inventario de la incubadora.</p>
      </div>
    </div>

    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='tarjetas.html'">
              🔄 Regresar
            </button>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main>
    <section class="form-container">
      <div class="form-header mb-4">
        <h2 class="text-center mb-3">Distribucion de trabajo para Lavado </h2>
      </div>
  <!-- Historial de parámetros  -->
  <div class="form-container">
    <div class="search-section mb-4">
      <select class="form-select">
        <option>Buscar por ID o planta</option>
      </select>
    </div>

    <div class="material-section mb-4">
      <button class="btn-inicio">Buscar </button>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>ID Planta</th>
          <th>Variedad</th>
          <th>Etapa de la variedad</th>
          <th>Tiempo en incubadora</th>
        </tr>
      </thead>
      <tbody>
        <tr>
            
            <td>ID Planta:WJQ8</td>
            <td>Variedad:Forza</td>
            <td>Etapa 2</td>
            <td>2 meses y 3 dias</td>
           
          </tr>
          <tr>
            <td>ID Planta:WJQ8</td>
            <td>Variedad:Forza</td>
            <td>Etapa 3</td>
            <td>2 meses y 3 dias</td>
            
          </tr>

          <tr>
            <td>ID Planta:WJQ8</td>
            <td>Variedad:Forza</td>
            <td>Etapa 1</td>
            <td>2 meses y 3 dias</td>
            
          </tr>

          <tr>
            <td>ID Planta:WJQ8</td>
            <td>Variedad:Forza</td>
            <td>Etapa 2</td>
            <td>2 meses y 3 dias</td>
            
          </tr>
      </tbody>
    </table>

    <div class="d-grid gap-2 mt-4">
        <button type="submit" class="btn-submit">
          Guardar
        </button>
      </div>
</section>
</main>
<footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>
</body>
</html>
  