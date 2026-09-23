# Ruang Sinau

Platform latihan soal & materi belajar gratis — dibangun dengan **PHP native** (tanpa framework)
dan **MySQL**. Siapa saja bisa berlatih soal interaktif dari SD sampai SMA, dan pengelola bisa
terus menambahkan latihan soal baru maupun materi belajar lain lewat panel admin.

## Fitur

**Situs publik**
- Jelajahi konten berdasarkan Jenjang → Mata Pelajaran → Kelas → Topik
- Latihan soal interaktif (berkas HTML mandiri, ditampilkan dalam halaman situs)
- Materi belajar dalam format artikel (teks kaya, gambar, tabel)
- Pencarian (full-text search MySQL)
- Desain responsif, cepat, tanpa dependensi JavaScript berat

**Panel admin** (`/admin`)
- Dasbor statistik (jumlah konten, dilihat, draf vs terbit)
- Kelola Jenjang, Mata Pelajaran, Kelas (struktur sepenuhnya dinamis — bukan hanya SD/SMP/SMA)
- Tambah/ubah/hapus Latihan Soal (unggah berkas `.html`) dan Materi (editor teks kaya bawaan)
- Manajemen pengguna admin (peran Admin / Super Admin)
- Log aktivitas (jejak audit setiap perubahan)
- Login dengan proteksi percobaan gagal berulang (brute-force protection)

## Kebutuhan Server

- PHP 8.0 atau lebih baru, dengan ekstensi: `pdo_mysql`, `fileinfo`, `mbstring`
- MySQL 5.7+ / MariaDB 10.3+ (mendukung `FULLTEXT` index pada InnoDB)
- Apache dengan `mod_rewrite` dan `mod_headers` (disarankan, tidak wajib)

## Instalasi

1. **Unggah seluruh isi folder** `ruang-sinau/` ke direktori hosting Anda (mis. `public_html/`).

2. **Buat database** baru di MySQL/phpMyAdmin, lalu impor skema:
   ```
   mysql -u USER -p NAMA_DATABASE < database/schema.sql
   ```
   File ini otomatis membuat seluruh tabel, satu akun admin awal, dan tiga jenjang contoh
   (SD, SMP, SMA).

3. **Salin dan sesuaikan konfigurasi:**
   - Buka `config/config.php`
   - Isi `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` sesuai kredensial hosting Anda
   - Ubah `APP_URL` ke alamat situs Anda yang sebenarnya (tanpa garis miring di akhir)
   - Ubah `APP_ENV` menjadi `'production'` setelah semua diuji berjalan lancar

4. **Pastikan folder unggahan bisa ditulis** oleh PHP:
   ```
   chmod 755 uploads/quizzes uploads/materi
   ```

5. **Login ke panel admin** di `https://domain-anda.com/admin/login.php`
   - Username: `admin`
   - Password: `GantiSegera123!`
   - **Segera ganti password ini** lewat menu Profil Saya setelah login pertama.

6. Mulai tambahkan konten: **Jenjang → Mata Pelajaran → Kelas → Latihan/Materi**, dari menu sidebar admin.

## Struktur Folder

```
ruang-sinau/
├── admin/                 Panel admin (perlu login)
│   └── includes/          Header/footer & bootstrap admin
├── assets/
│   ├── css/                style.css (situs publik), admin.css (panel admin)
│   └── js/
├── config/
│   └── config.php          Kredensial database & pengaturan aplikasi — WAJIB disesuaikan
├── database/
│   └── schema.sql           Skema tabel + data awal
├── includes/                Kode bersama: koneksi DB, fungsi bantu, autentikasi, header/footer publik
├── uploads/
│   ├── quizzes/              Berkas HTML latihan soal yang diunggah admin
│   └── materi/                Gambar yang disisipkan ke materi
├── index.php, jenjang.php, mapel.php, kelas.php, topik.php, katalog.php, cari.php, tentang.php
└── .htaccess
```

## Cara Kerja Latihan Soal

Setiap latihan soal disimpan sebagai **satu berkas HTML mandiri** (self-contained) — persis
seperti berkas latihan soal interaktif yang sudah pernah dibuat sebelumnya (dengan jsPDF untuk
sertifikat, animasi, dsb). Admin cukup mengunggah berkas `.html` itu apa adanya lewat menu
**Latihan & Materi → Tambah Baru**; situs akan menampilkannya dalam bingkai (iframe) di halaman
topik, lengkap dengan judul, breadcrumb, dan navigasi situs di sekelilingnya.

Pendekatan ini dipilih secara sengaja: latihan-latihan yang sudah ada punya visualisasi khusus
per topik (bagan batang, uang-uangan, kisi persen, dsb.) yang ditulis khusus untuk tiap soal.
Mengubahnya menjadi data database akan berisiko merusak visualisasi tersebut. Dengan pendekatan
"unggah berkas HTML", seluruh latihan yang sudah ada bisa langsung dipakai tanpa modifikasi.

**Menambahkan latihan baru dari folder Google Drive Anda:**
1. Unduh berkas `.html` dari Google Drive ke komputer Anda
2. Di admin, buka **Latihan & Materi → Tambah Baru**
3. Pilih Jenjang / Mata Pelajaran / Kelas yang sesuai (buat dulu jika belum ada)
4. Unggah berkas `.html`, isi judul & deskripsi, atur status ke **Terbit**

## Cara Kerja Materi

Materi (bukan latihan soal) ditulis langsung di editor teks kaya bawaan admin — mendukung
heading, paragraf, daftar, tautan, tabel, dan gambar (diunggah lewat tombol 🖼 di toolbar).
Konten materi disanitasi otomatis di sisi server sebelum disimpan, untuk mencegah suntikan
skrip berbahaya (XSS) meski ditulis dalam HTML.

## Menambahkan Jenis Konten Lain

Karena struktur Jenjang/Mata Pelajaran/Kelas dibuat sepenuhnya dinamis (bukan kode yang
di-hardcode), Anda bisa menambahkan jenjang baru di luar SD/SMP/SMA — misalnya "Kuliah",
"Umum", atau "Pelatihan Guru" — langsung dari menu **Kelola Jenjang**, tanpa menyentuh kode
sama sekali.

## Catatan Keamanan

- Semua query database memakai *prepared statement* (PDO) — aman dari SQL injection.
- Password admin di-hash dengan `password_hash()` (bcrypt); tidak pernah disimpan sebagai teks biasa.
- Setiap formulir dilindungi token CSRF.
- Login dibatasi 5 percobaan gagal per 15 menit (per kombinasi username + alamat IP).
- Folder `uploads/` diberi `.htaccess` yang menonaktifkan eksekusi skrip apa pun — berkas di
  sana hanya bisa disajikan sebagai teks/HTML statis, tidak pernah dijalankan sebagai kode server.
- Berkas unggahan divalidasi dari sisi ekstensi **dan** tipe MIME sebenarnya (bukan hanya nama berkas).
- Konten materi disanitasi dengan whitelist tag HTML sebelum disimpan.
- Setiap aksi penting di panel admin (tambah/ubah/hapus, login/logout) tercatat di **Log Aktivitas**.

**Sebelum go-live, pastikan juga:**
- `config/config.php` memakai kredensial database yang kuat dan `APP_ENV` diset `'production'`
- Situs diakses lewat HTTPS (agar cookie sesi terkirim aman)
- Backup rutin untuk database dan folder `uploads/`

## Lisensi & Kepemilikan

Dibangun khusus untuk kebutuhan Anda. Bebas dimodifikasi dan dikembangkan sesuai kebutuhan.
