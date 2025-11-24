<?php
session_start();
$password = getenv('ADMIN_PASSWORD') ?: 'change_me_please';
$password = 'change_me_please';

// Обработка логина
if (isset($_POST['login'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['logged_in'] = true;
        header('Location: /admin/');
        exit;
    } else {
        $error = 'Неверный пароль';
    }
}

// Обработка логаута
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

// Проверка авторизации
if (!isset($_SESSION['logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Вход - VPN Admin</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
            h1 { margin-bottom: 30px; color: #333; text-align: center; }
            input { width: 100%; padding: 12px; margin-bottom: 20px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; }
            input:focus { outline: none; border-color: #667eea; }
            button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; transition: background 0.3s; }
            button:hover { background: #5568d3; }
            .error { background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 VPN Admin</h1>
            <?php if (isset($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Пароль" required autofocus>
                <button type="submit" name="login">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Функция для чтения пользователей
function getUsers() {
    $usersFile = '/root/vpn-project/configs/users.txt';
    $users = [];
    
    if (!file_exists($usersFile)) {
        return $users;
    }
    
    $lines = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (count($parts) >= 4) {
            $users[] = [
                'username' => $parts[0],
                'uuid' => $parts[1],
                'email' => $parts[2],
                'date' => $parts[3]
            ];
        }
    }
    
    return $users;
}

$users = getUsers();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-number { font-size: 32px; font-weight: bold; color: #667eea; }
        .stat-label { color: #666; margin-top: 5px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; }
        .add-user-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end; }
        input { padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 14px; }
        input:focus { outline: none; border-color: #667eea; }
        button { padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; transition: background 0.3s; }
        button:hover { background: #5568d3; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-small { padding: 6px 12px; font-size: 12px; }
        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #333; border-bottom: 2px solid #dee2e6; }
        .users-table td { padding: 12px; border-bottom: 1px solid #dee2e6; }
        .user-actions { display: flex; gap: 5px; flex-wrap: wrap; }
        .copy-btn { background: #3498db; }
        .copy-btn:hover { background: #2980b9; }
        .logout-btn { background: rgba(255,255,255,0.2); }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        .message { padding: 12px; border-radius: 5px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🚀 VPN Admin Panel</h1>
            <a href="?logout" class="logout-btn" style="text-decoration: none; color: white; padding: 10px 20px; border-radius: 5px;">Выход</a>
        </div>
    </div>

    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="total-users"><?= count($users) ?></div>
                <div class="stat-label">Всего пользователей</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">✅</div>
                <div class="stat-label">Сервер активен</div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 20px;">➕ Добавить пользователя</h2>
            <div id="message"></div>
            <form class="add-user-form" id="add-user-form">
                <div>
                    <label style="display: block; margin-bottom: 5px; color: #666;">Имя пользователя</label>
                    <input type="text" name="username" placeholder="Например: ivan" required>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; color: #666;">Email (опционально)</label>
                    <input type="text" name="email" placeholder="ivan@your-domain.com">
                </div>
                <button type="submit">Добавить</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 20px;">👥 Пользователи (<?= count($users) ?>)</h2>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Имя</th>
                        <th>Email</th>
                        <th>UUID</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="users-list">
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><code style="font-size: 11px;"><?= htmlspecialchars($user['uuid']) ?></code></td>
                        <td><?= htmlspecialchars($user['date']) ?></td>
                        <td>
                            <div class="user-actions">
                                <button class="btn-small copy-btn" onclick="copyConfig('<?= $user['uuid'] ?>', '<?= $user['username'] ?>', 'vision')">Vision</button>
                                <button class="btn-small copy-btn" onclick="copyConfig('<?= $user['uuid'] ?>', '<?= $user['username'] ?>', 'ws')">WS</button>
                                <button class="btn-small" onclick="showQR('<?= $user['uuid'] ?>', '<?= $user['username'] ?>', 'vision')">QR Vision</button>
                                <button class="btn-small" onclick="showQR('<?= $user['uuid'] ?>', '<?= $user['username'] ?>', 'ws')">QR WS</button>
                                <button class="btn-small btn-danger" onclick="deleteUser('<?= $user['username'] ?>')">Удалить</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Добавление пользователя
        document.getElementById('add-user-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById('message');
            
            try {
                const response = await fetch('/admin/api.php?action=add_user', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    messageDiv.innerHTML = '<div class="message error">' + data.error + '</div>';
                } else {
                    messageDiv.innerHTML = '<div class="message success">Пользователь успешно добавлен!</div>';
                    this.reset();
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (error) {
                messageDiv.innerHTML = '<div class="message error">Ошибка: ' + error.message + '</div>';
            }
        });

        // Удаление пользователя
        async function deleteUser(username) {
            if (!confirm('Удалить пользователя ' + username + '?')) return;
            
            const formData = new FormData();
            formData.append('username', username);
            
            try {
                const response = await fetch('/admin/api.php?action=delete_user', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    alert('Ошибка: ' + data.error);
                } else {
                    location.reload();
                }
            } catch (error) {
                alert('Ошибка: ' + error.message);
            }
        }

        // Копирование конфига
        function copyConfig(uuid, username, type) {
            const domain = 'your-domain.com';
            let link;
            
            if (type === 'vision') {
                link = `vless://${uuid}@${domain}:8443?type=tcp&security=tls&sni=${domain}&flow=xtls-rprx-vision#${username}-vision`;
            } else {
                link = `vless://${uuid}@${domain}:443?type=ws&security=tls&path=/ws&host=${domain}#${username}-ws`;
            }
            
            navigator.clipboard.writeText(link).then(() => {
                alert('Конфигурация скопирована в буфер обмена!');
            });
        }

        // Показать QR код
        function showQR(uuid, username, type) {
            type = type || 'vision';
            const width = 400;
            const height = 600;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            window.open(
                '/admin/qr.php?uuid=' + uuid + '&username=' + username + '&type=' + type, 
                '_blank',
                'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top
            );
        }
    </script>
</body>
</html>
