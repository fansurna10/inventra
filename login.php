
<?php
require_once __DIR__ . '/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash, nama_lengkap, role
         FROM users
         WHERE username = ? AND aktif = 1
         LIMIT 1"
    );

    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_success'] = true;

        header('Location: index.php');
        exit;

    } else {
        $error = 'Username atau password tidak sesuai.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login | INVENTRA</title>

<link rel="stylesheet" href="assets/css/style.css">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 25px;
    overflow: hidden;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #063d2c, #087443, #15965b);
    background-size: 200% 200%;
    animation: backgroundMove 10s ease infinite;
}

@keyframes backgroundMove {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.background-circle {
    position: fixed;
    border-radius: 50%;
    background: rgba(255,255,255,.07);
    animation: float 9s ease-in-out infinite;
    pointer-events: none;
}

.circle-one {
    width: 380px;
    height: 380px;
    top: -120px;
    left: -100px;
}

.circle-two {
    width: 300px;
    height: 300px;
    bottom: -100px;
    right: -60px;
    animation-delay: 2s;
}

.circle-three {
    width: 130px;
    height: 130px;
    top: 65%;
    left: 12%;
    animation-delay: 4s;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-30px); }
}

.login-card {
    position: relative;
    z-index: 2;
    width: min(430px, 100%);
    padding: 42px 38px;
    background: rgba(255,255,255,.97);
    border-radius: 22px;
    box-shadow: 0 25px 80px rgba(0,0,0,.25);
    animation: cardEnter .85s cubic-bezier(.2,.8,.2,1) both;
}

@keyframes cardEnter {
    from {
        opacity: 0;
        transform: translateY(40px) scale(.96);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.brand {
    text-align: center;
    margin-bottom: 30px;
}

.brand-logo {
    width: 65px;
    height: 65px;
    margin: auto;
    display: grid;
    place-items: center;
    color: white;
    font-size: 32px;
    font-weight: 800;
    border-radius: 19px;
    background: linear-gradient(135deg,#087443,#20b978);
    box-shadow: 0 10px 25px #08744345;
    animation: logoEnter 1s ease both;
}

@keyframes logoEnter {
    from { opacity: 0; transform: rotate(-15deg) scale(.5); }
    to { opacity: 1; transform: rotate(0) scale(1); }
}

.brand h1 {
    color: #087443;
    font-size: 27px;
    letter-spacing: 2px;
    margin: 15px 0 5px;
}

.brand p {
    color: #84928b;
    font-size: 12px;
    letter-spacing: 1.5px;
    margin: 0;
}

.login-title {
    color: #24352c;
    text-align: center;
    font-size: 22px;
    margin-bottom: 7px;
}

.login-description {
    color: #8a978f;
    text-align: center;
    font-size: 13px;
    margin-bottom: 27px;
}

.form-group {
    margin-bottom: 19px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #34483c;
    margin-bottom: 8px;
}

.input-wrap {
    position: relative;
}

.input-wrap input {
    width: 100%;
    padding: 14px 15px;
    border: 1px solid #dce7e0;
    border-radius: 10px;
    outline: none;
    font-size: 14px;
    transition: .25s;
    background: #fbfdfb;
}

.input-wrap input:focus {
    border-color: #15965b;
    box-shadow: 0 0 0 4px #15965b18;
    background: white;
}

.password-input {
    padding-right: 75px !important;
}

.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: #15965b;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.login-button {
    width: 100%;
    border: none;
    padding: 15px;
    margin-top: 10px;
    border-radius: 10px;
    background: linear-gradient(135deg,#087443,#19a968);
    color: white;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: .3s;
    box-shadow: 0 8px 20px #08744330;
}

.login-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 25px #08744345;
}

.login-button:active {
    transform: scale(.98);
}

.error {
    padding: 12px;
    background: #fff0f0;
    color: #b42318;
    border: 1px solid #ffd4d4;
    border-radius: 9px;
    font-size: 13px;
    margin-bottom: 18px;
    animation: shake .35s ease;
}

@keyframes shake {
    0%,100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.login-footer {
    text-align: center;
    color: #9aa69f;
    font-size: 11px;
    margin-top: 27px;
}

.loading-screen {
    position: fixed;
    inset: 0;
    z-index: 10;
    display: none;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    background: #075638;
    color: white;
}

.loading-screen.active {
    display: flex;
    animation: fadeIn .3s ease;
}

.loading-logo {
    width: 75px;
    height: 75px;
    display: grid;
    place-items: center;
    border: 2px solid rgba(255,255,255,.5);
    border-radius: 20px;
    font-size: 35px;
    font-weight: bold;
    animation: pulse 1s infinite;
}

.loading-screen p {
    font-size: 13px;
    letter-spacing: 2px;
    margin-top: 22px;
}

.loader {
    width: 35px;
    height: 35px;
    margin-top: 10px;
    border: 3px solid #ffffff40;
    border-top-color: white;
    border-radius: 50%;
    animation: spin .8s linear infinite;
}

@keyframes pulse {
    50% { transform: scale(1.1); }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@media(max-width:480px) {
    .login-card {
        padding: 32px 24px;
    }
}
</style>
</head>

<body>

<div class="background-circle circle-one"></div>
<div class="background-circle circle-two"></div>
<div class="background-circle circle-three"></div>

<main class="login-card">

    <div class="brand">
        <div class="brand-logo">I</div>
        <h1>INVENTRA</h1>
        <p>INVENTORY & KANBAN SYSTEM</p>
    </div>

    <h2 class="login-title">Welcome Back</h2>
    <p class="login-description">
        Silakan login untuk mengakses sistem INVENTRA.
    </p>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" autocomplete="on">

        <div class="form-group">
            <label>Username</label>
            <div class="input-wrap">
                <input
                    type="text"
                    name="username"
                    placeholder="Masukkan username"
                    required
                    autofocus
                    autocomplete="username"
                >
            </div>
        </div>

        <div class="form-group">
            <label>Password</label>
            <div class="input-wrap">
                <input
                    type="password"
                    name="password"
                    id="password"
                    class="password-input"
                    placeholder="Masukkan password"
                    required
                    autocomplete="current-password"
                >
                <button
                    type="button"
                    class="toggle-password"
                    id="togglePassword"
                >Lihat</button>
            </div>
        </div>

        <button type="submit" class="login-button" id="loginButton">
            Masuk ke Sistem →
        </button>

    </form>

    <div class="login-footer">
        © 2026 INVENTRA. All Rights Reserved.
    </div>

</main>

<div class="loading-screen" id="loadingScreen">
    <div class="loading-logo">I</div>
    <p>MEMASUKI INVENTRA</p>
    <div class="loader"></div>
</div>

<script>
const password = document.getElementById('password');
const toggle = document.getElementById('togglePassword');

toggle.addEventListener('click', function () {
    if (password.type === 'password') {
        password.type = 'text';
        toggle.textContent = 'Sembunyikan';
    } else {
        password.type = 'password';
        toggle.textContent = 'Lihat';
    }
});

document.getElementById('loginForm').addEventListener('submit', function () {
    document.getElementById('loadingScreen').classList.add('active');

    const button = document.getElementById('loginButton');
    button.disabled = true;
});
</script>

</body>
</html>
