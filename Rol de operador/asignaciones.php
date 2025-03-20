<?php
include 'db.php'; // Conectar a la BD
session_start(); // Iniciar sesión para control de permisos

// Verificar si el usuario es un supervisor o administrador
$ID_Supervisor = $_SESSION['ID_Operador'] ?? null; // Asumimos que el supervisor está autenticado

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ID_Operador = $_POST["ID_Operador"];
    $ID_Variedad = $_POST["ID_Variedad"];
    $ID_Etapa = $_POST["ID_Etapa"];
    $Fecha = $_POST["Fecha"];

    // Verificar si el operador ya tiene una asignación para la fecha
    $sql_check = "SELECT * FROM Asignaciones WHERE ID_Operador = ? AND Fecha = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("is", $ID_Operador, $Fecha);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        echo "<script>alert('El operador ya tiene una asignación para esta fecha.');</script>";
    } else {
        // Insertar nueva asignación
        $sql_insert = "INSERT INTO Asignaciones (ID_Operador, ID_Variedad, ID_Etapa, Fecha, Estado) 
                       VALUES (?, ?, ?, ?, 'Pendiente')";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("iiis", $ID_Operador, $ID_Variedad, $ID_Etapa, $Fecha);

        if ($stmt_insert->execute()) {
            echo "<script>alert('Asignación registrada correctamente.'); window.location.href='asignaciones.php';</script>";
        } else {
            echo "<script>alert('Error al registrar la asignación.');</script>";
        }
    }
}

// Obtener la lista de operadores
$sql_operadores = "SELECT ID_Operador, Nombre FROM Operadores";
$result_operadores = $conn->query($sql_operadores);

// Obtener la lista de variedades
$sql_variedades = "SELECT ID_Variedad, Nombre_Variedad FROM Variedades";
$result_variedades = $conn->query($sql_variedades);

// Obtener la lista de etapas desde `Catalogo_Etapas`
$sql_etapas = "SELECT ID_Etapa, Descripcion FROM Catalogo_Etapas";
$result_etapas = $conn->query($sql_etapas);

// Obtener las asignaciones actuales
$sql_asignaciones = "SELECT A.ID_Asignacion, O.Nombre AS Operador, V.Nombre_Variedad, C.Descripcion AS Etapa, A.Fecha, A.Estado 
                     FROM Asignaciones A
                     JOIN Operadores O ON A.ID_Operador = O.ID_Operador
                     JOIN Variedades V ON A.ID_Variedad = V.ID_Variedad
                     JOIN Catalogo_Etapas C ON A.ID_Etapa = C.ID_Etapa
                     ORDER BY A.Fecha DESC";
$result_asignaciones = $conn->query($sql_asignaciones);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asignación de Operadores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
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
      .form-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 10px;
        background: #fff;
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
                SUPERVISOR
            </a>
            <div>
                <h2>Asignación de Operadores</h2>
                <p>Asigna variedad y etapa a cada operador</p>
            </div>
        </div>
    </header>

    <main>
        <h2>Registrar Asignación</h2>
        <div class="form-container">
            <form method="POST" action="asignaciones.php">
                <div>
                    <label for="ID_Operador">Selecciona el Operador:</label>
                    <select name="ID_Operador" required>
                        <option value="">-- Selecciona un operador --</option>
                        <?php while ($row = $result_operadores->fetch_assoc()) { ?>
                            <option value="<?= $row['ID_Operador']; ?>"><?= $row['Nombre']; ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label for="ID_Variedad">Selecciona la Variedad:</label>
                    <select name="ID_Variedad" required>
                        <option value="">-- Selecciona una variedad --</option>
                        <?php while ($row = $result_variedades->fetch_assoc()) { ?>
                            <option value="<?= $row['ID_Variedad']; ?>"><?= $row['Nombre_Variedad']; ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label for="ID_Etapa">Selecciona la Etapa:</label>
                    <select name="ID_Etapa" required>
                        <option value="">-- Selecciona una etapa --</option>
                        <?php while ($row = $result_etapas->fetch_assoc()) { ?>
                            <option value="<?= $row['ID_Etapa']; ?>"><?= $row['Descripcion']; ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label for="Fecha">Fecha de Trabajo:</label>
                    <input type="date" name="Fecha" required>
                </div>

                <div class="centrado-horizontal">
                    <button type="submit">Asignar</button>
                </div>
            </form>
        </div>

        <h2>Asignaciones Actuales</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Operador</th>
                    <th>Variedad</th>
                    <th>Etapa</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result_asignaciones->fetch_assoc()) { ?>
                    <tr>
                        <td><?= $row['Operador']; ?></td>
                        <td><?= $row['Nombre_Variedad']; ?></td>
                        <td><?= $row['Etapa']; ?></td>
                        <td><?= $row['Fecha']; ?></td>
                        <td><?= $row['Estado']; ?></td>
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
git