<?php
include 'check_session.php';
include 'db_config.php';

// Redirigir si el usuario NO es administrador (Seguridad crítica)
if ($_SESSION['user_rol'] !== 'administrador') {
    header('Location: dashboard.php');
    exit();
}

$nombre_usuario = $_SESSION['nombre_usuario'];
$usuarios = [];

// Obtener la lista de todos los usuarios
try {
    $stmt = $pdo->query("SELECT id, usuario, rol, nombre_completo FROM usuarios ORDER BY nombre_completo");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_usuarios = "No se pudieron cargar los usuarios.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 50px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <a class="navbar-brand" href="dashboard.php">Registro de Oficios</a>
    
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
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
            Administrador, **<?php echo htmlspecialchars($nombre_usuario); ?>**
        </span>
        <a href="logout.php" class="btn btn-outline-light">Cerrar Sesión</a>
    </div>
</nav>

<div class="container">
    <h3 class="mb-4">Gestión de Usuarios del Sistema</h3>
    
    <?php if (isset($error_usuarios)): ?>
        <div class="alert alert-danger"><?php echo $error_usuarios; ?></div>
    <?php endif; ?>
    <a href="crear_usuario.php" class="btn btn-success mb-3">Crear Nuevo Usuario</a>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Nombre Completo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="5" class="text-center">No hay usuarios registrados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['usuario']); ?></td>
                            <td><?php echo htmlspecialchars($user['rol']); ?></td>
                            <td><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                            <td>
                                <a href="editar_usuario.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">Editar</a>
                                <a href="eliminar_usuario.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar este usuario?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>