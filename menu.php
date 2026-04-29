<?php
require 'config.php';

// Validar sesión
validar_sesion();

// Procesar logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.html");
    exit;
}

$nombre_usuario = $_SESSION['nombre_usuario'];
$usuario_param = urlencode($nombre_usuario);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberGame - Menú Principal</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="menu.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
</head>
<body class="menu-page">
    <div class="menu-grid-overlay" aria-hidden="true"></div>
    <div class="container menu-container">
        <div class="card menu-card">
            <div class="menu-corner menu-corner-tl" aria-hidden="true"></div>
            <div class="menu-corner menu-corner-tr" aria-hidden="true"></div>
            <div class="menu-corner menu-corner-bl" aria-hidden="true"></div>
            <div class="menu-corner menu-corner-br" aria-hidden="true"></div>

            <h1 id="main-title" class="menu-title"><span class="menu-title-prefix">&gt;_</span> CYBER_GAME</h1>
            <h2 class="menu-welcome">Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?>!</h2>

            <div class="menu-opciones">
                <a href="index.html?view=config&usuario=<?php echo $usuario_param; ?>" class="menu-btn menu-btn-primary">
                    <span>INICIAR PARTIDA</span>
                </a>
                <a href="#" id="tutorial-btn" class="menu-btn menu-btn-secondary">
                    <span>TUTORIAL</span>
                </a>
                <a href="perfil.php" class="menu-btn menu-btn-secondary">
                    <span>VER PERFIL</span>
                </a>
                <a href="historial.php" class="menu-btn menu-btn-secondary">
                    <span>VER HISTORIAL</span>
                </a>
                <a href="menu.php?logout=1" class="menu-btn menu-btn-danger logout-btn">
                    <span>CERRAR SESION</span>
                </a>
            </div>

            <p class="menu-status"><span class="menu-status-dot"></span>SISTEMA OPERATIVO // RED SEGURA</p>
        </div>
    </div>

    <!-- Tutorial Modal -->
    <div id="tutorial-overlay" class="tutorial-overlay" aria-hidden="true">
        <div class="tutorial-modal" role="dialog" aria-modal="true" aria-labelledby="tutorial-title">
            <button class="tutorial-close" id="tutorial-close" aria-label="Cerrar tutorial">✕</button>
            <h3 id="tutorial-title" class="tutorial-title">Tutorial</h3>
            <div class="tutorial-body">
                <div class="tutorial-image-wrap">
                    <img id="tutorial-image" src="" alt="Paso del tutorial">
                </div>
                <div class="tutorial-text-scroll">
                    <div class="tutorial-text" id="tutorial-text"></div>
                </div>
            </div>
            <div class="tutorial-controls">
                <button id="tutorial-prev" class="tutorial-btn">◀ Anterior</button>
                <div class="tutorial-step-indicator" id="tutorial-step">1 / 5</div>
                <button id="tutorial-next" class="tutorial-btn">Siguiente ▶</button>
            </div>
        </div>
    </div>

    <script>
        (function(){
            const btn = document.getElementById('tutorial-btn');
            const overlay = document.getElementById('tutorial-overlay');
            const img = document.getElementById('tutorial-image');
            const text = document.getElementById('tutorial-text');
            const closeBtn = document.getElementById('tutorial-close');
            const prevBtn = document.getElementById('tutorial-prev');
            const nextBtn = document.getElementById('tutorial-next');
            const stepIndicator = document.getElementById('tutorial-step');

            if (!overlay || !btn || !img || !text || !closeBtn || !prevBtn || !nextBtn || !stepIndicator) {
                // Required elements missing — abort to avoid runtime errors
                return;
            }

            const basePath = 'imagenes_tuto';
            const steps = [
                { img: '1.png', text: `Hola, bienvenido o bienvenida al tutorial de cybergame, por favor lee atentamente y apoyate de las imagenes para
                 que te sirvan de guia visual. Comencemos.\n\n Primero encontraras la configuración inicial, aquí podras elegir los apartados que impactaran en 
                 tu partida, consta de 4 opciones que deberas configurar siempre: \n\nMÁXIMO DE RONDAS: Es la cantidad de rondas que tienes para ganar tu partida,
                  si llegas a ese limite sin ganar o perder, la partida acabará automaticamente. Puedes seleccionar entre 7 y 25 rondas.\n
                \nCIA inicial:\n Este será tu puntaje inicial de seguridad de la información, aprenderas de esto a lo largo de tus partidas, sin embargo por ahora
                 debes saber que consta de un triangulo, entre Confidencialidad, Integridad, y Accesibilidad, el equilibrio entre estos 3 aspectos es fundamental para
                  la seguridad cibernetica de cualquier software; ten cuidado, cada eleccion afectara de forma diferente a cada uno de estos puntajes. Puedes configurarlo entre 30 
                  y 80.\n\nPROBABILIDAD DE DESPIDO:\n\n Basicamente es la relacion con tu jefe, segun tus descisiones la probabilidad de que te despidan aumentara o bajara, entra más
                   abajo mejor para ti. (puedes configurarlo entre 5 y 80)\n\nPRESUPUESTO INICIAL: \nLas mejoras, aciertos y errores cuestan dinero, es por eso que deberas manejar 
                   un presupuesto, puedes configurarlo entre 10 y 80 millones.\n\nPasos para configurar: \n1: Elige el numero\n2: Puedes modificarlo con las flechas, esto es de ayuda 
                   si estas en un celular\n3: Inicia partida` },
                { img: '2.png', text: '' },
                { img: '3.png', text: 'Correos de elección múltiple' },
                { img: '4.png', text: 'Correos Phishing' },
                { img: '5.png', text: 'Ya sabes lo básico, recuerda que eres el CISO (Chief Information Security Officer) de una compañía, tu labor como jefe de seguridad de la información es aprender de tus errores y mantener a flote la empresa. ¡Mucha suerte!' }
            ];

            let index = 0;

            function showStep(i){
                index = i;
                const step = steps[i];
                img.src = encodeURI(basePath + '/' + step.img);
                img.alt = `Paso ${i+1}`;
                text.innerHTML = (step.text || '').replace(/\n/g, '<br>');
                stepIndicator.textContent = `${i+1} / ${steps.length}`;
                prevBtn.disabled = (i === 0);
                nextBtn.textContent = (i === steps.length - 1) ? 'Finalizar' : 'Siguiente ▶';
                if(i === steps.length - 1) nextBtn.classList.add('finish'); else nextBtn.classList.remove('finish');
            }

            function open(){
                overlay.style.display = 'flex';
                overlay.setAttribute('aria-hidden','false');
                document.body.style.overflow = 'hidden';
                showStep(0);
            }

            function close(){
                overlay.style.display = 'none';
                overlay.setAttribute('aria-hidden','true');
                document.body.style.overflow = '';
            }

            btn.addEventListener('click', function(e){ e.preventDefault(); open(); });
            closeBtn.addEventListener('click', close);
            overlay.addEventListener('click', function(e){ if(e.target === overlay) close(); });
            prevBtn.addEventListener('click', function(){ if(index>0) showStep(index-1); });
            nextBtn.addEventListener('click', function(){ if(index < steps.length-1) showStep(index+1); else close(); });
            document.addEventListener('keydown', function(e){ if(overlay.style.display === 'flex'){ if(e.key === 'Escape') close(); if(e.key === 'ArrowLeft') { if(index>0) showStep(index-1); } if(e.key === 'ArrowRight') { if(index<steps.length-1) showStep(index+1); } } });

            // initialize hidden
            overlay.style.display = 'none';
        })();
    </script>

</body>
</html>
