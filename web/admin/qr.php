<?php
// Генерация QR кодов для VPN конфигураций

session_start();

// Проверка авторизации
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    exit('Unauthorized');
}

$uuid = $_GET['uuid'] ?? '';
$username = $_GET['username'] ?? 'user';
$type = $_GET['type'] ?? 'vision';

if (empty($uuid)) {
    header('HTTP/1.0 400 Bad Request');
    exit('UUID required');
}

// Генерируем VLESS ссылку
$domain = 'your-domain.com';

if ($type === 'ws') {
    $port = 443;
    $params = 'type=ws&security=tls&path=/ws&host=' . $domain;
    $link = "vless://{$uuid}@{$domain}:{$port}?{$params}#{$username}-ws";
    $title = $username . ' (WebSocket)';
} else {
    $port = 8443;
    $params = 'type=tcp&security=tls&sni=' . $domain . '&flow=xtls-rprx-vision';
    $link = "vless://{$uuid}@{$domain}:{$port}?{$params}#{$username}-vision";
    $title = $username . ' (Vision)';
}

// Генерируем QR код через Python
$tempFile = '/tmp/qr_' . md5($link . time() . rand()) . '.png';
$cmd = 'python3 -c "import qrcode; qr = qrcode.QRCode(version=1, box_size=10, border=2); qr.add_data(' . escapeshellarg($link) . '); qr.make(fit=True); img = qr.make_image(fill_color=\'black\', back_color=\'white\'); img.save(' . escapeshellarg($tempFile) . ')" 2>&1';

exec($cmd, $output, $returnCode);

if (file_exists($tempFile) && filesize($tempFile) > 0) {
    // Отправляем HTML страницу с QR кодом
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>QR Code - <?= htmlspecialchars($title) ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 500px;
            }
            h1 { color: #333; margin-bottom: 20px; font-size: 24px; }
            .qr-code { margin: 20px 0; }
            .qr-code img { max-width: 100%; height: auto; border: 2px solid #eee; border-radius: 10px; }
            .config-link {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                word-break: break-all;
                font-family: monospace;
                font-size: 12px;
                margin: 20px 0;
                color: #333;
            }
            button {
                background: #667eea;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                margin: 5px;
                transition: background 0.3s;
            }
            button:hover { background: #5568d3; }
            .btn-secondary { background: #6c757d; }
            .btn-secondary:hover { background: #5a6268; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔐 <?= htmlspecialchars($title) ?></h1>
            <div class="qr-code">
                <img src="data:image/png;base64,<?= base64_encode(file_get_contents($tempFile)) ?>" alt="QR Code">
            </div>
            <div class="config-link"><?= htmlspecialchars($link) ?></div>
            <button onclick="copyToClipboard()">📋 Копировать ссылку</button>
            <button class="btn-secondary" onclick="window.close()">Закрыть</button>
        </div>
        <script>
            function copyToClipboard() {
                const link = <?= json_encode($link) ?>;
                navigator.clipboard.writeText(link).then(() => {
                    alert('Ссылка скопирована в буфер обмена!');
                }).catch(err => {
                    console.error('Ошибка копирования:', err);
                });
            }
        </script>
    </body>
    </html>
    <?php
    unlink($tempFile);
} else {
    header('HTTP/1.0 500 Internal Server Error');
    echo '<h1>Ошибка генерации QR кода</h1>';
    echo '<p>Return code: ' . $returnCode . '</p>';
    echo '<p>Output: ' . htmlspecialchars(implode("\n", $output)) . '</p>';
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
}
