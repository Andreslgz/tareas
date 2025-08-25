<?php
session_start();

$USER = 'admin';
$PASS = 'pr@2025';

// Verificar cookie de acceso persistente
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_login']) && $_COOKIE['remember_login'] === '1') {
    $_SESSION['logged_in'] = true;
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['username'] === $USER && $_POST['password'] === $PASS) {
        $_SESSION['logged_in'] = true;

        // Si eligió "Recordarme", crear cookie
        if (!empty($_POST['recordarme'])) {
            setcookie('remember_login', '1', time() + (30 * 24 * 60 * 60), "/"); // 30 días
        } else {
            setcookie('remember_login', '', time() - 3600, "/"); // eliminar si existía
        }

        header("Location: index.php");
        exit();
    } else {
        $error = "Credenciales incorrectas.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login | CESCOCONLINE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-100 to-green-300 px-4">

    <div class="w-full max-w-sm bg-white p-6 rounded-2xl shadow-xl">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-green-700">Acceso a Tareas</h1>
            <p class="text-sm text-gray-500">Ingrese sus credenciales</p>
        </div>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                <input type="text" name="username" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Clave</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-400">
            </div>
            <div class="flex items-center">
                <input id="recordarme" name="recordarme" type="checkbox"
                    class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                <label for="recordarme" class="ml-2 block text-sm text-gray-700">
                    Recordarme
                </label>
            </div>
            <button type="submit"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-md shadow">
                Ingresar
            </button>
            <?php if (isset($error)): ?>
                <p class="text-red-600 text-sm text-center mt-2"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
        </form>
    </div>

</body>
</html>