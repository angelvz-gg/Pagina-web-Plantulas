<?php
require '../db.php'; // Conexión a la base de datos

// Funciones
function generarUsuario($nombre, $apellido_p) {
  global $conn;
  $base = strtolower(trim($nombre)) . '.' . strtolower(trim($apellido_p));
  $usuario = $base;
  $i = 1;

  while (mysqli_num_rows(mysqli_query($conn, "SELECT * FROM Operadores WHERE Usuario='$usuario'")) > 0) {
    $usuario = $base . $i;
    $i++;
  }

  return $usuario;
}

function generarContrasena($longitud = 8) {
  $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
  return substr(str_shuffle($caracteres), 0, $longitud);
}

// Procesar formulario si se ha enviado
$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = $_POST['nombre'];
  $apellido_p = $_POST['apellido_p'];
  $apellido_m = $_POST['apellido_m'];
  $area = $_POST['area_produccion'];
  $puesto = $_POST['puesto'];
  $fecha_ingreso = $_POST['fecha_ingreso'];
  $correo = $_POST['correo'];
  $id_rol = $_POST['id_rol'];

  $usuario = generarUsuario($nombre, $apellido_p);
  $contrasena = generarContrasena();
  $hash = password_hash($contrasena, PASSWORD_DEFAULT);

  $sql = "INSERT INTO Operadores (
    Nombre, Apellido_P, Apellido_M, Area_Produccion, Puesto, Fecha_Ingreso,
    Correo_Electronico, Usuario, Contrasena_Hash, Activo, ID_Rol, Fecha_Registro
  ) VALUES (
    '$nombre', '$apellido_p', '$apellido_m', '$area', '$puesto', '$fecha_ingreso',
    '$correo', '$usuario', '$hash', TRUE, $id_rol, CURDATE()
  )";

  if (mysqli_query($conn, $sql)) {
    $mensaje = "<div style='color: green;'>
                  <h3>✅ Operador registrado correctamente</h3>
                  <p><strong>Usuario:</strong> $usuario</p>
                  <p><strong>Contraseña:</strong> $contrasena</p>
                </div>";
  } else {
    $mensaje = "<p style='color: red;'>❌ Error al registrar operador: " . mysqli_error($conn) . "</p>";
  }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Operador</title>
  <link rel="stylesheet" href="../style.css">
  
</head>
<body>
<div class="contenedor-pagina">
  <div class="encabezado">
    <div class="navbar-brand">🌱 Sistema Plantulas</div>
    <h2>Registro de Operadores</h2>
    <div>
      <a href="panel_admin.php"><button>Volver al Panel</button></a>
    </div>
  </div>

  <main>
    <?php echo $mensaje; ?>

    <form method="POST">
      <label>Nombre:
        <input type="text" name="nombre" required>
      </label>

      <label>Apellido Paterno:
        <input type="text" name="apellido_p" required>
      </label>

      <label>Apellido Materno:
        <input type="text" name="apellido_m" required>
      </label>

      <label>Área de Producción:
        <input type="text" name="area_produccion">
      </label>

      <label>Puesto:
        <input type="text" name="puesto" required>
      </label>

      <label>Fecha de Ingreso:
        <input type="date" name="fecha_ingreso" required>
      </label>

      <label>Correo Electrónico:
        <input type="email" name="correo">
      </label>

      <label>Rol del sistema:
        <select name="id_rol" required>
          <option value="">-- Selecciona un rol --</option>
          <option value="1">Administrador</option>
          <option value="2">Operador</option>
          <option value="3">Supervisor</option>
          <option value="4">Solo Consulta</option>
        </select>
      </label>

      <button type="submit">Registrar Operador</button>
    </form>
  </main>

  <footer>
    Sistema de Producción de Plantas &copy; <?php echo date("Y"); ?>
  </footer>
  </div>
</body>
</html>
