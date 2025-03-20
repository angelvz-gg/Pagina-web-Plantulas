<?php
header('Content-Type: application/json');
require 'conexion.php'; // Asegurar conexión a la base de datos

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['origen'], $data['cantidad'], $data['fechaSeleccion'], $data['lavadosPrevios'])) {
    echo json_encode(['error' => 'Faltan parámetros en la solicitud']);
    exit;
}

$origen = $data['origen'];
$cantidad = (int) $data['cantidad'];
$fechaSeleccion = $data['fechaSeleccion'];
$lavadosPrevios = (int) $data['lavadosPrevios'];

// Insertar el nuevo registro de lavado
$query = "INSERT INTO Historial_Lavados_Tuppers (Fecha_Lavado, Area_Procedencia, Cantidad, Lavados_Acumulados) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$nuevoLavado = $lavadosPrevios + 1;
$stmt->bind_param("ssii", $fechaSeleccion, $origen, $cantidad, $nuevoLavado);

if ($stmt->execute()) {
    echo json_encode(['mensaje' => 'Lavado registrado exitosamente']);
} else {
    echo json_encode(['error' => 'Error al registrar el lavado']);
}

$stmt->close();
$conn->close();
