<?php
$servername = "localhost"; // Servidor de MySQL
$username = "root"; // Usuario (por defecto en XAMPP)
$password = ""; // Sin contraseña en XAMPP
$database = "produccion_laboratorio"; // Nombre de tu base de datos

// Crear conexión
$conn = new mysqli($servername, $username, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
