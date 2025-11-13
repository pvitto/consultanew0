<?php
session_start();
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] >1800 )) {
    // Si el usuario ha estado inactivo durante más de 30 minutos, se cierra la sesión
    session_unset();     
    session_destroy();   
    header("Location: login.php?msg=session_expired");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time(); // Actualiza la marca de tiempo de la última actividad


if(isset($_GET['cerrar'])) {
    session_unset();     
    session_destroy();   
    header("Location: login.php");
    exit();
}
?>