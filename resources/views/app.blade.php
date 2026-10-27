<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#05080b">
    <meta name="description" content="Gachapón de personajes históricos del Paraguay: coleccioná 63 figuras con retratos y biografías de Wikipedia.">

    <script>
        (function () {
            try {
                var theme = window.localStorage.getItem('gacha-theme');
                var colors = {
                    default: '#05080b',
                    claro: '#f4efe3',
                    terere: '#04100a',
                    larissa: '#120410',
                    baila: '#08040f',
                    futbol: '#0a0305',
                    asado: '#0d0705',
                    mandioca: '#0e0b06',
                    emo: '#040407'
                };

                if (theme && colors[theme]) {
                    document.documentElement.setAttribute('data-theme', theme);
                    document.querySelector('meta[name="theme-color"]').setAttribute('content', colors[theme]);
                }
            } catch (error) {
                // Sin acceso a localStorage: se usa el tema por defecto.
            }
        })();
    </script>

    <title inertia>Gacha Guaraní · Historia del Paraguay</title>

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='48' fill='%2305080b' stroke='%23eec25f' stroke-width='4'/%3E%3Ctext x='50' y='68' font-size='52' text-anchor='middle' fill='%23eec25f' font-family='Georgia'%3E%E2%82%B2%3C/text%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="min-h-screen bg-night-950 font-sans text-bone-100 antialiased">
    @inertia
</body>
</html>
