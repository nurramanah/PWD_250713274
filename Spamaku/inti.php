<?php

// SETTING DATABASE
define('hostDatabase', '127.0.0.1');
define('namaDatabase', 'spamaku');
define('penggunaDatabase', 'root');
define('sandiDatabase', '');
define('namaAplikasi', 'Spamaku!');
define('batasUnggah', 2 * 1024 * 1024); // 2 MB

date_default_timezone_set('Asia/Jakarta');

// Daftar kegiatan resmi Keatmajayaan.
const daftarKeatmajayaan = [
    'LDPKM',
    'UKM Fair',
    'Jogja Istimewa',
    'PKKMB',
];

// FALLBACK JIKA MBSTRING TIDAK AKTIF
if (!function_exists('mb_strlen')) {
    function mb_strlen($teks, $encoding = null)
    {
        return (int)preg_match_all('/./us', (string)$teks);
    }
}

if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth(
        $teks,
        $mulai,
        $lebar,
        $akhiran = '',
        $encoding = null
    ) {
        $teks = (string)$teks;

        return strlen($teks) > $lebar
            ? substr($teks, $mulai, $lebar - strlen($akhiran)) . $akhiran
            : $teks;
    }
}

// SESSION
if (session_status() === PHP_SESSION_NONE) {
    // Cookie sesi hanya dikirim melalui HTTPS jika situs menggunakan HTTPS.
    $httpsAktif = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $httpsAktif,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// Semua file PHP berada di folder yang sama.
define(
    'alamatDasar',
    rtrim(
        str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])),
        '/'
    )
);

// KONEKSI DATABASE
try {
    $pdo = new PDO(
        'mysql:host=' . hostDatabase .
        ';dbname=' . namaDatabase .
        ';charset=utf8mb4',
        penggunaDatabase,
        sandiDatabase,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $error) {
    http_response_code(500);

    exit(
        'Gagal terhubung ke database :( Cek MySQL di XAMPP udah nyala belum, ' .
        'dan database "spamaku" udah di-import belum.'
    );
}


// FUNGSI KECIL TAPI PENTING

// Mengamankan teks sebelum ditampilkan ke HTML.
function escape($nilai): string
{
    return htmlspecialchars(
        (string)($nilai ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Membuat URL untuk halaman dan aset website.
function url(string $jalur = ''): string
{
    return str_replace(
        '&',
        '&amp;',
        alamatDasar . '/' . ltrim($jalur, '/')
    );
}

// Mengarahkan pengguna ke halaman lain.
function redirect(string $jalur): void
{
    header(
        'Location: ' .
        alamatDasar . '/' . ltrim($jalur, '/')
    );

    exit;
}

// Memeriksa apakah request menggunakan POST.
function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// Menyimpan pesan sementara.
function setPesan(string $jenis, string $isi): void
{
    $_SESSION['pesan'][] = [
        'jenis' => $jenis,
        'isi' => $isi,
    ];
}

// Mengambil pesan sementara dan menghapusnya dari session.
function getPesan(): array
{
    $daftar = $_SESSION['pesan'] ?? [];
    unset($_SESSION['pesan']);

    return $daftar;
}

// Mengambil nilai lama dari form.
function getNilaiLama(string $kunci, $bawaan = ''): string
{
    return escape($_POST[$kunci] ?? $bawaan);
}

// Menampilkan pesan kesalahan pada form.
function getPesanError(array $errors, string $kunci): string
{
    return isset($errors[$kunci])
        ? '<span class="field-msg bad">' . escape($errors[$kunci]) . '</span>'
        : '';
}

// Menentukan pilihan option yang aktif.
function terpilih($a, $b): string
{
    return (string)$a === (string)$b
        ? ' selected="selected"'
        : '';
}


// CSRF

// Membuat token CSRF jika belum tersedia.
function getTokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

// Membuat input tersembunyi untuk token CSRF.
function createInputCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' .
        getTokenCsrf() .
        '" />';
}

// Memeriksa token CSRF dari form yang dikirim.
function verifyCsrf(): void
{
    $tokenKirim = $_POST['csrf'] ?? '';

    if (
        !is_string($tokenKirim) ||
        !hash_equals(getTokenCsrf(), $tokenKirim)
    ) {
        http_response_code(419);

        exit(
            'Sesi formulir kedaluwarsa. ' .
            'Balik lagi terus refresh halamannya ya.'
        );
    }
}


// UPLOAD GAMBAR

// Memeriksa dan menyimpan gambar ke folder upload.
function uploadGambar(array $berkas, string $folder): string
{
    if ($berkas['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(
            'Upload gagal (kode ' . $berkas['error'] . ').'
        );
    }

    if ($berkas['size'] > batasUnggah) {
        throw new Exception('Ukuran file maksimal 2 MB.');
    }

    $tipeBoleh = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    // Memeriksa isi file, bukan hanya ekstensi namanya.
    $info = @getimagesize($berkas['tmp_name']);
    $mime = $info['mime'] ?? '';

    if ($mime === '' && class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))
            ->file($berkas['tmp_name']);
    }

    if (!isset($tipeBoleh[$mime])) {
        throw new Exception(
            'Format file harus ' .
            strtoupper(implode(', ', array_unique($tipeBoleh))) .
            '.'
        );
    }

    $folderUpload = __DIR__ . '/uploads';

    if (!is_dir("$folderUpload/$folder")) {
        mkdir("$folderUpload/$folder", 0775, true);
    }

    if (!is_file("$folderUpload/.htaccess")) {
        // Mencegah file PHP dijalankan dari folder uploads.
        file_put_contents(
            "$folderUpload/.htaccess",
            "<FilesMatch \"\\.(php|phtml|phar)$\">\n" .
            "  Require all denied\n" .
            "</FilesMatch>\n" .
            "Options -Indexes\n"
        );
    }

    // Nama file dibuat acak agar tidak bertabrakan.
    $namaBaru = bin2hex(random_bytes(12)) .
        '.' . $tipeBoleh[$mime];

    if (
        !move_uploaded_file(
            $berkas['tmp_name'],
            "$folderUpload/$folder/$namaBaru"
        )
    ) {
        throw new Exception('Gagal menyimpan file.');
    }

    return $namaBaru;
}

// Menghapus file upload.
function deleteUpload(?string $namaBerkas, string $folder): void
{
    $jalur = __DIR__ .
        "/uploads/$folder/" .
        basename((string)$namaBerkas);

    if ($namaBerkas && is_file($jalur)) {
        unlink($jalur);
    }
}

// Memeriksa apakah pengguna mengirim file.
function hasUpload(string $kolom): bool
{
    return isset($_FILES[$kolom]) &&
        $_FILES[$kolom]['error'] !== UPLOAD_ERR_NO_FILE;
}


// FUNGSI FORMAT

// Mengubah tanggal database menjadi format yang mudah dibaca.
function formatTanggal(
    ?string $waktu,
    bool $pakaiJam = false
): string {
    if (!$waktu) {
        return '-';
    }

    $bulan = [
        '',
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des',
    ];

    $hari = [
        'Min',
        'Sen',
        'Sel',
        'Rab',
        'Kam',
        'Jum',
        'Sab',
    ];

    $t = strtotime($waktu);

    $teks = $hari[date('w', $t)] .
        ', ' . date('j', $t) .
        ' ' . $bulan[(int)date('n', $t)] .
        ' ' . date('Y', $t);

    return $pakaiJam
        ? $teks . ' · ' . date('H:i', $t)
        : $teks;
}

// Mengubah angka menjadi format rupiah.
function formatRupiah(int $angka): string
{
    return $angka <= 0
        ? 'Gratis'
        : 'Rp' . number_format($angka, 0, ',', '.');
}

// Mengubah jarak kilometer menjadi meter atau kilometer.
function formatJarak(?float $km): string
{
    if ($km === null) {
        return '';
    }

    return $km < 1
        ? round($km * 1000) . ' m'
        : number_format($km, 1, ',', '.') . ' km';
}

// Mengambil URL foto profil atau gambar pengganti.
function getUrlAvatar(?array $pengguna): string
{
    if (!empty($pengguna['foto'])) {
        return url('uploads/avatars/' . $pengguna['foto']);
    }

    return "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'><rect width='120' height='120' fill='%2311233B'/><circle cx='60' cy='46' r='22' fill='%233B8FD6'/><path d='M18 112c4-24 22-36 42-36s38 12 42 36' fill='%233B8FD6'/></svg>";
}

// Mengambil URL poster event.
function getUrlPoster(array $acara): ?string
{
    return !empty($acara['poster'])
        ? url('uploads/posters/' . $acara['poster'])
        : null;
}


// URUSAN POIN SPAMA

// Mengambil seluruh kategori SPAMA.
function getSemuaKategori(): array
{
    static $simpanan = null;
    global $pdo;

    if ($simpanan === null) {
        $simpanan = [];

        foreach ($pdo->query('SELECT * FROM kategori ORDER BY urutan') as $k) {
            $simpanan[$k['id']] = $k;
        }
    }

    return $simpanan;
}

// Mengambil poin pengguna untuk setiap kategori.
function getPoinPengguna(int $idPengguna): array
{
    global $pdo;

    $poin = array_fill_keys(
        array_keys(getSemuaKategori()),
        0
    );

    $stmt = $pdo->prepare(
        'SELECT idKategori, poin
         FROM poinSikma
         WHERE idPengguna = ?'
    );

    $stmt->execute([$idPengguna]);

    foreach ($stmt as $r) {
        $poin[$r['idKategori']] = (int)$r['poin'];
    }

    return $poin;
}

// Memeriksa apakah kategori merupakan Keatmajayaan.
function isKeatmajayaan($idKategori): bool
{
    $kategori = getSemuaKategori()[(int)$idKategori] ?? null;

    return $kategori &&
        $kategori['singkatan'] === 'KAJ';
}

// Membuat badge poin pada kartu event.
function renderBadgePoin(array $acara): string
{
    $k = getSemuaKategori()[
        (int)($acara['idKategori'] ?? 0)
    ] ?? null;

    if (!$k) {
        return '';
    }

    return '<span class="badge-poin" style="--c:' .
        escape($k['warna']) .
        '" title="' . escape($k['nama']) . '">' .
        '+' . (int)$acara['poin'] .
        ' ' . escape($k['singkatan']) .
        '</span>';
}

// Membuat label kegiatan Keatmajayaan.
function renderLabelKeatmajayaan(array $acara): string
{
    if (empty($acara['jenisKeatmajayaan'])) {
        return '';
    }

    return '<span class="pill pill-info">' .
        escape($acara['jenisKeatmajayaan']) .
        (!empty($acara['susulan']) ? ' · Susulan' : '') .
        '</span>';
}

// Memeriksa apakah event sudah selesai.
function isAcaraSelesai(array $acara): bool
{
    return strtotime(
        $acara['tanggalSelesai'] ?: $acara['tanggalMulai']
    ) < time();
}

// Membuat label status.
function renderLabelStatus(string $status): string
{
    $warna = [
        'menunggu' => 'warn',
        'disetujui' => 'ok',
        'ditolak' => 'bad',
    ][$status] ?? 'info';

    return '<span class="pill pill-' . $warna . '">' .
        escape(ucfirst($status)) .
        '</span>';
}

// Mengambil daftar ID event yang ada di keranjang pengguna.
function getIdKeranjang(): array
{
    global $pdo;

    // Ambil pengguna aktif terlebih dahulu untuk menghindari
    // akses indeks id jika akun sudah tidak ditemukan.
    $pengguna = getPenggunaAktif();

    if (!$pengguna) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT idAcara
         FROM keranjang
         WHERE idPengguna = ?'
    );

    $stmt->execute([(int)$pengguna['id']]);

    return array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}

// Query dasar untuk mengambil event beserta nama organisasinya.
function sqlAcara(): string
{
    return "SELECT a.*, o.nama AS namaOrganisasi
            FROM acara a
            JOIN organisasi o ON o.id = a.idOrganisasi";
}


// LOGIN DAN HAK AKSES

// Memeriksa apakah session memiliki ID pengguna.
function isLogin(): bool
{
    return !empty($_SESSION['idPengguna']);
}

// Mengambil data pengguna yang sedang login.
function getPenggunaAktif(): ?array
{
    static $pengguna = false;
    global $pdo;

    if ($pengguna === false) {
        $pengguna = null;

        if (isLogin()) {
            $stmt = $pdo->prepare(
                'SELECT * FROM pengguna WHERE id = ?'
            );

            $stmt->execute([$_SESSION['idPengguna']]);
            $pengguna = $stmt->fetch() ?: null;

            if (!$pengguna) {
                unset($_SESSION['idPengguna']);
            }
        }
    }

    return $pengguna;
}

// Menyimpan ID pengguna ke session setelah login.
function loginPengguna(array $pengguna): void
{
    session_regenerate_id(true);
    $_SESSION['idPengguna'] = (int)$pengguna['id'];
}

// Memastikan pengguna sudah login.
function requireMasuk(): array
{
    if (!getPenggunaAktif()) {
        setPesan('warn', 'Silakan masuk terlebih dahulu.');

        $_SESSION['setelahMasuk'] =
            $_SERVER['REQUEST_URI'] ?? '';

        redirect('masuk.php');
    }

    return getPenggunaAktif();
}

// Memastikan pengguna memiliki peran admin.
function requireAdmin(): array
{
    $saya = requireMasuk();

    if ($saya['peran'] !== 'admin') {
        setPesan(
            'bad',
            'Halaman itu khusus admin. Hayo mau ngapain 👀'
        );

        redirect('dashboard.php');
    }

    return $saya;
}

// Mengambil organisasi yang diikuti pengguna.
function getOrganisasiPengguna(
    int $idPengguna,
    bool $cumaDisetujui = true
): array {
    global $pdo;

    $sql = 'SELECT o.*, a.jabatan
            FROM organisasi o
            JOIN anggota a ON a.idOrganisasi = o.id
            WHERE a.idPengguna = ?';

    if ($cumaDisetujui) {
        $sql .= " AND o.status = 'disetujui'";
    }

    $stmt = $pdo->prepare($sql . ' ORDER BY o.nama');
    $stmt->execute([$idPengguna]);

    return $stmt->fetchAll();
}

// Memeriksa keanggotaan pengguna dalam organisasi.
function isAnggotaOrganisasi(
    int $idPengguna,
    int $idOrganisasi,
    bool $harusKetua = false
): bool {
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT jabatan
         FROM anggota
         WHERE idPengguna = ? AND idOrganisasi = ?'
    );

    $stmt->execute([$idPengguna, $idOrganisasi]);
    $jabatan = $stmt->fetchColumn();

    return $jabatan !== false &&
        (!$harusKetua || $jabatan === 'ketua');
}

// Mengambil event dan memeriksa hak akses pengelolanya.
function requirePengelolaAcara(int $idAcara): array
{
    global $pdo;

    $saya = requireMasuk();

    $stmt = $pdo->prepare(
        'SELECT a.*,
                o.nama AS namaOrganisasi,
                o.status AS statusOrganisasi
         FROM acara a
         JOIN organisasi o ON o.id = a.idOrganisasi
         WHERE a.id = ?'
    );

    $stmt->execute([$idAcara]);
    $acara = $stmt->fetch();

    $boleh = $acara && (
        $saya['peran'] === 'admin' ||
        (
            $acara['statusOrganisasi'] === 'disetujui' &&
            isAnggotaOrganisasi(
                (int)$saya['id'],
                (int)$acara['idOrganisasi']
            )
        )
    );

    if (!$boleh) {
        setPesan(
            'bad',
            'Event tidak ditemukan atau kamu tidak punya akses.'
        );

        redirect('penyelenggara.php');
    }

    return $acara;
}


// TAMPILAN BERSAMA: HEADER, FOOTER, KARTU EVENT

// Membuat satu item menu navigasi.
function linkMenu(
    string $kunci,
    string $href,
    string $label,
    string $aktif,
    string $tambahan = ''
): string {
    return '<li><a href="' . url($href) . '"' .
        ($kunci === $aktif ? ' class="active"' : '') .
        '>' . $label . $tambahan . '</a></li>';
}

// Menampilkan bagian atas halaman, termasuk header dan navigasi.
function tampilAtas(
    string $judul,
    string $menuAktif = '',
    string $mode = 'mahasiswa'
): void {
    $saya = getPenggunaAktif();
    $jumlahKeranjang = count(getIdKeranjang());
    ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"
      lang="id"
      data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= escape($judul) ?> · <?= namaAplikasi ?></title>

  <script>
    document.documentElement.className += " js";

    try {
      if (localStorage.getItem("temaSpamaku")) {
        document.documentElement.setAttribute(
          "data-theme",
          localStorage.getItem("temaSpamaku")
        );
      }

      if (sessionStorage.getItem("splashSpamakuTampil")) {
        document.documentElement.className += " no-splash";
      }
    } catch (error) {}
  </script>

  <link rel="icon"
        href="<?= url('logo.webp') ?>"
        type="image/webp" />

  <link rel="preload"
        as="image"
        href="<?= url('spamaku.webp') ?>" />

  <link rel="stylesheet"
        href="<?= url('style.css') ?>" />
</head>

<body class="mode-<?= escape($mode) ?>">

<div id="splash"
     class="splash"
     role="presentation"
     aria-hidden="true">
  <div class="splash__logo">
    <img src="<?= url('spamaku.webp') ?>"
         alt="Spamaku!"
         width="720"
         height="709" />

    <img class="splash__glow"
         src="<?= url('spamaku.webp') ?>"
         alt=""
         width="720"
         height="709" />
  </div>

  <p class="splash__skip">klik untuk lewati</p>
</div>

<header class="topbar">
  <div class="container topbar__inner">
    <a href="<?= url($saya ? 'dashboard.php' : 'index.xhtml') ?>"
       class="brand"
       aria-label="Beranda">

      <img class="brand__logo brand__logo--gelap"
           src="<?= url('logoGelap.webp') ?>"
           alt=""
           width="34"
           height="36" />

      <img class="brand__logo brand__logo--terang"
           src="<?= url('logo.webp') ?>"
           alt=""
           width="34"
           height="36" />

      <span class="brand__text">SPAMA<span>KU!</span></span>
    </a>

    <button type="button"
            class="nav-toggle"
            aria-label="Buka menu"
            aria-expanded="false"
            data-nav-toggle="">
      <span></span><span></span><span></span>
    </button>

    <nav class="mainnav" data-nav="">
      <ul>
        <?php if ($mode === 'admin'): ?>
          <?= linkMenu('ringkasan', 'admin.php', 'Ringkasan', $menuAktif) ?>
          <?= linkMenu('acara', 'admin.php?hal=acara', 'Event', $menuAktif) ?>
          <?= linkMenu('organisasi', 'admin.php?hal=organisasi', 'Organisasi', $menuAktif) ?>
          <?= linkMenu('pengguna', 'admin.php?hal=pengguna', 'Pengguna', $menuAktif) ?>

        <?php elseif ($mode === 'penyelenggara'): ?>
          <?= linkMenu('kelola', 'penyelenggara.php', 'Kelola Event', $menuAktif) ?>
          <?= linkMenu('form', 'penyelenggara.php?hal=form', '+ Event Baru', $menuAktif) ?>
          <?= linkMenu('organisasi', 'penyelenggara.php?hal=organisasi', 'Organisasi', $menuAktif) ?>

        <?php elseif ($saya): ?>
          <?= linkMenu('dashboard', 'dashboard.php', 'Dashboard', $menuAktif) ?>
          <?= linkMenu('jelajah', 'jelajah.php', 'Jelajah', $menuAktif) ?>

          <?= linkMenu(
              'keranjang',
              'keranjang.php',
              'Keranjang',
              $menuAktif,
              $jumlahKeranjang
                  ? ' <span class="count">' . $jumlahKeranjang . '</span>'
                  : ''
          ) ?>

        <?php else: ?>
          <?= linkMenu('jelajah', 'jelajah.php', 'Jelajah Event', $menuAktif) ?>
        <?php endif; ?>
      </ul>

      <div class="nav-actions">
        <button type="button"
                class="icon-btn"
                data-theme-toggle=""
                aria-label="Ganti tema"
                title="Ganti tema">

          <svg xmlns="http://www.w3.org/2000/svg"
               class="i-moon"
               viewBox="0 0 24 24"
               width="18"
               height="18"
               aria-hidden="true">
            <path fill="currentColor"
                  d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
          </svg>

          <svg xmlns="http://www.w3.org/2000/svg"
               class="i-sun"
               viewBox="0 0 24 24"
               width="18"
               height="18"
               aria-hidden="true">
            <circle cx="12" cy="12" r="4.5" fill="currentColor"/>
            <g stroke="currentColor"
               stroke-width="2"
               stroke-linecap="round">
              <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            </g>
          </svg>
        </button>

        <?php if ($saya): ?>
          <details class="usermenu">
            <summary>
              <img src="<?= escape(getUrlAvatar($saya)) ?>"
                   alt=""
                   class="avatar avatar-sm"
                   width="32"
                   height="32" />

              <span class="usermenu__name">
                <?= escape($saya['namaPengguna']) ?>
              </span>
            </summary>

            <div class="usermenu__panel">
              <p class="usermenu__mode">Mode</p>

              <a href="<?= url('dashboard.php') ?>"
                 class="<?= $mode === 'mahasiswa' ? 'on' : '' ?>">
                🎓 Mahasiswa
              </a>

              <a href="<?= url('penyelenggara.php') ?>"
                 class="<?= $mode === 'penyelenggara' ? 'on' : '' ?>">
                🎪 Penyelenggara
              </a>

              <?php if ($saya['peran'] === 'admin'): ?>
                <a href="<?= url('admin.php') ?>"
                   class="<?= $mode === 'admin' ? 'on' : '' ?>">
                  🛡️ Admin
                </a>
              <?php endif; ?>

              <hr />

              <a href="<?= url('profil.php') ?>">Profil Saya</a>
              <a href="<?= url('masuk.php?keluar=1') ?>"
                 class="danger">
                Keluar
              </a>
            </div>
          </details>

        <?php else: ?>
          <a href="<?= url('masuk.php') ?>"
             class="btn btn-ghost btn-sm">
            Masuk
          </a>

          <a href="<?= url('masuk.php?daftar=1') ?>"
             class="btn btn-neon btn-sm">
            Daftar
          </a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>

<?php if ($mode !== 'mahasiswa'): ?>
  <div class="modebar modebar-<?= escape($mode) ?>">
    <div class="container">
      <?= $mode === 'admin'
          ? '🛡️ Mode Admin'
          : '🎪 Mode Penyelenggara' ?>

      <a href="<?= url('dashboard.php') ?>">
        Kembali ke Mode Mahasiswa →
      </a>
    </div>
  </div>
<?php endif; ?>

<main class="page">
  <div class="container">
    <?php foreach (getPesan() as $p): ?>
      <div class="alert alert-<?= escape($p['jenis']) ?>"
           role="status">
        <?= escape($p['isi']) ?>
      </div>
    <?php endforeach;
}

// Menutup konten halaman dan menampilkan footer.
function tampilBawah(): void
{
    ?>
  </div>
</main>

<footer class="footer">
  <div class="container footer__inner">
    <p>
      <span class="brand__text small">SPAMA<span>KU!</span></span>
      — cari event, kumpulkan poin, lulus tepat waktu.
    </p>

    <p class="muted">
      Tugas Besar Pemrograman Web Dasar · UAJY <?= date('Y') ?>
    </p>
  </div>
</footer>

<script src="<?= url('app.js') ?>"></script>
</body>
</html>
<?php
}

// Membuat kartu event yang dipakai di halaman Jelajah dan Dashboard.
function kartuAcara(array $acara, array $idKeranjang): void
{
    $pertama = getSemuaKategori()[
        (int)$acara['idKategori']
    ] ?? null;

    $inKeranjang = in_array(
        (int)$acara['id'],
        $idKeranjang,
        true
    );

    $isLewat = isAcaraSelesai($acara);
    $poster = getUrlPoster($acara);
    ?>
    <article class="event-card glow-hover reveal<?= $isLewat ? ' is-past' : '' ?>">
      <div class="event-card__media">
        <?php if ($poster): ?>
          <img src="<?= escape($poster) ?>"
               alt=""
               loading="lazy" />
        <?php else: ?>
          <div class="poster-ph"
               style="--c:<?= escape($pertama['warna'] ?? '#F3AD1B') ?>">
            <?= escape($pertama['singkatan'] ?? 'EVT') ?>
          </div>
        <?php endif; ?>

        <span class="event-card__date">
          <?= formatTanggal($acara['tanggalMulai']) ?>
        </span>
      </div>

      <?php if (isLogin() && !$isLewat): ?>
        <form method="post"
              action="<?= url('keranjang.php') ?>"
              class="event-card__fav"
              data-fav="">
          <?= createInputCsrf() ?>

          <input type="hidden" name="aksi" value="toggle" />
          <input type="hidden"
                 name="idAcara"
                 value="<?= (int)$acara['id'] ?>" />

          <button type="submit"
                  class="fav-btn<?= $inKeranjang ? ' on' : '' ?>"
                  aria-pressed="<?= $inKeranjang ? 'true' : 'false' ?>"
                  title="<?= $inKeranjang ? 'Hapus dari keranjang' : 'Tambah ke keranjang' ?>">
            ♥
          </button>
        </form>
      <?php endif; ?>

      <div class="event-card__body">
        <div class="badges">
          <?= renderBadgePoin($acara) ?>
          <?= renderLabelKeatmajayaan($acara) ?>
        </div>

        <h3>
          <a href="<?= url('jelajah.php?id=' . (int)$acara['id']) ?>">
            <?= escape($acara['judul']) ?>
          </a>
        </h3>

        <div class="event-card__meta">
          <span>🏛️ <?= escape($acara['namaOrganisasi']) ?></span>

          <span>
            <?= $acara['mode'] === 'online'
                ? '💻 Online'
                : '📍 ' . escape($acara['lokasi']) ?>

            <?php if (isset($acara['jarak'])): ?>
              · <strong><?= formatJarak((float)$acara['jarak']) ?></strong>
            <?php endif; ?>
          </span>
        </div>

        <div class="event-card__foot">
          <b><?= formatRupiah((int)$acara['biaya']) ?></b>

          <span class="muted">
            <?= $isLewat
                ? 'Selesai'
                : '+' . (int)$acara['poin'] .
                  ' poin ' . escape($pertama['nama'] ?? 'SPAMA') ?>
          </span>
        </div>
      </div>
    </article>
    <?php
}
