<?php
session_start();
require '../db.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 1) {
  header("Location: ../login.php");
  exit();
}

// Filtro por estado
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'todos';

$where = '';
if ($estado_filtro === 'activos') {
  $where = "WHERE o.Activo = 1";
} elseif ($estado_filtro === 'inactivos') {
  $where = "WHERE o.Activo = 0";
}

// Obtener lista de operadores
$operadores = mysqli_query($conn, "
  SELECT o.*, r.Nombre_Rol 
  FROM Operadores o
  LEFT JOIN Roles r ON o.ID_Rol = r.ID_Rol
  $where
  ORDER BY o.ID_Operador DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestionar Operadores</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <div class="contenedor-pagina">
    <div class="encabezado">
      <div class="navbar-brand">🌱 Sistema Plantulas</div>
      <h2>Gestionar Operadores</h2>
      <div>
        <a href="panel_admin.php"><button>Volver al Panel</button></a>
      </div>
    </div>

    <main>
      <!-- Filtro de estado -->
      <form method="GET" style="margin-bottom: 20px;">
        <label for="estado"><strong>Filtrar por estado:</strong></label>
        <select name="estado" id="estado" onchange="this.form.submit()">
          <option value="todos" <?php if ($estado_filtro === 'todos') echo 'selected'; ?>>Todos</option>
          <option value="activos" <?php if ($estado_filtro === 'activos') echo 'selected'; ?>>Activos</option>
          <option value="inactivos" <?php if ($estado_filtro === 'inactivos') echo 'selected'; ?>>Inactivos</option>
        </select>
      </form>

      <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; background:white;">
        <thead style="background-color: #45814d; color: white;">
          <tr>
            <th>ID</th>
            <th>Nombre completo</th>
            <th>Correo</th>
            <th>Puesto</th>
            <th>Área</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($op = mysqli_fetch_assoc($operadores)) : ?>
            <tr>
              <td><?php echo $op['ID_Operador']; ?></td>
              <td><?php echo $op['Nombre'] . " " . $op['Apellido_P'] . " " . $op['Apellido_M']; ?></td>
              <td><?php echo $op['Correo_Electronico']; ?></td>
              <td><?php echo $op['Puesto']; ?></td>
              <td><?php echo $op['Area_Produccion']; ?></td>
              <td><?php echo $op['Nombre_Rol']; ?></td>
              <td><?php echo ($op['Activo']) ? 'Activo' : 'Inactivo'; ?></td>
              <td>
                <a href="editar_operador.php?id=<?php echo $op['ID_Operador']; ?>"><button>✏️ Editar</button></a>
                <?php if ($op['Activo']) : ?>
                  <a href="cambiar_estado.php?id=<?php echo $op['ID_Operador']; ?>&estado=0"><button style="background-color: #dc3545; color: white;">Desactivar</button></a>
                <?php else : ?>
                  <a href="cambiar_estado.php?id=<?php echo $op['ID_Operador']; ?>&estado=1"><button style="background-color: #28a745; color: white;">Activar</button></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </main>

    <footer>
      Sistema de Producción de Plantas &copy; <?php echo date("Y"); ?>
    </footer>
  </div>
</body>
</html>
