(function () {
    const audio = new Audio();
    let fuenteCargada = false;
    let reintentoRegistrado = false;
    const volumenBase = 0.05;

    audio.loop = true;
    audio.preload = 'auto';
    audio.volume = volumenBase;

    function esMenu() {
        return /\/menu\.php$/i.test(window.location.pathname);
    }

    function esPartida() {
        const params = new URLSearchParams(window.location.search);
        return /\/index\.html$/i.test(window.location.pathname) && params.get('view') === 'partida';
    }

    function debeReproducir() {
        return esMenu() || esPartida();
    }

    function ajustarVolumenSegunVista() {
        audio.volume = esPartida() ? (volumenBase * 0.5) : volumenBase;
    }

    function limpiarListenersDesbloqueo() {
        if (!reintentoRegistrado) {
            return;
        }

        reintentoRegistrado = false;
        document.removeEventListener('pointerdown', desbloquearAudio, true);
        document.removeEventListener('keydown', desbloquearAudio, true);
        document.removeEventListener('touchstart', desbloquearAudio, true);
        document.removeEventListener('click', desbloquearAudio, true);
    }

    function registrarDesbloqueo() {
        if (reintentoRegistrado) {
            return;
        }

        reintentoRegistrado = true;
        document.addEventListener('pointerdown', desbloquearAudio, true);
        document.addEventListener('keydown', desbloquearAudio, true);
        document.addEventListener('touchstart', desbloquearAudio, true);
        document.addEventListener('click', desbloquearAudio, true);
    }

    function desbloquearAudio() {
        audio.play().then(function () {
            limpiarListenersDesbloqueo();
        }).catch(function () {
            // Se deja el listener activo para el siguiente gesto del usuario.
        });
    }

    function intentarReproducir() {
        audio.play().then(function () {
            limpiarListenersDesbloqueo();
        }).catch(function () {
            registrarDesbloqueo();
        });
    }

    function cargarYReproducir() {
        if (fuenteCargada || !debeReproducir()) {
            return;
        }

        fuenteCargada = true;
        ajustarVolumenSegunVista();

        fetch('music_random.php', { cache: 'no-store' })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.ok || !data.file) {
                    return;
                }

                audio.src = data.file;
                audio.load();
                intentarReproducir();
            })
            .catch(function () {
                fuenteCargada = false;
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', cargarYReproducir, { once: true });
    } else {
        cargarYReproducir();
    }
})();
