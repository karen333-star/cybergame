# 📧 Configuración de Email - CyberGame

## 🔍 Resumen de Implementación

El sistema de recuperación de contraseña envía códigos de verificación por email.

**Archivos clave:**
- `config.php` - Función `enviar_email_recuperacion()` con configuración
- `recuperar.php` - Endpoints de recuperación que usan la función
- `forgot.html` - Interfaz frontend

---

## 🚀 Flujo de Envío

### Paso 1: Solicitar Código
1. Usuario ingresa email en `forgot.html`
2. Frontend llama `recuperar.php?accion=solicitar_codigo`
3. Backend genera token aleatorio de **64 caracteres**
4. Token se hashea y guarda en BD (tabla `tokens_recuperacion`)
5. **Token SIN hashear se envía por email**
6. Usuario recibe primeros 8 caracteres del token

### Paso 2: Verificar Código
1. Usuario copia código de 8+ caracteres
2. Frontend valida contra token en BD
3. Si es válido: crea token temporal de sesión
4. Usuario pasa al Paso 3

### Paso 3: Resetear Contraseña
1. Nueva contraseña se valida (7+ chars, 1 mayús, 1 número)
2. Se hashea con BCRYPT en BD
3. Token se marca como **usado** (no reutilizable)
4. Sesión se limpia

---

## 📧 Configuración en Desarrollo (XAMPP)

### Opción A: Sin Email (Testing Manual)

Edita `config.php`:
```php
const EMAIL_CONFIG = [
    'driver' => 'none',  // Desactiva envío
];
```

**Ventaja:** No necesitas configurar email, solo pruebas el flujo

---

### Opción B: MailHog (Local - RECOMENDADO)

MailHog captura emails localmente sin enviar realmente.

1. **Descarga MailHog:**
   https://github.com/mailhog/mailhog/releases
   
2. **Ejecuta MailHog:**
   ```bash
   ./mailhog
   ```
   - Access: http://localhost:1025 (SMTP)
   - Web UI: http://localhost:8025

3. **Configura XAMPP php.ini:**
   ```ini
   [mail function]
   SMTP = localhost
   smtp_port = 1025
   sendmail_path = "/usr/sbin/sendmail -t -i"
   ```

4. **Los emails aparecen en:**
   http://localhost:8025/

---

### Opción C: Gmail (Desarrollo Local Real)

1. **Crea App Password en Google:**
   - Accede: https://myaccount.google.com/apppasswords
   - Selecciona: Mail + Windows
   - Copia password de 16 caracteres

2. **Configura `config.php`:**
   ```php
   const EMAIL_CONFIG = [
       'driver' => 'smtp',
       'host' => 'smtp.gmail.com',
       'port' => 587,
       'username' => 'tu-email@gmail.com',
       'password' => 'app-password-16-chars',
       'from_email' => 'tu-email@gmail.com',
       'from_name' => 'CyberGame',
   ];
   ```

3. **Modifica `enviar_email_recuperacion()` en `config.php`:**
   ```php
   if (EMAIL_CONFIG['driver'] === 'smtp') {
       // Implementar con PHPMailer (ver abajo)
   }
   ```

---

## 🔒 Configuración en Producción

### Opción Recomendada: PHPMailer + SendGrid

**1. Instala PHPMailer:**
```bash
composer require phpmailer/phpmailer
```

**2. En `config.php`, reemplaza la función:**
```php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviar_email_recuperacion($email_destino, $token, $nombre_usuario = 'Usuario') {
    $codigo = substr($token, 0, 8);
    $mail = new PHPMailer(true);

    try {
        // Configurar SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.sendgrid.net';
        $mail->SMTPAuth = true;
        $mail->Username = 'apikey';
        $mail->Password = 'SG.xxxxxx...'; // Tu API key de SendGrid
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Remitente y destinatario
        $mail->setFrom('noreply@tudominio.com', 'CyberGame');
        $mail->addAddress($email_destino);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = 'CyberGame - Recupera tu contraseña';
        $mail->Body = crearHtmlEmail($codigo, $nombre_usuario);
        $mail->AltBody = "Tu código es: $codigo\n\nVálido por 30 minutos";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}
```

**3. Proveedores de Email tipo SaaS:**
- **SendGrid** (recomendado): 100 emails/día gratis
- **Mailgun**: 1000 emails/mes gratis
- **AWS SES**: Muy económico
- **Postmark**: $10/mes

---

## 🧪 Testing

### Test 1: Verificar que BD guarda tokens
```sql
SELECT id_token, id_usuario, usado, expira_en 
FROM tokens_recuperacion 
ORDER BY creado_en DESC LIMIT 5;
```

**Esperado:** Nuevo token con `usado=0` y fecha futura

### Test 2: Verificar email en MailHog
- Abre http://localhost:8025
- Busca emails con Subject: "CyberGame"
- Verifica código de 8 caracteres

### Test 3: Flujo completo
1. Click "Olvidé contraseña"
2. Ingresa email registrado
3. Ver email con código
4. Ingresa código de 8 caracteres
5. Nueva contraseña (7+ chars, 1 mayús, 1 número)
6. Login con nueva contraseña

---

## 🔐 Seguridad en Emails

✅ **Lo que hace bien:**
- Token hasheado en BD (nunca se guarda en texto)
- Expiración de 30 minutos
- Token único por solicitud
- Uso único (no reutilizable después)
- Validación en servidor (no en cliente)

⚠️ **En Producción:**
- Usar HTTPS siempre
- No enviar links con token (¿por qué? Vulnerable si email se intercepta)
- Usar códigos cortos (8 chars) en lugar de links
- Loguear intentos fallidos
- Rate limit: máximo 5 solicitudes por email/hora

---

## 📝 Notas de Desarrollo

### Debugging
- Habilita logs en `php.ini`: `error_log = "/ruta/logs/php.log"`
- Revisa: `error_log()` en recuperar.php

### Testing sin configurar SMTP
```php
// En recuperar.php, después de generar token:
error_log("TEST: Código para usuario: " . substr($token, 0, 8));
```
Luego busca en los logs

### Variación: Usar JWT en lugar de Token
En producción, podrías usar JWT:
```php
// En lugar de token aleatorio:
$jwt = JWT::encode(['email' => $email, 'exp' => time() + 1800], SECRET_KEY);
// Enviar $jwt por email directamente
```

---

## 🐛 Solución de Problemas

| Problema | Solución |
|----------|---------|
| "Email no enviado" | Verifica SMTP en php.ini, revisa logs |
| "Código expirado" | Token + 30 min debe estar en futuro |
| "CODIGO_INVALIDO" | Código < 8 caracteres, rechazado |
| "SESION_INVALIDA" | Sesión expiró, reiniciar proceso |
| "PASS_DEBIL" | Requiere 7+ chars, 1 mayús, 1 número |

---

**Estado:** ✅ Funcional con mail() en desarrollo  
**Pendiente en Producción:** Implementar PHPMailer + SendGrid o Similar
