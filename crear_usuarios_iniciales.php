<?php
include 'db_config.php';

// Contraseñas de ejemplo
$contrasena_admin = 'admin123';
$contrasena_normal = 'user123';

// Encriptar las contraseñas
$hash_admin = password_hash($contrasena_admin, PASSWORD_DEFAULT);
$hash_normal = password_hash($contrasena_normal, PASSWORD_DEFAULT);

try {
    // Verificar si ya existen usuarios para evitar duplicados
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    if ($stmt->fetchColumn() > 0) {
        die("La tabla de usuarios ya tiene datos. No se crearán usuarios iniciales.");
    }

    // Insertar el usuario administrador
    $sql_admin = "INSERT INTO usuarios (usuario, contrasena, rol) VALUES (?, ?, ?)";
    $stmt_admin = $pdo->prepare($sql_admin);
    $stmt_admin->execute(['admin', $hash_admin, 'administrador']);

    // Insertar el usuario normal
    $sql_normal = "INSERT INTO usuarios (usuario, contrasena, rol) VALUES (?, ?, ?)";
    $stmt_normal = $pdo->prepare($sql_normal);
    $stmt_normal->execute(['normal', $hash_normal, 'normal']);

    echo "Usuarios iniciales creados exitosamente:<br>";
    echo "Usuario Administrador: <strong>admin</strong> (contraseña: admin123)<br>";
    echo "Usuario Normal: <strong>normal</strong> (contraseña: user123)<br>";

} catch (PDOException $e) {
    die("Error al crear usuarios: " . $e->getMessage());
}
?>