# Spamaku! 🎯

Website buat nyari dan "belanja" event supaya poin **SPAMA** cepat terpenuhi, biar lulus tanpa drama.
Tugas Besar Pemrograman Web Dasar: PHP murni (tanpa framework), XHTML, CSS, JavaScript, dan MySQL.

## Cara menjalankan (XAMPP)

1. Taruh folder `spamaku` di `C:\xampp\htdocs\`.
2. Di XAMPP Control Panel, nyalakan **Apache** dan **MySQL**.
3. Buka `http://localhost/phpmyadmin`, masuk ke tab **Import**, pilih `database.sql`, lalu klik **Go**.
   Kalau muncul "#1046 No database selected", abaikan saja. Datanya tetap masuk.
4. Buka `http://localhost/spamaku/`.

Kalau MySQL kalian pakai password, ubah setting di bagian atas `inti.php`.

| Akun demo | Username | Password |
|---|---|---|
| Mahasiswa | `mahasiswa` | `password123` |
| Penyelenggara | `panitia` | `password123` |
| Admin | `admin` | `admin123` |

## Spamaku! itu apa (dan bukan apa)

Spamaku! cuma **tracker SPAMA + rekomendasi kegiatan**.
- Poin resmi, bukti, dan verifikasinya tetap di **SIKMA**. Mahasiswa cukup menyalin angka poinnya
  dari SIKMA di Dashboard, lalu Spamaku! menghitung kategori yang masih kurang dan merekomendasikan event.
- Pendaftaran event tetap lewat **link resmi panitia** (Google Form, dll). Spamaku! hanya menampilkan
  event dan mengantar ke link itu. Keranjang dipakai untuk menyimpan event incaran.

## Isi folder (cuma 17 file!)

| File | Isinya |
|---|---|
| `index.xhtml` | Halaman utama (XHTML murni). Datanya diambil dari `api.php` |
| `inti.php` | Jantungnya website: setting, koneksi database, fungsi bantu, login, header & footer |
| `masuk.php` | Login, daftar akun (`?daftar=1`), logout (`?keluar=1`) |
| `dashboard.php` | Isi poin dari SIKMA, lihat progres per kategori + rekomendasi event |
| `jelajah.php` | Daftar event + filter + event terdekat; detail event (`?id=`) |
| `keranjang.php` | Event yang disimpan + simulasi poin kalau semuanya diikuti |
| `profil.php` | Lihat & ubah profil, foto, password |
| `penyelenggara.php` | Kelola event, form event + link pendaftaran (`?hal=form`), organisasi (`?hal=organisasi`) |
| `admin.php` | Ringkasan, verifikasi event & organisasi, kelola pengguna (`?hal=...`) |
| `api.php` | JSON untuk halaman utama & cek username/email |
| `style.css` | Semua tampilan, termasuk splash dan efek neon |
| `app.js` | Semua JavaScript, dibagi 7 bagian |
| `database.sql` | Struktur tabel + data demo |
| `spamaku.webp` | Logo Spamaku! untuk splash screen |
| `logo.webp`, `logoGelap.webp` | Ikon tab browser & tombol beranda (versi terang & gelap) |
| `index.php`, `.htaccess` | Supaya `localhost/spamaku/` langsung membuka `index.xhtml` |

Folder `uploads/` (foto profil & poster) dibuat otomatis saat pertama kali ada yang upload.

## Ketentuan tugas

| Ketentuan | Di mana |
|---|---|
| Registrasi + password terenkripsi | `masuk.php?daftar=1`, `password_hash()` |
| Login | `masuk.php` |
| Lihat & ubah profil | `profil.php` |
| Minimal 2 CRUD | Event (`penyelenggara.php`), Keranjang (`keranjang.php`), Organisasi |
| Bonus: UI admin | `admin.php` |
| Bonus: geolocation | Tombol "Event terdekat" (`jelajah.php`) dan "Pakai lokasi saya" (form event) |
| Bonus: cek username/email langsung | `app.js` bagian 4 + `api.php?aksi=cek` |
| Bonus: foto profil | `profil.php` |

## Penamaan

Semua nama camelCase tanpa `_` atau `-`. Bahasanya campur: kata kerja Inggris + kata benda Indonesia
(`requireMasuk()`, `getPoinPengguna()`, `$daftarAcara`). Yang masih pakai `_` hanya bawaan PHP/JS
(`password_hash`, `$_POST`) dan nama class CSS.

## Aturan poin event

- **1 event = 1 jenis poin SPAMA.** Panitia memilih satu kategori dan mengisi jumlah poinnya.
- **Keatmajayaan** hanya untuk 4 kegiatan resmi: **LDPKM, UKM Fair, Jogja Istimewa, PKKMB** (boleh ditandai *susulan*). Server menolak event Keatmajayaan selain itu.

## Cara memasang event (biar nggak bingung "kok form-nya nggak ada?")

1. Login, buka menu profil → **Penyelenggara** → **Organisasi**, lalu ajukan organisasi.
2. Login sebagai **admin** → **Mode Admin → Organisasi** → setujui. (Organisasi yang dibuat admin langsung aktif.)
3. Kembali ke akun panitia → **+ Event Baru**.

Akun `panitia` / `password123` sudah punya organisasi aktif, jadi bisa langsung bikin event.

> Setelah update ini, **import ulang `database.sql`** di phpMyAdmin, karena struktur tabel `acara` berubah.
