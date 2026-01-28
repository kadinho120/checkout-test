<?php
session_start();

// Credentials
$USER_CORRETO = 'kadinho120';
$PASS_CORRETO = 'Houshiengi22@';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($_SESSION['logged_in'] ?? false) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === $USER_CORRETO && $pass === $PASS_CORRETO) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = $user;
        header("Location: index.php");
        exit;
    } else {
        $error = "Credenciais inválidas.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Login - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 h-screen flex items-center justify-center">
    <div class="bg-gray-800 p-8 rounded-xl shadow-2xl w-full max-w-md border border-gray-700">
        <h1 class="text-2xl font-bold text-white text-center mb-6">Acesso Administrativo</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500 text-red-500 px-4 py-2 rounded mb-4 text-sm text-center">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-gray-300 text-sm font-medium mb-1">Usuário</label>
                <input type="text" name="username"
                    class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    required>
            </div>
            <div>
                <label class="block text-gray-300 text-sm font-medium mb-1">Senha</label>
                <input type="password" name="password"
                    class="w-full bg-gray-900 border border-gray-600 rounded-lg px-4 py-3 text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    required>
            </div>
            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
                Entrar
            </button>
        </form>
    </div>
</body>

</html>