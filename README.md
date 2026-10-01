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
- Tandai topik sebagai **berbayar di aplikasi mobile** (beli sekali, bukan langganan) — situs web
  tetap gratis untuk topik yang sama; kelola transaksi, setujui/tolak/refund, dan beri akses manual
  lewat menu **Pembelian**
- Manajemen pengguna aplikasi mobile (aktif/nonaktifkan akun, verifikasi manual) lewat menu
  **Pengguna Aplikasi**
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

Cara tercepat — **Impor Massal** (untuk banyak berkas sekaligus):
1. Di Google Drive, buka folder per kelas (mis. "Kelas 4 Done") yang berisi banyak berkas `.html`
2. Pilih semua berkas di dalamnya, klik **Download** — Google Drive otomatis menzipkannya
3. Di admin, buka **Impor Massal**, pilih Jenjang/Mata Pelajaran/Kelas tujuan, unggah zip tersebut
4. Semua berkas HTML di dalam zip otomatis terdaftar sebagai **draf** — tinjau judulnya sebentar di menu Latihan & Materi, lalu terbitkan

Cara satuan (untuk satu berkas):
1. Unduh berkas `.html` dari Google Drive ke komputer Anda
2. Di admin, buka **Latihan & Materi → Tambah Baru**
3. Pilih Jenjang / Mata Pelajaran / Kelas yang sesuai (buat dulu jika belum ada)
4. Unggah berkas `.html`, isi judul & deskripsi, atur status ke **Terbit**

**Catatan untuk Impor Massal:** membutuhkan ekstensi PHP `zip` aktif di server (umumnya sudah aktif secara default di hosting shared PHP modern). Jika hosting Anda membatasi `upload_max_filesize` / `post_max_size` di bawah 80MB, sesuaikan `php.ini` atau pecah folder besar menjadi beberapa zip lebih kecil (per kelas biasanya sudah cukup kecil).

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

## API Mobile (`/api/v1/`)

Untuk aplikasi mobile (`ruang-sinau-mobile/`, React Native/Expo), situs ini menyediakan REST API
JSON di `/api/v1/`. Akun pengguna aplikasi (`app_users`) terpisah dari `admin_users` — dikelola
lewat menu admin **Pengguna Aplikasi**, atau didaftarkan sendiri lewat aplikasi mobile.

**Sebelum dipakai, wajib diisi dulu di `config/config.php`:**
- `JWT_SECRET` — string acak panjang (mis. `bin2hex(random_bytes(32))`), jangan pernah dibagikan.
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM_EMAIL` — untuk kirim kode OTP
  verifikasi email & reset password. Selama `SMTP_HOST` kosong (mode `development`), email tidak
  benar-benar terkirim — isinya (termasuk kode OTP) ditulis ke `storage/mail_log/*.html` supaya
  alur registrasi/lupa-password tetap bisa ditest.

**Autentikasi**: `Authorization: Bearer <access_token>` (JWT, umur 15 menit). Saat kedaluwarsa,
tukar `refresh_token` (umur 30 hari, disimpan ter-hash & dirotasi tiap dipakai) lewat
`POST /api/v1/auth/refresh`.

**Endpoint auth**: `POST auth/register`, `POST auth/verify-email` (kode OTP 6 digit),
`POST auth/resend-otp`, `POST auth/login`, `POST auth/refresh`, `POST auth/logout`,
`POST auth/forgot-password`, `POST auth/reset-password`.

**Endpoint profil**: `GET/PATCH me` (butuh Bearer token).

**Endpoint konten** (publik, baca saja, mengikuti data yang sama dengan situs web —
hanya status `published`): `GET jenjang`, `GET mapel?jenjang=`, `GET kelas?mapel=&jenjang=`,
`GET topik?kelas=&mapel=&jenjang=`, `GET topik/detail?slug=&kelas=`, `GET search?q=`.

**Pembelian satu-kali per topik (khusus mobile)**: topik yang ditandai berbayar di admin
mengembalikan `berbayar_mobile`, `harga`, dan `sudah_dibeli` di endpoint konten di atas — jika
request menyertakan Bearer token dan pengguna belum membeli, field `konten`/`file_url` pada
`topik/detail` dikosongkan (`null`) supaya app menampilkan layar beli. Alur beli:
`POST pembelian` (body `{"topik_id": ...}`, butuh Bearer token) mencatat transaksi berstatus
`menunggu` konfirmasi admin dari menu **Pembelian**; `GET pembelian` mengambil riwayat pembelian
milik pengguna yang login. Belum terhubung ke payment gateway (Midtrans/Xendit) — konfirmasi
pembayaran masih manual lewat panel admin.

Semua endpoint mengembalikan JSON `{"sukses": true, "data": {...}}` atau
`{"sukses": false, "error": {"kode": "...", "pesan": "..."}}`.

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
