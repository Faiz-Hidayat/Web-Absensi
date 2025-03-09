<?php
require_once '../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$error = '';
$success = '';

// Get admin data
$sql = "SELECT * FROM admin WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header("Location: ../logout.php");
    exit();
}

// Process form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = $_POST['username'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'];

    try {
        $conn->beginTransaction();

        // Check if username is taken by another admin
        $check_sql = "SELECT COUNT(*) FROM admin WHERE username = :username AND id != :id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute(['username' => $username, 'id' => $_SESSION['admin_id']]);

        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("Username sudah digunakan oleh admin lain");
        }

        // Check if email is taken by another admin
        $check_sql = "SELECT COUNT(*) FROM admin WHERE email = :email AND id != :id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute(['email' => $email, 'id' => $_SESSION['admin_id']]);

        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("Email sudah digunakan oleh admin lain");
        }

        // Process photo upload if any
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($_FILES['foto']['type'], $allowed_types)) {
                throw new Exception("Format file tidak didukung. Gunakan JPG atau PNG");
            }

            $max_size = 2 * 1024 * 1024; // 2MB
            if ($_FILES['foto']['size'] > $max_size) {
                throw new Exception("Ukuran file terlalu besar. Maksimum 2MB");
            }

            $upload_dir = '../../uploads/admin/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $_SESSION['admin_id'] . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $foto_path = 'uploads/admin/' . $filename;

                // Update photo in session
                $_SESSION['admin_photo'] = $foto_path;
            } else {
                throw new Exception("Gagal mengunggah foto");
            }
        } else {
            $foto_path = $admin['foto_profil']; // Changed from 'foto' to 'foto_profil'
        }

        // Update admin profile
        $sql = "UPDATE admin SET 
                username = :username, 
                nama_lengkap = :nama_lengkap, 
                email = :email, 
                foto_profil = :foto_profil  /* Changed from 'foto' to 'foto_profil' */
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'nama_lengkap' => $nama_lengkap,
            'email' => $email,
            'foto_profil' => $foto_path,  // Changed from 'foto' to 'foto_profil'
            'id' => $_SESSION['admin_id']
        ]);

        // Update session name
        $_SESSION['admin_name'] = $nama_lengkap;

        // Log activity
        $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                VALUES ('admin', :admin_id, 'update', :description)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'admin_id' => $_SESSION['admin_id'],
            'description' => "Admin mengubah profil"
        ]);

        $conn->commit();
        $success = "Profil berhasil diperbarui";

        // Refresh admin data
        $sql = "SELECT * FROM admin WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field password harus diisi.";
    } else if ($new_password !== $confirm_password) {
        $error = "Password baru dan konfirmasi password tidak cocok.";
    } else {
        try {
            // First check if current password is correct
            $sql = "SELECT password FROM admin WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['id' => $_SESSION['admin_id']]);
            $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // For this simplified version, we're comparing passwords directly
            // In a real application, you should use password_verify with hashed passwords
            if ($admin_data && $admin_data['password'] === $current_password) {
                // Start transaction before updating
                $conn->beginTransaction();

                // Update password
                $sql = "UPDATE admin SET password = :password WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'password' => $new_password,
                    'id' => $_SESSION['admin_id']
                ]);

                // Log activity
                $sql = "INSERT INTO activity_log (user_type, user_id, activity_type, description) 
                        VALUES ('admin', :user_id, 'update', :description)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'user_id' => $_SESSION['admin_id'],
                    'description' => "Admin mengubah password"
                ]);

                // Commit transaction
                $conn->commit();

                $success = "Password berhasil diubah";
            } else {
                $error = "Password saat ini tidak benar.";
            }
        } catch (Exception $e) {
            // Only rollback if there's an active transaction
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin - SMKN 40 Jakarta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(147, 51, 234, 0.3);
        }

        body {
            background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%);
        }

        .menu-active {
            background: linear-gradient(to right, rgba(147, 51, 234, 0.2), rgba(147, 51, 234, 0.05));
            border-left: 4px solid #9333ea;
        }

        /* Responsive and animation styles */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.3s ease-out forwards;
        }

        /* Mobile responsive styles */
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }

        .mobile-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease-in-out;
        }

        /* Responsive form styles */
        @media (max-width: 640px) {
            .profile-photo {
                width: 100px;
                height: 100px;
            }

            .form-container {
                padding: 1rem;
            }

            .password-grid {
                grid-template-columns: 1fr;
            }

            /* Better touch targets for mobile */
            .mobile-touch-target {
                min-height: 44px;
            }

            /* Improved spacing for mobile forms */
            .mobile-form-spacing>div {
                margin-bottom: 1.5rem;
            }

            /* Fix file input on mobile */
            .file-input-container {
                flex-direction: column;
                align-items: stretch;
            }

            .file-input-container button {
                margin-top: 0.5rem;
                width: 100%;
                justify-content: center;
            }
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;
            /* IE and Edge */
            scrollbar-width: none;
            /* Firefox */
        }

        /* Button hover effects */
        .btn-hover-effect {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-hover-effect:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.15);
        }
    </style>
</head>

<body class="min-h-screen text-white bg-fixed">
    <!-- Mobile Overlay - only visible when sidebar is open on mobile -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

    <!-- Side Navigation -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 glass-effect border-r border-purple-900/30 z-50 sidebar-transition -translate-x-full lg:translate-x-0">
        <div class="flex items-center justify-between p-4 lg:p-6 border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <img src="../../assets/default/logo-smk40.png" alt="SMKN 40" class="h-8 lg:h-10 w-auto">
                <div>
                    <h1 class="font-semibold text-sm lg:text-base">SMKN 40</h1>
                    <p class="text-xs text-gray-400">Sistem Absensi</p>
                </div>
            </div>
            <!-- Close sidebar button - only visible on mobile -->
            <button class="text-gray-400 hover:text-white lg:hidden" onclick="toggleSidebar()">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <nav class="p-4 space-y-2 overflow-y-auto no-scrollbar" style="max-height: calc(100vh - 80px);">
            <a href="../dashboard/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="../absensi/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-calendar-check"></i>
                <span>Absensi</span>
            </a>
            <a href="../siswa/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-users"></i>
                <span>Data Siswa</span>
            </a>
            <a href="../laporan/" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-purple-500/10 transition-colors">
                <i class="fas fa-file-alt"></i>
                <span>Laporan</span>
            </a>
            <a href="index.php" class="flex items-center gap-3 text-white/90 p-3 rounded-lg menu-active">
                <i class="fas fa-user-cog text-purple-500"></i>
                <span>Profil</span>
            </a>
            <a href="../logout.php" class="flex items-center gap-3 text-gray-400 p-3 rounded-lg hover:bg-red-500/10 hover:text-red-500 transition-colors mt-10">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 transition-all duration-300">
        <!-- Mobile Header -->
        <div class="lg:hidden bg-gray-900/60 backdrop-blur-lg sticky top-0 z-30 px-4 py-3 flex items-center justify-between border-b border-purple-900/30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="text-white p-2 -ml-2 rounded-lg hover:bg-gray-800/60" aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <img src="../../assets/default/logo-smk40.png" alt="SMKN 40" class="h-8 w-auto">
            </div>
            <div class="flex items-center gap-3">
                <span id="current-time-mobile" class="text-sm font-medium hidden sm:block"></span>
                <?php
                $photo_path = !empty($admin['foto_profil']) ? $admin['foto_profil'] : 'assets/default/avatar.png';
                ?>
                <img src="../../<?= $photo_path ?>" alt="Profile" class="h-8 w-8 rounded-full object-cover border border-purple-500/50">
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-xl lg:text-2xl font-bold mb-4 lg:mb-6 flex items-center">
                    <i class="fas fa-user-circle text-purple-500 mr-3 hidden sm:inline"></i>
                    Profil Admin
                </h1>

                <?php if ($success): ?>
                    <div class="bg-green-500/10 border border-green-500/30 text-green-500 rounded-lg p-3 lg:p-4 mb-4 lg:mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-2 lg:mr-3"></i>
                        <p class="text-sm"><?= $success ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-500 rounded-lg p-3 lg:p-4 mb-4 lg:mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2 lg:mr-3"></i>
                        <p class="text-sm"><?= $error ?></p>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                    <!-- Profile Summary - Improved UI -->
                    <div class="glass-effect rounded-xl overflow-hidden">
                        <!-- Profile Header with Gradient -->
                        <div class="bg-gradient-to-r from-purple-900/50 to-indigo-900/50 p-6 relative">
                            <!-- Profile Photo with Enhanced Styling -->
                            <div class="w-24 h-24 sm:w-32 sm:h-32 mx-auto rounded-full overflow-hidden border-4 border-white/20 shadow-lg relative z-10 profile-photo">
                                <?php
                                // Fix the image path issue
                                $photo_path = !empty($admin['foto_profil']) ? $admin['foto_profil'] : 'assets/default/avatar.png';
                                ?>
                                <img src="../../<?= $photo_path ?>" alt="Admin Profile" class="w-full h-full object-cover">
                            </div>

                            <!-- Decorative Elements -->
                            <div class="absolute top-0 left-0 w-full h-full opacity-20 hidden sm:block">
                                <div class="absolute top-4 left-4 w-12 h-12 rounded-full border-2 border-white/20"></div>
                                <div class="absolute bottom-8 right-8 w-16 h-16 rounded-full border-2 border-white/10"></div>
                                <div class="absolute top-1/2 right-4 w-8 h-8 rounded-full bg-purple-500/20"></div>
                            </div>
                        </div>

                        <!-- Profile Info with Better Typography -->
                        <div class="p-4 sm:p-6 text-center">
                            <h2 class="text-lg sm:text-xl font-bold text-white mb-1"><?= htmlspecialchars($admin['nama_lengkap']) ?></h2>
                            <p class="inline-block px-3 py-1 bg-purple-500/10 text-purple-400 rounded-full text-xs font-medium mb-3">
                                Administrator
                            </p>

                            <div class="space-y-3 mt-4">
                                <div class="flex items-center justify-center text-gray-300">
                                    <i class="fas fa-user-circle text-purple-400 mr-2"></i>
                                    <span><?= htmlspecialchars($admin['username']) ?></span>
                                </div>

                                <div class="flex items-center justify-center text-gray-300">
                                    <i class="fas fa-envelope text-purple-400 mr-2"></i>
                                    <span class="text-sm break-all"><?= htmlspecialchars($admin['email']) ?></span>
                                </div>

                                <div class="pt-4 mt-4 border-t border-gray-800/50">
                                    <div class="flex items-center justify-center text-gray-400">
                                        <i class="fas fa-clock text-purple-400 mr-2"></i>
                                        <span class="text-sm">
                                            Terakhir login:<br>
                                            <span class="font-medium text-white">
                                                <?php
                                                // Properly handle the last_login field with better formatting
                                                if (!empty($admin['last_login'])) {
                                                    echo date('d F Y, H:i', strtotime($admin['last_login']));
                                                } else {
                                                    echo "<span class='text-gray-500'>Belum tercatat</span>";
                                                }
                                                ?>
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Profile Actions -->
                            <div class="mt-6">
                                <button type="button" onclick="document.getElementById('foto').click();"
                                    class="px-4 py-2.5 bg-purple-600/30 hover:bg-purple-600/50 rounded-lg text-sm 
                                        text-purple-300 transition-colors w-full flex items-center justify-center
                                        mobile-touch-target btn-hover-effect">
                                    <i class="fas fa-camera mr-2"></i> Ubah Foto
                                </button>
                            </div>
                        </div>

                        <!-- Account Stats -->
                        <div class="grid grid-cols-2 divide-x divide-gray-800/50 border-t border-gray-800/50">
                            <div class="p-4 text-center">
                                <span class="text-gray-400 text-xs block mb-1">Peran</span>
                                <span class="text-white font-medium">Admin</span>
                            </div>
                            <div class="p-4 text-center">
                                <span class="text-gray-400 text-xs block mb-1">Status</span>
                                <span class="text-green-400 font-medium flex items-center justify-center">
                                    <i class="fas fa-circle text-xs mr-1"></i> Aktif
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile Form -->
                    <div class="glass-effect rounded-xl p-4 lg:p-6 lg:col-span-2 form-container">
                        <h3 class="font-semibold text-lg mb-4 flex items-center">
                            <i class="fas fa-user-edit text-purple-500 mr-2"></i>
                            Edit Profil
                        </h3>
                        <form method="POST" enctype="multipart/form-data" class="mobile-form-spacing">
                            <div class="mb-4">
                                <label for="username" class="block text-sm text-gray-400 mb-2">Username</label>
                                <input type="text" id="username" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 mobile-touch-target">
                            </div>

                            <div class="mb-4">
                                <label for="nama_lengkap" class="block text-sm text-gray-400 mb-2">Nama Lengkap</label>
                                <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($admin['nama_lengkap']) ?>" required
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 mobile-touch-target">
                            </div>

                            <div class="mb-4">
                                <label for="email" class="block text-sm text-gray-400 mb-2">Email</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required
                                    class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 mobile-touch-target">
                            </div>

                            <div class="mb-6">
                                <label for="foto" class="block text-sm text-gray-400 mb-2">Foto Profil</label>
                                <input type="file" id="foto" name="foto" accept="image/*" class="hidden">
                                <div class="file-input-container flex">
                                    <div class="relative flex-grow">
                                        <input type="text" readonly placeholder="Pilih foto..."
                                            class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white cursor-pointer mobile-touch-target"
                                            id="file-name" onclick="document.getElementById('foto').click();">
                                        <div class="absolute inset-y-0 bottom-2 right-0 flex items-center">
                                            <button type="button" onclick="document.getElementById('foto').click();"
                                                class="h-full px-3 bg-purple-600 hover:bg-purple-700 rounded-r-lg text-white text-sm transition-colors">
                                                Browse
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">JPG atau PNG, maks. 2MB</p>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" name="update_profile" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium text-white transition-colors mobile-touch-target btn-hover-effect">
                                    <i class="fas fa-save mr-2"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password Form -->
                    <div class="glass-effect rounded-xl p-4 lg:p-6 lg:col-span-3 form-container">
                        <h3 class="font-semibold text-lg mb-4 flex items-center">
                            <i class="fas fa-lock text-purple-500 mr-2"></i>
                            Ubah Password
                        </h3>

                        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 password-grid mobile-form-spacing">
                            <div>
                                <label for="current_password" class="block text-sm text-gray-400 mb-2">Password Saat Ini</label>
                                <div class="relative">
                                    <input type="password" id="current_password" name="current_password" required
                                        class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 pr-10 mobile-touch-target">
                                    <button type="button" onclick="togglePassword('current_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 p-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label for="new_password" class="block text-sm text-gray-400 mb-2">Password Baru</label>
                                <div class="relative">
                                    <input type="password" id="new_password" name="new_password" required
                                        class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 pr-10 mobile-touch-target">
                                    <button type="button" onclick="togglePassword('new_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 p-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Minimal 6 karakter</p>
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-sm text-gray-400 mb-2">Konfirmasi Password</label>
                                <div class="relative">
                                    <input type="password" id="confirm_password" name="confirm_password" required
                                        class="w-full bg-gray-800/50 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-purple-500 pr-10 mobile-touch-target">
                                    <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 p-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="md:col-span-3 flex justify-end mt-2">
                                <button type="submit" name="change_password" class="px-6 py-2.5 bg-yellow-600 hover:bg-yellow-700 rounded-lg font-medium text-white 
                                transition-colors mobile-touch-target w-full md:w-auto btn-hover-effect flex items-center justify-center">
                                    <i class="fas fa-key mr-2"></i> Ubah Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Back button for mobile -->
                <div class="mt-6 flex justify-center lg:hidden">
                    <a href="../dashboard/" class="inline-flex items-center justify-center px-4 py-2.5 bg-gray-700 hover:bg-gray-600 rounded-lg text-white transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('foto').addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'Tidak ada file dipilih';
            document.getElementById('file-name').value = fileName;

            // Add file validation for mobile
            validateFile(this.files[0]);
        });

        function validateFile(file) {
            if (!file) return;

            // Check file size (max 2MB)
            const maxSize = 2 * 1024 * 1024; // 2MB
            if (file.size > maxSize) {
                alert('Ukuran file terlalu besar. Maksimum ukuran file adalah 2MB.');
                document.getElementById('foto').value = '';
                document.getElementById('file-name').value = 'Tidak ada file dipilih';
                return false;
            }

            // Check file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!validTypes.includes(file.type)) {
                alert('Format file tidak valid. Gunakan JPG atau PNG.');
                document.getElementById('foto').value = '';
                document.getElementById('file-name').value = 'Tidak ada file dipilih';
                return false;
            }

            return true;
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.firstElementChild;

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Add mobile sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');

            if (sidebar.classList.contains('-translate-x-full')) {
                // Open sidebar
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            } else {
                // Close sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        // Update time for mobile view
        function updateMobileTime() {
            const mobileTimeElement = document.getElementById('current-time-mobile');
            if (mobileTimeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
                mobileTimeElement.textContent = timeString;
            }
        }

        // Add mobile time updater
        setInterval(updateMobileTime, 60000); // Update every minute
        updateMobileTime(); // Initial call

        // Make sure sidebar closes when pressing escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close sidebar on mobile
                if (window.innerWidth < 1024) { // lg breakpoint in Tailwind
                    const sidebar = document.getElementById('sidebar');
                    if (!sidebar.classList.contains('-translate-x-full')) {
                        toggleSidebar();
                    }
                }
            }
        });

        // Better file input UI for touch devices
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
            const fileInputContainer = document.querySelector('.file-input-container');
            const browseButton = fileInputContainer.querySelector('button');

            // Remove absolute positioning for better touch experience
            browseButton.classList.remove('absolute', 'right-2', 'top-1/2', 'transform', '-translate-y-1/2');
            fileInputContainer.classList.add('flex', 'flex-col', 'space-y-2');
        }

        // Better touch handling for file input
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
            // Make touch targets bigger without changing layout
            const fileInputField = document.getElementById('file-name');
            if (fileInputField) {
                fileInputField.classList.add('py-3');
            }

            // Ensure the buttons are more tappable
            const buttons = document.querySelectorAll('button');
            buttons.forEach(button => {
                if (!button.classList.contains('absolute')) {
                    button.classList.add('py-3');
                }
            });
        }

        // Fix viewport height issues on mobile browsers
        function setMobileHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }

        window.addEventListener('resize', setMobileHeight);
        setMobileHeight();
    </script>
</body>

</html>