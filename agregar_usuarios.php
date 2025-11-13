<?php 


require_once 'logout.php';

    if (!isset($_SESSION['idusuario']) || $_SESSION['idusuario'] != "ADMIN") {
        header("Location: consulta_inventario.php");
        exit();
    }

    function AgregarUsuario($con, $nuevoUsuario) {
        // Recoger las líneas de la base de datos
        $lineas = recogerLineas_crearTabla($con);

        // Crear un array de acceso con las columnas correspondientes, con valores predeterminados de 0
        $accesoDatos = ['usuario' => $nuevoUsuario];
        foreach ($lineas as $linea) {
            $accesoDatos["linea_" . $linea] = 0;
            $accesoDatos["linea_" . $linea . "_alterno"] = 0;
            $accesoDatos["linea_" . $linea . "_complementario"] = 0;
            $accesoDatos["linea_" . $linea . "_precio"] = 0;
        }

        // Crear la lista de columnas y valores para la inserción
        $colLista = implode(", ", array_keys($accesoDatos));
        $molde = implode(", ", array_map(function($col) { return ":$col"; }, array_keys($accesoDatos)));
        $updateList = implode(", ", array_map(function($col) { return "$col = VALUES($col)"; }, array_keys($accesoDatos)));

        // Preparar y ejecutar la consulta de inserción
        $query = $con->prepare("INSERT INTO acceso_a_usuarios ($colLista) VALUES ($molde) ON DUPLICATE KEY UPDATE $updateList");
        $query->execute($accesoDatos);
    }


    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        require_once "funciones_recoger.php";
        $nombreUsuario = trim($_POST['nombreUsuario']);

        try {
            // Conectar a la base de datos
            $con = ConectarBaseDatos();
    
            // Verificar si el usuario ya existe
            $query_verificar = $con->prepare("SELECT COUNT(*) AS existe FROM acceso_a_usuarios WHERE usuario = :usuario");
            $query_verificar->bindParam(':usuario', $nombreUsuario);
            $query_verificar->execute();
            $resultado = $query_verificar->fetch(PDO::FETCH_ASSOC);
    
            if ($resultado['existe'] > 0) {
                // Si el usuario ya existe, mostrar un mensaje de error con opción de volver atrás
                echo "<script>";
                echo "alert('El usuario ya existe. Intente agregar otro usuario.');";
                echo "window.history.back();"; // Volver atrás en el historial del navegador
                echo "</script>";
            } else {
                
                AgregarUsuario($con, $nombreUsuario);
                
                // Mostrar mensaje de éxito y esperar confirmación del usuario
                echo "<script>";
                echo "if (confirm('Usuario agregado correctamente. Presione OK para continuar.')) {";
                echo "  window.location.href = 'usuarios.php';"; // Redireccionar a la página principal
                echo "} else {";
                echo "  window.location.href = 'usuarios.php';"; // Redireccionar a la página principal si cancela
                echo "}";
                echo "</script>";
            }
    
        } catch (PDOException $e) {
            // Manejo de errores de la base de datos
            echo "<script>";
            echo "alert('Error al agregar usuario: " . $e->getMessage() . "');";
            echo "window.history.back();"; // Volver atrás en el historial del navegador en caso de error
            echo "</script>";
        }
    }
?>