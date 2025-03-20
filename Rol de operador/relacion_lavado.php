<?php
include 'db.php';
session_start();

// Verificar si el operador ha iniciado sesión
if (!isset($_SESSION["ID_Operador"])) {
    echo "<script>alert('Debes iniciar sesión primero.'); window.location.href='login.php';</script>";
    exit();
}

$ID_Operador = $_SESSION["ID_Operador"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fecha = date('Y-m-d');
    $id_tupper = $_POST["id_tupper"];
    $id_variedad = $_POST["id_variedad"];
    $cantidad_lavada = $_POST["cantidad_lavada"];

    // Insertar en la base de datos
    $sql = "INSERT INTO lavado_plantas (ID_Tupper, ID_Variedad, Fecha_Lavado, Cantidad_Lavada, Operador_Responsable) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisii", $id_tupper, $id_variedad, $fecha, $cantidad_lavada, $ID_Operador);

    if ($stmt->execute()) {
        echo "<script>alert('Registro de lavado guardado correctamente.'); window.location.href='relacion_lavado.php';</script>";
    } else {
        echo "<script>alert('Error al registrar el lavado.');</script>";
    }
}

// Obtener registros de lavado del operador actual
$sql_registros = "SELECT LP.Fecha_Lavado, LP.Cantidad_Lavada, V.Nombre_Variedad, LP.ID_Tupper
                  FROM lavado_plantas LP
                  JOIN Variedades V ON LP.ID_Variedad = V.ID_Variedad
                  WHERE LP.Operador_Responsable = ?
                  ORDER BY LP.Fecha_Lavado DESC";
$stmt = $conn->prepare($sql_registros);
$stmt->bind_param("i", $ID_Operador);
$stmt->execute();
$result_registros = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relación de Lavado - Plantulas Agrodex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Se mantiene el CSS original */
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
            background-color: #45814D;
            color: white;
            font-size: 22px;
            padding: 10px 20px;
        }

        .encabezado .navbar-brand {
            display: flex;
            align-items: center;
        }

        .barra-navegacion .navbar {
            background-color: #6FAF71 !important;
            padding: 0;
        }

        .form-container {
            background-color: white;
            width: 90%;
            max-width: 600px;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
        }

        .form-title {
            font-size: 24px;
            color: #2A2A2A;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #45814D;
        }

        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #D9B310;
            border-radius: 5px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            background-color: #45814D;
            color: white;
            margin-top: 20px;
        }

        footer {
            background-color: #45814D;
            color: white;
            text-align: center;
            padding: 15px 0;
            margin-top: auto;
        }
    </style>
</head>
<body>
    <header>
        <div class="encabezado">
            <a class="navbar-brand" href="#">
                <img src="logoplantulas.png" alt="Logo" width="130" height="124">
                PLANTULAS AGRODEX
            </a>
            <h2>RELACIÓN DE LAVADO</h2>
        </div>
    </header>

    <main>
        <div class="form-container">
            <h1 class="form-title">Registro de Lavado</h1>
            <form method="POST" action="relacion_lavado.php">
                <div class="form-group">
                    <label for="id_tupper">ID del Tupper:</label>
                    <input type="text" id="id_tupper" name="id_tupper" placeholder="Ingrese el ID del tupper" required>
                </div>

                <div class="form-group">
                    <label for="id_variedad">Variedad:</label>
                    <input type="text" id="id_variedad" name="id_variedad" placeholder="Ingrese el ID de la variedad" required>
                </div>

                <div class="form-group">
                    <label for="cantidad_lavada">Cantidad Lavada:</label>
                    <input type="number" id="cantidad_lavada" name="cantidad_lavada" placeholder="Ingrese la cantidad lavada" required>
                </div>

                <button type="submit" class="submit-btn">Guardar Información</button>
            </form>
        </div>

        <h2>Historial de Lavado</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>ID Tupper</th>
                    <th>Variedad</th>
                    <th>Cantidad Lavada</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result_registros->fetch_assoc()) { ?>
                    <tr>
                        <td><?= $row['Fecha_Lavado']; ?></td>
                        <td><?= $row['ID_Tupper']; ?></td>
                        <td><?= $row['Nombre_Variedad']; ?></td>
                        <td><?= $row['Cantidad_Lavada']; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </main>

    <footer>
        <p>&copy; 2025 PLANTAS AGRODEX. Todos los derechos reservados.</p>
    </footer>
</body>
</html>
