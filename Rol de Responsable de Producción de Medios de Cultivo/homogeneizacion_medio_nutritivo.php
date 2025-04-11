<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Homogeneización del Medio</title>
  <link rel="stylesheet" href="../style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center">
      </a>
      <div>
        <h2>Homogeneización del Medio</h2>
        <p>Registra el proceso de homogeneización del medio nutritivo madre.</p>
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
        <h2 class="text-center mb-3">Registrar Homogeneización</h2>
      </div>

      <div class="main-content">
        <!-- Formulario -->
        <form id="homogeneizacionForm" class="formulario">
          <div class="form-left">
            <div class="form-group">
              <label for="fecha" class="form-label">Fecha</label>
              <input type="date" id="fecha" class="form-control" name="fecha" required>
            </div>

            <div class="form-group">
              <label for="hora" class="form-label">Hora</label>
              <input type="time" id="hora" class="form-control" name="hora" required>
            </div>

            <div class="form-group">
              <label for="codigoMedio" class="form-label">Código de Medio Nutritivo Madre</label>
              <input type="text" id="codigoMedio" class="form-control" name="codigoMedio" placeholder="Ej: MED001" required>
            </div>

            <div class="form-group">
              <label for="cantidadCreada" class="form-label">Cantidad Creada (L)</label>
              <input type="number" id="cantidadCreada" class="form-control" name="cantidadCreada" placeholder="Ej: 50" step="0.1" required>
            </div>

            <div class="form-group">
              <label for="cantidadOcupada" class="form-label">Cantidad Ocupada (L)</label>
              <input type="number" id="cantidadOcupada" class="form-control" name="cantidadOcupada" placeholder="Ej: 10" step="0.1" required>
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
          <h2 class="text-center mb-3">Historial de Homogeneización</h2>
          <table class="schedule-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Código Medio</th>
                <th>Cantidad Creada (L)</th>
                <th>Cantidad Ocupada (L)</th>
                <th>Observaciones</th>
              </tr>
            </thead>
            <tbody id="historialBody">
              <!-- Se llenará dinámicamente -->
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  let historialHomogeneizacion = JSON.parse(localStorage.getItem("historialHomogeneizacion")) || [];

  function actualizarHistorial() {
    const tbody = document.getElementById("historialBody");
    tbody.innerHTML = "";

    historialHomogeneizacion.forEach(registro => {
      const tr = document.createElement("tr");

      const celdas = [
        registro.fecha,
        registro.hora,
        registro.codigoMedio,
        registro.cantidadCreada + " L",
        registro.cantidadOcupada + " L",
        registro.observaciones || "Sin observaciones"
      ];

      celdas.forEach(dato => {
        const td = document.createElement("td");
        td.textContent = dato;
        tr.appendChild(td);
      });

      tbody.appendChild(tr);
    });
  }

  document.getElementById("homogeneizacionForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const nuevoRegistro = {
      fecha: document.getElementById("fecha").value,
      hora: document.getElementById("hora").value,
      codigoMedio: document.getElementById("codigoMedio").value,
      cantidadCreada: document.getElementById("cantidadCreada").value,
      cantidadOcupada: document.getElementById("cantidadOcupada").value,
      observaciones: document.getElementById("observaciones").value
    };

    historialHomogeneizacion.push(nuevoRegistro);
    localStorage.setItem("historialHomogeneizacion", JSON.stringify(historialHomogeneizacion));

    actualizarHistorial();
    this.reset();
    alert("Registro de homogeneización guardado exitosamente");
  });

  document.addEventListener("DOMContentLoaded", actualizarHistorial);
</script>

</body>
</html>
