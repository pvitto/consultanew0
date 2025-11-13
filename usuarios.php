<?php
require_once 'logout.php';
require_once 'funciones_recoger.php';

if (!isset($_SESSION['idusuario']) || $_SESSION['idusuario'] != "ADMIN") {
    header("Location: consulta_inventario.php");
    exit();
}

function actualizarUsuarios($con) {
    // Obtener nombres de columnas de la tabla acceso_a_usuarios
    $columnas = obtenerNombresColumnas($con, 'acceso_a_usuarios');
    
    // Excluir la primera columna que es 'usuario'
    $columnasSecundarias = array_slice($columnas, 1);
    
    // Construir la lista de columnas y los valores a insertar
    $columnasStr = implode(", ", $columnas);
    $valoresStr = "Idusuario, " . implode(", ", array_fill(0, count($columnasSecundarias), 0));
    
    $consulta = "
        INSERT INTO acceso_a_usuarios ($columnasStr)
        SELECT $valoresStr
        FROM usuarios
        WHERE NOT EXISTS (
            SELECT 1
            FROM acceso_a_usuarios
            WHERE acceso_a_usuarios.usuario = usuarios.Idusuario
        )
    ";
    $con->exec($consulta);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h1 class="mt-5 mb-4">Lista de Usuarios</h1>
        <form class="mb-4" method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Buscar usuario" name="buscar_usuario">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">Buscar</button>
                </div>
            </div>
        </form>

        <a href='consulta_inventario.php' class="btn btn-primary">Volver</a>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalManejarLineas">
            Administrar Lineas
        </button>

        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalAgregarUsuario">
            Agregar Usuario
        </button>

        <div class="row">
            <?php
            $con = ConectarBaseDatos();
            actualizarUsuarios($con);

            if (isset($_GET['buscar_usuario']) && !empty($_GET['buscar_usuario'])) {
                $buscarUsuario = $_GET['buscar_usuario'];
                $usuarios = BuscarUsuario($con, $buscarUsuario);
            } else {
                $usuarios = recogerAccesoUsuarios($con);
            }

            foreach ($usuarios as $usuario) {
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $usuario; ?></h5>
                            <a href='manejar_permisos.php?usuario=<?php echo $usuario; ?>' class="btn btn-primary">Administrar Permisos</a>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <!-- Modal para agregar usuario -->
    <div class="modal fade" id="modalAgregarUsuario" tabindex="-1" role="dialog" aria-labelledby="modalAgregarUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAgregarUsuarioLabel">Agregar Nuevo Usuario</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Aquí va el formulario para agregar un usuario -->
                    <form method="POST" action="agregar_usuarios.php">
                        <div class="form-group">
                            <label for="nombreUsuario">Nombre de Usuario:</label>
                            <input type="text" class="form-control" id="nombreUsuario" name="nombreUsuario" required>
                        </div>
                        <button type="submit" class="btn btn-success">Agregar Usuario</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalManejarLineas" tabindex="-1" role="dialog" aria-labelledby="modalManejarLineasLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalManejarLineasLabel">Manejar Linea</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Aquí va el formulario para agregar un usuario -->
                    <form method="POST" action="manejar_lineas.php">
                        <div class="form-group">
                            <label for="nombre_linea">Número de Línea:</label>
                            <input type="text" class="form-control" id="nombre_linea" name="nombre_linea" required>
                        </div>
                        <button type="submit" class="btn btn-success" name="agregar_linea">Agregar Línea</button>
                        <button type="submit" class="btn btn-danger" name="eliminar_linea">Eliminar Línea</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>
