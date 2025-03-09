<?php
require_once '../config/database.php';

if (isset($_SESSION['siswa_id'])) {
    header("Location: dashboard/");
    exit();
}

$error = '';

// Process the login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis = $_POST['nis'];
    $password = $_POST['password'];

    // Validate inputs
    if (empty($nis) || empty($password)) {
        $error = 'NIS dan password tidak boleh kosong.';
    } else {
        // Check the NIS in the database
        $sql = "SELECT * FROM siswa WHERE nis = :nis";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['nis' => $nis]);
        $siswa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($siswa) {
            // Verify the password (in a real app, use password_verify with hashed passwords)
            if ($password === $siswa['password']) {
                // Set session variables
                $_SESSION['siswa_id'] = $siswa['id'];
                $_SESSION['siswa_nis'] = $siswa['nis'];
                $_SESSION['siswa_name'] = $siswa['nama_lengkap'];
                $_SESSION['siswa_kelas'] = $siswa['kelas'];
                $_SESSION['siswa_jurusan'] = $siswa['jurusan'];
                $_SESSION['siswa_email'] = $siswa['email'];
                $_SESSION['siswa_photo'] = $siswa['foto_profil'];

                // Record login activity
                $log_sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                           VALUES ('siswa', :user_id, 'login', :description)";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->execute([
                    'user_id' => $siswa['id'],
                    'description' => "Siswa {$siswa['nama_lengkap']} login ke sistem"
                ]);

                // Redirect to dashboard
                header("Location: dashboard/");
                exit();
            } else {
                $error = 'Password yang Anda masukkan salah.';
            }
        } else {
            $error = 'NIS tidak ditemukan.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Siswa - SMKN 40 Jakarta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(147, 51, 234, 0.3);
        }

        /* Fix white background in autofill */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: white;
            -webkit-box-shadow: 0 0 0px 1000px #1F2937 inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        body {
            background-image: url('../assets/default/bg-pattern.png');
            background-repeat: repeat;
        }

        .animated-gradient {
            background: linear-gradient(-45deg, #6941c6, #9333ea, #4338ca, #7e22ce);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }

        @keyframes gradientBG {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .login-btn {
            background-image: linear-gradient(to right, #8b5cf6, #6d28d9, #5b21b6);
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background-image: linear-gradient(to right, #7c3aed, #6d28d9, #4c1d95);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(124, 58, 237, 0.6);
        }
    </style>
</head>

<body class="bg-gray-900 min-h-screen flex items-center justify-center">
    <!-- Purple Gradient Overlay -->
    <div class="fixed inset-0 bg-gradient-to-br from-indigo-900/50 to-purple-900/50 pointer-events-none"></div>

    <!-- Grid Pattern Overlay -->
    <div class="fixed inset-0 opacity-20 pointer-events-none bg-[url('../assets/default/grid-pattern.svg')]"></div>

    <div class="max-w-md w-full mx-4 relative z-10">
        <!-- Login Card -->
        <div class="mb-8 text-center">
            <!-- School Logo -->
            <img src="../assets/default/logo-smk40.png" alt="SMKN 40 Jakarta"
                class="h-24 mx-auto drop-shadow-lg">

            <!-- Animated Badge -->
            <div class="inline-block mt-4 mb-2 animated-gradient text-white py-1 px-4 rounded-full text-xs font-medium tracking-wider">
                SISTEM ABSENSI SISWA
            </div>

            <h2 class="text-3xl font-bold text-white mt-2">Login Siswa</h2>
            <p class="text-gray-300 mt-2">Masuk ke akun siswa Anda</p>
        </div>

        <!-- Login Form -->
        <div class="glass-effect rounded-2xl shadow-2xl p-8 border border-purple-500/30 shadow-purple-900/20">
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 px-4 py-3 rounded-lg relative mb-6 flex items-center" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p class="text-sm"><?= $error ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">
                        <i class="fas fa-id-card text-purple-500 mr-2"></i>NIS
                    </label>
                    <div class="relative">
                        <input type="text" name="nis"
                            class="w-full px-4 py-3 rounded-lg bg-gray-800/80 border border-purple-500/30 text-white 
                            focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 
                            transition-all duration-300 placeholder-gray-500"
                            placeholder="Masukkan NIS" autofocus>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-300 text-sm font-medium mb-2">
                        <i class="fas fa-lock text-purple-500 mr-2"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="password"
                            class="w-full px-4 py-3 rounded-lg bg-gray-800/80 border border-purple-500/30 text-white 
                            focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 
                            transition-all duration-300 placeholder-gray-500"
                            placeholder="Masukkan password">
                        <button type="button" onclick="togglePassword()"
                            class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-purple-400 focus:outline-none">
                            <i class="fas fa-eye text-lg" id="toggleIcon"></i>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Default password: siswa_[NIS]</p>
                </div>

                <button type="submit"
                    class="w-full login-btn text-white font-medium py-3 px-4 rounded-lg
                    focus:outline-none focus:ring-2 focus:ring-purple-500/30 flex items-center justify-center
                    shadow-xl shadow-purple-900/20">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-400">
                <p>Tidak bisa login? Silakan hubungi Admin</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-400 text-sm">
            <p>&copy; <?= date('Y') ?> SMKN 40 Jakarta</p>
            <p class="mt-1 text-gray-500">Sistem Informasi Absensi Siswa</p>
        </div>

        <!-- Admin Link -->
        <div class="text-center mt-4">
            <a href="../admin/login.php" class="text-xs text-purple-400 hover:text-purple-300 transition-colors flex items-center justify-center">
                <i class="fas fa-user-shield mr-1"></i> Admin Login
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>