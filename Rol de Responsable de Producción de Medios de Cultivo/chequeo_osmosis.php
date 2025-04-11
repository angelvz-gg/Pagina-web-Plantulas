<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Chequeo de Ósmosis Inversa</title>
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
        <h2>Chequeo de Ósmosis Inversa</h2>
        <p>Verifica el estado y funcionamiento del sistema de ósmosis inversa.</p>
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
        <h2 class="text-center mb-3">Registro de Parámetros</h2>
      </div>

      <div class="main-content">
        <!-- Formulario -->
        <form id="osmosisForm" class="formulario">
          <div class="form-left">
            <div class="form-group">
              <label for="flujoAgua" class="form-label">Flujo de Agua (L/h)</label>
              <input type="number" id="flujoAgua" class="form-control" name="flujoAgua" placeholder="Ej: 500" required>
            </div>
            <div class="form-group">
              <label for="presionEntrada" class="form-label">Presión de Entrada (bar)</label>
              <input type="number" id="presionEntrada" class="form-control" name="presionEntrada" placeholder="Ej: 3.5" step="0.1" required>
            </div>
            <div class="form-group">
              <label for="presionSalida" class="form-label">Presión de Salida (bar)</label>
              <input type="number" id="presionSalida" class="form-control" name="presionSalida" placeholder="Ej: 1.2" step="0.1" required>
            </div>
            <div class="form-group">
              <label for="conductividad" class="form-label">Conductividad (µS/cm)</label>
              <input type="number" id="conductividad" class="form-control" name="conductividad" placeholder="Ej: 50" required>
            </div>
            <div class="form-group">
              <label for="observaciones" class="form-label">Observaciones</label>
              <textarea id="observaciones" class="form-control" name="observaciones" rows="3" placeholder="Ingrese observaciones relevantes..."></textarea>
            </div>
            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn-submit">
                Guardar Chequeo
              </button>
            </div>
          </div>
        </form>

        <!-- Historial -->
        <div class="form-right">
          <h2>Historial de Chequeos</h2>
          <table class="schedule-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Flujo (L/h)</th>
                <th>Presión Entrada (bar)</th>
                <th>Presión Salida (bar)</th>
                <th>Conductividad (µS/cm)</th>
                <th>Observaciones</th>
              </tr>
            </thead>
            <tbody id="historialBody">
              <!-- Historial se carga con JavaScript -->
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script>
  let historialChequeos = JSON.parse(localStorage.getItem("historialChequeos")) || [];

  function actualizarHistorial() {
    const tbody = document.getElementById("historialBody");
    tbody.innerHTML = "";

    historialChequeos.forEach(chequeo => {
      const tr = document.createElement("tr");

      tr.innerHTML = `
        <td>${chequeo.fecha}</td>
        <td>${chequeo.flujoAgua}</td>
        <td>${chequeo.presionEntrada}</td>
        <td>${chequeo.presionSalida}</td>
        <td>${chequeo.conductividad}</td>
        <td>${chequeo.observaciones}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  document.getElementById("osmosisForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const flujoAgua = document.getElementById("flujoAgua").value;
    const presionEntrada = document.getElementById("presionEntrada").value;
    const presionSalida = document.getElementById("presionSalida").value;
    const conductividad = document.getElementById("conductividad").value;
    const observaciones = document.getElementById("observaciones").value;
    const fecha = new Date().toLocaleDateString();

    const nuevoChequeo = { fecha, flujoAgua, presionEntrada, presionSalida, conductividad, observaciones };

    historialChequeos.push(nuevoChequeo);
    localStorage.setItem("historialChequeos", JSON.stringify(historialChequeos));

    actualizarHistorial();
    document.getElementById("osmosisForm").reset();
    alert("✅ Chequeo guardado exitosamente");
  });

  document.addEventListener("DOMContentLoaded", actualizarHistorial);
</script>

</body>
</html>
