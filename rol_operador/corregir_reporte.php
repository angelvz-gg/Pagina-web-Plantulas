<?php
// 0) Mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1) Validar sesión y rol
require_once __DIR__ . '/../session_manager.php';
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['ID_Operador'])) {
    header('Location: ../login.php?mensaje=Debe iniciar sesión');
    exit;
}
$ID_Operador = (int) $_SESSION['ID_Operador'];

if ((int) $_SESSION['Rol'] !== 2) {
    echo "<p class=\"error\">⚠️ Acceso denegado. Solo Operador.</p>";
    exit;
}
// 2) Variables para el modal de sesión (3 min inactividad, aviso 1 min antes)
$sessionLifetime = 60 * 3;   // 180 s
$warningOffset   = 60 * 1;   // 60 s
$nowTs           = time();

// Verificar que se reciban los parámetros tipo e id
if (!isset($_GET['tipo']) || !isset($_GET['id'])) {
    echo "Parámetros inválidos.";
    exit();
}

$tipo = $_GET['tipo'];
$id = $_GET['id'];

// Tipos permitidos
$allowedTypes = ['multiplicacion', 'enraizamiento'];
if (!in_array($tipo, $allowedTypes)) {
    echo "Tipo inválido.";
    exit();
}

// Procesar la actualización si se envía el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recibimos los valores (los inputs que no fueron editables se envían vía hidden)
    $tasa = $_POST['Tasa_Multiplicacion'] ?? null;
    $cantidad = $_POST['Cantidad_Dividida'] ?? null;
    $tuppersLlenos = $_POST['Tuppers_Llenos'] ?? null;
    $tuppersVacios = $_POST['Tuppers_Desocupados'] ?? null;
    
    // Se asume que luego de la corrección se vuelve a poner el estado a "Pendiente"
    // y se limpian los campos de observaciones y los campos rechazados.
    if ($tipo === "multiplicacion") {
        $stmt = $conn->prepare("UPDATE multiplicacion 
            SET Tasa_Multiplicacion = ?, Cantidad_Dividida = ?, Tuppers_Llenos = ?, Tuppers_Desocupados = ?, 
                Estado_Revision = 'Pendiente', Observaciones_Revision = NULL, Campos_Rechazados = NULL 
            WHERE ID_Multiplicacion = ?");
        $stmt->bind_param("iiiii", $tasa, $cantidad, $tuppersLlenos, $tuppersVacios, $id);
    } else { // enraizamiento
        $stmt = $conn->prepare("UPDATE enraizamiento 
            SET Tasa_Multiplicacion = ?, Cantidad_Dividida = ?, Tuppers_Llenos = ?, Tuppers_Desocupados = ?, 
                Estado_Revision = 'Pendiente', Observaciones_Revision = NULL, Campos_Rechazados = NULL 
            WHERE ID_Enraizamiento = ?");
        $stmt->bind_param("iiiii", $tasa, $cantidad, $tuppersLlenos, $tuppersVacios, $id);
    }
    $stmt->execute();
    echo "<script>alert('Reporte corregido exitosamente.'); window.location.href='dashboard_cultivo.php';</script>";
    exit();
}

// Si es GET, se obtiene el reporte desde la base de datos
if ($tipo === "multiplicacion") {
    $stmt = $conn->prepare("SELECT M.*, V.Codigo_Variedad, V.Nombre_Variedad 
        FROM multiplicacion M 
        LEFT JOIN variedades V ON M.ID_Variedad = V.ID_Variedad 
        WHERE ID_Multiplicacion = ?");
    $stmt->bind_param("i", $id);
} else {
    $stmt = $conn->prepare("SELECT E.*, V.Codigo_Variedad, V.Nombre_Variedad 
        FROM enraizamiento E 
        LEFT JOIN variedades V ON E.ID_Variedad = V.ID_Variedad 
        WHERE ID_Enraizamiento = ?");
    $stmt->bind_param("i", $id);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Reporte no encontrado.";
    exit();
}
$reporte = $result->fetch_assoc();

// Decodificar el campo de campos rechazados (se espera un JSON con un arreglo de nombres de campos)
$camposRechazados = [];
if (!empty($reporte['Campos_Rechazados'])) {
    $camposRechazados = json_decode($reporte['Campos_Rechazados'], true);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Corregir Reporte</title>
    <link rel="stylesheet" href="../style.css?v=<?= time(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      /* Estilo para inputs readonly */
      .readonly {
          background-color: #e9ecef;
      }
    </style>
      <script>
    const SESSION_LIFETIME = <?= $sessionLifetime * 1000 ?>;
    const WARNING_OFFSET   = <?= $warningOffset   * 1000 ?>;
    let START_TS         = <?= $nowTs           * 1000 ?>;
  </script>
</head>
<body>
<div class="contenedor-pagina">
  <header>
    <div class="encabezado">
      <a class="navbar-brand" href="#">
        <img src="../logoplantulas.png" alt="Logo" width="130" height="124">
      </a>
      <h2>Corregir Reporte - <?= ucfirst($tipo) ?></h2>
    </div>

    <div class="barra-navegacion">
        <nav class="navbar bg-body-tertiary">
          <div class="container-fluid">
            <div class="Opciones-barra">
              <button onclick="window.location.href='dashboard_cultivo.php'">
              🏠 Volver al Inicio
              </button>
            </div>
          </div>
        </nav>
      </div>
  </header>
  
  <main>
    <div class="container mt-4">
      <p>Se te ha retornado el reporte con las siguientes observaciones. Solo puedes corregir las áreas marcadas como incorrectas.</p>
      <form method="POST">
        <input type="hidden" name="tipo" value="<?= $tipo ?>">
        <input type="hidden" name="id" value="<?= $id ?>">

        <!-- Datos generales: no editables -->
        <div class="mb-3">
            <label class="form-label">Código de Variedad</label>
            <input type="text" class="form-control readonly" value="<?= $reporte['Codigo_Variedad'] . " - " . $reporte['Nombre_Variedad'] ?>" disabled>
        </div>
        <div class="mb-3">
            <label class="form-label">Fecha de Siembra</label>
            <input type="text" class="form-control readonly" value="<?= $reporte['Fecha_Siembra'] ?>" disabled>
        </div>

        <!-- Campo: Tasa de Multiplicación -->
        <div class="mb-3">
            <label class="form-label">Tasa de Multiplicación</label>
            <?php if (in_array('Tasa_Multiplicacion', $camposRechazados)): ?>
                <input type="number" name="Tasa_Multiplicacion" class="form-control" value="<?= $reporte['Tasa_Multiplicacion'] ?>" required>
            <?php else: ?>
                <input type="number" class="form-control readonly" value="<?= $reporte['Tasa_Multiplicacion'] ?>" disabled>
                <input type="hidden" name="Tasa_Multiplicacion" value="<?= $reporte['Tasa_Multiplicacion'] ?>">
            <?php endif; ?>
        </div>

        <!-- Campo: Cantidad Dividida -->
        <div class="mb-3">
            <label class="form-label">Cantidad Dividida</label>
            <?php if (in_array('Cantidad_Dividida', $camposRechazados)): ?>
                <input type="number" name="Cantidad_Dividida" class="form-control" value="<?= $reporte['Cantidad_Dividida'] ?>" required>
            <?php else: ?>
                <input type="number" class="form-control readonly" value="<?= $reporte['Cantidad_Dividida'] ?>" disabled>
                <input type="hidden" name="Cantidad_Dividida" value="<?= $reporte['Cantidad_Dividida'] ?>">
            <?php endif; ?>
        </div>

        <!-- Campo: Tuppers Llenos -->
        <div class="mb-3">
            <label class="form-label">Tuppers Llenos</label>
            <?php if (in_array('Tuppers_Llenos', $camposRechazados)): ?>
                <input type="number" name="Tuppers_Llenos" class="form-control" value="<?= $reporte['Tuppers_Llenos'] ?>" required>
            <?php else: ?>
                <input type="number" class="form-control readonly" value="<?= $reporte['Tuppers_Llenos'] ?>" disabled>
                <input type="hidden" name="Tuppers_Llenos" value="<?= $reporte['Tuppers_Llenos'] ?>">
            <?php endif; ?>
        </div>

        <!-- Campo: Tuppers Vacíos -->
        <div class="mb-3">
            <label class="form-label">Tuppers Vacíos</label>
            <?php if (in_array('Tuppers_Desocupados', $camposRechazados)): ?>
                <input type="number" name="Tuppers_Desocupados" class="form-control" value="<?= $reporte['Tuppers_Desocupados'] ?>" required>
            <?php else: ?>
                <input type="number" class="form-control readonly" value="<?= $reporte['Tuppers_Desocupados'] ?>" disabled>
                <input type="hidden" name="Tuppers_Desocupados" value="<?= $reporte['Tuppers_Desocupados'] ?>">
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">Enviar Corrección</button>
      </form>
    </div>
  </main>
  <footer>
    <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
  </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

 <!-- Modal de advertencia de sesión -->
 <script>
 (function(){
  // Estado y referencias a los temporizadores
  let modalShown = false,
      warningTimer,
      expireTimer;

  // Función para mostrar el modal de aviso
  function showModal() {
    modalShown = true;
    const modalHtml = `
      <div id="session-warning" class="modal-overlay">
        <div class="modal-box">
          <p>Tu sesión va a expirar pronto. ¿Deseas mantenerla activa?</p>
          <button id="keepalive-btn" class="btn-keepalive">Seguir activo</button>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document
      .getElementById('keepalive-btn')
      .addEventListener('click', keepSessionAlive);
  }

  // Función para llamar a keepalive.php y, si es OK, reiniciar los timers
  function keepSessionAlive() {
    fetch('../keepalive.php', { credentials: 'same-origin' })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'OK') {
          // Quitar el modal
          const modal = document.getElementById('session-warning');
          if (modal) modal.remove();

          // Reiniciar tiempo de inicio
          START_TS   = Date.now();
          modalShown = false;

          // Reprogramar los timers
          clearTimeout(warningTimer);
          clearTimeout(expireTimer);
          scheduleTimers();
        } else {
          alert('No se pudo extender la sesión');
        }
      })
      .catch(() => alert('Error al mantener viva la sesión'));
  }

  // Configura los timeouts para mostrar el aviso y para la expiración real
  function scheduleTimers() {
    const elapsed     = Date.now() - START_TS;
    const warnAfter   = SESSION_LIFETIME - WARNING_OFFSET;
    const expireAfter = SESSION_LIFETIME;

    warningTimer = setTimeout(showModal, Math.max(warnAfter - elapsed, 0));

    expireTimer = setTimeout(() => {
      if (!modalShown) {
        showModal();
      } else {
        window.location.href = '/plantulas/login.php?mensaje='
          + encodeURIComponent('Sesión caducada por inactividad');
      }
    }, Math.max(expireAfter - elapsed, 0));
  }

  // Inicia la lógica al cargar el script
  scheduleTimers();
})();
  </script>
</body>
</html>
