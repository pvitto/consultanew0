<?php
// Ocultar errores visuales
error_reporting(0);

require_once 'logout.php';
require_once 'funciones_recoger.php';
require_once 'funciones_tablas.php';
require_once 'precios_pedidos.php'; 

// 1. SEGURIDAD
if (!isset($_SESSION['idusuario']) || $_SESSION['idusuario'] != "ADMIN") {
    header("Location: consulta_inventario.php");
    exit();
}

$con = ConectarBaseDatos();
if (!ChequearExistenciaTabla("acceso_a_usuarios", $con)) { CrearTabla($con); }

// =========================================================================
// 2. FUNCIONES SIMULADOR (Versión Simplificada y Segura)
// =========================================================================

function obtenerDescuentoSimulado($usuario, $linea, $con) {
    $columna = "d" . intval($linea); 
    try {
        $sql = $con->prepare("SELECT $columna FROM usuarios WHERE Idusuario = :usuario");
        $sql->execute(['usuario' => $usuario]);
        $res = $sql->fetch(PDO::FETCH_ASSOC);
        return isset($res[$columna]) ? (int)$res[$columna] : 0;
    } catch (Exception $e) { return 0; }
}

function MostrarAlternosSimulados($cadenaAlternos, $usuarioSimulado, $con) {
    if (empty($cadenaAlternos)) return "";
    
    $items = explode("...", $cadenaAlternos);
    $html = "";
    $filas = "";

    foreach ($items as $itemStr) {
        if (trim($itemStr) == "") continue;

        $partes = explode("COMENTARIO:", $itemStr);
        $refAlt = trim($partes[0]);
        $comentario = isset($partes[1]) ? trim($partes[1]) : "";
        
        $tipoLabel = "Alterno";
        if (stripos($itemStr, "Complementario") !== false) $tipoLabel = "Complementario";
        elseif (stripos($itemStr, "Alias") !== false) $tipoLabel = "Alias";

        // CONSULTA SEGURA SIN JOIN (Evita error de Marcas)
        try {
            $sql = $con->prepare("SELECT * FROM inventario WHERE Referencia = :ref AND Tipo='agro'");
            $sql->execute(['ref' => $refAlt]);
            $datos = $sql->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { continue; }

        if ($datos) {
            $linea = $datos['Linea'];
            $desc = obtenerDescuentoSimulado($usuarioSimulado, $linea, $con);
            $precio = $datos['Precio'];
            $precioFinal = round($precio - ($precio * $desc / 100));
            
            $filas .= "<tr>
                        <td><b>{$refAlt}</b></td>
                        <td>{$datos['descripcion']}</td>
                        <td><small>{$comentario}</small></td>
                        <td><span class='badge badge-info'>{$tipoLabel}</span></td>
                        <td>{$datos['Existencias']}</td>
                        <td>{$datos['Existencias_bog']}</td>
                        <td>{$datos['Marca']}</td>
                        <td>$ " . number_format($precioFinal, 0, '.', ',') . "</td>
                       </tr>";
        }
    }

    if ($filas != "") {
        $html .= '<div class="mt-3"><h6 class="text-secondary border-bottom pb-2">Alternos y Complementarios</h6>
                  <table class="table table-sm table-bordered table-hover bg-white" style="font-size:13px;">
                    <thead class="thead-light"><tr><th>Ref</th><th>Descripción</th><th>Comentario</th><th>Tipo</th><th>BAQ</th><th>BOG</th><th>Marca</th><th>Precio</th></tr></thead>
                    <tbody>' . $filas . '</tbody>
                  </table></div>';
    }
    return $html;
}

function MostrarPedidosSimulados($referencia, $usuarioSimulado, $precioActualItem, $con) {
    // 1. Obtener Línea de forma segura
    try {
        $stmtL = $con->prepare("SELECT Linea FROM inventario WHERE Referencia = :ref LIMIT 1");
        $stmtL->execute(['ref' => $referencia]);
        $linData = $stmtL->fetch(PDO::FETCH_ASSOC);
        $lineaItem = $linData ? $linData['Linea'] : 0;
    } catch(Exception $e) { $lineaItem = 0; }
    
    $descuento = obtenerDescuentoSimulado($usuarioSimulado, $lineaItem, $con);

    // 2. Consulta simple a pedidos SIN JOINS complejos
    try {
        $sql = "SELECT * FROM pedidos WHERE referencia = :ref ORDER BY FechaPedido ASC";
        $stmt = $con->prepare($sql);
        $stmt->execute(['ref' => $referencia]);
    } catch(Exception $e) { return ""; }
    
    $html = "";
    if ($stmt->rowCount() > 0) {
        $filas = "";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $precioPedido = 0;
            if (function_exists('PreciosPedidos')) {
                // Asumimos que la tabla pedidos tiene Proveedor, Moneda, Costo. Si no, ajustar aqui.
                $precioPedido = PreciosPedidos($row['Proveedor'], $row['Moneda'], $row['Costo'], $lineaItem, $descuento);
            }
            
            if ($precioActualItem > 0 && $precioPedido < $precioActualItem) {
                $precioPedido = $precioActualItem;
            }

            $fecha = date("d-M-Y", strtotime($row['FechaPedido']));

            $filas .= "<tr>
                        <td>{$row['IdPedido']}</td>
                        <td>{$row['referencia']}</td>
                        <td>{$row['Cantidad']}</td>
                        <td><b>{$fecha}</b></td>
                        <td>" . ($row['Marca'] ?? '') . "</td>
                        <td>$ " . number_format($precioPedido, 0, '.', ',') . "</td>
                       </tr>";
        }

        $html .= '<div class="mt-3 mb-3">
                  <table class="table modern-table table-hover" style="border: 1px solid #ffc107;">
                    <caption style="caption-side: top; text-align:center; font-weight:bold; color:#856404; background-color:#fff3cd;">⚠️ Próximos en llegar (Pedidos)</caption>
                    <thead><tr><th>Pedido</th><th>Referencia</th><th>Cantidad</th><th>Llegada Aprox.</th><th>Marca</th><th>Precio (Simulado)</th></tr></thead>
                    <tbody>' . $filas . '</tbody>
                  </table>
                  </div>';
    }
    return $html;
}

function ExistenciasAdmin($usuarioSimulado, $resultado_alterno, $resultuse, $resultcostex, $con, $referencia) {
    $entra = 0;
    $consulta = '';
    
    $consulta .= '<h5 class="text-primary mt-4">Resultados para: <b>'. $referencia .'</b> <small style="color:#666">(Simulando a: '.$usuarioSimulado.')</small></h5>';
    
    // BLOQUE 1: AGROCOSTA
    if ($resultado_alterno && !empty($resultado_alterno)) {
        $entra = 1;
        $registros = isset($resultado_alterno['Referencia']) ? [$resultado_alterno] : $resultado_alterno;

        foreach ($registros as $fila) {
            $existencias = 0; $existencias_bog = 0;
            if (($fila['Existencias'] + $fila['Existencias_bog']) > 0 && $fila['Tipo'] == 'agro') {
                $existencias = $fila['Existencias'];
                $existencias_bog = $fila['Existencias_bog'];
            } elseif (($fila['Existencias'] + $fila['Existencias_bog']) < 1 && $fila['Tipo'] == 'agro') {
                $existencias = 'IMPORTACION (60 DIAS)';
            }

            $linea = $fila['Linea'];
            $descuento = obtenerDescuentoSimulado($usuarioSimulado, $linea, $con);
            $precio = $fila['Precio'];
            $precio_con_descuento = round($precio - ($precio * $descuento / 100));

            $consulta .= '<table class="table modern-table table-hover">
                            <thead><tr><th>Referencia</th><th>Descripción</th><th>BAQ</th><th>BOG</th><th>Marca</th><th>Precio (Antes IVA)</th></tr></thead>
                            <tbody><tr>
                                <td>'.$fila['Referencia'].'</td>
                                <td>'.$fila['descripcion'].'</td>
                                <td>'.$existencias.'</td>
                                <td>'.$existencias_bog.'</td>
                                <td>'.($fila['Marca'] ?? '').'</td>
                                <td>$'.number_format($precio_con_descuento, 0, '.', ',').'</td>
                            </tr></tbody>
                          </table>';
            
            $consulta .= MostrarAlternosSimulados($fila['Alternos'], $usuarioSimulado, $con);
            $consulta .= MostrarPedidosSimulados($fila['Referencia'], $usuarioSimulado, $precio_con_descuento, $con);
        }
    }

    // BLOQUE 2: ALIAS
    if ($resultuse && !empty($resultuse) && $entra != 1) {
        $entra = 2;
        $fila = isset($resultuse[0]) ? $resultuse[0] : $resultuse;
        $referenciaP = trim(substr($fila['descripcion'], 4));
        
        $stmtP = $con->prepare("SELECT * FROM inventario WHERE Referencia = :refP AND Tipo='agro'");
        $stmtP->execute(['refP' => $referenciaP]);
        $padres = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        if ($padres) {
            $consulta .= '<div class="alert alert-info p-2 mt-2">La referencia buscada es un ALIAS de: <b>'.$referenciaP.'</b></div>';
            foreach ($padres as $p) {
                $descP = obtenerDescuentoSimulado($usuarioSimulado, $p['Linea'], $con);
                $precP = round($p['Precio'] - ($p['Precio'] * $descP / 100));
                
                $consulta .= '<table class="table modern-table table-hover">
                            <thead><tr><th>Ref Principal</th><th>Descripción</th><th>BAQ</th><th>Precio</th></tr></thead>
                            <tbody><tr>
                                <td>'.$p['Referencia'].'</td>
                                <td>'.$p['descripcion'].'</td>
                                <td>'.$p['Existencias'].'</td>
                                <td>$'.number_format($precP, 0, '.', ',').'</td>
                            </tr></tbody>
                          </table>';
                
                $consulta .= MostrarAlternosSimulados($p['Alternos'], $usuarioSimulado, $con);
                $consulta .= MostrarPedidosSimulados($p['Referencia'], $usuarioSimulado, $precP, $con);
            }
        }
    }
    
    // BLOQUE 3: COSTEX
    if ($resultcostex && !empty($resultcostex) && $entra != 1 && $entra != 2) {
        $registros = isset($resultcostex['Referencia']) ? [$resultcostex] : $resultcostex;
        foreach ($registros as $row) {
             $desc = obtenerDescuentoSimulado($usuarioSimulado, $row['Linea'], $con);
             $prec = round($row['Precio'] - ($row['Precio'] * $desc / 100));
             
             $consulta .= '<table class="table modern-table table-hover" style="border-color:#e96363;">
                            <caption style="background-color:#e96363; color:white; font-weight:bold; text-align:center;">Opción Importación (5-7 días)</caption>
                            <thead><tr><th>Referencia</th><th>Descripción</th><th>Disponible</th><th>Marca</th><th>Precio</th></tr></thead>
                            <tbody><tr>
                                <td>'.$row['Referencia'].'</td>
                                <td>'.$row['descripcion'].'</td>
                                <td>'.$row['Existencias'].' (Importación)</td>
                                <td>CTP</td>
                                <td>$'.number_format($prec, 0, '.', ',').'</td>
                            </tr></tbody>
                          </table>';
        }
    }

    return $consulta;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Consulta Inventario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body { background-color: #ededed; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .top-nav-bar { background-color: #f8f9fa; width: 100%; padding: 15px 0; text-align: center; border-bottom: 1px solid #ddd; margin-bottom: 25px; }
        .top-nav-bar ul { list-style-type: none; padding: 0; margin: 0; }
        .top-nav-bar li { display: inline; margin: 0 15px; color: #555; font-size: 15px; font-weight: 500; }
        .top-nav-bar li a { color: #333; text-decoration: none; }
        .top-nav-bar li a:hover { color: #007bff; border-bottom: 2px solid #007bff; }
        .separator { color: #ccc; margin: 0 5px; }
        .modern-table { border-collapse: separate; border-spacing: 0; width: 100%; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; margin-top: 15px; }
        .modern-table th { background-color: #e9f2ff; color: #0056b3; font-weight: 600; text-align: center; padding: 12px; }
        .modern-table td { text-align: center; vertical-align: middle; padding: 10px; border-bottom: 1px solid #f0f0f0; }
        .result-row { transition: background 0.2s; cursor: pointer; }
        .result-row:hover { background-color: #f0f8ff; transform: translateX(5px); }
    </style>
</head>
<body>

    <div class="top-nav-bar">
        <ul>
            <li><span style="color:#007bff">Bienvenido <?php echo $_SESSION['idusuario']; ?></span></li>
            <span class="separator">•</span>
            <li><a href="logout.php?cerrar=1">Cerrar Sesión</a></li>
            <?php if ($_SESSION['idusuario'] == "ADMIN") { ?>
                <span class="separator">•</span>
                <li><a href='usuarios.php'>Lista de Usuarios</a></li>
                <span class="separator">•</span>
                <li><a href='consulta_inventario.php'>Modo Normal</a></li>
            <?php } ?>
        </ul>
    </div>

    <div class="container">
        <h2 class="text-center mb-4" style="color:#444">Consulta de Precios (Simulador Admin)</h2>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="mb-4">
            <div class="form-group">
                <label style="font-weight:bold; color:#0056b3;">1. Simular Usuario:</label>
                <select class="form-control" name="usuario" required style="border: 2px solid #b2dcff; font-weight:bold;">
                    <option value="">-- Seleccione Cliente --</option>
                    <?php
                    $selUser = $_POST['usuario'] ?? $_SESSION['selected_usuario'] ?? '';
                    try {
                        $stmt = $con->query("SELECT Idusuario FROM usuarios ORDER BY Idusuario ASC");
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $selected = ($row['Idusuario'] == $selUser) ? 'selected' : '';
                            echo "<option value='".$row['Idusuario']."' $selected>".$row['Idusuario']."</option>";
                        }
                    } catch (Exception $e) {}
                    ?>
                </select>
            </div>

            <div class="form-group mt-3">
                <label style="font-weight:bold; margin-right: 15px;">2. Buscar por:</label>
                <div class="custom-control custom-radio custom-control-inline">
                    <input type="radio" id="rdRef" name="tipo_busqueda" value="referencia" class="custom-control-input" 
                        <?php echo (!isset($_POST['tipo_busqueda']) || $_POST['tipo_busqueda'] == 'referencia') ? 'checked' : ''; ?> 
                        onclick="toggleSearch('ref')">
                    <label class="custom-control-label" for="rdRef">Referencia</label>
                </div>
                <div class="custom-control custom-radio custom-control-inline">
                    <input type="radio" id="rdDesc" name="tipo_busqueda" value="descripcion" class="custom-control-input"
                        <?php echo (isset($_POST['tipo_busqueda']) && $_POST['tipo_busqueda'] == 'descripcion') ? 'checked' : ''; ?> 
                        onclick="toggleSearch('desc')">
                    <label class="custom-control-label" for="rdDesc">Descripción</label>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-9">
                    <div id="box-ref" style="display:block;">
                        <input type="text" class="form-control" name="referencia" id="inputRef" 
                               value="<?php echo (isset($_POST['tipo_busqueda']) && $_POST['tipo_busqueda'] == 'referencia') ? $_POST['referencia'] : ''; ?>" 
                               placeholder="Escriba la referencia exacta">
                    </div>
                    <div id="box-desc" style="display:none;">
                        <input type="text" class="form-control" name="descripcion" id="inputDesc" 
                               value="<?php echo (isset($_POST['tipo_busqueda']) && $_POST['tipo_busqueda'] == 'descripcion') ? $_POST['descripcion'] : ''; ?>" 
                               placeholder="Escriba palabras clave">
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="buscar" class="btn btn-primary btn-block" style="font-weight:bold">CONSULTAR</button>
                </div>
            </div>
        </form>

        <hr>

        <?php
        $accion = '';
        if (isset($_POST['buscar'])) {
            $accion = $_POST['tipo_busqueda'];
            if(isset($_POST['usuario'])) $_SESSION['selected_usuario'] = $_POST['usuario'];
        } elseif (isset($_GET['referencia'])) {
            $accion = 'referencia';
        }

        $usuarioSimulado = $_SESSION['selected_usuario'] ?? '';

        if ($accion && $usuarioSimulado) {
            
            if ($accion == 'referencia') {
                $ref = trim(isset($_GET['referencia']) ? $_GET['referencia'] : $_POST['referencia']);
                
                try {
                    $sql = $con->prepare("SELECT * FROM inventario WHERE Referencia = :ref AND Tipo='agro'");
                    $sql->execute(['ref' => $ref]);
                    $resultado_alterno = $sql->fetchAll(PDO::FETCH_ASSOC);

                    $sql2 = $con->prepare("SELECT * FROM inventario WHERE Referencia = :ref AND Tipo='use'");
                    $sql2->execute(['ref' => $ref]);
                    $resultuse = $sql2->fetchAll(PDO::FETCH_ASSOC);

                    $sql3 = $con->prepare("SELECT * FROM inventario WHERE Referencia = :ref AND Tipo='costex'");
                    $sql3->execute(['ref' => $ref]);
                    $resultcostex = $sql3->fetchAll(PDO::FETCH_ASSOC);

                    if ($resultado_alterno || $resultuse || $resultcostex) {
                        echo ExistenciasAdmin($usuarioSimulado, $resultado_alterno, $resultuse, $resultcostex, $con, $ref);
                        try {
                            $fecha = date('Y-m-d H:i:s');
                            $logSql = "INSERT INTO mov (Idusuario, referencia, Precio, fechaconsulta) VALUES (:usr, :ref, 0, :fecha)";
                            $logStmt = $con->prepare($logSql);
                            $logUsuario = $_SESSION['idusuario'] . " (Sim: $usuarioSimulado)";
                            $logStmt->execute(['usr'=>$logUsuario, 'ref'=>$ref, 'fecha'=>$fecha]);
                        } catch(Exception $e){}
                    } else {
                        echo '<div class="alert alert-danger text-center">No se encontró la referencia: <strong>'.$ref.'</strong></div>';
                    }
                } catch (Exception $e) { echo "<div class='alert alert-warning'>Error BD: ".$e->getMessage()."</div>"; }
            
            } elseif ($accion == 'descripcion') {
                $desc = trim($_POST['descripcion']);
                $palabras = explode(" ", $desc);
                $condiciones = [];
                $params = [];
                foreach ($palabras as $index => $palabra) {
                    $key = ":p$index";
                    $condiciones[] = "descripcion LIKE $key";
                    $params[$key] = "%" . $palabra . "%";
                }
                $whereSQL = implode(" AND ", $condiciones);
                
                try {
                    $sqlDesc = $con->prepare("SELECT Referencia, descripcion, Existencias, Existencias_bog FROM inventario WHERE $whereSQL AND Tipo='agro' LIMIT 50");
                    $sqlDesc->execute($params);
                    $resultados = $sqlDesc->fetchAll(PDO::FETCH_ASSOC);

                    if ($resultados) {
                        echo '<h5 class="text-secondary">Resultados para: "<i>'.$desc.'</i>"</h5>';
                        echo '<div class="list-group mt-3">';
                        echo '<div class="list-group-item list-group-item-action active"><div class="row font-weight-bold"><div class="col-3">Referencia</div><div class="col-5">Descripción</div><div class="col-2">BAQ</div><div class="col-2">BOG</div></div></div>';
                        foreach($resultados as $row) {
                            echo '<a href="consulta_inventario_admin.php?referencia='.$row['Referencia'].'" class="list-group-item list-group-item-action result-row">';
                            echo '<div class="row"><div class="col-3 text-primary font-weight-bold">'.$row['Referencia'].'</div><div class="col-5">'.$row['descripcion'].'</div><div class="col-2">'.$row['Existencias'].'</div><div class="col-2">'.$row['Existencias_bog'].'</div></div></a>';
                        }
                        echo '</div>';
                    } else {
                         echo '<div class="alert alert-info text-center">No se encontraron coincidencias.</div>';
                    }
                } catch (Exception $e) { echo "<div class='alert alert-warning'>Error BD: ".$e->getMessage()."</div>"; }
            }

        } elseif (isset($_POST['buscar']) && !$usuarioSimulado) {
            echo '<div class="alert alert-danger">⚠️ Por favor seleccione un usuario para simular.</div>';
        }
        ?>
    </div>

    <script>
        function toggleSearch(type) {
            var boxRef = document.getElementById('box-ref');
            var boxDesc = document.getElementById('box-desc');
            var inRef = document.getElementById('inputRef');
            var inDesc = document.getElementById('inputDesc');
            if (type === 'ref') {
                boxRef.style.display = 'block'; boxDesc.style.display = 'none';
                inRef.required = true; inDesc.required = false; inDesc.value = ''; 
            } else {
                boxRef.style.display = 'none'; boxDesc.style.display = 'block';
                inRef.required = false; inDesc.required = true; inRef.value = ''; 
            }
        }
        document.addEventListener("DOMContentLoaded", function() {
            var isDesc = <?php echo (isset($_POST['tipo_busqueda']) && $_POST['tipo_busqueda'] == 'descripcion') ? 'true' : 'false'; ?>;
            if(isDesc) toggleSearch('desc'); else toggleSearch('ref');
        });
    </script>
</body>
</html>