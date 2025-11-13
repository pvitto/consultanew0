<?php
    function ConectarBaseDatos() {

        //conexion web
      /*$dsn = 'mysql:dbname=agrocosta_db;host=65.109.49.57';
        $usuario = 'agrocosta_pagina';
        $password = 'Agr0costa*-';
    
        try {
            $con = new PDO($dsn, $usuario, $password);
            $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $con;
        } catch (PDOException $e) {
            echo 'Connection Failed: ' . $e->getMessage();
            exit;
        }*/

        //conexion local
        $host = "127.0.0.1";   // o "localhost"
        $puerto = "3306";      // puerto de MySQL
        $bd = "agrocosta_db";
        $usuario = "root";
        $clave = "";

        try {
            $con = new PDO("mysql:host=$host;port=$puerto;dbname=$bd;charset=utf8", $usuario, $clave);
            $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $con;
            //echo "Conexión exitosa a la base de datos";
        } catch (PDOException $e) {
            echo "Error de conexión: " . $e->getMessage();
        }


    }

    function recogerUsuarios($con) {
        $resultado = $con->query("SELECT * FROM usuarios ORDER BY Idusuario ASC");
        return $resultado->fetchAll(PDO::FETCH_COLUMN);
    }

    function recogerAccesoUsuarios($con)
    {
        $resultado = $con->query("SELECT * FROM acceso_a_usuarios");
        return $resultado->fetchAll(PDO::FETCH_COLUMN);
    }
    
    function recogerLineas_crearTabla($con)
    {
        $query = $con->query("SELECT DISTINCT Linea FROM inventario ORDER BY Linea ASC");
        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    function recogerLineas($con) {
        $query = $con->prepare("DESCRIBE acceso_a_usuarios");
        $query->execute();
        $columnas = $query->fetchAll(PDO::FETCH_COLUMN);
    
        $lineas = [];
        foreach ($columnas as $columna) {
            if (
                $columna != "usuario" &&
                strpos($columna, "alterno") === false &&
                strpos($columna, "complementario") === false &&
                strpos($columna, "precio") === false &&
                strpos($columna, "descripcion") === false
                ) {

                
                $partes = explode("_", $columna);
                $lineas[] = $partes[1];
            }
        }
        return $lineas;
    }

    function obtenerNombresColumnas($con, $tabla) {
        $query = $con->prepare("DESCRIBE $tabla");
        $query->execute();
        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    function existeLinea($con, $numeroLinea) {
        $columnaLinea = "linea_" . $numeroLinea;
        $query = "SHOW COLUMNS FROM acceso_a_usuarios LIKE '$columnaLinea'";
        $result = $con->query($query);
        return $result->fetch(PDO::FETCH_ASSOC) !== false;
    }
    
    function BuscarUsuario($con, $nombre) {
        $nombre = '%' . $nombre . '%';
        $query = $con->prepare("SELECT * FROM acceso_a_usuarios WHERE usuario LIKE :termino");
        $query->execute(['termino' => $nombre]);
        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    function recogerPermisosUsuario($con, $usuario) {
        $stmt = $con->prepare("SELECT * FROM acceso_a_usuarios WHERE usuario = :usuario");
        $stmt->bindParam(':usuario', $usuario);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

function BloquearLineas($linea, $tipo = '') {
     //Ejemplo: 'precio', 'alterno', 'complementario', 'descripcion' o '' (vacío para la línea principal)
    // 0 = permiso, 1 = sin permiso
    if (!isset($_SESSION['permisos_usuario']) || empty($_SESSION['permisos_usuario'])) {
        return false; // No hay permisos cargados en sesión
    }

    // Construir el nombre de la columna
    $columna = "linea_" . trim($linea);
    if (!empty($tipo)) {
        $columna .= "_" . strtolower(trim($tipo));
    }

    // Revisar si existe esa columna en el array de permisos
    if (!array_key_exists($columna, $_SESSION['permisos_usuario'])) {
        return false; // No existe esa columna (permiso no definido)
    }
    // Retornar true si el permiso es 1, false si es 0
    return $_SESSION['permisos_usuario'][$columna] ? true : false;
}





?>