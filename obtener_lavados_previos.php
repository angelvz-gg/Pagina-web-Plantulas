<?php
header('Content-Type: application/json');
require 'conexion.php'; // Asegúrate de tener un archivo de conexión a la base de datos

if (!isset($_GET['origen'])) {
    echo json_encode(['error' => 'Falta el parámetro de origen']);
    exit;
}

$origen = $_GET['origen'];

// Consulta para obtener el promedio de lavados previos según el área de procedencia
$query = "SELECT AVG(Lavados_Acumulados) as lavados FROM Historial_Lavados_Tuppers WHERE Area_Procedencia = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $origen);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode(['lavados' => round($data['lavados'], 0)]);
} else {
    echo json_encode(['lavados' => 0]);
}

$stmt->close();
$conn->close();
