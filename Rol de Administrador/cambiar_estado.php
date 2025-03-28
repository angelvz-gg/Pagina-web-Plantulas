<?php
session_start();
require '../db.php';

// Verificar que es administrador
if (!isset($_SESSION['ID_Operador']) || $_SESSION['Rol'] != 1) {
  header("Location: ../login.php");
  exit();
}

// Validar datos recibidos
if (!isset($_GET['id']) || !isset($_GET['estado'])) {
  echo "Datos incompletos.";
  exit();
}

$id = intval($_GET['id']);
$nuevo_estado = intval($_GET['estado']); // 1 = activo, 0 = inactivo

// Actualizar estado
$sql = "UPDATE Operadores SET Activo = $nuevo_estado, Fecha_Actualizacion = NOW() WHERE ID_Operador = $id";

if (mysqli_query($conn, $sql)) {
  header("Location: gestionar_operadores.php");
  exit();
} else {
  echo "❌ Error al actualizar estado: " . mysqli_error($conn);
}
?>
