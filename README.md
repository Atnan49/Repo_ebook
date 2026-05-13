# RepoBook - Web Repositori Ebook 📚

RepoBook adalah aplikasi web repositori buku digital (ebook) interaktif yang memungkinkan pengguna untuk membaca, mencari, menyimpan, dan mengunggah koleksi buku dalam format PDF. Sistem ini dilengkapi dengan manajemen persetujuan (moderasi) oleh admin untuk memastikan kualitas konten.

<p align="center">
  <img src="assets/img/dashboard.png" alt="Dashboard Public RepoBook" width="100%">
</p>

## Fitur Utama ✨

- **Katalog Ebook Dinamis**: Menampilkan daftar buku terbaru, terpopuler, dan difilter berdasarkan kategori.
- **Baca PDF Aman**: Sistem *streaming* PDF mandiri yang melindungi file sumber agar tidak dapat diunduh secara langsung tanpa izin.
- **Fitur Bookmark AJAX**: Simpan buku favorit ke perpustakaan pribadi tanpa perlu memuat ulang halaman (*real-time*).
- **Upload Terintegrasi & Cerdas**: Pengguna dapat mengunggah buku beserta cover. File gambar cover (JPG/PNG) akan secara **otomatis dikonversi menjadi format WebP** untuk menghemat penyimpanan dan mempercepat waktu muat.
- **Dashboard Moderasi Admin**: Panel khusus bagi admin untuk meninjau (Menyetujui/Menolak) publikasi buku dari pengguna.
- **Sistem Keamanan**: Autentikasi login/register, sanitasi output (XSS protection), *Prepared Statements* PDO (mencegah SQL Injection), dan token CSRF di setiap formulir.

## Teknologi yang Digunakan 💻

- **Frontend**: HTML5, CSS3 murni (Vanilla), JavaScript (ES6+ & Fetch API)
- **Backend**: PHP 8+ (Native dengan PDO)
- **Database**: MySQL / MariaDB
- **Ekstensi**: PHP GD Library (untuk pemrosesan gambar WebP)

## Cara Instalasi 🚀

1. **Kloning repositori ini** ke dalam direktori server lokal Anda (misal: `htdocs` untuk XAMPP):
   ```bash
   git clone https://github.com/Atnan49/Repo_ebook.git
   ```

2. **Siapkan Database**:
   - Buat database baru bernama `repo_ebook` (atau sesuai keinginan) melalui phpMyAdmin.
   - Import skema awal dari file `database/schema.sql`.
   
   > **Catatan:** Terdapat akun admin bawaan dalam *schema.sql* dengan email `admin@repo.com` dan password `Password`.

3. **Konfigurasi Database**:
   - Buka file `config/database.php`.
   - Sesuaikan konfigurasi (username, password, nama db) jika menggunakan *credentials* selain bawaan default XAMPP (`root` / kosong).

4. **Hak Akses Folder**:
   - Pastikan direktori `storage/pdfs` dan `assets/covers` memiliki hak akses tulis (*write permissions*) agar proses upload berjalan lancar. Folder `storage` otomatis dilindungi file `.htaccess`.

5. **Akses Aplikasi**:
   - Buka web browser dan arahkan ke `http://localhost/Projek/Repo_ebook/public/` (sesuaikan jalur instalasi lokal Anda).

## Struktur Direktori Utama 📂

```text
Repo_ebook/
├── admin/          # Panel kontrol admin & halaman moderasi persetujuan
├── assets/         # File statis: CSS, JavaScript utama (app.js), dan Cover buku
├── config/         # Konfigurasi database Singleton dan path konstanta
├── database/       # File dump schema SQL untuk struktur awal
├── includes/       # Helper PHP, logika Auth, komponen UI Header & Sidebar
├── public/         # Halaman aplikasi yang dapat diakses pengguna umum
└── storage/        # Penyimpanan file PDF privat yang diproteksi (.htaccess)
```

## Kontribusi 🤝

Jika Anda menemukan *bug* atau ingin menambahkan fitur baru, silakan lakukan *Fork* repositori ini dan kirimkan *Pull Request*. Segala bentuk kontribusi sangat dihargai!

---
*Dibuat untuk mempermudah ekosistem literasi digital yang aman dan cepat.* 📖
