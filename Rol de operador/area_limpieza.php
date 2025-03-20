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
    $area = implode(", ", $_POST["areas"]); // Convertir las áreas seleccionadas en un string separado por comas
    $estado = implode(", ", $_POST["estado"]); // Convertir los estados de limpieza en string separado por comas

    // Insertar en la base de datos
    $sql = "INSERT INTO Registro_Limpieza (ID_Operador, Fecha, Area, Estado_Limpieza) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $ID_Operador, $fecha, $area, $estado);

    if ($stmt->execute()) {
        echo "<script>alert('Registro de limpieza guardado correctamente.'); window.location.href='area_limpieza.php';</script>";
    } else {
        echo "<script>alert('Error al registrar la limpieza.');</script>";
    }
}

// Obtener registros de limpieza del operador actual
$sql_registros = "SELECT * FROM Registro_Limpieza WHERE ID_Operador = ? ORDER BY Fecha DESC";
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
    <title>Limpieza - Plantulas Agrodex</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Se mantiene el CSS original */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            border: 25px solid #45814D;
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

        .content {
            display: flex;
            justify-content: center;
            gap: 2%;
            margin-top: 20px;
        }

        .section {
            width: 49%;
            border: 1px solid #D9B310;
            padding: 20px;
            border-radius: 5px;
            background: white;
            margin-bottom: 20px;
        }

        .save-button {
            display: block;
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            text-align: center;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background-color: #45814D;
            color: white;
            font-weight: bold;
            transition: 0.3s;
        }

        input[type="text"], input[type="date"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        footer {
            background-color: #45814D;
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
                <img src="logoplantulas.png" alt="Logo" width="130" height="124">
                PLANTULAS AGRODEX
            </a>
            <h1>🌿 LIMPIEZA DE ÁREAS</h1>
        </div>
    </header>

    <main>
        <h2>Registro de Limpieza</h2>
        <form method="POST" action="area_limpieza.php">
            <div class="content">
                <div class="section">
                    <h2>Áreas limpiadas</h2>
                    <h3>Selecciona las áreas:</h3>
                    <select name="areas[]" multiple required>
                        <option value="Área común">Área común</option>
                        <option value="Baños">Baños</option>
                        <option value="Zona de secado de tupper">Zona de secado de tupper</option>
                        <option value="Zona de almacenamiento de tupper">Zona de almacenamiento de tupper</option>
                        <option value="Zona de tupper vacío">Zona de tupper vacío</option>
                        <option value="Zona de cajas vacías y osmocis">Zona de cajas vacías y osmocis</option>
                        <option value="Incubador">Incubador</option>
                        <option value="Zona de zapatos">Zona de zapatos</option>
                        <option value="Área de preparación de medios">Área de preparación de medios</option>
                        <option value="Área de reactivos">Área de reactivos</option>
                        <option value="Siembras etapa 2">Siembras etapa 2</option>
                        <option value="Siembras etapa 3">Siembras etapa 3</option>
                    </select>
                </div>

                <div class="section">
                    <h2>Estado de Limpieza</h2>
                    <h3>Validación:</h3>
                    <select name="estado[]" multiple required>
                        <option value="Limpio">Limpio</option>
                        <option value="Sucia">Sucia</option>
                        <option value="Parcialmente Limpia">Parcialmente Limpia</option>
                    </select>
                    <button class="save-button" type="submit">Guardar Registro</button>
                </div>
            </div>
        </form>

        <h2>Historial de Limpieza</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Áreas</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result_registros->fetch_assoc()) { ?>
                    <tr>
                        <td><?= $row['Fecha']; ?></td>
                        <td><?= $row['Area']; ?></td>
                        <td><?= $row['Estado_Limpieza']; ?></td>
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
