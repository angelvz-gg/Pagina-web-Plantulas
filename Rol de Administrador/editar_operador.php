<?php
session_start();
require '../db.php';

// Verificación de sesión y rol
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 1) {
  header("Location: ../login.php");
  exit();
}

// Obtener el ID del operador
if (!isset($_GET['id'])) {
  echo "ID no especificado.";
  exit();
}

$id = intval($_GET['id']);
$mensaje = "";

// Actualizar datos si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = $_POST['nombre'];
  $apellido_p = $_POST['apellido_p'];
  $apellido_m = $_POST['apellido_m'];
  $correo = $_POST['correo'];
  $puesto = $_POST['puesto'];
  $area = $_POST['area_produccion'];
  $id_rol = $_POST['id_rol'];

  $sql = "UPDATE Operadores SET 
    Nombre = '$nombre',
    Apellido_P = '$apellido_p',
    Apellido_M = '$apellido_m',
    Correo_Electronico = '$correo',
    Puesto = '$puesto',
    Area_Produccion = '$area',
    ID_Rol = $id_rol,
    Fecha_Actualizacion = NOW()
    WHERE ID_Operador = $id";

  if (mysqli_query($conn, $sql)) {
    $mensaje = "<p style='color: green;'>✅ Datos actualizados correctamente</p>";
  } else {
    $mensaje = "<p style='color: red;'>❌ Error: " . mysqli_error($conn) . "</p>";
  }
}

// Obtener los datos actuales del operador
$res = mysqli_query($conn, "SELECT * FROM Operadores WHERE ID_Operador = $id");
$operador = mysqli_fetch_assoc($res);

if (!$operador) {
  echo "Operador no encontrado.";
  exit();
}

// Obtener roles disponibles
$roles = mysqli_query($conn, "SELECT * FROM Roles");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Operador</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="contenedor-pagina">
  <div class="encabezado">
    <div class="navbar-brand">🌱 Sistema Plantulas</div>
    <h2>Editar Operador</h2>
    <div>
      <a href="gestionar_operadores.php"><button>Volver</button></a>
    </div>
  </div>

  <main>
    <?php echo $mensaje; ?>

    <form method="POST">
      <label>Nombre:
        <input type="text" name="nombre" value="<?php echo $operador['Nombre']; ?>" required>
      </label>

      <label>Apellido Paterno:
        <input type="text" name="apellido_p" value="<?php echo $operador['Apellido_P']; ?>" required>
      </label>

      <label>Apellido Materno:
        <input type="text" name="apellido_m" value="<?php echo $operador['Apellido_M']; ?>" required>
      </label>

      <label>Correo Electrónico:
        <input type="email" name="correo" value="<?php echo $operador['Correo_Electronico']; ?>">
      </label>

      <label>Puesto:
        <input type="text" name="puesto" value="<?php echo $operador['Puesto']; ?>" required>
      </label>

      <label>Área de Producción:
        <input type="text" name="area_produccion" value="<?php echo $operador['Area_Produccion']; ?>">
      </label>

      <label>Rol del sistema:
        <select name="id_rol" required>
          <?php while ($rol = mysqli_fetch_assoc($roles)) : ?>
            <option value="<?php echo $rol['ID_Rol']; ?>" <?php if ($rol['ID_Rol'] == $operador['ID_Rol']) echo 'selected'; ?>>
              <?php echo $rol['Nombre_Rol']; ?>
            </option>
          <?php endwhile; ?>
        </select>
      </label>

      <button type="submit">Guardar Cambios</button>
    </form>
  </main>

  <footer>
    Sistema de Producción de Plantas &copy; <?php echo date("Y"); ?>
  </footer>
</div>
</body>
</html>
