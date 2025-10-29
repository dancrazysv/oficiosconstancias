<?php
include 'check_session.php';
include 'db_config.php';

// 1. Restricción de acceso: Solo administradores
if ($_SESSION['user_rol'] !== 'administrador') {
    header('Location: dashboard.php');
    exit();
}

$user_id_to_edit = $_GET['id'] ?? null; // ID del usuario a editar
$usuario_data = null;
$error = '';
$success = '';

if (!$user_id_to_edit) {
    die("ID de usuario no proporcionado.");
}

// === 2. Lógica de Actualización (POST) ===
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_completo = $_POST['nombre_completo'] ?? '';
    $usuario = $_POST['usuario'] ?? '';
    $rol = $_POST['rol'] ?? '';
    $contrasena_plana = $_POST['contrasena'] ?? '';
    
    $sql_password_update = '';
    $final_params = [];

    // Lógica para actualizar la contraseña si fue proporcionada
    if (!empty($contrasena_plana)) {
        // Utilizamos password_hash() para seguridad
        $contrasena_hash = password_hash($contrasena_plana, PASSWORD_DEFAULT); 
        $sql_password_update = ", contrasena = ?";
        $final_params[] = $contrasena_hash;
    }
    
    // Parámetros de actualización de campos básicos
    $final_params[] = $usuario;
    $final_params[] = $rol;
    $final_params[] = $nombre_completo;
    $final_params[] = $user_id_to_edit; // El ID del usuario va al final

    try {
        // Construir la sentencia SQL con la cláusula condicional de contraseña
        $sql = "UPDATE usuarios SET usuario = ?, rol = ?, nombre_completo = ? {$sql_password_update} WHERE id = ?";
        
        $stmt_update = $pdo->prepare($sql);
        
        // La ejecución requiere reordenar los parámetros para que el ID vaya al final
        $params_to_execute = [];
        $params_to_execute[] = $usuario;
        $params_to_execute[] = $rol;
        $params_to_execute[] = $nombre_completo;
        if (!empty($contrasena_plana)) {
            $params_to_execute[] = $contrasena_hash;
        }
        $params_to_execute[] = $user_id_to_edit;

        // Ejecutar la consulta
        $stmt_update->execute($params_to_execute);
        
        $success = "Usuario actualizado exitosamente.";
        
        // Redirigir después de la actualización
        header("Location: usuarios_admin.php");
        exit();

    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Error: El nombre de usuario ya existe.";
        } else {
            $error = "Error al actualizar el usuario: " . $e->getMessage();
        }
    }
}

// === 3. Carga Inicial de Datos del Usuario (GET) ===
try {
    $stmt = $pdo->prepare("SELECT id, usuario, rol, nombre_completo FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id_to_edit]);
    $usuario_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario_data) {
        die("Usuario no encontrado.");
    }
} catch (PDOException $e) {
    die("Error al cargar datos del usuario.");
}

$nombre_usuario_sesion = $_SESSION['nombre_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <a class="navbar-brand" href="dashboard.php">Registro de Oficios</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
            </li>
            <li class="nav-item active">
                <a class="nav-link" href="usuarios_admin.php">Gestión de Usuarios</a>
            </li>
        </ul>
        <span class="navbar-text mr-3">
            Administrador, **<?php echo htmlspecialchars($nombre_usuario_sesion); ?>**
        </span>
        <a href="logout.php" class="btn btn-outline-light">Cerrar Sesión</a>
    </div>
</nav>
<div class="container mt-5">
    <a href="usuarios_admin.php" class="btn btn-secondary mb-3">Volver a Gestión</a>
    <h2>Editar Usuario: <?php echo htmlspecialchars($usuario_data['usuario']); ?></h2>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Nombre Completo:</label>
            <input type="text" name="nombre_completo" class="form-control" 
                   value="<?php echo htmlspecialchars($usuario_data['nombre_completo']); ?>" required>
        </div>
        <div class="form-group">
            <label>Usuario:</label>
            <input type="text" name="usuario" class="form-control" 
                   value="<?php echo htmlspecialchars($usuario_data['usuario']); ?>" required>
        </div>
        <div class="form-group">
            <label>Rol:</label>
            <select name="rol" class="form-control" required>
                <option value="normal" <?php echo ($usuario_data['rol'] == 'normal') ? 'selected' : ''; ?>>Normal</option>
                <option value="supervisor" <?php echo ($usuario_data['rol'] == 'supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                <option value="administrador" <?php echo ($usuario_data['rol'] == 'administrador') ? 'selected' : ''; ?>>Administrador</option>
            </select>
        </div>
        <div class="form-group">
            <label>Nueva Contraseña (Dejar vacío para no cambiar):</label>
            <input type="password" name="contrasena" class="form-control" 
                   placeholder="Ingrese solo si desea cambiar la contraseña">
            <small class="form-text text-muted">La contraseña se guardará usando el método seguro (password_hash).</small>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>
</body>
</html>