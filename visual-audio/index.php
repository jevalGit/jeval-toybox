<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audio-Visualizer beta</title>
    <link rel="stylesheet" href="https://cdn.jeval.cl/0/bootstrap/bootstrap-5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        #audio-container {
            width: 100%;
            text-align: center;
        }
        #canvas-container {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 498px;
            height: 201px;
            overflow: hidden;
        }
        #background-container {
            position: absolute;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            z-index: -1;
        }
        canvas {
            border: 1px solid #ccc;
            width: 100%;
            height: 100%;
        }
        .slider-container {
            margin-top: 20px;
            text-align: center;
        }
        .color-selector, .smoothing-selector, .fftsize-selector, .visibility-selector, .speed-selector {
            margin-top: 10px;
        }
        .audio-controls-container {
            width: 501px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="audio-container">
            <div id="canvas-container">
                <div id="background-container" style="background-image: url('ruta_a_tu_imagen.jpg');"></div>
                <canvas id="visualizer"></canvas>
            </div>
            <div class="visibility-selector">
                <label for="visibility-slider">Visibilidad:</label>
                <input type="range" min="0" max="1" step="0.01" value="1" class="slider" id="visibility-slider">
            </div>
            <div class="fftsize-selector">
                <label for="fftsize-slider">Tamaño de FFT:</label>
                <input type="range" min="32" max="2048" step="32" value="256" class="slider" id="fftsize-slider">
            </div>
            <div class="smoothing-selector">
                <label for="smoothing-slider">Suavizado:</label>
                <input type="range" min="0" max="1" step="0.01" value="0.85" class="slider" id="smoothing-slider">
            </div>
            <div class="color-selector">
                <label for="color-selector">Color de las barras:</label>
                <input type="color" id="color-selector" value="#ff0000">
            </div>
            <div class="speed-selector">
                <label for="speed-slider">Velocidad:</label>
                <input type="range" min="0.5" max="2" step="0.1" value="1" class="slider" id="speed-slider">
            </div>
            <div class="reverse-selector">
                <label for="reverse-switch">Reproducir en reversa:</label>
                <input type="checkbox" id="reverse-switch">
            </div>
            <label for="audio-file">Seleccionar archivo de audio:</label>
            <input type="file" id="audio-file" accept="audio/*">
            <div class="image-selector">
                <label for="image-upload">Seleccionar imagen de fondo:</label>
                <input type="file" id="image-upload" accept="image/*">
            </div>
            <div class="audio-controls-container">
                <audio id="audio" controls style="width: 501px; height: 52px; display: none;">
                    Tu navegador no soporta el elemento de audio.
                </audio>
            </div>
            <button id="play-button">Reproducir</button>
            <button id="pause-button">Pausar</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function() {
            
            $("#background-container").resizable({
                aspectRatio: true,
                maxWidth: 1000,
                minWidth: 50,
                maxHeight: 500,
                minHeight: 25
            });

           
            $("#audio-file").change(function() {
                const file = this.files[0];
                if (file) {
                    const audio = document.getElementById("audio");
                    audio.src = URL.createObjectURL(file);
                    audio.style.display = "block";
                }
            });

           
            $("#image-upload").change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = new Image();
                        img.onload = function() {
                            const canvas = document.createElement('canvas');
                            canvas.width = 498;
                            canvas.height = 199;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                            document.getElementById('background-container').style.backgroundImage = `url('${canvas.toDataURL()}')`;
                        };
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });

           
            $("#play-button").click(function() {
                const audio = document.getElementById('audio');
                audio.play();
            });

            
            $("#pause-button").click(function() {
                const audio = document.getElementById('audio');
                audio.pause();
            });
        });

        window.onload = function() {
            const audio = document.getElementById('audio');
            const canvas = document.getElementById('visualizer');
            const canvasCtx = canvas.getContext('2d');
            const visibilitySlider = document.getElementById('visibility-slider');
            const colorSelector = document.getElementById('color-selector');
            const smoothingSlider = document.getElementById('smoothing-slider');
            const fftSizeSlider = document.getElementById('fftsize-slider');
            const speedSlider = document.getElementById('speed-slider');
            const reverseSwitch = document.getElementById('reverse-switch');

            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const source = audioCtx.createMediaElementSource(audio);
            const analyser = audioCtx.createAnalyser();

            source.connect(analyser);
            analyser.connect(audioCtx.destination);

            let fftSize = 256;
            analyser.fftSize = fftSize;
            const bufferLength = analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);
            let prevDataArray = new Uint8Array(bufferLength);

            function draw() {
                requestAnimationFrame(draw);

                analyser.getByteFrequencyData(dataArray);

                
                for (let i = 0; i < bufferLength; i++) {
                    dataArray[i] = dataArray[i] * smoothingSlider.value + prevDataArray[i] * (1 - smoothingSlider.value);
                }

                prevDataArray = dataArray.slice();

                
                canvas.width = 498;
                canvas.height = 201;

                
                canvasCtx.clearRect(0, 0, canvas.width, canvas.height);

                const barWidth = (canvas.width / bufferLength) * 2.5;
                let x = 0;

                
                const visibility = visibilitySlider.value;

                
                const color = colorSelector.value;

                
                canvasCtx.fillStyle = color;

                for (let i = 0; i < bufferLength; i++) {
                    const barHeight = dataArray[i];

                    
                    const y = canvas.height - barHeight / 2;

                    
                    canvasCtx.globalAlpha = visibility;

                    canvasCtx.fillRect(x, y, barWidth, barHeight / 2);

                    x += barWidth + 1;
                }

                
                canvasCtx.globalAlpha = 1;
            };

            draw();

            audio.onplay = function() {
                audioCtx.resume().then(() => {
                    console.log('Playback resumed successfully');
                });
            };

            
            fftSizeSlider.addEventListener('input', function() {
                fftSize = parseInt(fftSizeSlider.value);
                analyser.fftSize = fftSize;
                prevDataArray = new Uint8Array(bufferLength);
                dataArray.fill(0);
            });

            
            speedSlider.addEventListener('input', function() {
                audio.playbackRate = parseFloat(speedSlider.value);
            });

            
            reverseSwitch.addEventListener('change', function() {
                audio.playbackRate = reverseSwitch.checked ? -Math.abs(audio.playbackRate) : Math.abs(audio.playbackRate);
            });
        };
    </script>
</body>
</html>
