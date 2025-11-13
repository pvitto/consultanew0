<?php

require_once 'logout.php';
require_once 'funciones_recoger.php';

function exportarExcel() {

$pdo = ConectarBaseDatos();

    $sql = "SELECT b.Idusuario, a.referencia, descripcion, Existencias, FORMAT(b.Precio, 'c', 'es-co') as Precio, Linea, DiasSinVentas, ABCClass, DATE_SUB(b.fechaconsulta, INTERVAL 5 HOUR) as fechaconsulta 
            FROM `inventario` a 
            INNER JOIN mov b ON a.Referencia = b.referencia 
            WHERE Existencias>0 and (a.ABCClass = 6 OR DiasSinVentas > 180) order by fechaconsulta desc";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventario.csv";');
    header('Pragma: no-cache');
    header('Expires: 0');

    $salida = fopen('php://output', 'w');
    // Escribir la cabecera
    fputcsv($salida, array('Idusuario', 'Referencia', 'Descripcion', 'Existencias','Precio' ,'Linea', 'Dias Sin Ventas', 'ABC Class', 'Fecha Consulta'));

    // Escribir los datos
    foreach ($resultados as $fila) {
        fputcsv($salida, $fila);
    }

    fclose($salida);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exportar'])) {
    // Asumiendo que ya tienes una conexión PDO configurada
    $pdo = ConectarBaseDatos();
    exportarExcel($pdo);
}
?>