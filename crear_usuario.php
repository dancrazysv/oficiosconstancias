<?php
include 'check_session.php';
include 'db_config.php';

// Redirigir si no es administrador
if ($_SESSION['user_rol'] !== 'administrador') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $contrasena_plana = $_POST['contrasena'] ?? '';
    $rol = $_POST['rol'] ?? 'normal';
    $nombre_completo = $_POST['nombre_completo'] ?? '';

    // Utiliza password_hash() para seguridad
    $contrasena_hash = password_hash($contrasena_plana, PASSWORD_DEFAULT); 

    if ($contrasena_hash === false) {
        $error = "Error: Falló la generación del hash de la contraseña. Revise la configuración de PHP.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, contrasena, rol, nombre_completo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$usuario, $contrasena_hash, $rol, $nombre_completo]);
            $success = "Usuario creado exitosamente.";
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Error: El nombre de usuario ya existe. Por favor, elija otro.";
            } else {
                $error = "Error al crear el usuario. Por favor, intente de nuevo. (Detalle: " . $e->getMessage() . ")";
            }
        }
    }
}
$nombre_usuario_sesion = $_SESSION['nombre_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Usuario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <a class="navbar-brand" href="dashboard.php">Registro de Oficios</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="crear_oficio.php">Crear Oficio</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
            </li>
            <li class="nav-item active">
                <a class="nav-link" href="usuarios_admin.php">Gestión de Usuarios</a>
            </li>
        </ul>
        <span class="navbar-text mr-3">
            Bienvenido, **<?php echo htmlspecialchars($nombre_usuario_sesion); ?>**
        </span>
        <a href="logout.php" class="btn btn-outline-light">Cerrar Sesión</a>
    </div>
</nav>
<div class="container mt-5">
    <a href="usuarios_admin.php" class="btn btn-secondary mb-3">Volver al Dashboard</a>
    <h2>Crear Nuevo Usuario</h2>
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Nombre Completo:</label>
            <input type="text" name="nombre_completo" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Usuario:</label>
            <input type="text" name="usuario" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Contraseña:</label>
            <input type="password" name="contrasena" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Rol:</label>
            <select name="rol" class="form-control" required>
                <option value="normal">Normal</option>
                <option value="supervisor">Supervisor</option>
                <option value="administrador">Administrador</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Crear Usuario</button>
    </form>
</div>
</body>
</html>