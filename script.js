// VALIDACIÓN DE CONTRASEÑA (7+ chars, 1 mayús, 1 número)
function validarContraseña(password) {
    const regex = /^(?=.*[A-Z])(?=.*\d).{7,}$/;
    return regex.test(password);
}

function mostrarRegistro() {
    document.getElementById("login").classList.add("hidden");
    document.getElementById("register").classList.remove("hidden");
    document.getElementById("forgot").classList.add("hidden");
    document.getElementById("verificarCodigo").classList.add("hidden");
    document.getElementById("config").classList.add("hidden");
    document.getElementById("partida").classList.add("hidden");
}

    function mostrarLogin() {
        document.getElementById("register").classList.add("hidden");
        document.getElementById("login").classList.remove("hidden");
        document.getElementById("forgot").classList.add("hidden");
        document.getElementById("verificarCodigo").classList.add("hidden");
        document.getElementById("config").classList.add("hidden");
        document.getElementById("partida").classList.add("hidden");
    }

    function mostrarForgot() {
        document.getElementById("login").classList.add("hidden");
        document.getElementById("register").classList.add("hidden");
        document.getElementById("forgot").classList.remove("hidden");
        document.getElementById("verificarCodigo").classList.add("hidden");
        document.getElementById("config").classList.add("hidden");
        document.getElementById("partida").classList.add("hidden");
    }

    function enviarCodigoRecuperacion() {
        const correo = document.getElementById("correoForgot").value;
        if (!correo) {
            alert('Ingresa un correo');
            return;
        }

        // TODO: Llamar a backend para enviar código real
        alert(`Código enviado a ${correo}`);
        document.getElementById("forgot").classList.add("hidden");
        document.getElementById("verificarCodigo").classList.remove("hidden");
    }

function verificarCodigoRecuperacion() {
    const codigo = document.getElementById("codigo").value;
    if (!codigo) {
        alert('Ingresa un código');
        return;
    }

    // TODO: Llamar a backend para verificar código
    alert(`Código ${codigo} verificado. Redireccionar a reset de contraseña`);
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
            if (data.indexOf("LOGIN_OK|") === 0) {
                const nombreUsuario = data.split("|").slice(1).join("|").trim();
                if (nombreUsuario) {
                    localStorage.setItem('cybergame_nombre_usuario', nombreUsuario);
                }
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
 