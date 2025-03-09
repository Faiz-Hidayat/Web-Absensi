<?php
require_once '../config/database.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard/");
    exit();
}

$error = '';

// Process the login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate username and password
    if (empty($username) || empty($password)) {
        $error = 'Username dan password tidak boleh kosong.';
    } else {
        // Check the username in the database
        $sql = "SELECT * FROM admin WHERE username = :username";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            // Verify the password (in a real app, use password_verify with hashed passwords)
            if ($password === $admin['password']) {
                // Set session variables
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['nama_lengkap'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_photo'] = $admin['foto_profil']; // Changed from 'foto' to 'foto_profil'
                $_SESSION['admin_last_login'] = $admin['last_login'];

                // Record this login time
                $current_time = date('Y-m-d H:i:s');
                $update_sql = "UPDATE admin SET last_login = :login_time WHERE id = :admin_id";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->execute([
                    'login_time' => $current_time,
                    'admin_id' => $admin['id']
                ]);

                // Store the current login time in session
                $_SESSION['admin_last_login'] = $current_time;

                // Log activity
                $log_sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                            VALUES ('admin', :user_id, 'login', :description)";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->execute([
                    'user_id' => $admin['id'],
                    'description' => "Admin {$admin['nama_lengkap']} login ke sistem"
                ]);

                // Redirect to dashboard
                header("Location: dashboard/index.php");
                exit();
            } else {
                $error = 'Password yang Anda masukkan salah.';
            }
        } else {
            $error = 'Username tidak ditemukan.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - SMKN 40 Jakarta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            /* Darker background */
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
    </style>
</head>

<body class="bg-gray-900 min-h-screen flex items-center justify-center bg-[url('../assets/default/bg-pattern.png')] bg-repeat">
    <!-- Purple Gradient Overlay -->
    <div class="fixed inset-0 bg-gradient-to-br from-purple-900/50 to-gray-900/50 pointer-events-none"></div>

    <div class="max-w-md w-full mx-4 relative z-10">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <img src="../assets/default/logo-smk40.png" alt="SMKN 40"
                class="h-24 mx-auto mb-4 drop-shadow-[0_0_15px_rgba(147,51,234,0.5)]">
            <h2 class="text-3xl font-bold text-white mb-2 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-purple-600">
                Login Admin
            </h2>
            <p class="text-gray-400">Sistem Absensi SMKN 40 Jakarta</p>
        </div>

        <!-- Login Form -->
        <div class="glass-effect rounded-xl shadow-2xl p-8 shadow-purple-900/20">
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 px-4 py-3 rounded-lg relative mb-6 flex items-center" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p class="text-sm"><?= $error ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-4">
                    <div class="group">
                        <label class="text-gray-300 text-sm font-medium mb-2 block opacity-90">
                            <i class="fas fa-user text-purple-500 mr-2"></i>Username
                        </label>
                        <input type="text" name="username" required
                            class="w-full px-5 py-4 rounded-lg bg-gray-800/80 border border-purple-500/30 text-white 
                            focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 
                            transition-all duration-300 placeholder-gray-500"
                            placeholder="Masukkan username">
                    </div>

                    <div class="group">
                        <label class="text-gray-300 text-sm font-medium mb-2 block opacity-90">
                            <i class="fas fa-lock text-purple-500 mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" name="password" required id="password"
                                class="w-full px-5 py-4 rounded-lg bg-gray-800/80 border border-purple-500/30 text-white 
                                focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20
                                transition-all duration-300 placeholder-gray-500"
                                placeholder="Masukkan password">
                            <button type="button" onclick="togglePassword()"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-purple-500
                                transition-colors duration-300">
                                <i class="fas fa-eye text-lg" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-purple-600 to-purple-800 text-white font-medium py-4 px-4 
                    rounded-lg transition duration-300 hover:opacity-90 transform hover:-translate-y-0.5
                    focus:outline-none focus:ring-2 focus:ring-purple-500/20 flex items-center justify-center
                    shadow-lg shadow-purple-900/30">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-400 text-sm">
            <p>&copy; <?= date('Y') ?> SMKN 40 Jakarta</p>
            <p class="mt-1 text-gray-500">Sistem Informasi Absensi Siswa</p>
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