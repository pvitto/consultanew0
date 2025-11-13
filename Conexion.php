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
