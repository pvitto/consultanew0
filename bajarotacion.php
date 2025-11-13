<?php

require_once 'logout.php';
require_once 'funciones_recoger.php';

if (!isset($_SESSION['idusuario']) || $_SESSION['idusuario'] != "ADMIN") {
    header("Location: consulta_inventario.php");
    exit();
}

function generarTablaConPaginacion($pdo, $paginaActual = 1) {
    $registrosPorPagina = 15;
    $offset = ($paginaActual - 1) * $registrosPorPagina;

    // Preparar la consulta con límite y desplazamiento para la paginación
    $sql = "SELECT b.Idusuario, a.referencia, descripcion, Existencias, FORMAT(b.Precio, 'c', 'es-co') as Precio, Linea, DiasSinVentas, ABCClass, DATE_SUB(b.fechaconsulta, INTERVAL 5 HOUR) as fechaconsulta 
            FROM `inventario` a 
            INNER JOIN mov b ON a.Referencia = b.referencia 
            WHERE Existencias>0 and (a.ABCClass = 6 OR DiasSinVentas > 180) order by fechaconsulta desc
            LIMIT :limit OFFSET :offset  ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar el total de registros para la paginación
    $sqlCount = "SELECT COUNT(*) FROM `inventario` a 
                 INNER JOIN mov b ON a.Referencia = b.referencia 
                 WHERE Existencias>0 and (a.ABCClass = 6 OR DiasSinVentas > 180)";
    $totalRegistros = $pdo->query($sqlCount)->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

    // Generar la tabla HTML con los resultados
    echo '<table border="1" class="table table-hover">';
    echo '<tr><th>Idusuario</th><th>Referencia</th><th>Descripción</th><th>Existencias</th><th>Precio</th><th>Línea</th><th>Días Sin Ventas</th><th>ABC Class</th><th>Fecha Consulta</th></tr>';
    
    foreach ($resultados as $fila) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($fila['Idusuario']) . '</td>';
        echo '<td>' . htmlspecialchars($fila['referencia']) . '</td>';
        echo '<td>' . htmlspecialchars($fila['descripcion']) . '</td>';
        echo '<td>' . htmlspecialchars($fila['Existencias']) . '</td>';
        echo '<td>' . htmlspecialchars($fila['Precio']) . '</td>';
        echo '<td>' . htmlspecialchars($fila['Linea']) . '</td>';
        echo '<td>' . htmlspecialchars($fila['DiasSinVentas']) . '</td>';
        echo '<td>' . htmlspecialchars($fila['ABCClass']) . '</td>';
        echo '<td>' . htmlspecialchars($fila['fechaconsulta']) . '</td>';
        echo '</tr>';
    }

    echo '</table>';

    // Generar enlaces de paginación
    echo '<div>  <nav aria-label="..."><ul class="pagination">';
    
    for ($i = 1; $i <= $totalPaginas; $i++) {
    
    
    if ($paginaActual === $i) {
         echo '<li class="page-item active"><a class="page-link" href="?pagina=' . $i . '">' . $i . '</a> </li>';
    }
    else{
    
    if ($i === 1) {
         echo '<li class="page-item"><a class="page-link" href="?pagina=' . $i . '">' . $i . '</a> </li>';
    }
    
    else{
    
    if ($i === $paginaActual + 1 || $i === $paginaActual - 1 || $i== $totalPaginas) {
         echo '<li class="page-item"><a class="page-link" href="?pagina=' . $i . '">' . $i . '</a> </li>';
    }
    
    else{
    
    
    if ($i> $paginaActual + 1 && $i< $paginaActual + 3 ) {
         echo '<li class="page-item"><a class="page-link" href="#">...</a> </li>';
    }
    
    }
    
    }
 
      //echo '<li class="page-item"><a class="page-link" href="?pagina=' . $i . '">' . $i . '</a> </li>';
    }
    
    
        
    }
    echo '</ul></nav></div>';
    
   

  



}
// Obtener la página actual desde la URL, si existe
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

/*
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$con = ConectarBaseDatos();
 generarTablaConPaginacion($con, $paginaActual);
}*/

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Inventario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">

<style>
th {
    text-align: inherit;
    background-color: black;
    color: #fff;
}

.table td {
    padding: 5px 5px !important;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
    text-align: center;
}

*, ::after, ::before {
    box-sizing: content-box;
}
</style>
</head>
<body>

  <!-- Begin page content -->
    <main role="main" class="container">
      <h1 class="text-center mb-4">Seguimiento ITEMS con baja rotacion</h1>
      <p class="lead">Permite realizar un seguimiento estricto a los ITEMS con baja rotacion.</p>
        <div class="container mt-5">
        <div>
         <!--  <form method="POST" class="text-center mb-4">
            <button type="submit" name="consultar" class="btn btn-primary">Consultar Inventario</button>
        </form> -->
        <a name="consultar" href='?pagina=1' class="btn btn-primary">Consultar ITEMS</a>
        <!--  <button id="exportBtn1" class="btn btn-success">Exportar a Excel</button> -->
        <button id="exportBtn" class="btn btn-success">Exportar a Excel</button>

        </div>
        <br/>
        <div id="resultados">
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['pagina'])) {
                // Aquí se debe asegurar de que la conexión está establecida
                $con = ConectarBaseDatos();
                $paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
                generarTablaConPaginacion($con, $paginaActual);
            }
            ?>
        </div>
    </div>
    </main>
    
    

    
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
       <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>


 <script>
        document.getElementById('exportBtn').addEventListener('click', function() {
            $.ajax({
                url: 'exportar_excel.php',
                type: 'POST',
                data: { exportar: true },
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(blob) {
                    var link = document.createElement('a');
                    link.href = window.URL.createObjectURL(blob);
                    link.download = 'inventario.csv';
                    link.click();
                },
                error: function() {
                    alert('Hubo un problema al exportar la tabla a Excel.');
                }
            });
        });
    </script>
    
    <script>
       document.getElementById('exportBtn1').addEventListener('click', function() {
            // Obtener los datos de la tabla
            var tabla = document.querySelector('#resultados table');
            if (!tabla) {
                alert('No hay datos para exportar.');
                return;
            }

            var csv = [];
            var filas = tabla.querySelectorAll('tr');

            // Iterar sobre las filas de la tabla
            filas.forEach(function(fila) {
                var celdas = fila.querySelectorAll('td, th');
                var filaArray = [];
                celdas.forEach(function(celda) {
                    filaArray.push('"' + celda.innerText.replace(/"/g, '""') + '"');
                });
                csv.push(filaArray.join(','));
            });

            // Crear un Blob con los datos CSV
            var csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
            var enlace = document.createElement('a');
            enlace.href = URL.createObjectURL(csvFile);
            enlace.download = 'tabla_inventario.csv';
            enlace.click();
        });
        
        
    </script>
</body>
</html>