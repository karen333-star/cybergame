<?php
require 'config.php';

echo "=== VERIFICACIÓN COMPLETA DEL SISTEMA ===\n\n";

// 1. Verificar BD
echo "1. Conexión a Base de Datos:\n";
if ($conn->ping()) {
    echo "   ✅ Conectado a CYBERGAME\n";
} else {
    echo "   ❌ ERROR: No conectado\n";
    exit;
}

// 2. Verificar tabla usuarios
echo "\n2. Tabla usuarios:\n";
$result = $conn->query("SELECT COUNT(*) as total FROM usuarios");
$row = $result->fetch_assoc();
echo "   Total de usuarios: " . $row['total'] . "\n";

// 3. Verificar tabla tokens
echo "\n3. Tabla tokens_recuperacion:\n";
$result = $conn->query("DESCRIBE tokens_recuperacion");
$campos = [];
while ($row = $result->fetch_assoc()) {
    $campos[] = $row['Field'];
}
echo "   Campos: " . implode(", ", $campos) . "\n";

// 4. Test de funciones
echo "\n4. Funciones principales:\n";
$codigo = generar_codigo_6_digitos();
echo "   ✅ Código generado: $codigo\n";
echo "   ✅ Validar contraseña: " . (validar_contraseña('Test123') ? 'OK' : 'FALLA') . "\n";

// 5. Test de envío de email
echo "\n5. Sistema de Email:\n";
echo "   Modo sandbox: " . (EMAIL_CONFIG['use_sandbox'] ? 'ACTIVADO' : 'DESACTIVADO') . "\n";
$resultado = enviar_email_recuperacion('test@example.com', '123456', 'Usuario Prueba');
echo "   Método: " . $resultado['method'] . "\n";
if (isset($resultado['file_path'])) {
    echo "   Archivo: " . basename($resultado['file_path']) . "\n";
}

// 6. Listar usuarios registrados
echo "\n6. Usuarios en la Base de Datos:\n";
$result = $conn->query("SELECT id_usuario, nombre_usuario, email FROM usuarios LIMIT 5");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   - " . $row['nombre_usuario'] . " (" . $row['email'] . ")\n";
    }
} else {
    echo "   ⚠️ No hay usuarios registrados\n";
}

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";
?>