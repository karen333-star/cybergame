// VALIDACIÓN DE CONTRASEÑA (7+ chars, 1 mayús, 1 número)
function validarContraseña(password) {
    const regex = /^(?=.*[A-Z])(?=.*\d).{7,}$/;
    return regex.test(password);
}

function mostrarRegistro() {
    document.getElementById("login").classList.add("hidden");
    document.getElementById("register").classList.remove("hidden");
    document.getElementById("forgot").classList.add("hidden");
}

    function mostrarLogin() {
        document.getElementById("register").classList.add("hidden");
        document.getElementById("login").classList.remove("hidden");
        document.getElementById("forgot").classList.add("hidden");
    }

    function mostrarForgot() {
        document.getElementById("login").classList.add("hidden");
        document.getElementById("register").classList.add("hidden");
        document.getElementById("forgot").classList.remove("hidden");
        document.getElementById("verificarCodigo").classList.add("hidden");
        document.getElementById("resetPassword").classList.add("hidden");
    }

    function enviarCodigoRecuperacion() {
        const correo = document.getElementById("correoForgot").value;
        if (!correo) {
            alert('Ingresa un correo válido');
            return;
        }

        if (!correo.includes('@')) {
            alert('Ingresa un correo válido');
            return;
        }

        // Guardar email para posterior verificación
        window.recoveryEmail = correo;

        fetch('recuperar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'accion=solicitar_codigo&email=' + encodeURIComponent(correo)
        })
        .then(response => response.json())
        .then(data => {
            console.log("Respuesta solicitar_codigo:", data);
            if (data.ok) {
                // ========== MENSAJE PARA USUARIO ==========
                let mensajeAlerta = '✅ Hemos enviado un código de verificación a tu correo.\n\nRevisa tu bandeja de entrada (o spam).';
                
                // ========== DEBUG: En desarrollo, mostrar código si está disponible ==========
                // Si mail() no funciona, el servidor devuelve el código en la respuesta
                if (data.codigo_debug) {
                    mensajeAlerta += '\n\n🧪 CÓDIGO DE PRUEBA (DEV): ' + data.codigo_debug;
                    console.log('💡 Código debug disponible:', data.codigo_debug);
                }
                
                alert(mensajeAlerta);
                document.getElementById("forgot").classList.add("hidden");
                document.getElementById("verificarCodigo").classList.remove("hidden");
                document.getElementById("correoVerificacion").value = correo;
            } else {
                alert('Error: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(err => console.error('Error fetch:', err));
    }

function verificarCodigoRecuperacion() {
    const codigo = document.getElementById("codigo").value;
    if (!codigo) {
        alert('Ingresa el código de verificación');
        return;
    }

    if (codigo.length < 8) {
        alert('El código debe tener al menos 8 caracteres');
        return;
    }

    const correo = window.recoveryEmail || document.getElementById("correoVerificacion").value;

    fetch('recuperar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'accion=verificar_codigo&email=' + encodeURIComponent(correo) + '&codigo=' + encodeURIComponent(codigo)
    })
    .then(response => response.json())
    .then(data => {
        console.log("Respuesta verificar_codigo:", data);
        if (data.ok) {
            alert('Código verificado correctamente');
            window.tempResetToken = data.temp_token;
            document.getElementById("verificarCodigo").classList.add("hidden");
            document.getElementById("resetPassword").classList.remove("hidden");
            document.getElementById("newPasswordEmail").value = correo;
        } else {
            alert('Error: ' + (data.error || 'Código inválido o expirado'));
        }
    })
    .catch(err => console.error('Error fetch:', err));
}

function resetearContraseña() {
    const password = document.getElementById("newPassword").value;
    const passwordConfirm = document.getElementById("newPasswordConfirm").value;

    if (!password || !passwordConfirm) {
        alert('Completa ambos campos');
        return;
    }

    if (!validarContraseña(password)) {
        alert('Contraseña debe tener al menos 7 caracteres, 1 mayúscula y 1 número');
        return;
    }

    if (password !== passwordConfirm) {
        alert('Las contraseñas no coinciden');
        return;
    }

    fetch('recuperar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'accion=resetear_contraseña&temp_token=' + encodeURIComponent(window.tempResetToken) + 
              '&password=' + encodeURIComponent(password) + 
              '&password_confirm=' + encodeURIComponent(passwordConfirm)
    })
    .then(response => response.json())
    .then(data => {
        console.log("Respuesta resetear_contraseña:", data);
        if (data.ok) {
            alert('Contraseña actualizada exitosamente. Por favor inicia sesión con tu nueva contraseña.');
            mostrarLogin();
            document.getElementById("loginForm").reset();
        } else {
            alert('Error: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(err => console.error('Error fetch:', err));
}

// FORMULARIO LOGIN
const formLogin = document.getElementById("formLogin");
if (formLogin) {
    formLogin.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const identidad = document.querySelector('#formLogin input[name="identidad"]').value;
        const password = document.querySelector('#formLogin input[name="password"]').value;

        if (!identidad || !password) {
            alert('Completa todos los campos');
            return;
        }

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'accion=login&identidad=' + encodeURIComponent(identidad) + '&password=' + encodeURIComponent(password)
        })
        .then(response => response.text())
        .then(data => {
            console.log("Respuesta servidor (login):", data);
            if (data === "LOGIN_OK") {
                window.location.href = "menu.php";
            } else if (data === "NO_EXISTE") {
                alert('Usuario o email no existe');
            } else if (data === "PASS_INCORRECTA") {
                alert('Contraseña incorrecta');
            } else {
                alert('Error en el login: ' + data);
            }
        })
        .catch(err => console.error('Error fetch:', err));
    });
}

// FORMULARIO REGISTRO
const formRegister = document.getElementById("formRegister");
if (formRegister) {
    formRegister.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const email = document.querySelector('#formRegister input[name="email"]').value;
        const nombre_usuario = document.querySelector('#formRegister input[name="nombre_usuario"]').value;
        const password = document.querySelector('#formRegister input[name="password"]').value;
        const password_confirm = document.querySelector('#formRegister input[name="password_confirm"]').value;

        if (!email || !nombre_usuario || !password || !password_confirm) {
            alert('Completa todos los campos');
            return;
        }

        if (!validarContraseña(password)) {
            alert('Contraseña debe tener al menos 7 caracteres, 1 mayúscula y 1 número');
            return;
        }

        if (password !== password_confirm) {
            alert('Las contraseñas no coinciden');
            return;
        }

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'accion=registro&email=' + encodeURIComponent(email) + '&nombre_usuario=' + encodeURIComponent(nombre_usuario) + '&password=' + encodeURIComponent(password) + '&password_confirm=' + encodeURIComponent(password_confirm)
        })
        .then(response => response.text())
        .then(data => {
            console.log("Respuesta servidor (registro):", data);
            if (data === "REGISTRO_OK") {
                alert('Registro exitoso. Por favor inicia sesión');
                mostrarLogin();
            } else if (data === "EMAIL_EXISTE") {
                alert('El email ya está registrado');
            } else if (data === "USUARIO_EXISTE") {
                alert('El nombre de usuario ya está registrado');
            } else if (data === "PASS_NO_COINCIDEN") {
                alert('Las contraseñas no coinciden');
            } else if (data === "PASS_INVALIDA") {
                alert('Contraseña debe tener al menos 7 caracteres, 1 mayúscula y 1 número');
            } else {
                alert('Error en el registro: ' + data);
            }
        })
        .catch(err => console.error('Error fetch:', err));
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof actualizarConfigVisual === 'function') {
        actualizarConfigVisual();
    }
});
 