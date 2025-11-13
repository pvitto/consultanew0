<?php


require_once 'logout.php';

require_once 'funciones_recoger.php';
require_once 'funciones_tablas.php';

if (!isset($_SESSION['idusuario']) || $_SESSION['idusuario'] != "ADMIN") {
    header("Location: consulta_inventario.php");
    exit();
}

function actualizarPermisos($con, $usuario, $permisos) {
    // Recibo los permisos para dividirlos en columna (linea, linea alterno, linea complemento) y permiso (falso o verdadero)
    $setPart = [];
    foreach ($permisos as $columna => $valor) {
        $setPart[] = "$columna = :$columna";
    }
    $setQuery = implode(", ", $setPart);

    // Query para actualizar los permisos de la base de datos utilizando la informacion recibida
    $query = $con->prepare("UPDATE acceso_a_usuarios SET $setQuery WHERE usuario = :usuario");
    $query->bindParam(':usuario', $usuario);
    foreach ($permisos as $columna => $valor) {
        $query->bindValue(":$columna", $valor, PDO::PARAM_BOOL);
    }

    $query->execute();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permisos de Usuario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">

    <script>
        // Función para desmarcar todos los checkboxes alternos
        function marcarDesmarcarAlternos(marcar) {
            var checkboxes = document.querySelectorAll('input[name^="permisos[linea_"][name$="_alterno]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = marcar;
            });
        }

        // Función para marcar o desmarcar todos los checkboxes complementarios
        function marcarDesmarcarComplementarios(marcar) {
            var checkboxes = document.querySelectorAll('input[name^="permisos[linea_"][name$="_complementario]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = marcar;
            });
        }

        // Función para marcar o desmarcar todos los checkboxes precios
        function marcarDesmarcarPrecios(marcar) {
            var checkboxes = document.querySelectorAll('input[name^="permisos[linea_"][name$="_precio]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = marcar;
            });
        }

        // Función para marcar o desmarcar todos los checkboxes descripciones
        function marcarDesmarcarDescripciones(marcar) {
            var checkboxes = document.querySelectorAll('input[name^="permisos[linea_"][name$="_descripcion]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = marcar;
         });
}
    </script>
    
</head>
<body>
    <div class="container">
        <?php
        $con = ConectarBaseDatos();

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar'])) {
            $usuario = $_POST['usuario'];

            $permisos = [];
            foreach ($_POST['permisos'] as $columna => $valor) {

                // Revisa si la key ya existe en caso de que sea una columna de alterno o complementario que ya ha sido creada y bloqueada por una iteracion anterior
                if (array_key_exists($columna, $permisos))
                    continue;
                
                $permisos[$columna] = $valor === '0' ? 0 : 1;
                
                // Si la columna es una columna de permiso de linea y tiene el acceso denegado, procede a bloquear los alternos y complementarios de esta
                if ((strpos($columna, "alterno") === false && strpos($columna, "complementario") === false) && $permisos[$columna] == '1' && strpos($columna, "precio") === false &&
    strpos($columna, "_descripcion") === false)
                {

                    $permisos[$columna . "_alterno"] = 1;
                    $permisos[$columna . "_complementario"] = 1;
                    $permisos[$columna . "_precio"] = 1;
                    $permisos[$columna . "_descripcion"] = 1;
                }
            }
            actualizarPermisos($con, $usuario, $permisos);
            echo "<div class='alert alert-success'>Permisos actualizados correctamente para el usuario '$usuario'.</div>";
        }


        if (isset($_GET['usuario']) || isset($_POST['usuario'])) {
            $usuario = $_GET['usuario'] ?? $_POST['usuario'];
            renderizarTabla($con, $usuario);
        }
        ?>
    </div>
</body>
</html>
