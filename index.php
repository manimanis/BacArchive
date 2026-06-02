<?php
/**
 * BacArchive — Entry Point
 * Sert l'application Vue.js 3 construite
 */

// Si c'est une requête API, laisser api/index.php gérer
if (isset($_GET['action'])) {
    require __DIR__ . '/api/index.php';
    exit;
}

// Servir l'application Vue.js
$distPath = __DIR__ . '/dist/index.html';
if (file_exists($distPath)) {
    // Production: servir les fichiers construits
    // Gérer les routes SPA
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    // Si le fichier demandé existe dans dist/, le servir directement avec le bon MIME type
    $filePath = __DIR__ . '/dist' . str_replace($basePath, '', $requestUri);
    if ($requestUri !== '/' && file_exists($filePath) && is_file($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'js'   => 'application/javascript',
            'mjs'  => 'application/javascript',
            'css'  => 'text/css',
            'svg'  => 'image/svg+xml',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext] . '; charset=utf-8');
        }
        readfile($filePath);
        exit;
    }

    // Sinon, servir index.html (SPA routing)
    header('Content-Type: text/html; charset=utf-8');
    readfile($distPath);
    exit;
}

// Mode développement: afficher une page de démarrage
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BacArchive</title>
    <link rel="icon" href="assets/icon.svg">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #1a3a5c;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            max-width: 600px;
            padding: 40px;
        }
        h1 { font-size: 28px; margin-bottom: 10px; }
        p { color: #a0c0d8; margin: 8px 0; }
        .cmd {
            background: #0f2540;
            border: 1px solid #2980b9;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 14px;
            color: #63c2f5;
            text-align: left;
        }
        .cmd .comment { color: #64748b; }
        .btn {
            display: inline-block;
            margin-top: 20px;
            background: #2980b9;
            color: white;
            padding: 10px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn:hover { background: #3498db; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 BacArchive</h1>
        <p>Collecte et gestion centralisée des travaux du Bac pratique</p>
        <p style="color:#e67e22;font-weight:600">Mode développement — Le frontend n'est pas encore construit</p>
        <div class="cmd">
            <div class="comment"># 1. Installer les dépendances PHP</div>
            composer install<br><br>
            <div class="comment"># 2. Installer et construire le frontend</div>
            cd frontend<br>
            npm install<br>
            npm run build<br><br>
            <div class="comment"># 3. Ouvrir dans le navigateur</div>
            http://localhost/BacArchive/
        </div>
        <a href="api/index.php?action=load-settings" class="btn">Tester l'API →</a>
    </div>
</body>
</html>