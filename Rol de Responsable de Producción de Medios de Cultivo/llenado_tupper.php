<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Llenado y Etiquetado</title>
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
        <h2>Llenado y Etiquetado</h2>
        <p>Registra el medio nutritivo contenido en cada tupper y la fecha de llenado.</p>
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
        <h2 class="text-center mb-3">Registrar Llenado y Etiquetado</h2>
      </div>

      <div class="main-content">
        <!-- Formulario -->
        <form id="llenadoForm" class="formulario">
          <div class="form-left">
            <div class="form-group">
              <label for="codigoMedio" class="form-label">Código de Medio Nutritivo Madre</label>
              <input type="text" id="codigoMedio" class="form-control" name="codigoMedio" placeholder="Ej: MED001" required>
            </div>

            <div class="form-group">
              <label for="fecha" class="form-label">Fecha</label>
              <input type="date" id="fecha" class="form-control" name="fecha" required>
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
          <h2 class="text-center mb-3">Historial de Llenado y Etiquetado</h2>
          <table class="schedule-table">
            <thead>
              <tr>
                <th>Código de Medio Nutritivo Madre</th>
                <th>Fecha</th>
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
  let historial_llenado_y_etiquetado = JSON.parse(localStorage.getItem("historialllenado")) || [];

  document.getElementById("llenadoForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const nuevoRegistro = {
      codigo: document.getElementById("codigoMedio").value,
      fecha: document.getElementById("fecha").value
    };

    historial_llenado_y_etiquetado.push(nuevoRegistro);
    localStorage.setItem("historialllenado", JSON.stringify(historial_llenado_y_etiquetado));

    actualizarHistorial();
    this.reset();
    alert("Registro guardado exitosamente");
  });

  function actualizarHistorial() {
    const tbody = document.getElementById("historialBody");
    tbody.innerHTML = "";

    historial_llenado_y_etiquetado.forEach(registro => {
      const tr = document.createElement("tr");

      const celdas = [
        registro.codigo,
        registro.fecha
      ];

      celdas.forEach(dato => {
        const td = document.createElement("td");
        td.textContent = dato;
        tr.appendChild(td);
      });

      tbody.appendChild(tr);
    });
  }

  document.addEventListener("DOMContentLoaded", actualizarHistorial);
</script>

</body>
</html>
