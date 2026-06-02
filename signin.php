<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: http://'.$_SERVER['HTTP_HOST'].'/project_work/home');
    exit;
}
$error   = $_GET['error']   ?? '';
$success = $_GET['success'] ?? '';
$sistem  = 'http://'.$_SERVER['HTTP_HOST'].'/project_work';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once('config/connection/connection.php');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $st = $conn->prepare("
            SELECT u.*, r.is_admin 
            FROM users u 
            LEFT JOIN roles r ON u.id_role = r.id_role 
            WHERE u.username = ? AND u.is_active = 1 
            LIMIT 1
        ");
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama']     = $user['nama'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['id_role']  = (int)($user['id_role'] ?? 0);
            $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);
            $_SESSION['outlet_id']= $user['outlet_id'] ?? null;
            header("Location: $sistem/home");
            exit;
        }
    }
    header("Location: $sistem/signin?error=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockMate Pro — PT PSY BERKAH INDONESIA</title>
    <meta name="description" content="Sistem Manajemen Inventori & Gudang PT PSY BERKAH INDONESIA">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .bg-image {
            background-image: url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?q=80&w=2070&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            outline: none;
            transition: all 0.2s;
            color: #334155;
        }
        .form-input:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        .btn-primary {
            background-color: #0284c7;
            color: white;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: #0369a1;
        }
    </style>
</head>
<body class="min-h-screen flex bg-slate-50">

    <!-- Left Side: Image Banner -->
    <div class="hidden lg:flex lg:w-7/12 bg-image relative">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md" style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);"></div>
        <div class="relative z-10 flex flex-col justify-between p-12 w-full">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-sky-500 rounded-lg flex items-center justify-center text-white">
                    <i class="fa-solid fa-layer-group text-lg"></i>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">
                    Stock<span class="text-sky-400">Mate</span>
                </h1>
            </div>
            
            <div class="max-w-xl">
                <h2 class="text-4xl font-bold text-white mb-4 leading-tight">
                    Sistem Manajemen Inventori & Gudang
                </h2>
                <p class="text-slate-300 text-lg mb-8">
                    Kelola stok barang, pantau distribusi ke outlet, dan lakukan stock opname dengan akurat dan real-time.
                </p>
                <div class="flex items-center gap-4 text-sm font-medium text-slate-400 uppercase tracking-wider">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-check text-sky-500"></i> Gudang</span>
                    <span class="flex items-center gap-2"><i class="fa-solid fa-check text-sky-500"></i> Outlet</span>
                    <span class="flex items-center gap-2"><i class="fa-solid fa-check text-sky-500"></i> Laporan</span>
                </div>
            </div>
            
            <div class="text-slate-400 text-sm">
                &copy; <?= date('Y') ?> PT PSY BERKAH INDONESIA
            </div>
        </div>
    </div>

    <!-- Right Side: Login Form -->
    <div class="w-full lg:w-5/12 flex flex-col justify-center px-8 sm:px-16 xl:px-24 bg-white relative shadow-2xl z-10">
        
        <div class="w-full max-w-sm mx-auto">
            <!-- Mobile Logo -->
            <div class="flex items-center gap-3 mb-8 lg:hidden">
                <div class="w-10 h-10 bg-sky-600 rounded-lg flex items-center justify-center text-white shadow-lg">
                    <i class="fa-solid fa-layer-group text-lg"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Stock<span class="text-sky-600">Mate</span></h1>
                    <p class="text-xs text-slate-500 font-semibold tracking-wider">PT PSY BERKAH INDONESIA</p>
                </div>
            </div>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-slate-800 mb-2">Selamat Datang</h2>
                <p class="text-slate-500 text-sm">Silakan masuk dengan akun yang telah didaftarkan oleh administrator.</p>
            </div>

            <!-- Alerts -->
            <?php if ($error === '1'): ?>
            <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-6 text-sm">
                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                <span>Username atau password salah. Silakan coba lagi.</span>
            </div>
            <?php endif; ?>
            
            <?php if ($success === 'logout'): ?>
            <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg p-3 mb-6 text-sm">
                <i class="fa-solid fa-circle-check mt-0.5"></i>
                <span>Anda telah berhasil keluar dari sistem.</span>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="<?= $sistem ?>/signin" class="space-y-5" id="loginForm">
                
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5" for="username">Username</label>
                    <div class="relative">
                        <input type="text" name="username" id="username" required
                               placeholder="Masukkan username"
                               class="form-input">
                        <i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5" for="password">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required
                               placeholder="Masukkan password"
                               class="form-input pr-10">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <button type="button" id="togglePass" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-sky-600 transition-colors">
                            <i class="fa-solid fa-eye-slash text-sm" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" id="btnLogin"
                            class="btn-primary w-full py-2.5 px-4 rounded-lg font-semibold flex items-center justify-center gap-2">
                        <i class="fa-solid fa-arrow-right-to-bracket" id="loginIcon"></i>
                        <span id="btnText">Masuk ke Sistem</span>
                    </button>
                </div>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-100 text-center lg:hidden">
                <p class="text-slate-400 text-xs">&copy; <?= date('Y') ?> PT PSY BERKAH INDONESIA</p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('togglePass').addEventListener('click', function() {
            const p = document.getElementById('password');
            const i = document.getElementById('eyeIcon');
            if (p.type === 'password') {
                p.type = 'text';
                i.className = 'fa-solid fa-eye text-sky-600';
            } else {
                p.type = 'password';
                i.className = 'fa-solid fa-eye-slash text-slate-400';
            }
        });

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnLogin');
            const icon = document.getElementById('loginIcon');
            const text = document.getElementById('btnText');
            
            btn.style.opacity = '0.8';
            btn.style.cursor = 'not-allowed';
            icon.className = 'fa-solid fa-circle-notch fa-spin';
            text.textContent = 'Memverifikasi...';
        });
    </script>
</body>
</html>
