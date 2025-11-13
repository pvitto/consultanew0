<?php


// PROBAR API
//api que uso para la taza: https://openexchangerates.org/account/usage
// usuario: cacosta@agro-costa.com
// tiene 1000 peticiones al mes... por eso guardo diriamete en la BD lo que hara que sean 30 peticiones al mes... en la web nos muestra el uso que llevamos.
// verificar su estado en la web nos muestra hay un status, pero tambien con 
// PRUEBAS DE LA API
// https://openexchangerates.org/api/latest.json?app_id=58ce53769ce7469bad9753a923cae85a // ese numero al final es el API KEY mio de la cuenta creada con el correo
// cacosta@agro-costa.com




// no muestre errores si no me ejecuta algo puedo quitarlo para ver los erroes
require_once 'funciones_recoger.php';
//require_once 'funciones_tablas.php';

function PreciosPedidos($vendorid,$moneda,$costo, $linea, $descuento ){

// Conecta a la base de datos
$con = ConectarBaseDatos();

// APP ID de Open Exchange Rates
$app_id = "58ce53769ce7469bad9753a923cae85a";
$url = "https://openexchangerates.org/api/latest.json?app_id={$app_id}";

// --- FECHAS ---
// Fijar la zona horaria a Bogotá, Colombia
date_default_timezone_set('America/Bogota');
$hoy = new DateTime();
$anioMes = $hoy->format('Y-m'); // ej: 2025-10 (mes actual)


// 1. Revisar si ya existe registro para este mes en la tabla moneda
$sql = "SELECT * FROM monedas WHERE idmoneda = :moneda and DATE_FORMAT(fecha, '%Y-%m') = :anioMes ORDER BY fecha ASC LIMIT 2"; // las más antiguas del mes (si existen) ";
$stmt = $con->prepare($sql);
   $stmt->execute([
        ':moneda' => $moneda,
        ':anioMes' => $anioMes
    ]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Inicializar variables
$tasa = null;



if ($row) {
        $tasa = $row['taza'];
       //echo " Usando tasa {$moneda} {$tasa} guardada en BD ({$row['fecha']})<br>";
}
else 
{

    try {
    // --- No existe, consultar API ---
    // Obtener la respuesta
    $response = file_get_contents($url);

    if ($response === false) {
        //hacer por si no hay conexion permita seguir proceso y responda con el precio final de todo en 0.
        /*die("Error al consultar API");
        return 0;*/

        echo "<script>console.error('Error: No se pudo conectar con la API de tasas.');</script>";
        return 0;
    }


    $data = json_decode($response, true);

    // Verificar que traiga la TRM
    if (!isset($data['rates']['COP']) && !isset($data['rates']['EUR'])) {

        echo "<script>console.error('Error: Respuesta de API inválida o sin datos de tasas.');</script>";
        return 0;
    }

            // Valor oficial 1 USD en COP
            $usdToCop = $data['rates']['COP'];
            $eurToCop = $usdToCop / $data['rates']['EUR'];

            // Fecha desde el timestamp de la API (Y-m-d)
            $timestamp = $data['timestamp'];
            date_default_timezone_set("America/Bogota");
            $fechaConsulta = date("Y-m-d", $timestamp);

              // Insertar registros en tabla moneda
            $sql = "INSERT INTO monedas (idmoneda, taza, fecha) VALUES (:idmoneda, :taza, :fecha)";
            $stmt = $con->prepare($sql);

            // Insertar USD
            $stmt->execute([
                ':idmoneda' => 'USD',
                ':taza'    => $usdToCop,
                ':fecha'    => $fechaConsulta
            ]);

            // Insertar EUR
            $stmt->execute([
                ':idmoneda' => 'EUR',
                ':taza'    => $eurToCop,
                ':fecha'    => $fechaConsulta
            ]);

           // echo "Tasas insertadas en BD<br>";

             // Seleccionar tasa según moneda solicitada
            if ($moneda === 'USD') {
                $tasa = $usdToCop;
            } elseif ($moneda === 'EUR') {
                $tasa = $eurToCop;
            } else {
                echo "<script>console.error('Error: Moneda no reconocida ({$moneda}).');</script>";
                return 0;
            }

        } catch (Exception $e) {
            echo "<script>console.error('Excepción API: " . addslashes($e->getMessage()) . "');</script>";
            return 0;
        }
}




// --- Ahora haces cálculos ---

$precioCOP=0;
$factor=0.00;
$utilidad=0.00;
$maxDescuento=0;


// --- CONSULTAR POLÍTICAS DE PRECIO ---

$sql = "SELECT Factor, Utilidad_Minima FROM politica_price WHERE Idproveedor = :vendorid";
$stmtPolitica = $con->prepare($sql);
$stmtPolitica->execute(['vendorid' => $vendorid]);

// Obtener todos los resultados
$politica = $stmtPolitica->fetch(PDO::FETCH_ASSOC);

// Validar resultados
if (!$politica) {
        echo "<script>console.warn('No hay política de precios para el proveedor {$vendorid}.');</script>";
        return 0;
}

$factor = $politica['Factor'];
$utilidad = $politica['Utilidad_Minima'];

// --- CONSULTAR MAX DESCUENTO DESDE resumen_descuentos ---
    $sql = "SELECT MAX(max_descuento) AS MaxDescuento 
            FROM resumen_descuentos 
            WHERE linea = :linea";
    $stmt = $con->prepare($sql);
    $stmt->execute([':linea' => $linea]);
    $rowDesc = $stmt->fetch(PDO::FETCH_ASSOC);

 $maxDescuento = $rowDesc['MaxDescuento'];

  // --- VALIDAR DATOS ---
    if ($factor <= 0 || $utilidad <= 0 || $maxDescuento <= 0 || $costo <= 0 || $tasa <= 0) {
        echo "<script>console.warn('Datos incompletos: factor, utilidad, tasa, descuento o costo inválido.');</script>";
        return 0;
    }


if($moneda='USD')
{
    $ajuste = 150; // sobrecargo fijo
    $precioCOP = ($costo * $factor * ($tasa + $ajuste)) / ($utilidad * $maxDescuento);

}
elseif($moneda='EUR')
{
    $ajuste = 150; // sobrecargo fijo
    $precioCOP = ($costo * $factor * ($tasa + $ajuste)) / ($utilidad * $maxDescuento);
}

if ($_SESSION['idusuario'] == "ADMIN")
{
echo $costo. ' '. $factor .' ' . $tasa + $ajuste.' '.$utilidad .' '. $maxDescuento . ' '.$descuento.' L:'.$linea. ' Listo ';
}

/*
echo "USD->COP: " . number_format($usdToCop, 2) . "<br>";
echo "EUR->COP: " . number_format($eurToCop, 2) . "<br>";
echo "Precio USD: " . number_format($precioCOP_usd, 2) . " COP<br>";
echo "Precio EUR: " . number_format($precioCOP_eur, 2) . " COP<br>";
*/



return round($precioCOP - ($precioCOP * $descuento / 100));


}



?>