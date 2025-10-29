<?php
// Inicia la sesión para este script de prueba
session_start();

// Muestra el contenido de la variable superglobal de sesión
echo "<h2>Contenido de la variable de sesión:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Muestra si hay un usuario logueado
if (isset($_SESSION['user_id'])) {
    echo "<h3>✅ Sesión activa. Usuario ID: " . $_SESSION['user_id'] . "</h3>";
} else {
    echo "<h3>❌ Sesión inactiva.</h3>";
}

echo "<h3>Pasos para probar:</h3>";
echo "<ol>";
echo "<li>Entra a `login.php` y loguéate con un usuario (por ejemplo, 'admin').</li>";
echo "<li>Después de iniciar sesión, ve a esta URL: `http://localhost/registro-oficios/test_session.php`</li>";
echo "<li>Deberías ver 'Sesión activa' y los datos del usuario. Si ves 'Sesión inactiva', hay un problema con la configuración de PHP o las cookies.</li>";
echo "</ol>";
?>