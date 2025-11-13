<?php
    require_once 'logout.php';

require_once 'funciones_recoger.php';

if (!isset($_SESSION['idusuario']) || $_SESSION['idusuario'] != "ADMIN") {
    header("Location: consulta_inventario.php");
    exit();
}

function agregarColumnas($con, $numeroLinea) {
    // Agregar un 0 a la izquierda si el número de línea es un solo dígito
    $numeroLinea = str_pad($numeroLinea, 2, "0", STR_PAD_LEFT);

    $columnaLinea = "linea_" . $numeroLinea;
    $columnaAlterno = "linea_" . $numeroLinea . "_alterno";
    $columnaComplementario = "linea_" . $numeroLinea . "_complementario";
    $columnaPrecio = "linea_" . $numeroLinea . "_precio"; // New column

    if (existeLinea($con, $numeroLinea)) {
        echo "<script>";
        echo "alert('La linea $numeroLinea ya existe, por lo que no se pudo agregar. Ingrese una linea que no sea existente.');";
        echo "window.history.back();"; // Volver atrás en el historial del navegador
        echo "</script>";
        return false;
    }

    // Preparar y ejecutar la consulta para agregar las columnas
    $query = "
        ALTER TABLE acceso_a_usuarios
        ADD COLUMN $columnaLinea BOOLEAN DEFAULT 0,
        ADD COLUMN $columnaAlterno BOOLEAN DEFAULT 0,
        ADD COLUMN $columnaComplementario BOOLEAN DEFAULT 0,
        ADD COLUMN $columnaPrecio BOOLEAN DEFAULT 0
    ";

    if ($con->exec($query) !== false) {
        echo "<script>";
        echo "if (confirm('Las columnas para la linea $numeroLinea han sido agregadas correctamente. Presione OK para continuar.')) {";
        echo "  window.location.href = 'usuarios.php';"; // Redireccionar a la página principal
        echo "} else {";
        echo "  window.location.href = 'usuarios.php';"; // Redireccionar a la página principal si cancela
        echo "}";
        echo "</script>";
    } else {
        echo "<script>";
        echo "alert('La linea $numeroLinea no se pudo agregar.');";
        echo "window.history.back();"; // Volver atrás en el historial del navegador
        echo "</script>";
    }
}



function eliminarColumnas($con, $numeroLinea) {

    $numeroLinea = str_pad($numeroLinea, 2, "0", STR_PAD_LEFT);
    
    $columnaLinea = "linea_" . $numeroLinea;
    $columnaAlterno = "linea_" . $numeroLinea . "_alterno";
    $columnaComplementario = "linea_" . $numeroLinea . "_complementario";
    $columnaPrecio = "linea_" . $numeroLinea . "_precio"; // New column

    if (!existeLinea($con, $numeroLinea)) {
        echo "<script>";
        echo "alert('La linea $numeroLinea no existe, por lo que no se pudo eliminar. Ingrese una linea que sea existente.');";
        echo "window.history.back();"; // Volver atrás en el historial del navegador
        echo "</script>";
        return false;
    }

    // Preparar y ejecutar la consulta para eliminar las columnas
    $query = "
        ALTER TABLE acceso_a_usuarios
        DROP COLUMN $columnaLinea,
        DROP COLUMN $columnaAlterno,
        DROP COLUMN $columnaComplementario,
        DROP COLUMN $columnaPrecio
    ";

    if ($con->exec($query) !== false) {
        echo "<script>";
        echo "if (confirm('Las columnas para la linea $numeroLinea han sido eliminadas correctamente. Presione OK para continuar.')) {";
        echo "  window.location.href = 'usuarios.php';"; // Redireccionar a la página principal
        echo "} else {";
        echo "  window.location.href = 'usuarios.php';"; // Redireccionar a la página principal si cancela
        echo "}";
        echo "</script>";
    } else {
        echo "<script>";
        echo "alert('La linea $numeroLinea no se pudo eliminar.');";
        echo "window.history.back();"; // Volver atrás en el historial del navegador
        echo "</script>";
    }
}


$con = ConectarBaseDatos();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['agregar_linea'])) {
        $nombreLinea = $_POST['nombre_linea'];
        agregarColumnas($con, $nombreLinea);
    } elseif (isset($_POST['eliminar_linea'])) {
        $nombreLinea = $_POST['nombre_linea'];
        eliminarColumnas($con, $nombreLinea);
    }
}
?>
