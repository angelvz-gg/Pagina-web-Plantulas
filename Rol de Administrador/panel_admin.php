<?php
session_start();

// Validación de sesión
if (!isset($_SESSION['ID_Operador'])) {
  header("Location: ../login.php");
  exit();
}

// Validación de rol (1 = Admin)
if ($_SESSION['Rol'] != 1) {
  echo "<p style='color:red;'>⚠️ Acceso denegado. Este panel es solo para administradores.</p>";
  exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Administrador</title>
  <link rel="stylesheet" href="../style.css"> 
</head>
<body>

  <!-- Encabezado -->
  <div class="encabezado">
    <div class="navbar-brand">
      🌱 Sistema Plantulas
    </div>
    <h2>Panel Administrador</h2>
    <div>
      <form action="../Login/logout.php" method="post">
        
        <button type="submit">Cerrar sesión</button>
      </form>
    </div>
  </div>

  <!-- Contenido principal -->
  <main>
    <h3>Bienvenido, <?php echo $_SESSION['Nombre']; ?> 👤</h3>

    <div class="dashboard-grid">
      <div class="card">
        <h2>Registrar Operador</h2>
        <p>Alta de nuevos usuarios del sistema.</p>
        <a href="registro_operador.php">Registrar</a>
      </div>

      <div class="card">
        <h2>Gestionar Operadores</h2>
        <p>Editar o eliminar operadores registrados.</p>
        <a href="gestionar_operadores.php">Entrar</a>
      </div>

      <div class="card">
        <h2>Ver Reportes</h2>
        <p>Consultas y estadísticas generales del laboratorio.</p>
        <a href="ver_reportes.php">Abrir</a>
      </div>
    </div>
  </main>

  <!-- Pie de página -->
  <footer>
  2025 PLANTAS AGRODEX. Todos los derechos reservados. &copy; <?php echo date("Y"); ?>
  </footer>

</body>
</html>
