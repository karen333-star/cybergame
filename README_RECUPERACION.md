# CyberGame - Sistema de Recuperación de Contraseña

## ✅ Funcionalidades

- **Recuperación de contraseña** con códigos de 6 dígitos
- **Envío automático de emails** a cualquier proveedor
- **Auto-detección SMTP** según el dominio del email
- **Fallback a archivos locales** si el email falla
- **Interfaz web responsive** con validación JavaScript

## 🚀 Configuración Rápida

### 1. Configurar Email

Edita `config.php` y cambia estas líneas:

```php
const EMAIL_CONFIG = [
    'username' => 'tu-email@gmail.com',     // ← Tu email real
    'password' => 'tu-app-password',        // ← Contraseña de aplicación
    // ... resto igual
];
```

### 2. Para Diferentes Proveedores

#### Gmail
- Email: `tu-email@gmail.com`
- Password: Genera "App Password" en [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)

#### Outlook/Hotmail
- Email: `tu-email@outlook.com`
- Password: Tu contraseña normal de Outlook

#### Yahoo
- Email: `tu-email@yahoo.com`
- Password: Tu contraseña normal de Yahoo

#### iCloud
- Email: `tu-email@icloud.com`
- Password: Tu contraseña normal de iCloud

### 3. Probar el Sistema

1. Abre `http://localhost/cibergame/forgot.html`
2. Ingresa un email registrado en la BD
3. El sistema enviará el código por email O lo guardará en `emails_debug/`
4. Completa la recuperación usando el código de 6 dígitos

## 📧 Cómo Funciona

1. **Usuario solicita recuperación** → Se genera código de 6 dígitos
2. **Sistema detecta SMTP** → Según dominio del email remitente
3. **Intenta enviar por email** → Si falla, guarda en archivo local
4. **Usuario recibe código** → Por email real o archivo local
5. **Verifica código** → Compara con hash SHA256 en BD
6. **Resetea contraseña** → Actualiza BD y limpia tokens

## 🔧 Archivos Importantes

- `config.php` - Configuración y funciones de email
- `recuperar.php` - API backend para recuperación
- `forgot.html` - Interfaz de usuario
- `emails_debug/` - Emails guardados localmente (desarrollo)

## 🛡️ Seguridad

- Códigos hasheados con SHA256 antes de guardar en BD
- Tokens de sesión temporales para verificación
- Validación de contraseñas (7+ chars, mayúscula, número)
- Protección contra enumeración de emails
- Limpieza automática de tokens expirados

## 📊 Base de Datos

La tabla `tokens_recuperacion` debe tener:
```sql
CREATE TABLE tokens_recuperacion (
    id_token BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_en DATETIME NOT NULL,
    usado BOOLEAN DEFAULT FALSE,
    usado_en DATETIME NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);
```

¡El sistema está listo para funcionar con cualquier email! 🎉</content>
<parameter name="filePath">c:\xampp\htdocs\cibergame\README_RECUPERACION.md