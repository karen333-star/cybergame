
    function mostrarConfig() {
        document.getElementById("login").classList.add("hidden");
        document.getElementById("register").classList.add("hidden");
        document.getElementById("forgot").classList.add("hidden");
        document.getElementById("config").classList.remove("hidden");
    }

    function cambiarValor(id, delta) {
        const input = document.getElementById(id);
        if (!input) return;

        let valor = Number(input.value) || 0;
        valor += delta;

        const min = input.hasAttribute('min') ? Number(input.getAttribute('min')) : null;
        const max = input.hasAttribute('max') ? Number(input.getAttribute('max')) : null;

        if (min !== null && valor < min) valor = min;
        if (max !== null && valor > max) valor = max;

        input.value = valor;
    }

    function cambiarValorSeleccionado(delta) {
        const campo = document.getElementById('statSeleccionado').value;
        if (campo === 'cia') {
            cambiarValor('cia', delta);
        } else if (campo === 'presupuesto') {
            cambiarValor('presupuesto', delta * 1000);
        } else if (campo === 'despido') {
            cambiarValor('despido', delta);
        }
    }

    function iniciarPartida() {
        const cia = Number(document.getElementById("cia").value);
        const presupuesto = Number(document.getElementById("presupuesto").value);
        const despido = Number(document.getElementById("despido").value);

        if (cia < 0 || cia > 100 || despido < 0 || despido > 100 || presupuesto < 0) {
            alert('Valores invalidos en la configuración');
            return;
        }

        // Aquí irá la llamada al backend / inicio de juego
        alert(`Partida iniciada:\nCIA=${cia}\nPresupuesto=${presupuesto}\nProb. despido=${despido}%`);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const formLogin = document.getElementById('formLogin');
        if (formLogin) {
            formLogin.addEventListener('submit', function(e) {
                e.preventDefault();
                // en el servidor se puede validar; aquí vamos directo a fase 2
                mostrarConfig();
            });
        }
    });

