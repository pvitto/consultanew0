<?php

// no muestre errores si no me ejecuta algo puedo quitarlo para ver los erroes
error_reporting(0);

//require_once 'logout.php';

//session_start();
/*if (!isset($_SESSION['idusuario'])) {
header("Location: login.php");
exit();
}*/

// Conecta a la base de datos
$con = mysqli_connect("localhost", "agrocosta_pagina", "Agr0costa*-", "agrocosta_db");
// Verifica si la conexión ha sido exitosa
if (!$con) {
    die("Error al conectar a la base de datos: " . mysqli_error($con));
}

function guardarMovimiento($usuario, $referencia, $fecha, $precio_con_descuento, $con)
{
    //consulta para insertar el movimiento en la tabla mov
    $sql = "INSERT INTO mov (idusuario, referencia, Precio,fechaconsulta) VALUES ('$usuario', '$referencia','$precio_con_descuento' ,'$fecha')";

    //ejecutar consulta y verificar si fue exitosa
    if (mysqli_query($con, $sql)) {
        // echo "Movimiento registrado exitosamente";
    } else {
        // echo "Error al registrar movimiento: " . mysqli_error($con);
    }
}

function Precio($usuario)
{
    //consulta para insertar el movimiento en la tabla mov
    $sql = "INSERT INTO mov (idusuario, referencia, fechaconsulta) VALUES ('$usuario', '$referencia', '$fecha')";

    //ejecutar consulta y verificar si fue exitosa
    if (mysqli_query($con, $sql)) {
        // echo "Movimiento registrado exitosamente";
    } else {
        // echo "Error al registrar movimiento: " . mysqli_error($con);
    }
}

function Alternos($usuario, $alternos)
{

    if ($usuario != "CIPARC" && $usuario != "CATEKO" && $usuario != "IMGRAN" && $usuario != "RETROC") {
        return explode("...", $alternos);
    } else {
        $alternos = "";
        return explode("...", $alternos);
        
    }
}

function BloquearLineas($usuario, $linea)
{

    if (($usuario == "DISMAQ" && $linea == 19) || ($usuario == "COORTI" && ($linea == 29 || $linea == 19 || $linea == 41 || $linea == 31 || $linea == 53)) || ($usuario == "FERIA1" && ($linea == 41 || $linea == 31 || $linea == 53))
        || ($usuario == "IMGRAN" && ($linea == 31 || $linea == 41 || $linea == 53 || $linea == 19 || $linea == 29 || $linea == 55 || $linea == 27))
        || ($usuario == "IMELGR" && ($linea == 19 || $linea == 29 || $linea == 39 || $linea == 55))
        || ($usuario == "CIPARC" && ($linea == 19 || $linea == 29 || $linea == 04 || $linea == 12 || $linea == 15 || $linea == 18 || $linea == 27 || $linea == 31 || $linea == 36 || $linea == 47 || $linea == 67 || $linea == 98))
        || ($usuario == "CATEKO" && ($linea == 19 || $linea == 29 || $linea == 31 || $linea == 41 || $linea == 04))
    ) {
        return true;
    } else {
        return false;
    }
}

function FiltrarDescripcion($palabra)
{


 // Obtener las palabras de la descripcion P****
$palabras = explode(' ', $palabra);

// Filtrar palabras que no contienen numeros y limitar a las primeras dos palabras
$palabras_filtradas = array_filter($palabras, function($word) {
    return !preg_match('/\d/', $word); // Filtra palabras que contienen números
});

// Limitar a las dos primeras palabras
$palabras_finales = array_slice($palabras_filtradas, 0, 2);

// Unir las palabras filtradas en una cadena de texto
$descripcion = implode(' ', $palabras_finales);

return $descripcion;


}

function Existencias($usuario, $result, $resultuse, $resultcostex, $con)
{

    $entra = 0;

    //primero vamos con referencias de agrocosat
    if ($result && mysqli_num_rows($result) > 0) {

        while ($fila = mysqli_fetch_assoc($result)) {

            if (BloquearLineas($usuario, $fila['Linea'])) {

                return $consulta = '<td>' . '0' . '</td>' . '<td>' . '0' . '</td>' . '<td>' . '0' . '</td>';
            }

            $entra = 1;
            if (($fila['Existencias'] > '0') && $fila['Tipo'] == 'agro') {
                $existencias = 'DISPONIBLE';
                $precio = 0;
            } elseif (($fila['Existencias']) < '1' && $fila['Tipo'] == 'agro') {
                $existencias = 'IMPORTACION - TIEMPO DE ENTREGA: 60 DIAS';
                $precio = 0;
            }
            /*
            elseif(($fila['Existencias'] >'0') && $fila['Tipo']=='costex')
            {
            $existencias= 'ESTE REPUESTO ESTA PARA IMPORTACION. TIEMPO DE ENTREGA: 5-7 DIAS';
            $precio =0;

            }*/

            // Aplicar descuentos si es necesario
            $referencia = $fila['Referencia'];
            $descripcion = $fila['descripcion'];
            $linea = $fila['Linea'];
            global $alternos;
            global $precio_con_descuento;

            if (!(BloquearLineas($usuario, $fila['Linea']))) {
                $alternos = $fila['Alternos'];
            }

            // sacamos el descuento a aplicar y listo
            $descuento = 0;

            $precio_con_descuento = round($precio - ($precio * $descuento / 100));
            $consulta = '<td>' . $referencia . '</td>' . '<td>' . FiltrarDescripcion($descripcion). '</td>' . '<td>' . $existencias . '</td>';
        }
    }

    //sacando alias o use
    if ($resultuse && mysqli_num_rows($resultuse) > 0 && $entra != 1) {

        $entra = 2;
        while ($fila = mysqli_fetch_assoc($resultuse)) {

            if ($fila['Tipo'] == 'use') {
                $existencias = $fila['Existencias'];
                $precio = 0;
            }

            $referenciaP = trim(substr($fila['descripcion'], 4, 100));

            //echo '<script>alert("' . $referenciaP .'");</script>';

            $sqlP = "SELECT * FROM inventario WHERE referencia ='$referenciaP'";
            $resultP = mysqli_query($con, $sqlP);

            if ($resultP && mysqli_num_rows($resultP) > 0) {

                while ($fila = mysqli_fetch_assoc($resultP)) {

                    if (BloquearLineas($usuario, $fila['Linea'])) {

                        return $consulta = '<td>' . '0' . '</td>' . '<td>' . '0' . '</td>' . '<td>' . '0' . '</td>';
                    }

                    if (($fila['Existencias'] > '0') && $fila['Tipo'] == 'agro') {
                        $existencias = 'DISPONIBLE';
                        $precio = 0;
                    }
                    // costex siempre sera mayor a 0 porque asi se ingresa en la BD no es del todo necesario comporbar existencias
                    elseif (($fila['Existencias'] > '0') && $fila['Tipo'] == 'costex') {
                        $existencias = 'ESTE REPUESTO ESTA PARA IMPORTACION. ENTREGA: 5-7 DIAS';
                        $precio = 0;
                    } elseif (($fila['Existencias']) < '1' && $fila['Tipo'] == 'agro') {
                        $existencias = 'IMPORTACION - TIEMPO DE ENTREGA: 60 DIAS';
                        $precio = 0;
                    }
                    /*
                    elseif(($fila['Existencias'] >'0') && $fila['Tipo']=='costex')
                    {
                    $existencias= 'ESTE REPUESTO ESTA PARA IMPORTACION. TIEMPO DE ENTREGA: 5-7 DIAS';
                    $precio = 0;

                    }*/

                    // Aplicar descuentos si es necesario
                    $referencia = $fila['Referencia'];
                    $descripcion = $fila['descripcion'];
                    $linea = $fila['Linea'];
                    global $alternos;
                    if (!(BloquearLineas($usuario, $fila['Linea']))) {
                        $alternos = $fila['Alternos'];
                    }

                    // sacamos el descuento a aplicar y listo
                    $descuento = 0;

                    $precio_con_descuento = round($precio - ($precio * $descuento / 100));

                    $consulta = '<td>' . $referencia . '</td>' . '<td>' . FiltrarDescripcion($descripcion)  . '</td>' . '<td>' . $existencias . '</td>';
                }

                echo "<script>window.onload = function() {";
                echo "var x = document.getElementById('Mensaje');"; // Obtenemos el div con id 'miDiv'
                // echo "if (x.style.display === 'none') {"; // Si el div está oculto
                echo "x.style.display = 'block';"; // Lo mostramos
                // echo "document.getElementById('Mensaje').innerHTML = 'Este es el texto cuando el div está visible';"; // Cambiamos el texto
                // echo "} else {"; // Si el div está visible
                //echo "x.style.display = 'none';"; // Lo ocultamos
                //echo "document.getElementById('Mensaje').innerHTML = 'Este es el texto cuando el div está oculto';"; // Cambiamos el texto
                //echo "}";
                echo "}</script>";
            } else {

                $referencia = $fila['Referencia'];
                $descripcion = $fila['descripcion'];
                $linea = $fila['Linea'];
                global $alternos;
                if (!(BloquearLineas($usuario, $fila['Linea']))) {
                    $alternos = $fila['Alternos'];
                }

                // sacamos el descuento a aplicar y listo
                $descuento = 0;

                $precio_con_descuento = round($precio - ($precio * $descuento / 100));
                $consulta = '<td>' . $referencia . '</td>' . '<td>' . FiltrarDescripcion($descripcion)  . '</td>' . '<td>' . $existencias . '</td>';
            }
        }
    }

    // sacando item de costex
    if ($resultcostex && mysqli_num_rows($resultcostex) > 0 && $entra != 2 && $entra != 1) {

        $entra = 3;
        while ($fila = mysqli_fetch_assoc($resultcostex)) {

            if (($fila['Existencias'] > '0') && $fila['Tipo'] == 'costex') {
                $existencias = 'ESTE REPUESTO ESTA PARA IMPORTACION. TIEMPO DE ENTREGA: 5-7 DIAS';
                $precio = 0;
            }

            /*
            elseif (($fila['Existencias']) <'1')
            {
            $existencias= 'IMPORTACION - TIEMPO DE ENTREGA: 60 DIAS';
            $precio = 0;

            }

            elseif(($fila['Existencias'] >'0') && $fila['Tipo']=='costex')
            {
            $existencias= 'ESTE REPUESTO ESTA PARA IMPORTACION. TIEMPO DE ENTREGA: 5-7 DIAS';
            $precio = 0;

            }*/

            // Aplicar descuentos si es necesario
            $referencia = $fila['Referencia'];
            $descripcion = $fila['descripcion'];
            $linea = $fila['Linea'];
            global $alternos;
            if (!(BloquearLineas($usuario, $fila['Linea']))) {
                $alternos = $fila['Alternos'];
            }

            // sacamos el descuento a aplicar y listo
            $descuento = 0;

            $precio_con_descuento = round($precio - ($precio * $descuento / 100));

            $consulta = '<td>' . $referencia . '</td>' . '<td>' . FiltrarDescripcion($descripcion). '</td>' . '<td>' . $existencias . '</td>';
        }
    }

    //pedidos sacando
    global $Pedidos;
    $sqlPedidos = "SELECT * FROM pedidos WHERE referencia = '$referencia' ";
    $resultPedidos = mysqli_query($con, $sqlPedidos);
    if ($sqlPedidos && mysqli_num_rows($resultPedidos) > 0) {
        $Pedidos = '<table class="table modern-table table-hover" id="pedidos">
                    <caption>Proximos en Llegar</caption>           
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Referencia</th>
                            <th>Fecha de Llegada(Aproximada)</th>
                        </tr>
                    </thead>
                    <tbody>';
        while ($fila = mysqli_fetch_assoc($resultPedidos)) {
            $Pedidos = $Pedidos . '<tr>' . '<td>' . $fila['IdPedido'] . '</td>' . '<td>' . $fila['referencia'] . '</td>' . '<td>' . $fila['FechaPedido'] . '</td>' . '</tr>';

        }

        $Pedidos = $Pedidos . '</tbody> </table>';
    }

    return $consulta;

}
?>


<!DOCTYPE html>
<html>

<head>
    <title>Consulta de inventario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

    <style>
    body {
            padding: 0px;
            font-size: 14px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

         .modern-table {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
             }

    .modern-table:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }

    .modern-table thead {
      background-color: #e9f2ff;
    }

    .modern-table th {
      color: #0d6efd;
      font-weight: 600;
      font-size: 14px;
        padding: 5px 15px !important;
        vertical-align: middle !important;
    }

   .modern-table th {
      vertical-align: middle;
      text-align: center;
      font-size: 14px;
    }

    .modern-table td {
      vertical-align: middle;
      text-align: center;
      font-size: 14px;
    }

    .modern-table tbody tr:hover {
      background-color: #f5f9ff;
    }
        

        .form-inline {
            margin-bottom: 20px;
        }

        .form-inline label {
            margin-right: 10px;
        }

        .form-inline input {
            width: 150px;
            margin-right: 10px;
        }

        .alternos td {
            padding: 6px;

        }


        #tablaSecundaria th {
            vertical-align: bottom;
            background: #ffeeba;
            
            /*#f2d439*/
        }
        .alternos th {

            padding: 6px;
        }

        .alternos {
            width: 100%;
            font-size: 14px;

        }

        h4 {
            font-size: 18px;
        }

        h1 {
            margin-top: 20px;
        }

        body {
            background-color: #ededed;


        }

        nav {
            box-shadow: 1px 1px 20px #ccc;

        }

        .navbar .nav-item:not(:last-child) {
            margin-right: 35px;
        }

        .dropdown-toggle::after {
            transition: transform 0.15s linear;
        }
     
        .show.dropdown .dropdown-toggle::after {
            transform: translateY(3px);
        }

        .hover-row:hover {
      background-color: #f0f8ff; /* Color que prefieras al pasar el mouse */
      cursor: pointer;
    }

      .custom-row {
      background-color: #ffffff;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1rem;
      transition: all 0.3s ease;
    }
    .header-row {
      background-color: #f0f8ff;
      border-radius: 8px;
    padding: 0.6rem ! IMPORTANT;
      margin-bottom: 1rem;
      transition: all 0.3s ease;
    }


    .custom-row:hover {
      background-color: #f5f9ff;
      transform: translateY(-2px);
      text-decoration: none;
    }

    .row-title {
      font-weight: 600;
      color: #0d6efd;
      font-size: 1.1rem;
    }

    caption {
    padding-top: .75rem;
    padding-bottom: .75rem;
    color: #000000;
    text-align: center;
    caption-side: top;
    font-size: 16px;
}
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-light text-center" style="display: block;">

        <span class="navbar-text">
            <!-- Example single danger button -->
            <div class="btn-group">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo 'Bienvenido(a)' ?>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="logout.php?cerrar=1">Cerrar Sesión</a></li>
                </ul>
            </div>
            </div>

        </span>


    </nav>

    <h1 class="text-center mb-4">Consulta de Inventario</h1>

    <div class="container">
        <div class="">
            <form class="" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="referencia">Referencia:</label>
                    <input autofocus type="text" class="form-control" name="referencia" id="referencia" class="form-control" required>
                </div>

                <div class="">
                    <button type="submit" name="buscar" class="btn btn-primary">Buscar</button>
                </div>
            </form>
        </div>
        <?php

global $result;
global $resultuse;
global $resultcostex;
global $alternos;
if (isset($_POST['buscar'])):
    $referencia = $_POST['referencia'];

    $sql = "SELECT * FROM inventario WHERE referencia = '$referencia' and Tipo='agro'";
    $result = mysqli_query($con, $sql);

    $sql2 = "SELECT * FROM inventario WHERE referencia = '$referencia' and Tipo='use' LIMIT 1";
    $resultuse = mysqli_query($con, $sql2);

    $sql3 = "SELECT * FROM inventario WHERE referencia = '$referencia' and Tipo='costex'";
    $resultcostex = mysqli_query($con, $sql3);

    if (($result && mysqli_num_rows($result) > 0) || ($resultuse && mysqli_num_rows($resultuse) > 0) || ($resultcostex && mysqli_num_rows($resultcostex) > 0)):
    ?>

					               <table class="table modern-table table-hover" id="tablaPrincipal">
					                    <thead>
					                        <tr>
					                            <th>Referencia</th>
					                            <th>Descripción</th>
					                            <th>Disponibilidad</th>
					                        </tr>
					                    </thead>
					                    <tbody>

					                        <tr>

					                            <?php echo Existencias('GENERAL', $result, $resultuse, $resultcostex, $con);

    // $alternos=$fila['Alternos'];
    ?>


					                        </tr>

					                    </tbody>
					                </table>
					                <div class="alert alert-warning" role="alert" id="Mensaje" style="display:none; font-size:15px;">
					                    La Referencia, "<?php echo $_POST['referencia'] ?>", es un alias de la refrencia principal mostarada arriba.
					                </div>
					                 <div class="alert alert-warning" role="alert" id="MensajePrecios" style="color:#111;font-size:16px;">
					                  Consulte precios con su asesor de confianza o escribe al correo:  <a href="ventas@agro-costa.com">ventas@agro-costa.com</a>
					                </div>
					                <br />
					                <?php

    //$arregloAlternos = explode("...", $alternos); directo lo hare con fucnion para poner las excepciones
    global $alternos;
    // QUITAR SI QUEREMOS QUE FUNCIONE ALTERNO PARA TODOS LOS CLINTES NO REGISTRADOS.
    $alternos="";
    if (strlen($alternos) > 1) {
        echo $alternos.' '.strlen($alternos);
        ?>

					                    <table class="table modern-table table-hover" id="tablaPrincipal" style="border:none">
					                    <caption>Ver Alternos</caption> 
                                        <thead>
					                            <tr>
					                                <th>Referencia</th>
					                                <th>Comentario</th>
					                                <th>Tipo</th>
					                            </tr>
					                        </thead>
					                        <tbody>
					                          
                                              <?php
    
    //$arregloAlternos = explode("...", $alternos); directo lo hare con fucnion para poner las excepciones
        global $alternos;
        $arregloAlternos = Alternos('GENERAL', $alternos);

        ?>
					                            <?php foreach ($arregloAlternos as $valor) {?>
					                                <tr>
					                                    <?php $comentario = explode("COMENTARIO:", $valor);

            if (strcasecmp(trim($comentario[2]), 'Complementario') !== 0) {?>
					                                    <td><?php echo $comentario[0] ?></td>
					                                    <td><?php echo $comentario[1] ?></td>
					                                   <td><?php echo $comentario[2] ?></td>
					                                  <?php }?>

					                                </tr>
					                            <?php }
        ;?>
					                        </tbody>
					                    </table>
					                <?php
    }
    ?>
					                <?php
    global $precio_con_descuento;
    guardarMovimiento('GENERAL', $referencia, date('Y-m-d H:i:s'), $precio_con_descuento, $con);?>

					            <?php else: ?>
                <div class="alert alert-danger" role="alert">
                    No se encontraron resultados para la referencia "<?php echo $_POST['referencia'] ?>"
                </div>
        <?php endif;
endif?>

        <?php
global $Pedidos;
echo $Pedidos;

$leyenda = false;
if ($Pedidos) {
    $leyenda = true;
    ?>
             <div class="alert alert-warning" role="alert" id="MensajePrecios" style="color:#111;background:#d7e9fb;font-size:16px;">
                Si deseas conocer precios de la mercancia proxima a llegar, consulte con su asesor o al correo:  <a href="ventas@agro-costa.com">ventas@agro-costa.com</a>
                </div>
            <?php
}
;
?>

    </div>




    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/jquery.validate.min.js"></script>
    <script>
        // Valida el formulario con jQuery Validation
        $(document).ready(function() {
            $("form").validate({
                rules: {
                    referencia: "required"
                },
                messages: {
                    referencia: "Por favor ingrese la referencia del artículo"
                }
            });
        });
    </script>

      <script>
        // JavaScript para realizar la comparación y actualización de la fecha

/*
        document.addEventListener("DOMContentLoaded", function() {

            // Obtener la tabla principal y los pedidos
            var tablaPrincipal = document.getElementById('tablaPrincipal');
            var pedidos = document.getElementById('pedidos');

            // Supongamos que los datos de la tabla se pasan desde PHP y se almacenan en estas variables JavaScript
            var tablaPrincipalData = <?php echo json_encode($tablaPrincipalData); ?>;
            var pedidosData = <?php echo json_encode($pedidosData); ?>;

            // Función para comparar y actualizar la fecha
            function actualizarFecha() {


                // Suponiendo que la tercera celda de la segunda fila contiene la fecha en formato 'dd/mm/yyyy'
                var fechaPedido = pedidosData[1][2]; // PedidosData es un array bidimensional
                var disponible = tablaPrincipalData [1][2]; // tablaPrincipalData es un array bidimensional

                // Comprobar si la fecha contiene '60 DIAS'


                if (disponible.includes('60 DIAS')) {
                    // Actualizar la fecha en la tabla principal
                    tablaPrincipalData[1][2] = fechaPedido;
                }

                // Actualizar la tabla principal en el HTML
                actualizarTablaPrincipal();
            }

            // Función para actualizar la tabla principal en el HTML
            function actualizarTablaPrincipal() {
                tablaPrincipal.innerHTML = ''; // Limpiar la tabla

                // Iterar sobre los datos y crear filas y celdas
                tablaPrincipalData.forEach(function(filaData) {
                    var fila = document.createElement('tr');

                    filaData.forEach(function(celdaData) {
                        var celda = document.createElement('td');
                        celda.textContent = celdaData;
                        fila.appendChild(celda);
                    });

                    tablaPrincipal.appendChild(fila);
                });
            }


            // Llamar a la función para actualizar la fecha al cargar la página
            actualizarFecha();
        });
        */

document.addEventListener("DOMContentLoaded", function() {


        // Obtener la tabla principal y los pedidos
        var tablaPrincipal = document.getElementById('tablaPrincipal');
        var pedidos = document.getElementById('pedidos');

//tablaPrincipal.rows[1].cells[2].innerHTML= pedidos.rows[1].cells[2].textContent;

if (pedidos!== null) {
        // Obtener la fecha del pedido (segunda fila, tercera celda)
        var fechaPedido = pedidos.rows[1].cells[2].textContent;
         var disponible= tablaPrincipal.rows[1].cells[2].textContent;


        // Comprobar si la fecha contiene '60 DIAS'
        if (disponible.includes('60 DIAS')) {
            // Obtener la fecha del pedido y asignarla a la tabla principal
            tablaPrincipal.rows[1].cells[2].innerHTML="Proximo en Llegar: " + fechaPedido + '<a href="https://agro-costa.com/consulta/consulta_inventario.php#pedidos"></a>';
        }

        }
    });

    </script>
</body>

</html>

