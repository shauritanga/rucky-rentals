<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mwamba Properties</title>
    <!-- PWA -->
    <meta name="theme-color" content="#6366f1">
    <link rel="manifest" href="/manifest.json">
    <!-- iOS PWA -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Mwamba Properties">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    @viteReactRefresh
    @vite(['resources/js/app.jsx', 'resources/css/app.css'])
    @inertiaHead
</head>

<body>
    @inertia
    <script>
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js');
      }
      // Capture beforeinstallprompt before React mounts to avoid race condition
      window.__pwaInstallPrompt = null;
      window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        window.__pwaInstallPrompt = e;
      });
    </script>
</body>

</html>
