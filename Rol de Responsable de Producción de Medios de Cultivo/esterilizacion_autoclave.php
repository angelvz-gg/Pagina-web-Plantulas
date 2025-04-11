<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Esterilización en Autoclave</title>
  <link rel="stylesheet" href="../style.css" />
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
        <h2>Esterilización en Autoclave</h2>
        <p>Registra el proceso de esterilización de todo tipo de material.</p>
      </div>
    </div>

    <div class="barra-navegacion">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <div class="Opciones-barra">
            <button onclick="window.location.href='dashboard_rpmc.php'">
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
        <h2 class="text-center mb-3">Registrar Esterilización</h2>
      </div>

      <div class="main-content">
        <!-- Formulario -->
        <form id="esterilizacionForm" class="formulario">
          <div class="form-left">
            <div class="form-group">
              <label for="tipo_de_material" class="form-label">Tipo de material</label>
              <input type="text" id="tipo_de_material" class="form-control" name="tipodematerial" placeholder="Ej: Material X" required>
            </div>

            <div class="form-group">
              <label for="duracion" class="form-label">Duración (min):</label>
              <input type="number" id="duracion" class="form-control" name="duracion" placeholder="Ej: 30" step="1" required>
            </div>

            <div class="form-group">
              <label for="numero_de_esterilizacion" class="form-label">Número de Esterilización:</label>
              <input type="number" id="numero_de_esterilizacion" class="form-control" name="númerodeesterilizacion" placeholder="Ej: 2" step="1" required>
            </div>
          </div>

          <div class="form-left">
            <div class="form-group">
              <label for="lote" class="form-label">Lote (opcional):</label>
              <input type="text" id="lote" class="form-control" name="lote" placeholder="ID del Lote">
            </div>

            <div class="form-group">
              <label for="observaciones" class="form-label">Observaciones</label>
              <textarea id="observaciones" class="form-control" name="observaciones" rows="3" placeholder="Ingrese observaciones relevantes..."></textarea>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn-submit">
                Guardar Registro
              </button>
            </div>
          </div>
        </form>

        <!-- Historial -->
        <div class="form-right">
          <h2 class="text-center mb-3">Historial de Esterilización</h2>
          <table class="schedule-table">
            <thead>
              <tr>
                <th>Tipo de material</th>
                <th>Duración (Min)</th>
                <th>Nº de Esterilización</th>
                <th>Lote</th>
                <th>Observaciones</th>
              </tr>
            </thead>
            <tbody id="historialBody">
              <!-- Se llenará con JavaScript -->
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script>
  let historialEsterilizaciones = JSON.parse(localStorage.getItem("historialEsterilizaciones")) || [];

  function actualizarHistorial() {
    const tbody = document.getElementById("historialBody");
    tbody.innerHTML = "";

    historialEsterilizaciones.forEach(registro => {
      const tr = document.createElement("tr");

      const celdas = [
        registro.tipoMaterial,
        registro.duracion,
        registro.numeroEsterilizacion,
        registro.lote || 'N/A',
        registro.observaciones || 'Sin observaciones'
      ];

      celdas.forEach(dato => {
        const td = document.createElement("td");
        td.textContent = dato;
        tr.appendChild(td);
      });

      tbody.appendChild(tr);
    });
  }

  document.getElementById("esterilizacionForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const nuevoRegistro = {
      tipoMaterial: document.getElementById("tipo_de_material").value,
      duracion: document.getElementById("duracion").value,
      numeroEsterilizacion: document.getElementById("numero_de_esterilizacion").value,
      lote: document.getElementById("lote").value,
      observaciones: document.getElementById("observaciones").value,
      fecha: new Date().toLocaleString()
    };

    historialEsterilizaciones.push(nuevoRegistro);
    localStorage.setItem("historialEsterilizaciones", JSON.stringify(historialEsterilizaciones));

    actualizarHistorial();
    this.reset();
    alert("Registro de esterilización guardado exitosamente");
  });

  document.addEventListener("DOMContentLoaded", actualizarHistorial);
</script>
</body>
</html>
