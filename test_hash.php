<?php
// Script para diagnosticar si password_hash() y password_verify() funcionan.
echo "<h1>Diagnóstico de Seguridad y Hash (password_hash)</h1>";

$password_a_probar = '12345';

// 1. Generar un hash SEGURO (como lo haría crear_usuario.php)
$hash_generado = password_hash($password_a_probar, PASSWORD_DEFAULT);

echo "<h2>Paso 1: Generación del Hash</h2>";
echo "<p>Contraseña a hashear: <code>$password_a_probar</code></p>";
echo "<p>Hash generado (password_hash()): <code>$hash_generado</code></p>";
echo "<hr>";

// 2. Simular la verificación (como lo haría login.php)
echo "<h2>Paso 2: Simulación de Verificación</h2>";

if (password_verify($password_a_probar, $hash_generado)) {
    echo "<h1>✅ ÉXITO: La función password_verify() está funcionando.</h1>";
    echo "<p>Tu entorno **SÍ puede** validar la contraseña contra el hash.</p>";
} else {
    echo "<h1>❌ FALLA CRÍTICA: La función password_verify() está rota.</h1>";
    echo "<p><b>Diagnóstico:</b> Tu entorno PHP no está validando correctamente el hash que él mismo generó. Esto es un error de configuración o un bug en XAMPP.</p>";
}
?>