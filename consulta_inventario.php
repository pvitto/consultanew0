<?php



// no muestre errores si no me ejecuta algo puedo quitarlo para ver los erroes
error_reporting(0);

// errrores, solo quita la de arriba o quita esta comentada.
/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

require_once 'logout.php';
require_once 'funciones_recoger.php';
require_once 'funciones_tablas.php';
require_once 'precios_pedidos.php';


/*
// Ejemplo: verificar permiso cargado
if (isset($_SESSION['permisos_usuario'])) {
    echo "Permisos cargados correctamente ✅";

     echo "<pre>";
    print_r($_SESSION['permisos_usuario']);
    echo "</pre>";

     echo "<pre>";
    print_r($_SESSION['idusuario']);
 echo "<pre>";

} else {
    echo "No se encontraron permisos en la sesión 🚫";
}*/



//session_start();
if (!isset($_SESSION['idusuario'])) {
    header("Location: login.php");
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
           // echo "Movimiento registrado exitosamente";
        } else {
            // echo "Error al registrar movimiento";
        }
    } catch (PDOException $e) {
        // Catch and display PDO errors
        echo "Error al registrar movimiento: " . $e->getMessage();
    }
}



function Alternos($usuario, $alternos, $referencia, $con) {

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

function getDescuentoInfo($referencia, $con) {
    // Si no hay referencia, retorna false
    if (!$referencia) return false;

    // Prepara la consulta para obtener el descuento y la fecha de finalizacion para la referencia 
    $stmt = $con->prepare("SELECT descuento, fecha_final FROM descuentos_items WHERE item = :item LIMIT 1");
    
    // Asocia el valor de la referencia al parametro :item de la consulta
    $stmt->bindParam(':item', $referencia, PDO::PARAM_STR);

    // Ejecuta la consulta en la base de datos
    $stmt->execute();

    // Obtiene el resultado como un array asociativo (o false si no existe)
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si encontro informacion, retorna el descuento y la fecha en un array //asocia
    if ($info) {
        return [
            'descuento' => $info['descuento'],       // Valor del descuento
            'fecha_final' => $info['fecha_final']    // Fecha hasta cuando aplica
        ];
    }

    // Si no encontro nada, retorna false
    return false;
}


// ===============================================
// Funcion que imprime el recuadro visual del descuento si existe
// Recibe el array con los datos del descuento
function renderDescuentoBox($descuentoInfo) {
    function formatoFechaEsp($fecha) {
        $meses = [
            "01" => "ene", "02" => "feb", "03" => "mar", "04" => "abr",
            "05" => "may", "06" => "jun", "07" => "jul", "08" => "ago",
            "09" => "sep", "10" => "oct", "11" => "nov", "12" => "dic"
        ];
        $partes = explode("-", $fecha);
        if(count($partes) == 3) {
            return $partes[0] . '-' . $meses[$partes[1]] . '-' . $partes[2];
        }
        return $fecha;
    }
    if (!$descuentoInfo) return;

    // Convertimos ambas fechas a formato Y-m-d (sin horas)
    $fecha_actual = date('Y-m-d');
    $fecha_limite = $descuentoInfo['fecha_final'];

    // Si la fecha actual es mayor a la fecha final, ya venció la promo
    if ($fecha_actual > $fecha_limite) {
        return;
    }


    ?>
    <div style="display: flex; justify-content: center; margin: 0px 0 0px 0;">
      <div id="descuento-box" style="
        max-width: 700px;
        width: 100%;
        padding: 15px 25px;
        font-size: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin: 0 auto 13px auto;
        border-radius: 18px;
        background: linear-gradient(120deg, #ff5722 60%, #f9c846 100%);
        box-shadow: 0 5px 20px 0 rgba(255, 87, 34, 0.15), 0 1.5px 5px #ffc40088; 
        color: #fff;">
        <span class="icon-flash" style="font-size:22px; margin-right:0px; line-height:1;">⚡</span>
        <div style="width:100%; text-align: center;">
          <span style="font-weight:700; font-size:16px; letter-spacing:0.1px;">¡¡¡PROMOCIÓN!!! 
            <span style="font-size:22px; font-weight:900; color:#fff; text-shadow:0 0 5px #fff17f99;">
              <?php echo intval($descuentoInfo['descuento']); ?>%
            </span>
            de descuento en este ITEM, para pago de <span style="font-size:16px; text-decoration: underline;">contado</span>.
          </span>
          <br>
          <span style="font-size:12px; font-weight:600; margin-top:2px; display:inline-block;">
          (Promoción válida hasta <?php echo formatoFechaEsp($descuentoInfo['fecha_final']); ?>, compra mínima de $8.000.000
          Aplican condiciones y restricciones)
          </span>
        </div>
      </div>
    </div>
    <?php
}
//PAOLO


//definir variables globales





function ExistenciasCostex($resultcostex, $referencia){

    // Sacando item de costex
    if ($resultcostex && !empty($resultcostex) > 0 ) {

        $fila = $resultcostex;

        if ($fila['Existencias'] > '0' && $fila['Tipo'] == 'costex') {
            $entrega = 'PARA IMPORTACION. TIEMPO DE ENTREGA: 5-7 DIAS';
            $existencias= $fila['Existencias'] . ' <br/>(PARA IMPORTACION, TIEMPO DE ENTREGA: 5-7 DIAS)' ;
        }


        $referencia = $fila['Referencia'];
        $descripcion = $fila['descripcion'];
        $linea = $fila['Linea'];
        global $alternos;

        $precio_con_descuento = 0;
        $descuento = $_SESSION['D' . $linea];


        if (!BloquearLineas($fila['Linea'])) {
            $alternos = $fila['Alternos'];
        }
    
        
        if (!BloquearLineas($fila['Linea'], 'precio'))
        {
            $precio = $fila['Precio'];
            $precio_con_descuento = round($precio - ($precio * $descuento / 100));
        }


  

        $consulta = '<table class="table modern-table table-hover" id="tablaImportacion">
        <caption style="background-color:#e96363; font-weight:bold">Opción Importación de Emergencia (5 - 7 dias)</caption>
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th>Disponible</th>
                            <th>Marca</th>
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>
            <tr>
        <td>' . $referencia . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>CTP</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>
        </tr>

                    </tbody>
                    </table>';
            return $consulta;
    }

    return;


}



function Existencias($usuario, $resultado_alterno, $resultuse, $resultcostex, $con, $referencia)
{
    $entra = 0;
    $consulta='';
    $precio = 0;
    $consulta .= '<h4 style="font-size:18px">Referencia Buscada: '. $referencia .'</h4><br/>';
    $referenciaP = '';
 
    // Primero vamos con referencias de agrocosat
    if ($resultado_alterno && !empty($resultado_alterno)) {

        $registros = $resultado_alterno;
        if (BloquearLineas($registros[0]['Linea'])) {

            return '<table class="table modern-table table-hover" id="tablaPrincipal">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th>Bodega <br> BARRANQUILLA </th>
                            <th>Bodega <br> BOGOTÁ</th>
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>

                        <tr>
                        <td>0</td><td>0</td><td>0</td><td></td><td>$0</td> </tr>

                    </tbody>
                    </table>';
        }

        $entra = 1;


        

        foreach ($registros as $fila) {

        if (($fila['Existencias'] + $fila['Existencias_bog']) > '0' && $fila['Tipo'] == 'agro') {
            $existencias = $fila['Existencias'];
            $existencias_bog = $fila['Existencias_bog'];
            

        } 
        
        elseif (($fila['Existencias'] + $fila['Existencias_bog'])  < '1' && $fila['Tipo'] == 'agro') {
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

        
        if (!BloquearLineas($fila['Linea'], 'precio'))
        {
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
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>

                        <tr>
                    <td>' . $referencia . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>' . $existencias_bog . '</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>
                    </tr>

                    </tbody>
                    </table>';


                    if (($fila['Existencias'] + $fila['Existencias_bog'])  < '1' && $fila['Tipo'] == 'agro') {

                       $consulta.= '<br>'. ExistenciasCostex($resultcostex, $referencia);

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
        $sqlP = "SELECT * FROM inventario WHERE referencia = :referenciaP and Tipo='agro' ";
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
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>

                        <tr>
                        <td>0</td><td>0</td><td>0</td><td></td><td>$0</td> </tr>

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
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>
            <tr>
            <td>' . $referenciap . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>' . $existencias_bog . '</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>
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
                            <th>Precio Antes de IVA</th>
                        </tr>
                    </thead>
                    <tbody>
            <tr>
            <td>' . $referenciap . '</td><td>' . $descripcion . '</td><td>' . $existencias . '</td><td>' . $existencias_bog . '</td><td>$' . number_format($precio_con_descuento, 0, '.', ',') . '</td>
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

<style>
    body {
            padding: 0px;
            font-size: 14px;
    }

     .container {
            max-width: 1000px !important;
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

        .container {
            max-width: 900px !important;
            margin: 0 auto;
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



/*Descuentos CSS*/

#descuento-box {
  /* Glassmorphism + Neumorphism + Glow + Gradient */
  background: linear-gradient(120deg, #ff5722 60%, #f9c846 100%);
  border-radius: 18px;
  box-shadow: 0 8px 32px 0 rgba(255, 87, 34, 0.25), 0 2px 8px #ffc400bb;
  color: #fff;
  font-family: 'Montserrat', Arial, sans-serif;
  font-size: 18px;
  padding: 18px 28px;
  margin: 30px auto 15px auto;
  max-width: 640px;
  position: relative;
  display: flex;
  align-items: center;
  gap: 20px;
  overflow: hidden;
  backdrop-filter: blur(3px);
  border: 1.5px solid rgba(255,255,255,0.25);
  animation: pulse-pop 1.5s infinite alternate;
}

#descuento-box .icon-flash {
  animation: spin-flash 0.3s linear infinite;
  margin-right: 18px;
  font-size: 30px;
  filter: drop-shadow(0 0 8px #fff17f99);
}

#descuento-box strong {
  background: rgba(255,255,255,0.14);
  border-radius: 8px;
  padding: 2px 9px 2px 9px;
  color: #fffde4;
  font-size: 21px;
  margin: 0 7px;
}

@keyframes pulse-pop {
  0% { transform: scale(1); box-shadow: 0 0 12px #ffb30044; }
  50% { transform: scale(1.035); box-shadow: 0 0 28px #ff9800cc, 0 4px 28px #ffb30033; }
  100% { transform: scale(1); }
}
@keyframes spin-flash {
  0% { transform: rotate(-7deg);}
  50% { transform: rotate(7deg);}
  100% { transform: rotate(-7deg);}
}

/*Descuentos CSS*/
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
                                <li><a href='bajarotacion.php' class="dr$conwn-item">Baja Rotacion (ADMIN)</a></li>
                            </ul>
 
                            <ul class="dr$conwn-menu">
                                <li><a href='consulta_inventario_admin.php' class="dr$conwn-item">Consultar Inventario (ADMIN)</a></li>
                            </ul>
                        <?php
                    }

                   // Existencias($_SESSION['idusuario'], $resultado_alterno, $resultuse[0], $resultcostex[0], $con)

                    
                ?>
            </div>
            </div>

        </span>


    </nav>

    <h1 class="text-center mb-4">Consulta de Precios</h1>

    <div class="container">
        <div class="">
        
       <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="tipo_busqueda">Buscar por:</label>
                <div>
                    <input type="radio" id="referencia" name="tipo_busqueda" value="referencia" 
                    <?php echo  ((!isset($_POST['tipo_busqueda']) && !isset($_GET['tipo_busqueda'])) || $_POST['tipo_busqueda'] == 'referencia') ? 'checked' : ''; ?> 
                    onclick="cambiarBusqueda()"> 

                    <label for="referencia">Referencia</label>
                    <input type="radio" id="descripcion" name="tipo_busqueda" value="descripcion" 
                   <?php echo ((($_POST['tipo_busqueda'] ?? $_GET['tipo_busqueda'] ?? '') === 'descripcion') ? 'checked' : ''); ?> onclick="cambiarBusqueda()">
                    <label for="descripcion">Descripción</label>
                </div>

            </div>
            
            <!-- Campo de búsqueda por referencia -->
            <div class="form-group" id="referencia_group">
                <input autofocus type="text" class="form-control" name="referencia" id="referencia" placeholder="Escribe una referencia..." required>
            </div>
            
            <!-- Campo de búsqueda por descripción (oculto por defecto) -->
            <div class="form-group" id="descripcion_group" style="display:none;">
                <input autofocus type="text" class="form-control" name="descripcion" id="descripcion" placeholder="Escribe una descripcion..." required>
            </div>

          <div class="d-flex align-items-center">
                
                <button type="submit" name="buscar" class="btn btn-primary">Buscar</button>

                <?php
                // --- INICIO: Lógica para mostrar el botón "Volver" ---
                
                // Determina el tipo de búsqueda actual
                $tipo_busqueda_actual = $_POST['tipo_busqueda'] ?? $_GET['tipo_busqueda'] ?? 'referencia';
                
                // Comprueba si se ha realizado una búsqueda
                $busqueda_realizada = isset($_POST['buscar']) || isset($_GET['referencia']);

                // Muestra el botón "Volver" SÓLO si se ha realizado una búsqueda Y fue por "referencia"
                if ($busqueda_realizada && $tipo_busqueda_actual == "referencia") {
                ?>
                    <a href="#" onclick="history.go(-1); return false;" class="btn btn-secondary ml-2">
                        &larr; Volver
                    </a>
                <?php
                }
                // --- FIN: Lógica para mostrar el botón "Volver" ---
                ?>
            </div>
    </form>




        </div>
        <?php



        global $resultado_alterno;
        global $resultuse;
        global $resultcostex;
        global $alternos;
        if (isset($_POST['buscar']) || isset($_GET['referencia'])) :

            $tipo_busqueda = $_POST['tipo_busqueda'];

            if ($tipo_busqueda == "referencia" || isset($_GET['referencia'])) :
            
            if ($tipo_busqueda == "referencia" )
            {
                $referencia = $_POST['referencia'];

            }
            else{

                $referencia = $_GET['referencia'];


            }
            
            

            $sql = $con->prepare("SELECT * FROM inventario WHERE referencia = :referencia and Tipo='agro'");
            $sql->execute(['referencia' => $referencia]);
            $resultado_alterno = $sql->fetchAll();

            //$cantidad = count($resultado_alterno);
            //echo "Se encontraron $cantidad registros.";

            $sql2 = $con->prepare("SELECT * FROM inventario WHERE referencia = :referencia and Tipo='use'");
            $sql2->execute(['referencia' => $referencia]);
            $resultuse = $sql2->fetchAll();

            $sql3 = $con->prepare("SELECT * FROM inventario WHERE referencia = :referencia and Tipo='costex'");
            $sql3->execute(['referencia' => $referencia]);
            $resultcostex = $sql3->fetchAll();

            if (($resultado_alterno && $sql->rowCount() > 0) || ($resultuse && $sql2->rowCount() > 0) || ($resultcostex && $sql3->rowCount() > 0)) :
        
                // Mostrar descuento 
                //$descuentoInfo = getDescuentoInfo($referencia, $con); // Obtiene info del descuento
                //renderDescuentoBox($descuentoInfo); // Muestra el aviso si hay descuento

                if ($sql->rowCount() > 0 
                && isset($resultado_alterno[0]['Existencias']) 
                && intval($resultado_alterno[0]['Existencias']) > 0
                ) {
                    $descuentoInfo = getDescuentoInfo($referencia, $con);
                    if ($descuentoInfo) {
                        renderDescuentoBox($descuentoInfo);
                    }
                 }
        
        ?>
                <br>
               

                 <?php echo Existencias($_SESSION['idusuario'], $resultado_alterno, $resultuse[0], $resultcostex[0], $con, $referencia );

                            // $alternos=$fila['Alternos'];
                   ?>

                <!--
                <div class="alert alert-warning" role="alert" id="MensajePrecios1" style="display:none;color:#111;background:#d7e9fb;font-size:16px;">
                Si deseas conocer precios de la mercancia proxima a llegar, consulte con su asesor o al correo:  <a href="ventas@agro-costa.com">ventas@agro-costa.com</a>
                </div>-->
 
                <br />
        <?php //inicio de alternos
        // global $alternos; // Esta variable ya fue declarada y usada arriba
        $arregloAlternos = Alternos($_SESSION['idusuario'],  $alternos, $referencia, $con);

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
                <div class="col">Bodega Barranquilla</div>
                <div class="col">Bodega Bogotá</div>
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
                $desc_alterno_db = $desc_alterno_comentario; // Usar la del comentario por defecto

                $sql = $con->prepare("SELECT * FROM inventario WHERE referencia = :referencia and Tipo='agro' ");
                $sql->execute(['referencia' => $ref_alterno]);
                $resultado = $sql->fetchAll();

                if ($resultado && count($resultado) > 0) {
                    foreach ($resultado as $fila) {
                        $precioAlterno =  $fila['Precio'];
                        $existenciasAlterno = $fila['Existencias'] + $fila['Existencias_bog'];
                        $existencias_baq= $fila['Existencias'];
                        $existencias_bog =$fila['Existencias_bog'];
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
$mensaje = '<button type="button" class="btn btn-primary" style="font-size: 12px; padding: 5px 5px;">Ver pedidos</button>';                }
            ?>
                <a href="consulta_inventario.php?referencia=<?php echo $ref_alterno; ?>&tipo_busqueda=referencia">
                    <div class="custom-row row hover-row" style="text-align: center;">
                        <div class="col"><?php echo $ref_alterno; ?></div>
                        <div class="col"><?php echo $desc_alterno_db; ?></div>
                        <div class="col"><?php echo $com_alterno; ?></div>
                        <div class="col">Alterno</div>
                        <div class="col"><?php echo $existencias_baq; ?></div>
                        <div class="col"><?php echo $existencias_bog; ?></div>
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
                $desc_alterno_db = $desc_alterno_comentario; // Usar la del comentario por defecto

                $sql = $con->prepare("SELECT * FROM inventario WHERE referencia = :referencia and Tipo='agro' ");
                $sql->execute(['referencia' => $ref_alterno]);
                $resultado = $sql->fetchAll();

                if ($resultado && count($resultado) > 0) {
                    foreach ($resultado as $fila) {
                        $precioAlterno =  $fila['Precio'];
                        $existenciasAlterno = $fila['Existencias'] + $fila['Existencias_bog'];
                        $existencias_baq= $fila['Existencias'];
                        $existencias_bog =$fila['Existencias_bog'];
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
$mensaje = '<button type="button" class="btn btn-primary" style="font-size: 12px; padding: 5px 5px;">Ver pedidos</button>';                }
        ?>
                <a href="consulta_inventario.php?referencia=<?php echo $ref_alterno; ?>&tipo_busqueda=referencia">
                    <div class="custom-row row hover-row" style="text-align: center;">
                        <div class="col"><?php echo $ref_alterno; ?></div>
                        <div class="col"><?php echo $desc_alterno_db; ?></div>
                        <div class="col"><?php echo $com_alterno; ?></div>
                        <div class="col">Complementario</div>
                        <div class="col"><?php echo $existencias_baq; ?></div>
                        <div class="col"><?php echo $existencias_bog; ?></div>
                        <div class="col"><?php echo $mensaje; ?></div>
                    </div>
                </a>
        <?php 
            } // --- FIN LOOP COMPLEMENTARIOS ---
        ?>
        </div> <?php 
        } // --- FIN if (!empty($arregloAlternos...)) ---
    ?>
                <?php
                global $precio_con_descuento;
                guardarMovimiento($_SESSION['idusuario'], $referencia, date('Y-m-d H:i:s'), $precio_con_descuento, $con); ?>

           
        <?php
        global $Pedidos;
        echo $Pedidos;
        ?>

           

 <?php else : ?>
                <div class="alert alert-danger" role="alert">
                    No se encontraron resultados para la referencia "<?php echo $_POST['referencia'] ?>"
                </div>
    
                 <?php endif;
         ?>

        <?php

                

         else :

                //echo "bieneeee " . $tipo_busqueda. $_POST['descripcion'];

                $limite=0;
                if (($tipo_busqueda == "descripcion" ))
                {
                    if ($_SESSION['idusuario'] == "ADMIN"){

                        $limite=50;


                    }
                    else
                    {
                        $limite=10;


                    }

                    // Texto que ingresa el usuario
                    $descripcion = $_POST['descripcion'];

                    // Lo dividimos en palabras
                    $palabras = explode(" ", $descripcion);

                    // Construimos la cláusula WHERE dinámica
                    $condiciones = [];
                    $params = [];

                    
                    /* funciona
                    // Creamos dinámicamente cada condición LIKE
                    foreach ($palabras as $i => $palabra) {
                        $key = ":descripcion$i";
                        $condiciones[] = "descripcion LIKE $key";
                        $params[$key] = "%" . $palabra . "%";
                    }

                    // Unimos las condiciones con AND
                    $where = implode(" AND ", $condiciones);
                    */
                    
                    $condicionesDescripcion = [];
                    $condicionesReferencia = [];
                    $params = [];

                    foreach ($palabras as $i => $palabra) {
                        $key = ":descripcion$i";
                        $condicionesDescripcion[] = "descripcion LIKE $key";
                        $condicionesReferencia[] = "referencia LIKE $key";
                        $params[$key] = "%" . $palabra . "%";
                    }

                    // Construimos las condiciones
                    $where = "((" . implode(" AND ", $condicionesDescripcion) . ") OR (" . implode(" AND ", $condicionesReferencia) . "))";
                    


                    /*
                    // Consulta por descripción (limitada a las primeras 20 coincidencias)
                    $sql = $con->prepare("SELECT * FROM inventario WHERE descripcion LIKE :descripcion AND Tipo<>'use' LIMIT $limite ");
                    $sql->execute(['descripcion' => "%$descripcion%"]);
                    $resultado = $sql->fetchAll();
                    */

                    //NUEVA PARA QUE ME SALGA CONSULTA POR CUALQUEIR PARTE DE LA CADENA
                    // Límite fijo (o dinámico si lo quieres más adelante)
                    $sql = $con->prepare("SELECT Referencia, descripcion, Existencias as Existencias, Existencias_bog, Precio AS Precio, Linea AS Linea  FROM inventario WHERE $where AND Tipo='agro' group by Referencia, descripcion order by sum(Existencias + Existencias_bog) desc LIMIT $limite ");
                    $sql->execute($params);
                    $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);

                    //pendientes
                    //link alternos
                    // BOTON ATRAS
                    
                    //CLIK EN ALTERNOS
                      //  descripciones adicionals
                       // marcas pedidos
                        //marcas inventario
                    // marcas
                    // Marcas ultima entrada... marcas de pedidos... marcas de items invenatrio utlma entrada y listo... si esta en cero no mostrar nada.

                    // su chatbt se conecta con make ojo y make se coencta con todo ojooooo buen soplo no es que se conecte manual por API.
                    // si se coencta por make puedo hacer mas cosa ojo.

                    //anotar tecnolgia
                    //cron para temas de ejcutar diariamente una URL local... servidor o cron como servico 
                    //O usa un servicio en la nube (como cron-job.org) que llame a una URL PHP periódicamente.

                    if ($resultado && count($resultado) > 0) {
                        echo "<br><h4>Resultados encontrados: $descripcion </h4>";
                        echo '<div class="container mt-4">
                        <div class="header-row row hover-row border p-2" style="font-weight:800; text-align:center;background-color: #b2dcff;">
                        <div class="col" ><h4 >Elige la opción deseada para más información </h4></div></div>
                        <div class="header-row row hover-row border p-2" style="color: #0d6efd;font-weight: 600;font-size: 14px;padding: 5px 15px !important;vertical-align: middle !important;background-color: #e9f2ff; border-bottom: 2px solid #dee2e6;text-align: center; border-bottom: 2px solid #dee2e6 !important;">
                            <div class="col">Referencia</div>
                            <div class="col">Descripcion</div>
                            <div class="col">Bodega <br> BARRANQUILLA</div>
                            <div class="col">Bodega <br> BOGOTÁ</div>
                        </div>';

                        foreach ($resultado as $fila) {

                            if(BloquearLineas($fila['Linea'], 'descripcion')==false){

                            if (($fila['Existencias']+$fila['Existencias_bog'])>0)
                            {
                                $descuento = $_SESSION['D' . $fila['Linea']];
                                $precioADesccripcion = round($fila['Precio'] - ($fila['Precio']  * $descuento / 100));
                                //echo "<li><a href='consulta_inventario.php?referencia=" . $fila['Referencia'] . "'>" . $fila['Referencia'] . " - " . $fila['descripcion'] . "</a></li>";
                                echo '<a href="consulta_inventario.php?referencia='.$fila['Referencia'] . '&tipo_busqueda='.$tipo_busqueda.'"><div class="custom-row row hover-row" style="text-align: center;"><div class="col">' . $fila['Referencia'] . '</div><div class="col">' . $fila['descripcion'] . '</div> <div class="col">' . $fila['Existencias'] . '</div> <div class="col">' . $fila['Existencias_bog'].'</div> </div></a>';

                            }
                            else{

                                 echo '<a href="consulta_inventario.php?referencia='.$fila['Referencia'] . '&tipo_busqueda='.$tipo_busqueda.'"><div class="custom-row row hover-row" style="text-align: center;"><div class="col">' . $fila['Referencia'] . '</div><div class="col">' . $fila['descripcion'] . '</div> <div class="col">' . $fila['Existencias'] . '</div> <div class="col">' . $fila['Existencias_bog']. '</div> </div></a>';




                            }
                        }

                            


                        }
                        echo "</div>";
                    } 
                    else {
                        echo "<div class='alert alert-danger'>No se encontraron resultados.</div>";
                    }

            }
            



            endif;
        endif;



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



     function cambiarBusqueda() {
    var tipoBusqueda = document.querySelector('input[name="tipo_busqueda"]:checked').value;

    if (tipoBusqueda == "referencia") {
        document.getElementById("referencia_group").style.display = "block";
        document.getElementById("descripcion_group").style.display = "none";
    } else if (tipoBusqueda == "descripcion") {
        document.getElementById("referencia_group").style.display = "none";
        document.getElementById("descripcion_group").style.display = "block";
    }
}

// Llamar la función para inicializar la interfaz cuando la página carga
document.addEventListener("DOMContentLoaded", function() {
    cambiarBusqueda();
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

if (pedidos!== null) {
        // Obtener la fecha del pedido (segunda fila, tercera celda)
        var fechaPedido = pedidos.rows[1].cells[3].textContent;
        var precioPedido = pedidos.rows[1].cells[5].textContent;
        var disponible= tablaPrincipal .rows[1].cells[2].textContent;
        var marca= pedidos.rows[1].cells[4].textContent;


        // Comprobar si la fecha contiene '60 DIAS'
        if (disponible.includes('60 DIAS')) {
            // Obtener la fecha del pedido y asignarla a la tabla principal
            tablaPrincipal.rows[1].cells[2].innerHTML="Proximo en Llegar: " + fechaPedido + '<a href="https://agro-costa.com/consulta/consulta_inventario.php#pedidos"></a>';
            tablaPrincipal.rows[1].cells[4].innerHTML=precioPedido; 
            tablaPrincipal.rows[0].cells[2].innerHTML="Disponible";
            tablaPrincipal.rows[0].cells[3].innerHTML="Marca";
            tablaPrincipal.rows[1].cells[3].innerHTML=marca;
            //document.querySelectorAll('#tablaPrincipal tr').forEach(tr => tr.deleteCell(3));

            

            document.getElementById('MensajePrecios1').style.display = 'block' ;
        }
        
        }
    });
        
    </script>
</body>

</html>

    
