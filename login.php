<?php
ob_start();
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario    = trim($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    // Consulta segura con prepared statement
    $sql  = "SELECT * FROM operadores 
             WHERE LOWER(TRIM(Usuario)) = LOWER(?) 
               AND Activo = 1
             LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado && $resultado->num_rows === 1) {
        $operador = $resultado->fetch_assoc();

        if (password_verify($contrasena, $operador['Contrasena_Hash'])) {
            // Iniciar sesión
            $_SESSION['ID_Operador'] = $operador['ID_Operador'];
            $_SESSION['Usuario']     = $operador['Usuario'];
            $_SESSION['Nombre']      = $operador['Nombre'];
            $_SESSION['Rol']         = $operador['ID_Rol'];

            // Actualizar último acceso
            $upd = $conn->prepare("UPDATE operadores SET Ultimo_Acceso = NOW() WHERE ID_Operador = ?");
            $upd->bind_param('i', $operador['ID_Operador']);
            $upd->execute();

            // Rutas por rol
            $rutas = [
                1 => '/plantulas/rol_administrador/panel_admin.php',
                2 => '/plantulas/rol_operador/dashboard_cultivo.php',
                3 => '/plantulas/rol_supervisor/panel_supervisor.php',
                4 => '/plantulas/rol_consulta/panel_consulta.php',
                5 => '/plantulas/rol_encargado_general_produccion/dashboard_egp.php',
                6 => '/plantulas/rol_gerente_produccion_laboratorio/dashboard_gpl.php',
                7 => '/plantulas/rol_responsable_produccion_medios_cultivo/dashboard_rpmc.php',
            ];
            $destino = $rutas[$operador['ID_Rol']] ?? '/plantulas/panel.php';

            echo "<script>window.location.href = '{$destino}';</script>";
            exit;
        } else {
            $error = "❌ Contraseña incorrecta";
        }
    } else {
        $error = "❌ Usuario no encontrado o inactivo";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inicio de Sesión</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="contenedor-pagina">
    <!-- HEADER -->
    <div class="encabezado">
      <div class="navbar-brand">🌱 Sistema Plantulas</div>
      <h2>Inicio de Sesión</h2>
    </div>

    <!-- LOGIN FORM -->
    <main class="login-container">
      <?php if (isset($error)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
      <?php endif; ?>

      <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <label>
          Usuario:
          <input type="text" name="usuario" required>
        </label>

        <label>
          Contraseña:
          <input type="password" name="contrasena" required>
        </label>

        <button type="submit" class="btn-inicio">Ingresar</button>
      </form>
    </main>

    <!-- FOOTER -->
    <footer>
      Sistema de Producción de Plantas &copy; <?php echo date("Y"); ?>
    </footer>
  </div>
</body>
</html>
<?php ob_end_flush(); ?>
