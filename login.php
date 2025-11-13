<?php
session_start(); // Inicia la sesión

require_once 'funciones_recoger.php';

// Verifica si el usuario ha enviado el formulario de inicio de sesión
if (isset($_POST['login'])) {
    $usuario = $_POST['usuario'];
    $contraseña = $_POST['contraseña'];

    // Conecta a la base de datos
    $con = ConectarBaseDatos();

    // Prepara la consulta SQL para buscar al usuario en la tabla Usuarios
    $sql = "SELECT * FROM usuarios WHERE Idusuario = :usuario AND Pass = :contrasena";

    $query = $con->prepare($sql);
    $query->execute(['usuario' => $usuario, 'contrasena' => $contraseña]);
    $resultado = $query->fetch(PDO::FETCH_ASSOC);

    // Verifica si se encontró al usuario
    if (!empty($resultado)) {

        // Guarda los datos del usuario en la sesión
        $_SESSION['idusuario'] = $resultado['Idusuario'];

        // Asigna dinámicamente los valores de descuento con el formato requerido
        foreach ($resultado as $key => $value) {
            // Utiliza una expresión regular para filtrar las columnas que empiezan con 'd'
            if (preg_match('/^d(\d+)$/', $key, $matches)) {
                // Formatea la clave con 'D' mayúscula y sin ceros al frente
                $formattedKey = 'D' . $matches[1];
                $_SESSION[$formattedKey] = $value;
            }
        }

        // --- Cargar todos los permisos del usuario en sesion ---
        $sql = "SELECT * FROM acceso_a_usuarios WHERE usuario = :usuario";
        $stmt = $con->prepare($sql);
        $stmt->execute([':usuario' => $usuario]);
        $permisos = $stmt->fetch(PDO::FETCH_ASSOC);

        // --- Guardarlos en la sesión ---
        if ($permisos) {
            $_SESSION['permisos_usuario'] = $permisos;
        } else {
            $_SESSION['permisos_usuario'] = [];
        }



        // Redirecciona al usuario a la página de consulta de inventario
        header("Location: consulta_inventario.php");
        exit();
    } else {
        $mensaje = "Usuario o contraseña incorrectos";
    }
}
?>



<!DOCTYPE html>
<html>
<head>
    <title>Iniciar sesión</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<style>

</style>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="logo text-center">
            <img src="logonew1.png" class="img-responsive" style="margin: 0 auto; width:40%;">
            <!-- <a href="javascript:void(0);">Agro<b>Costa</b></a>
            <small>Aquí va el slogan</small> -->
            <br/><br/>
        </div>
                <div class="card">
                    <div class="card-header">
                        Iniciar sesión
                    </div>
                    <div class="card-body">
                        <?php if (isset($mensaje)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $mensaje ?>
                            </div>
                        <?php endif?>
                        <form id="formulario" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-group">

                            <?php
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'session_expired') {
        echo '<div class="alert alert-warning">La sesión ha caducado debido a mas de 30 minutos inactivo. Por favor, inicie sesión de nuevo.</div>';
    }
}
?>


                                <label for="usuario">Usuario:</label>
                                <input type="text" name="usuario" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="contraseña">Contraseña:</label>
                                <input type="password" name="contraseña" class="form-control" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary btn-block">Iniciar sesión</button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                                                
                            <a href="https://agro-costa.com/consulta/consulta_disponibilidad.php" name="login" class="btn btn-default btn-block" style="background:#000;color: #fff;font-size:18px">Consultar Disponibilidad de Inventario AQUI</a>

                    </div>
            </div>
        </div>
    </div>

    <script>
        // Valida el formulario con jQuery Validation
        $(document).ready(function() {
            $("#formulario").validate({
                rules: {
                    usuario: "required",
                    contraseña: "required"
                },
                messages: {
                    usuario: "Por favor ingrese su usuario",
                    contraseña: "Por favor ingrese su contraseña"
                }
            });
        });
    </script>
</body>
</html>