<?php
include 'db.php'; // Conectar a la BD
session_start(); // Iniciar sesión para manejar el ID del operador

// Suponemos que el operador ha iniciado sesión y su ID está en $_SESSION
$ID_Operador = $_SESSION['ID_Operador'] ?? null;

// Verificar si el operador tiene una asignación activa para hoy
$fecha_actual = date('Y-m-d');

$sql = "SELECT * FROM Asignaciones 
        WHERE ID_Operador = ? AND Fecha = ? AND Estado = 'Pendiente' 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $ID_Operador, $fecha_actual);
$stmt->execute();
$result = $stmt->get_result();
$asignacion = $result->fetch_assoc();

// Si no hay asignación válida, mostrar error y bloquear registro
if (!$asignacion) {
    die("<script>alert('No tienes una asignación activa para hoy. Contacta a tu supervisor.'); window.location.href='dashboard_cultivo.php';</script>");
}

// Si el operador está asignado, obtener los detalles
$ID_Variedad = $asignacion['ID_Variedad'];
$ID_Etapa = $asignacion['ID_Etapa'];
$ID_Asignacion = $asignacion['ID_Asignacion'];

// Si se envió el formulario, registrar los datos en la tabla correspondiente
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tasa = $_POST["tasa_multiplicacion"];
    $id_medio = $_POST["id_medio_nutritivo"];
    $fecha_medio = $_POST["fecha_medio"];
    $num_brotes = $_POST["numero_brotes"];
    $tupper_lleno = $_POST["tupper_lleno"];
    $tupper_vacio = $_POST["tupper_vacios"];

    // Insertar en la tabla correcta según la etapa asignada
    if ($ID_Etapa == 1) { // 1 = Multiplicación
        $sql_insert = "INSERT INTO Multiplicacion 
                      (ID_Asignacion, Fecha_Siembra, ID_Variedad, Tasa_Multiplicacion, ID_MedioNutritivo, Fecha_MedioNutritivo, Cantidad_Dividida, Tuppers_Llenos, Tuppers_Desocupados) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } else { // 2 = Enraizamiento
        $sql_insert = "INSERT INTO Enraizamiento 
                      (ID_Asignacion, Fecha_Siembra, ID_Variedad, Tasa_Multiplicacion, ID_MedioNutritivo, Fecha_MedioNutritivo, Cantidad_Dividida, Tuppers_Llenos, Tuppers_Desocupados) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("ississiii", $ID_Asignacion, $fecha_actual, $ID_Variedad, $tasa, $id_medio, $fecha_medio, $num_brotes, $tupper_lleno, $tupper_vacio);

    if ($stmt_insert->execute()) {
        // Marcar la asignación como completada
        $sql_update = "UPDATE Asignaciones SET Estado = 'Completado' WHERE ID_Asignacion = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("i", $ID_Asignacion);
        $stmt_update->execute();

        echo "<script>alert('Registro guardado exitosamente.'); window.location.href='dashboard_cultivo.php';</script>";
    } else {
        echo "<script>alert('Error al guardar el registro.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte Disección</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
      /* Se mantiene el mismo CSS */
      html, body {
        height: 100%;
        margin: 0;
        display: flex;
        flex-direction: column;
        border: 4px solid #45814d;
      }

      .encabezado {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background-color: #45814d;
        color: white;
        font-size: 22px;
        padding: 10px 20px;
      }

      .encabezado .navbar-brand {
        display: flex;
        align-items: center;
      }

      .form-left label {
        font-weight: bold;
        margin-bottom: 5px;
      }

      .form-left input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 16px;
      }

      .centrado-horizontal {
        width: 50%;
        margin: 0 auto;
        padding: 20px;
        text-align: center;
      }

      footer {
        background-color: #45814d;
        color: white;
        text-align: center;
        padding: 10px 0;
      }
    </style>
</head>
<body>
    <header>
        <div class="encabezado">
            <a class="navbar-brand" href="#">
                <img src="logoplantulas.png" alt="Logo" width="130" height="124" class="d-inline-block align-text-center" />
                OPERADOR
            </a>
            <div>
                <h2>Reporte de siembra</h2>
                <p>Llena tu reporte de hoy, con la información que se pide</p>
            </div>
        </div>
    </header>

    <main>
        <h2>Registro de reporte</h2>
        <div class="form-content">
            <form class="form-left" method="POST" action="reporte_diseccion.php">
                <div>
                    <label for="tasa_multiplicacion">Tasa de Multiplicación:</label>
                    <input type="text" id="tasa_multiplicacion" name="tasa_multiplicacion" required>
                </div>
                <div>
                    <label for="id_medio_nutritivo">Medio Nutritivo:</label>
                    <input type="text" id="id_medio_nutritivo" name="id_medio_nutritivo" required>
                </div>
                <div>
                    <label for="fecha_medio">Fecha del Medio Nutritivo:</label>
                    <input type="date" id="fecha_medio" name="fecha_medio" required>
                </div>
                <div>
                    <label for="numero_brotes">Número de Brotes Divididos:</label>
                    <input type="text" id="numero_brotes" name="numero_brotes" required>
                </div>
                <div>
                    <label for="tupper_lleno">Tuppers llenos:</label>
                    <input type="text" id="tupper_lleno" name="tupper_lleno" required>
                </div>
                <div>
                    <label for="tupper_vacios">Tuppers vacíos:</label>
                    <input type="text" id="tupper_vacios" name="tupper_vacios" required>
                </div>
                <div class="centrado-horizontal">
                    <button type="submit">Guardar información</button>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
