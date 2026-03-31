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
        document.getElementById("config").classList.add("hidden");
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
 