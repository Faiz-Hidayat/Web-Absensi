# 📊 Sistem Informasi Absensi Siswa

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Sistem Informasi Absensi Siswa adalah aplikasi berbasis web yang dirancang untuk mempermudah pengelolaan kehadiran siswa di sekolah. Dengan antarmuka yang intuitif dan fitur lengkap, aplikasi ini memungkinkan admin dan siswa untuk mengelola dan memantau absensi secara efisien.

## ✨ Demo

Aplikasi demo dapat diakses di:

-   Portal Admin: [web-absensi.wuaze.com/admin](http://web-absensi.wuaze.com/admin/)
-   Portal Siswa: [web-absensi.wuaze.com/siswa](http://web-absensi.wuaze.com/siswa/)

**Kredensial Login Admin:**

-   Username: `admin`
-   Password: `admin123`

**Kredensial Login Siswa:**

-   Email: `2024002`
-   Password: `siswa_2024002`

## 🚀 Fitur

-   🔐 Sistem autentikasi yang aman untuk admin dan siswa
-   📝 Pengelolaan data absensi harian (hadir, sakit, izin, terlambat, alpha)
-   👨‍🎓 Manajemen data siswa berdasarkan kelas dan jurusan
-   ✅ Siswa dapat melakukan pengajuan absensi dengan bukti pendukung
-   ⚠️ Admin dapat menyetujui atau menolak pengajuan absensi
-   📊 Laporan absensi dengan berbagai filter (tanggal, kelas, jurusan)
-   📱 Desain responsif untuk berbagai ukuran perangkat
-   🔄 Log aktivitas untuk melacak tindakan pengguna dalam sistem
-   👤 Manajemen profil pengguna termasuk foto profil

## 🛠️ Teknologi yang Digunakan

-   **Front-end**: HTML5, CSS3, JavaScript, Bootstrap 5
-   **Back-end**: PHP 8.1+
-   **Database**: MySQL 8.0
-   **Library**: DOMPDF untuk generasi laporan PDF
-   **Framework CSS**: Font Awesome untuk ikon
-   **Koneksi Database**: MySQLi untuk pengelolaan database

## 📋 Persyaratan Sistem

-   PHP 8.0 atau lebih tinggi
-   MySQL 8.0 atau lebih tinggi
-   Web server (Apache/Nginx)
-   Ekstensi PHP: MySQLi, GD, FileInfo

## 📥 Instalasi

1. Clone repositori ini:

    ```bash
    git clone https://github.com/faiz-hidayat/Web-Absensi.git
    ```

2. Pindah ke direktori proyek:

    ```bash
    cd Web-Absensi
    ```

3. Buat database baru:

    ```sql
    CREATE DATABASE absensi_siswa;
    ```

4. Import struktur database dari file `database/database.sql`

5. Konfigurasi koneksi database:

    - Sesuaikan kredensial database pada file `config/database.php`

6. Buat folder `uploads` dengan subfolder yang diperlukan:

    ```bash
    mkdir -p uploads/admin uploads/siswa
    chmod 755 uploads
    ```

7. Akses aplikasi melalui browser:
    ```
    http://localhost/Web-Absensi
    ```

## 💻 Cara Penggunaan

### Login Admin

-   Gunakan username `admin` dan password `admin123` untuk masuk ke panel admin
-   Kelola data siswa, absensi, dan laporan melalui menu yang tersedia

### Login Siswa

-   Siswa menggunakan NIS dan password yang telah diberikan untuk login
-   Siswa dapat mengajukan absensi dan melihat riwayat absensi mereka

### Pengelolaan Absensi

1. **Pengajuan Absensi oleh Siswa**:

    - Siswa login ke portal siswa
    - Pilih status absensi (Hadir, Sakit, Izin)
    - Isi keterangan jika diperlukan
    - Upload bukti pendukung (untuk sakit atau izin)
    - Kirim pengajuan untuk ditinjau oleh admin

2. **Persetujuan Absensi oleh Admin**:

    - Admin menerima notifikasi pengajuan absensi baru
    - Admin dapat melihat detail pengajuan termasuk bukti
    - Admin menyetujui atau menolak pengajuan tersebut

3. **Laporan Absensi**:

    - Admin dapat melihat laporan absensi siswa
    - Filter berdasarkan tanggal, kelas, jurusan, atau status absensi
    - Ekspor laporan dalam format PDF

4. **Manajemen Siswa**:

    - Admin dapat menambah, mengedit, dan menghapus data siswa
    - Mengatur kelas dan jurusan siswa
    - Mengatur akses login siswa

5. **Log Aktivitas**:
    - Admin dapat melihat log aktivitas sistem
    - Memantau tindakan yang dilakukan oleh admin dan siswa

## 📸 Screenshot

![Dashboard Admin](/screenshots/dashboard-admin.png)
![Dashboard Siswa](/screenshots/dashboard-siswa.png)

## 👨‍💻 Pengembang

Dikembangkan oleh [Muhammad Faiz Hidayat](https://github.com/faiz-hidayat)

## 📄 Lisensi

Proyek ini dilisensikan di bawah [MIT License](LICENSE).
