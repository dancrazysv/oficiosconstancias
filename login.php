<?php
// ¡DEBE SER LA PRIMERA LÍNEA!
ob_start();
session_start();

include 'db_config.php';

$mensaje_error = '';

// Redirige si ya está logueado
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';

    if (!empty($usuario) && !empty($contrasena)) {
        try {
            // Consulta para obtener el usuario, incluyendo nombre_completo
            $stmt = $pdo->prepare("SELECT id, contrasena, rol, nombre_completo FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // === LÓGICA DE VERIFICACIÓN CON password_verify() (ESTÁNDAR SEGURO) ===
                if (password_verify($contrasena, $user['contrasena'])) { 
                
                    // === INICIO DE SESIÓN EXITOSO ===
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_rol'] = $user['rol'];
                    $_SESSION['nombre_usuario'] = $user['nombre_completo']; 
                    
                    ob_end_clean(); // Limpia el búfer antes de la redirección
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $mensaje_error = "Usuario o contraseña incorrectos.";
                }
            } else {
                $mensaje_error = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error de conexión a la base de datos: " . $e->getMessage();
        }
    } else {
        $mensaje_error = "Por favor, complete todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .logo-img {
            display: block;
            margin: 0 auto 20px;
            max-width: 100px; /* Tamaño del escudo */
            height: auto;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="img/escudo.png" alt="Escudo Institucional" class="logo-img">
        <h2 class="text-center mb-4">Iniciar Sesión</h2>
        
        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $mensaje_error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" required>
            </div>
            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <input type="password" class="form-control" id="contrasena" name="contrasena" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
        </form>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>