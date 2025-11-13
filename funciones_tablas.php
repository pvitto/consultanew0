<?php 
    function CrearTabla($con) {
        $lineas = recogerLineas_crearTabla($con);
        $usuarios = recogerUsuarios($con);
    
        // Creando las columnas en pares, una columna para el acceso a la línea en sí y la otra para el acceso a alternos
        $columns = [];
        foreach ($lineas as $linea) {
            $columns[] = "linea_" . $linea . " BOOLEAN";
            $columns[] = "linea_" . $linea . "_alterno BOOLEAN";
            $columns[] = "linea_" . $linea . "_complementario BOOLEAN";
            $columns[] = "linea_" . $linea . "_precio BOOLEAN";
            $columns[] = "linea_" . $linea . "_descripcion BOOLEAN";

        }
        $createTableQuery = "CREATE TABLE IF NOT EXISTS acceso_a_usuarios (usuario VARCHAR(255) PRIMARY KEY, " . implode(", ", $columns) . ")";
        $con->exec($createTableQuery);
    
        // Agregando usuarios con sus permisos a la tabla
        foreach ($usuarios as $usuario) {
            $accesoDatos = ['usuario' => $usuario];
            foreach ($lineas as $linea) {
                $accesoDatos["linea_" . $linea] = BloquearLineas($usuario, $linea, $con) ? 1 : 0;
                // Si la línea está bloqueada, entonces los alternos también
                $accesoDatos["linea_" . $linea . "_alterno"] = $accesoDatos["linea_" . $linea];
                $accesoDatos["linea_" . $linea . "_complementario"] = $accesoDatos["linea_" . $linea];
                $accesoDatos["linea_" . $linea . "_precio"] = $accesoDatos["linea_" . $linea];
            }
    
            // Queries para agregar columnas y datos dinámicamente
            $colLista = implode(", ", array_keys($accesoDatos));
            $molde = implode(", ", array_map(function($col) { return ":$col"; }, array_keys($accesoDatos)));
            $updateList = implode(", ", array_map(function($col) { return "$col = VALUES($col)"; }, array_keys($accesoDatos)));
    
            $query = $con->prepare("INSERT INTO acceso_a_usuarios ($colLista) VALUES ($molde) ON DUPLICATE KEY UPDATE $updateList");
            $query->execute($accesoDatos);
        }
    }
    

    function ChequearExistenciaTabla($nombre, $con)
    {
        $query = $con->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :nombreBase AND table_name = :nombreTabla");
        $query->execute(['nombreBase' => 'agrocosta_db', 'nombreTabla' => $nombre]);
        $resultado = $query->fetchColumn();

        return $resultado ? true : false;
    }

    function renderizarTabla($con, $usuario) {
        $permisos = recogerPermisosUsuario($con, $usuario);
        $lineas = recogerLineas($con);
    
        if ($permisos) {
            echo "<h2>$usuario</h2>";
            echo '<form method="POST">
            <input type="hidden" name="usuario" value="' . htmlspecialchars($usuario) . '">
            <label><input type="checkbox" name="marcardesmarcar_alterno" onchange="marcarDesmarcarAlternos(this.checked)" checked> Marcar / Desmarcar todos los alternos</label><br>
            <label><input type="checkbox" name="marcardesmarcar_complementario" onchange="marcarDesmarcarComplementarios(this.checked)" checked> Marcar / Desmarcar todos los complementarios</label><br>
            <label><input type="checkbox" name="marcardesmarcar_precio" onchange="marcarDesmarcarPrecios(this.checked)" checked> Marcar / Desmarcar todos los precios</label><br>
            <label> <input type="checkbox" name="marcardesmarcar_descripcion" onchange="marcarDesmarcarDescripciones(this.checked)" checked> Marcar / Desmarcar todas las descripciones</label><br>
            </form>';
            echo "<form method='POST'>";
            echo "<input type='hidden' name='usuario' value='$usuario'>";
            echo "<a href='usuarios.php' class='btn btn-primary'>Lista de Usuarios</a>";
            echo "<table class='table table-bordered'>";
            echo "<thead><tr><th>Linea</th><th>Permiso</th><th>Alterno</th><th>Complementario</th><th>Precio</th><th>Descripción</th></tr></thead>";
            echo "<tbody>";
    
            foreach ($lineas as $linea) {
                // Revisa cuales de los checkbox de cada linea estan marcados y cuales no
                $permisoLinea = $permisos["linea_" . $linea] == 0 ? 'checked' : '';
                $permisoAlterno = $permisos["linea_" . $linea . "_alterno"] == 0 ? 'checked' : '';
                $permisoComplementario = $permisos["linea_" . $linea . "_complementario"] == 0 ? 'checked' : '';
                $permisoPrecio = $permisos["linea_" . $linea . "_precio"] == 0 ? 'checked' : '';
                $permisoDescripcion = $permisos["linea_" . $linea . "_descripcion"] == 0 ? 'checked' : '';

    
                echo "<tr>
                        <td>$linea</td>
                        <td>
                            <input type='hidden' name='permisos[linea_" . $linea . "]' value='1'>
                            <input type='checkbox' name='permisos[linea_" . $linea . "]' value='0' $permisoLinea>
                        </td>
                        <td>
                            <input type='hidden' name='permisos[linea_" . $linea . "_alterno]' value='1'>
                            <input type='checkbox' name='permisos[linea_" . $linea . "_alterno]' value='0' $permisoAlterno>
                        </td>
                        <td>
                            <input type='hidden' name='permisos[linea_" . $linea . "_complementario]' value='1'>
                            <input type='checkbox' name='permisos[linea_" . $linea . "_complementario]' value='0' $permisoComplementario>
                        </td>
                        <td>
                            <input type='hidden' name='permisos[linea_" . $linea . "_precio]' value='1'>
                            <input type='checkbox' name='permisos[linea_" . $linea . "_precio]' value='0' $permisoPrecio>
                        </td>
                         <td>
                            <input type='hidden' name='permisos[linea_" . $linea . "_descripcion]' value='1'>
                            <input type='checkbox' name='permisos[linea_" . $linea . "_descripcion]' value='0' $permisoDescripcion>
                        </td>
                      </tr>";
            }
    
            echo "</tbody></table>";
            echo "<button type='submit' name='actualizar' class='btn btn-primary'>Actualizar Permisos</button>";
            echo "</form>";
        } else {
            echo "<div class='alert alert-danger'>No se encontraron permisos para el usuario '$usuario'.</div>";
        }
    }
    
?>