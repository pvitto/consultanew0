<?php

// no muestre errores si no me ejecuta algo puedo quitarlo para ver los erroes
error_reporting(0);

require_once 'logout.php';
require_once 'funciones_recoger.php';
require_once 'funciones_tablas.php';

//session_start();
if (!isset($_SESSION['idusuario']) || $_SESSION['idusuario'] != "ADMIN") {
    header("Location: consulta_inventario.php");
    exit();
}

// Conecta a la base de datos
$con = ConectarBaseDatos();

if (!ChequearExistenciaTabla("acceso_a_usuarios", $con))
{
    CrearTabla($con);
}
    


function guardarMovimiento($usuario, $referencia, $fecha, $precio_con_descuento, $con)
{
    try {
        // Validate and format the date to ensure it's in the correct MySQL DATETIME format
        $fecha = date('Y-m-d H:i:s', strtotime($fecha));

        // Prepare the SQL statement
        $sql = "INSERT INTO mov (Idusuario, referencia, Precio, fechaconsulta) VALUES (:usuario, :referencia, :precio, :fecha)";
        $stmt = $con->prepare($sql);
        
        // Bind parameters to the statement
        $stmt->bindParam(':usuario', $usuario);
        $stmt->bindParam(':referencia', $referencia);
        $stmt->bindParam(':precio', $precio_con_descuento);
        $stmt->bindParam(':fecha', $fecha);
        
        // Execute the query
        if ($stmt->execute()) {
            echo "Movimiento registrado exitosamente";
        } else {
            echo "Error al registrar movimiento";
        }
    } catch (PDOException $e) {
        // Catch and display PDO errors
        echo "Error al registrar movimiento: " . $e->getMessage();
    }
}

function Alternos($usuario, $alternos, $referencia, $con)
{

    // Busca la línea en la que está el ítem utilizando la descripción de los alternos
    $buscarQuery = $con->prepare("SELECT Linea FROM inventario WHERE Referencia = :referencia");
    $buscarQuery->execute(['referencia' => $referencia]);
    $linea = $buscarQuery->fetchColumn();

    if (!$linea) {
        return [];
    }

    // Revisa los permisos del usuario para los alternos y complementarios
    $resultado_alterno = BloquearLineas($linea, 'alterno');
    $resultado_complementario = BloquearLineas($linea, 'complementario');

    $string = explode("...", $alternos);

    $string_alterno = [];
    $string_complementario = [];
    $string_alias = [];

    // Separa si los comentarios son de un alterno, complementario o alias
    foreach ($string as $str) {
        if (strpos($str, "Alterno") !== false) {
            $string_alterno[] = $str;
        } elseif (strpos($str, "Complementario") !== false) {
            $string_complementario[] = $str;
        } else {
            $string_alias[] = $str;
        }
    }

    $resultado_alias = !empty($string_alias);

    // Crea keys para cada tipo de comentario para que sea mas facil sortearlas afuera de la funcion
    $resultado_final = [
        'alternos' => $resultado_alterno ? [] : $string_alterno,
        'complementarios' => $resultado_complementario ? [] : $string_complementario,
        //'alias' => $resultado_alias ? $string_alias : []
    ];

    return $resultado_final;
}



function Existencias($usuario, $resultado_alterno, $resultuse, $resultcostex, $con, $referencia)
{
    $entra = 0;
    $consulta = '';
    $precio = 0;
    $consulta .= '<h4 style="font-size:18px">Referencia Buscada: ' . $referencia . '</h4><br/>';
    $referenciaP = '';

    // Primero vamos con referencias de agrocosat
    if ($resultado_alterno && !empty($resultado_alterno)) {

        $registros = $resultado_alterno;
        if (BloquearLineas($registros[0]['Linea'])) {

            return '';
        }

        $entra = 1;




        foreach ($registros as $fila) {

            if (($fila['Existencias'] + $fila['Existencias_bog']) > '0' && $fila['Tipo'] == 'agro') {
                $existencias = $fila['Existencias'];
                $existencias_bog = $fila['Existencias_bog'];
            } elseif (($fila['Existencias'] + $fila['Existencias_bog']) < '1' && $fila['Tipo'] == 'agro') {
                $existencias = 'IMPORTACION - TIEMPO DE ENTREGA: 60 DIAS';
                $precio = 0;
            }


            $referencia = $fila['Referencia'];
            $descripcion = $fila['descripcion'];
            $linea = $fila['Linea'];
            global $alternos, $precio_con_descuento, $descuento;

            $descuento = $_SESSION['D' . $linea];
            $precio_con_descuento = 0;

            if (!BloquearLineas($fila['Linea'])) {
                $alternos = $fila['Alternos'];
            }


            if (!BloquearLineas($fila['Linea'], 'precio')) {
                $precio = $fila['Precio'];
                $precio_con_descuento = round($precio - ($precio * $descuento / 100));
            }

            $consulta .= '<table class="table modern-table table-hover" id="tablaPrincipal">
                        <caption style="background-color:#b2dcff; font-weight:bold"">Consulta de inventario</caption>
                        <thead>
                            <tr>
                                <th>Referencia</th>
                                <th>Descripción</th>
                                <th>Bodega <br> BARRANQUILLA </th>
                                <th>Bodega <br> BOGOTÁ</th>
                                <th>Marca</th>
                                <th>Precio Antes de IVA</th>
                            </tr>
                        </thead>
                        <tbody>

                            <tr>

                        <td>' . $referencia . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>' . $existencias_bog . '</td><td>' . (!empty($fila['MarcaTexto']) ? $fila['MarcaTexto'] : $fila['Marcas']) . '</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>

                        <td>' . $referencia . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>' . $existencias_bog . '</td><td>' . $fila['MarcaTexto'] . '</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>

                        </tr>

                        </tbody>
                        </table>';


            if (($fila['Existencias'] + $fila['Existencias_bog']) < '1' && $fila['Tipo'] == 'agro') {

                $consulta .= '<br>' . ExistenciasCostex($resultcostex, $referencia);
            }
        }





        if ($precio == 0) {
            /* $consulta .= '<div class="alert alert-warning" role="alert" id="MensajePrecioBloqueado" style="color:#111;background:#d7e9fb;font-size:16px;">
                                Si deseas conocer precios de esta mercancia, consulte con su asesor o al correo:
                                <a href="ventas@agro-costa.com">ventas@agro-costa.com</a>
                                </div>';*/
        }
    }


    // Sacando alias o use
    if ($resultuse && !empty($resultuse) > 0 && $entra != 1) {
        $entra = 2;
        $fila = $resultuse;
        if ($fila['Tipo'] == 'use') {
            $existencias = $fila['Existencias'];
            $precio = 0;
        }

        global $referenciaP;
        $referenciaP = trim(substr($fila['descripcion'], 4, 100));
fix-marca-visibility-admin-v2
        $sqlP = "SELECT inventario.*, marcas.Marca AS MarcaTexto FROM inventario LEFT JOIN marcas ON Marcas=Proveedor WHERE referencia = :referenciaP and Tipo='agro' ";
        $sqlP = "SELECT inventario.*, marcas.Marca AS MarcaTexto FROM inventario LEFT JOIN marcas ON inventario.Marcas = marcas.Proveedor WHERE referencia = :referenciaP and Tipo='agro' ";
 main
        $stmtP = $con->prepare($sqlP);
        $stmtP->execute(['referenciaP' => $referenciaP]);
        $resultP = $stmtP->fetchAll();

        if ($stmtP && $stmtP->rowCount() > 0) {
            $registros = $resultP;
            if (BloquearLineas($registros[0]['Linea'])) {
                    return ' <table class="table modern-table table-hover" id="tablaPrincipal">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th>Bodega <br> BARRANQUILLA </th>
                            <th>Bodega <br> BOGOTÁ</th>
                            <th>Marca</th>
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>

                        <tr>
                        <td>0</td><td>0</td><td>0</td><td></td><td></td><td>$0</td> </tr>

                    </tbody>
                    </table>';
            }


            foreach ($registros as $fila) {


            if (($fila['Existencias'] + $fila['Existencias_bog']) > '0' && $fila['Tipo'] == 'agro') {
                $existencias = $fila['Existencias'];
                $existencias_bog = $fila['Existencias_bog'];

            } elseif (($fila['Existencias'] + $fila['Existencias_bog']) > '0' && $fila['Tipo'] == 'costex') {
                $existencias = 'ESTE REPUESTO ESTA PARA IMPORTACION. ENTREGA: 5-7 DIAS';
                $existencias_bog = 0;

            } elseif (($fila['Existencias'] + $fila['Existencias_bog']) < '1' && $fila['Tipo'] == 'agro') {
                $existencias = 'IMPORTACION - TIEMPO DE ENTREGA: 60 DIAS';
                $existencias_bog = 0;
                $precio = 0;
            }

            $referenciap = $fila['Referencia'];
            $descripcion = $fila['descripcion'];
            $linea = (int)$fila['Linea'];
            global $alternos, $descuento;

            $descuento = $_SESSION['D' . $linea];
            global $precio_con_descuento;
            $precio_con_descuento = 0;

            if (!BloquearLineas($fila['Linea'])) {
                $alternos = $fila['Alternos'];
            }


            if (!BloquearLineas($fila['Linea'], 'precio'))
            {
                $precio = $fila['Precio'];
                $precio_con_descuento = round($precio - ($precio * $descuento / 100));
            }

            $consulta .= '<table class="table modern-table table-hover" id="tablaPrincipal">

           <caption style="background-color:#b2dcff;font-weight:bold"">Consulta de Inventario</caption>

                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th>Bodega <br> BARRANQUILLA </th>
                            <th>Bodega <br> BOGOTÁ</th>
                            <th>Marca</th>
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>
            <tr>

            <td>' . $referenciap . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>' . $existencias_bog . '</td><td>' . (!empty($fila['MarcaTexto']) ? $fila['MarcaTexto'] : $fila['Marcas']) . '</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>

            <td>' . $referenciap . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>' . $existencias_bog . '</td><td>' . $fila['MarcaTexto'] . '</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>
 
            </tr>

                    </tbody>
                    </table>

                    <div class="alert alert-warning" role="alert" id="Mensaje" style="display:none;font-size:16px;">
                    La Referencia,'. $referencia.', Equivale en Agro-Costa a la referencia '.$referenciaP.'.
                </div>';




                     if (($fila['Existencias'] + $fila['Existencias_bog'])  < '1' && $fila['Tipo'] == 'agro') {

                       $consulta.= '<br>'. ExistenciasCostex($resultcostex, $referencia);

                    }




            }






            if ($precio == 0) {
                /*$consulta .= '<div class="alert alert-warning" role="alert" id="MensajePrecioBloqueado" style="color:#111;background:#d7e9fb;font-size:16px;">
                              Si deseas conocer precios de esta mercancia, consulte con su asesor o al correo:
                              <a href="ventas@agro-costa.com">ventas@agro-costa.com</a>
                              </div>';*/
            }

            echo "<script>window.onload = function() {";
            echo "var x = document.getElementById('Mensaje');";
            echo "x.style.display = 'block';";
            echo "}</script>";
        } else {
            $referenciap = $fila['Referencia'];
            $descripcion = $fila['descripcion'];
            $linea = $fila['Linea'];
            global $alternos;


            $descuento = $_SESSION['D' . $linea];
            global $precio_con_descuento;
            $precio_con_descuento = 0;

            if (!BloquearLineas($fila['Linea'])) {
                $alternos = $fila['Alternos'];
            }


            if (!BloquearLineas($fila['Linea'], 'precio'))
            {
                $precio = $fila['Precio'];
                $precio_con_descuento = round($precio - ($precio * $descuento / 100));
            }


            $consulta = '<table class="table modern-table table-hover" id="tablaPrincipal">
            <caption style="background-color:#b2dcff;font-weight:bold"">Consulta de Inventario</caption>

                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th>Bodega <br> BARRANQUILLA </th>
                            <th>Bodega <br> BOGOTÁ</th>
                            <th>Marca</th>
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>
            <tr>

            <td>' . $referenciap . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>' . $existencias_bog . '</td><td>' . (!empty($fila['MarcaTexto']) ? $fila['MarcaTexto'] : $fila['Marcas']) . '</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>

            <td>' . $referenciap . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>' . $existencias_bog . '</td><td>' . $fila['MarcaTexto'] . '</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>

             </tr>

                    </tbody>
                    </table>
                    <div class="alert alert-warning" role="alert" id="Mensaje" style="display:none;font-size:16px;">
                    La Referencia,'. $referencia.', Equivale en Agro-Costa a la referencia '.$referenciaP.'.
                </div>';

            if ($precio == 0) {
                /*$consulta .= '<div class="alert alert-warning" role="alert" id="MensajePrecioBloqueado" style="color:#111;background:#d7e9fb;font-size:16px;">
                              Si deseas conocer precios de esta mercancia, consulte con su asesor o al correo:
                              <a href="ventas@agro-costa.com">ventas@agro-costa.com</a>
                              </div>';*/
            }
        }

    }

    // Sacando item de costex
    if ($resultcostex && !empty($resultcostex) > 0 && $entra != 2 && $entra != 1) {
        $entra = 3;
        $fila = $resultcostex;



             $consulta.= ExistenciasCostex($resultcostex, $referencia);


    }

    // Pedidos sacando
    global $Pedidos;
    global $descuento;
    global $precio_con_descuento;
    $sqlPedidos = "SELECT * FROM pedidos a inner join inventario b on a.referencia=b.Referencia left join marcas c on c.proveedor=a.proveedor WHERE a.referencia = :referencia AND Tipo='agro' order by a.FechaPedido ASC";
    $stmtPedidos = $con->prepare($sqlPedidos);

    if ($entra=='2')
    {$stmtPedidos->execute(['referencia' => $referenciaP]);}
    else
    {$stmtPedidos->execute(['referencia' => $referencia]); }


    if ($stmtPedidos && $stmtPedidos->rowCount() > 0) {
        $Pedidos = '<table class="table modern-table table-hover" id="pedidos">
                    <caption style="font-weight:bold">Proximos en Llegar</caption>
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Referencia</th>
                            <th>Cantidad</th>
                            <th>Fecha de Llegada(Aproximada)</th>
                            <th>Marca</th>
                            <th>Precio Antes de IVA</th>

                        </tr>
                    </thead>
                    <tbody>';

        while ($fila = $stmtPedidos->fetch(PDO::FETCH_ASSOC)) {
           // $precioPedido = 0;
            $precioPedido = PreciosPedidos($fila['Proveedor'], $fila['Moneda'], $fila['Costo'], $fila['Linea'] ,$descuento);
            if($precioPedido<$precio_con_descuento){

                $precioPedido=$precio_con_descuento;


            }

            // Formatear la fecha al formato deseado: Y-m-d (2025-Nov-20 no 2025-11-20) con mes abreviado en letras
            $fecha= $fila['FechaPedido'];
            $date = new DateTime($fecha);
            $fecha = $date->format('d-M-Y');

            $Pedidos .= '<tr><td>' . $fila['IdPedido'] . '</td><td>' . $fila['referencia'] . '</td><td>' . $fila['Cantidad'] . '</td><td>' . $fecha . '</td><td>' . $fila['Marca'] . '</td> <td>' .'$'. number_format($precioPedido,  0, '.', ',').'</td> </tr>';
        }

        $Pedidos .= '</tbody></table>';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script> 
    
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
                <a href="#" class="dr$conwn-toggle" data-bs-toggle="dr$conwn" aria-expanded="false">
                    <?php echo 'Bienvenido ' . $_SESSION['idusuario'] ?>
                </a>
                <ul class="dr$conwn-menu">
                    <li><a class="dr$conwn-item" href="logout.php?cerrar=1">Cerrar Sesión</a></li>
                </ul>

                <?php
                    if ($_SESSION['idusuario'] == "ADMIN")
                    {
                        ?>
                            <ul class="dr$conwn-menu">
                                <li><a href='usuarios.php' class="dr$conwn-item">Lista de Usuarios</a></li>
                            </ul>

                            <ul class="dr$conwn-menu">
                                <li><a href='consulta_inventario.php' class="dr$conwn-item">Consultar Inventario</a></li>
                            </ul>
                        <?php
                    }

                    
                ?>
            </div>
            </div>

        </span>


    </nav>

    <h1 class="text-center mb-4">Consulta de Precios</h1>

    <div class="container">
        <div class="">
            <?php
            try {
                $usuarios = recogerUsuarios($con);

            } catch (PDOException $e) {
                echo "Error en la conexión: " . $e->getMessage();
            }
            ?>
<a href="consulta_inventario.php" class="btn btn-primary mb-3" style="font-weight: 500;">
                    &larr; Volver a Consulta inventario
                </a>
                
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario:</label>
                    <select class="form-select" name="usuario" id="usuario">
                        <option value="">Seleccione un usuario</option>
                        <?php
                            if (isset($_POST['buscar']))
                            {
                                $_SESSION['selected_usuario'] = $_POST['usuario'];
                            }
                            
                            if (!empty($usuarios)) {
                                foreach ($usuarios as $usuario) {
                                    // Verificar si el usuario actual es el que está almacenado en la sesión
                                    $selected = ($usuario == $_SESSION['selected_usuario']) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($usuario) . "' $selected>" . htmlspecialchars($usuario) . "</option>";
                                }
                            } else {
                                echo "<option value=''>No hay usuarios disponibles</option>";
                            }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="referencia" class="form-label">Referencia:</label>
                    <input type="text" class="form-control" name="referencia" id="referencia" required>
                </div>

<div class="d-flex align-items-center">
            
                <button type="submit" name="buscar" class="btn btn-primary">Buscar</button>

                <?php
                // --- INICIO: Lógica para mostrar el botón "Volver" ---
                
                // Muestra el botón "Volver" SÓLO si se ha realizado una búsqueda
                if (isset($_POST['buscar'])) {
                ?>
                    <a href="#" onclick="history.go(-1); return false;" class="btn btn-secondary ml-2">
                        &larr; Atrás
                    </a>
                <?php
                }
                // --- FIN: Lógica para mostrar el botón "Volver" ---
                ?>
            </div>            </form>
        </div>
        <?php



        global $resultado_alterno;
        global $resultuse;
        global $resultcostex;
        global $alternos;
        global $usuario;
        if (isset($_POST['buscar'])) :
            $referencia = $_POST['referencia'];
            $idUsuario = $_POST['usuario'];
// --- Cargar descuentos del USUARIO SELECCIONADO en la sesión ---
            // Esto es para que las funciones de 'consulta_inventario.php' funcionen sin cambios.
            $sql_descuentos = $con->prepare("SELECT * FROM usuarios WHERE Idusuario = :usuario LIMIT 1");
            $sql_descuentos->execute(['usuario' => $idUsuario]);
            $descuentos_usuario = $sql_descuentos->fetch(PDO::FETCH_ASSOC);

            if ($descuentos_usuario) {
                foreach ($descuentos_usuario as $key => $value) {
                    // Cargar solo las columnas de descuento (ej: d1, d2, d100)
                    if (strpos($key, 'd') === 0 && is_numeric(substr($key, 1))) {
                        // Importante: El user file usa 'D' mayúscula en la sesión
                        $session_key = 'D' . substr($key, 1);
                        $_SESSION[$session_key] = $value;
                    }
                }
            }
            // --- Fin de la carga de descuentos ---
            $_SESSION['selected_usuario'] = $idUsuario;


            $sql = $con->prepare("SELECT inventario.*, marcas.Marca AS MarcaTexto FROM inventario LEFT JOIN marcas ON Marcas=Proveedor WHERE referencia = :referencia and Tipo='agro'");

            $sql = $con->prepare("SELECT inventario.*, marcas.Marca AS MarcaTexto FROM inventario LEFT JOIN marcas ON inventario.Marcas = marcas.Proveedor WHERE referencia = :referencia and Tipo='agro'");

            $sql->execute(['referencia' => $referencia]);
            $resultado_alterno = $sql->fetchAll();


            $sql2 = $con->prepare("SELECT * FROM inventario WHERE referencia = :referencia and Tipo='use'");
            $sql2->execute(['referencia' => $referencia]);
            $resultuse = $sql2->fetchAll();

            $sql3 = $con->prepare("SELECT * FROM inventario WHERE referencia = :referencia and Tipo='costex'");
            $sql3->execute(['referencia' => $referencia]);
            $resultcostex = $sql3->fetchAll();

            if (($resultado_alterno && $sql->rowCount() > 0) || ($resultuse && $sql2->rowCount() > 0) || ($resultcostex && $sql3->rowCount() > 0)) :
        ?>
 
                <table class="table modern-table table-hover" id="tablaPrincipal">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th>Disponible</th>
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>

                        <tr>

                            <?php
                            
                            echo Existencias($idUsuario, $resultado_alterno[0], $resultuse[0], $resultcostex[0], $con);

                            // $alternos=$fila['Alternos'];
                            ?>


                        </tr>

                    </tbody>
                </table>
                <div class="alert alert-warning" role="alert" id="Mensaje" style="display:none;font-size:16px;">
                    La Referencia, "<?php echo $_POST['referencia'] ?>", es un alias de la referencia principal mostrada arriba.
                </div>

  <div class="alert alert-warning" role="alert" id="MensajePrecios1" style="display:none;color:#111;background:#d7e9fb;font-size:16px;">
                Si deseas conocer precios de la mercancia proxima a llegar, consulte con su asesor o al correo:  <a href="ventas@agro-costa.com">ventas@agro-costa.com</a>
                </div>
 
                <br />
                <?php
                 
                //$arregloAlternos = explode("...", $alternos); directo lo hare con fucnion para poner las excepciones
                global $alternos;
                $arregloAlternos = Alternos($_SESSION['idusuario'],  $alternos, $referencia, $con);

                if (!empty($arregloAlternos["alternos"]) || !empty($arregloAlternos["complementarios"]) || !empty($arregloAlternos["alias"])) {
                    //echo $alternos.' '.strlen($alternos);
                ?>
  
       <?php
                // global $alternos; // Esta variable ya fue declarada y usada arriba
                $arregloAlternos = Alternos($idUsuario,  $alternos, $referencia, $con);

                // Revisa si hay alternos O complementarios para mostrar
                if (!empty($arregloAlternos["alternos"]) || !empty($arregloAlternos["complementarios"])) {
            ?>

                        <div class="container mt-4">

                                <div class="header-row row hover-row border p-2" style="font-weight:800; text-align:center;background-color: #b2dcff;">
                    <div class="col"><h4>Ver Alternos y Complementarios</h4></div>
                </div>

                                <div class="header-row row hover-row border p-2" style="color: #0d6efd;font-weight: 600;font-size: 14px;padding: 5px 15px !important;vertical-align: middle !important;background-color: #e9f2ff; border-bottom: 2px solid #dee2e6;text-align: center; border-bottom: 2px solid #dee2e6 !important;">
                    <div class="col">Referencia</div>
                    <div class="col">Descripción</div>
                    <div class="col">Comentario</div>
                    <div class="col">Tipo</div>
                    <div class="col">Bod. BAQ</div>
                    <div class="col">Bod. BOG</div>
                    <div class="col">Marca</div>
                    <div class="col">Precio</div>
                </div>

            <?php
                // --- INICIO LOOP ALTERNOS ---
                foreach ($arregloAlternos['alternos'] as $valor) {
                    $comentario = explode("COMENTARIO:", $valor);

                    // Asignamos variables con seguridad (trim y ?? '')
                    $ref_alterno = trim($comentario[0] ?? '');
                    $desc_alterno_comentario = trim($comentario[1] ?? ''); // Descripción del comentario
                    $com_alterno = trim($comentario[2] ?? ''); // Comentario adicional

                    $existenciasAlterno = 0;
                    $precioAlterno = 0;
                    $lineaAlterno;
                    $mensaje;
                    $existencias_baq = 0;
                    $existencias_bog = 0;
                    $marcaAlterno = ''; // Initialize variable
                    $desc_alterno_db = $desc_alterno_comentario; // Usar la del comentario por defecto


                    $sql = $con->prepare("SELECT inventario.*, marcas.Marca AS MarcaTexto FROM inventario LEFT JOIN marcas ON Marcas=Proveedor WHERE referencia = :referencia and Tipo='agro' ");

                    $sql = $con->prepare("SELECT inventario.*, marcas.Marca AS MarcaTexto FROM inventario LEFT JOIN marcas ON inventario.Marcas = marcas.Proveedor WHERE referencia = :referencia and Tipo='agro' ");

                    $sql->execute(['referencia' => $ref_alterno]);
                    $resultado = $sql->fetchAll();

                    if ($resultado && count($resultado) > 0) {
                        foreach ($resultado as $fila) {
                            $precioAlterno =  $fila['Precio'];
                            $existenciasAlterno = $fila['Existencias'] + $fila['Existencias_bog'];
                            $existencias_baq= $fila['Existencias'];
                            $existencias_bog =$fila['Existencias_bog'];

                            $marcaAlterno = (!empty($fila['MarcaTexto']) ? $fila['MarcaTexto'] : $fila['Marcas']);

                            $marcaAlterno = $fila['MarcaTexto'];

                            $lineaAlterno = $fila['Linea'];

                            // Si la descripción del comentario está vacía, usamos la de la BD
                            if (empty($desc_alterno_db)) {
                                $desc_alterno_db = $fila['descripcion'];
                            }
                        }
                    }

                    if ($existenciasAlterno > 0) {
                        if (isset($lineaAlterno)) { // Asegurarse que la línea exista
                            $descuento = $_SESSION['D' . $lineaAlterno];
                            $precioAlterno = round($precioAlterno - ($precioAlterno * $descuento / 100));
                            $mensaje =  '$'.number_format($precioAlterno , 0, '.', ',');
                        } else {
                            $mensaje = '$0'; // Caso borde si no se encontró línea
                        }
                    } else {
                        // Ya no es un link, porque la FILA ENTERA es el link.
                        $mensaje = '<button type="button" class="btn btn-primary" style="font-size: 12px; padding: 5px 5px;">Ver pedidos</button>';
                    }
                ?>
                                                    <a href="consulta_inventario_admin.php?referencia=<?php echo $ref_alterno; ?>&usuario=<?php echo $idUsuario; ?>&buscar=">
                        <div class="custom-row row hover-row" style="text-align: center;">
                            <div class="col"><?php echo $ref_alterno; ?></div>
                            <div class="col"><?php echo $desc_alterno_db; ?></div>
                            <div class="col"><?php echo $com_alterno; ?></div>
                            <div class="col">Alterno</div>
                            <div class="col"><?php echo $existencias_baq; ?></div>
                            <div class="col"><?php echo $existencias_bog; ?></div>
                            <div class="col"><?php echo $marcaAlterno; ?></div>
                            <div class="col"><?php echo $mensaje; ?></div>
                        </div>
                    </a>
            <?php
                } // --- FIN LOOP ALTERNOS ---

                // --- INICIO LOOP COMPLEMENTARIOS ---
                foreach ($arregloAlternos['complementarios'] as $valor) {
                    $comentario = explode("COMENTARIO:", $valor);

                    // Asignamos variables con seguridad (trim y ?? '')
                    $ref_alterno = trim($comentario[0] ?? '');
                    $desc_alterno_comentario = trim($comentario[1] ?? ''); // Descripción del comentario
                    $com_alterno = trim($comentario[2] ?? ''); // Comentario adicional

                    $existenciasAlterno = 0;
                    $precioAlterno = 0;
                    $lineaAlterno;
                    $mensaje;
                    $existencias_baq = 0;
                    $existencias_bog = 0;
                    $marcaAlterno = ''; // Initialize variable
                    $desc_alterno_db = $desc_alterno_comentario; // Usar la del comentario por defecto


                    $sql = $con->prepare("SELECT inventario.*, marcas.Marca AS MarcaTexto FROM inventario LEFT JOIN marcas ON Marcas=Proveedor WHERE referencia = :referencia and Tipo='agro' ");

                    $sql = $con->prepare("SELECT inventario.*, marcas.Marca AS MarcaTexto FROM inventario LEFT JOIN marcas ON inventario.Marcas = marcas.Proveedor WHERE referencia = :referencia and Tipo='agro' ");

                    $sql->execute(['referencia' => $ref_alterno]);
                    $resultado = $sql->fetchAll();

                    if ($resultado && count($resultado) > 0) {
                        foreach ($resultado as $fila) {
                            $precioAlterno =  $fila['Precio'];
                            $existenciasAlterno = $fila['Existencias'] + $fila['Existencias_bog'];
                            $existencias_baq= $fila['Existencias'];
                            $existencias_bog =$fila['Existencias_bog'];

                            $marcaAlterno = (!empty($fila['MarcaTexto']) ? $fila['MarcaTexto'] : $fila['Marcas']);

                            $marcaAlterno = $fila['MarcaTexto'];

                            $lineaAlterno = $fila['Linea'];

                            // Si la descripción del comentario está vacía, usamos la de la BD
                            if (empty($desc_alterno_db)) {
                                $desc_alterno_db = $fila['descripcion'];
                            }
                        }
                    }

                    if ($existenciasAlterno > 0) {
                         if (isset($lineaAlterno)) { // Asegurarse que la línea exista
                            $descuento = $_SESSION['D' . $lineaAlterno];
                            $precioAlterno = round($precioAlterno - ($precioAlterno * $descuento / 100));
                            $mensaje =  '$'.number_format($precioAlterno , 0, '.', ',');
                        } else {
                            $mensaje = '$0'; // Caso borde si no se encontró línea
                        }
                    } else {
                        // Ya no es un link, porque la FILA ENTERA es el link.
                        $mensaje = '<button type="button" class="btn btn-primary" style="font-size: 12px; padding: 5px 5px;">Ver pedidos</button>';
                    }
            ?>
                                                    <a href="consulta_inventario_admin.php?referencia=<?php echo $ref_alterno; ?>&usuario=<?php echo $idUsuario; ?>&buscar=">
                        <div class="custom-row row hover-row" style="text-align: center;">
                            <div class="col"><?php echo $ref_alterno; ?></div>
                            <div class="col"><?php echo $desc_alterno_db; ?></div>
                            <div class="col"><?php echo $com_alterno; ?></div>
                            <div class="col">Complementario</div>
                            <div class="col"><?php echo $existencias_baq; ?></div>
                            <div class="col"><?php echo $existencias_bog; ?></div>
                            <div class="col"><?php echo $marcaAlterno; ?></div>
                            <div class="col"><?php echo $mensaje; ?></div>
                        </div>
                    </a>
            <?php
                } // --- FIN LOOP COMPLEMENTARIOS ---
            ?>
            </div>        <?php
                } // --- FIN if (!empty($arregloAlternos...)) ---
                ?>
            <?php };
        ?>
        
    </div>




    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/jquery.validate.min.js"></script>
    <script>
        // Valida el formulario con jQuery Validation
        $(document).ready(function() {
            $("form").validate({
                rules: {
                    referencia: "required",
                    usuario: "required"
                },
                messages: {
                    referencia: "Por favor ingrese la referencia del artículo",
                    usuario: "Por favor escoja un usuario"
                }
            });
        });
    </script>
    
    
     <script>
       
<?php echo json_encode($tablaPrincipalData); ?>;
           <?php echo json_encode($pedidosData); ?>;
  

        
document.addEventListener("DOMContentLoaded", function() {
         
         
        // Obtener la tabla principal y los pedidos
        var tablaPrincipal = document.getElementById('tablaPrincipal');
        var pedidos = document.getElementById('pedidos');

if (pedidos!== null) {
        // Obtener la fecha del pedido (segunda fila, tercera celda)
        var fechaPedido = pedidos.rows[1].cells[3].textContent;
         var disponible= tablaPrincipal .rows[1].cells[2].textContent;

        // Comprobar si la fecha contiene '60 DIAS'
        if (disponible.includes('60 DIAS')) {
            // Obtener la fecha del pedido y asignarla a la tabla principal
            tablaPrincipal.rows[1].cells[2].innerHTML="Proximo en Llegar: " + fechaPedido + '<a href="https://agro-costa.com/consulta/consulta_inventario.php#pedidos"></a>';
            

document.getElementById('MensajePrecios1').style.display = 'block' ;
        }
        
        }
    });
        
    </script>
</body>

</html>

    