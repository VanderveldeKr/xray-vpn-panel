<?php
// API для управления пользователями

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Пароль из переменной окружения или дефолтный (ОБЯЗАТЕЛЬНО ИЗМЕНИТЕ!)
$password = getenv('ADMIN_PASSWORD') ?: 'change_me_please';
session_start();

// Проверка авторизации
if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

// Добавление пользователя
if ($action === 'add_user') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['error' => 'Username required']);
        exit;
    }
    
    if (empty($email)) {
        $email = $username . '@jobbot.site';
    }
    
    // ПРОВЕРКА: пользователь уже существует?
    $usersFile = '/root/vpn-project/configs/users.txt';
    if (file_exists($usersFile)) {
        $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if ($parts[0] === $username) {
                echo json_encode(['error' => 'Пользователь ' . $username . ' уже существует']);
                exit;
            }
        }
    }
    
    // Генерируем UUID
    $uuid = trim(shell_exec('xray uuid 2>&1'));
    
    if (empty($uuid) || strlen($uuid) < 30) {
        echo json_encode(['error' => 'Не удалось сгенерировать UUID: ' . $uuid]);
        exit;
    }
    
    $date = date('Y-m-d');
    
    // Добавляем в файл пользователей
    $result = @file_put_contents($usersFile, "$username|$uuid|$email|$date\n", FILE_APPEND);
    if ($result === false) {
        echo json_encode(['error' => 'Ошибка записи в users.txt. Проверьте права доступа.']);
        exit;
    }
    
    // Обновляем конфигурацию Xray
    $configFile = '/usr/local/etc/xray/config.json';
    $configContent = @file_get_contents($configFile);
    
    if ($configContent === false) {
        // Откатываем изменения
        $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newLines = array_filter($lines, function($line) use ($username) {
            $parts = explode('|', $line);
            return $parts[0] !== $username;
        });
        file_put_contents($usersFile, implode("\n", $newLines) . "\n");
        
        echo json_encode(['error' => 'Ошибка чтения config.json. Проверьте права доступа.']);
        exit;
    }
    
    $config = json_decode($configContent, true);
    
    if (!is_array($config)) {
        // Откатываем изменения
        $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newLines = array_filter($lines, function($line) use ($username) {
            $parts = explode('|', $line);
            return $parts[0] !== $username;
        });
        file_put_contents($usersFile, implode("\n", $newLines) . "\n");
        
        echo json_encode(['error' => 'Ошибка парсинга config.json']);
        exit;
    }
    
    // ПРОВЕРКА: email уже есть в конфигурации?
    foreach ($config['inbounds'][0]['settings']['clients'] as $client) {
        if ($client['email'] === $email) {
            // Откатываем изменения
            $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $newLines = array_filter($lines, function($line) use ($username) {
                $parts = explode('|', $line);
                return $parts[0] !== $username;
            });
            file_put_contents($usersFile, implode("\n", $newLines) . "\n");
            
            echo json_encode(['error' => 'Email ' . $email . ' уже существует в конфигурации']);
            exit;
        }
    }
    
    // Добавляем в Vision
    $config['inbounds'][0]['settings']['clients'][] = [
        'id' => $uuid,
        'flow' => 'xtls-rprx-vision',
        'email' => $email
    ];
    
    // Добавляем в WebSocket с правильным email
    $wsEmail = str_replace('@jobbot.site', '-ws@jobbot.site', $email);
    $config['inbounds'][1]['settings']['clients'][] = [
        'id' => $uuid,
        'email' => $wsEmail
    ];
    
    // Сохраняем конфигурацию
    $result = @file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    if ($result === false) {
        // Откатываем изменения
        $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newLines = array_filter($lines, function($line) use ($username) {
            $parts = explode('|', $line);
            return $parts[0] !== $username;
        });
        file_put_contents($usersFile, implode("\n", $newLines) . "\n");
        
        echo json_encode(['error' => 'Ошибка записи config.json. Проверьте права доступа.']);
        exit;
    }
    
    // Перезапускаем Xray
    $restart_output = shell_exec('sudo systemctl restart xray 2>&1');
    
    // Проверяем что Xray запустился
    sleep(2);
    $status = trim(shell_exec('sudo systemctl is-active xray'));
    
    if ($status !== 'active') {
        // Откатываем изменения
        $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newLines = array_filter($lines, function($line) use ($username) {
            $parts = explode('|', $line);
            return $parts[0] !== $username;
        });
        file_put_contents($usersFile, implode("\n", $newLines) . "\n");
        
        // Пытаемся восстановить старую конфигурацию
        file_put_contents($configFile, $configContent);
        shell_exec('sudo systemctl restart xray 2>&1');
        
        echo json_encode(['error' => 'Xray не запустился. Изменения отменены. Проверьте логи: journalctl -u xray -n 50']);
        exit;
    }
    
    echo json_encode(['success' => true, 'uuid' => $uuid, 'username' => $username, 'message' => 'Пользователь успешно добавлен!']);
    exit;
}

// Удаление пользователя
if ($action === 'delete_user') {
    $username = $_POST['username'] ?? '';
    
    if (empty($username)) {
        echo json_encode(['error' => 'Username required']);
        exit;
    }
    
    // Получаем UUID пользователя
    $usersFile = '/root/vpn-project/configs/users.txt';
    $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $uuid = '';
    $newLines = [];
    
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if ($parts[0] === $username) {
            $uuid = $parts[1];
        } else {
            $newLines[] = $line;
        }
    }
    
    if (empty($uuid)) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Обновляем файл пользователей
    file_put_contents($usersFile, implode("\n", $newLines) . "\n");
    
    // Удаляем из конфигурации Xray
    $configFile = '/usr/local/etc/xray/config.json';
    $config = json_decode(file_get_contents($configFile), true);
    
    // Удаляем из Vision
    $config['inbounds'][0]['settings']['clients'] = array_values(
        array_filter($config['inbounds'][0]['settings']['clients'], function($client) use ($uuid) {
            return $client['id'] !== $uuid;
        })
    );
    
    // Удаляем из WebSocket
    $config['inbounds'][1]['settings']['clients'] = array_values(
        array_filter($config['inbounds'][1]['settings']['clients'], function($client) use ($uuid) {
            return $client['id'] !== $uuid;
        })
    );
    
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    // Перезапускаем Xray
    shell_exec('sudo systemctl restart xray 2>&1');
    
    echo json_encode(['success' => true, 'message' => 'Пользователь удален']);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
