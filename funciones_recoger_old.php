<?php
    function ConectarBaseDatos() {
        $dsn = 'mysql:dbname=agrocosta_db;host=127.0.0.1';
        $usuario = 'agrocosta_pagina';
        $password = 'Agr0costa*-';
    
        try {
            $con = new PDO($dsn, $usuario, $password);
            $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $con;
        } catch (PDOException $e) {
            echo 'Connection Failed: ' . $e->getMessage();
            exit;
        }
    }

    function recogerUsuarios($con) {
        $resultado = $con->query("SELECT * FROM usuarios");
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
            if ((strpos($columna, "alterno") === false && strpos($columna, "complementario") === false && strpos($columna, "precio") === false) && $columna != "usuario") {
                
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
?>