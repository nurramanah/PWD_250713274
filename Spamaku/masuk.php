<?php

require 'inti.php';

// LOGOUT
if (isset($_GET['keluar'])) {
    $_SESSION = [];
    session_regenerate_id(true);
    setPesan('ok', 'Kamu sudah keluar. Sampai jumpa!');
    redirect('masuk.php');
}

// udah login tapi buka halaman login lagi? langsung lempar ke dashboard
if (isLogin()) redirect('dashboard.php');

$modeDaftar = isset($_GET['daftar']);
$errors = [];

// REGISTRASI
if ($modeDaftar && isPost()) {
    verifyCsrf();

    $nama = trim($_POST['nama'] ?? '');
    $namaPengguna = strtolower(trim($_POST['namaPengguna'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $npm = trim($_POST['npm'] ?? '');
    $prodi = trim($_POST['prodi'] ?? '');
    $angkatan = trim($_POST['angkatan'] ?? '');
    $kataSandi = $_POST['kataSandi'] ?? '';

    // validasi. iya, satu-satu. nggak ada jalan pintas di dunia ini
    if (mb_strlen($nama) < 3) {
        $errors['nama'] = 'Nama minimal 3 karakter.';
    }

    if (!preg_match('/^[a-z0-9_.]{3,30}$/', $namaPengguna)) {
        $errors['namaPengguna'] = 'Username 3–30 karakter: huruf kecil, angka, titik, atau garis bawah.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    if ($npm !== '' && !preg_match('/^[0-9]{5,20}$/', $npm)) {
        $errors['npm'] = 'NPM hanya berisi angka.';
    }

    if ($angkatan !== '' && !preg_match('/^20[0-9]{2}$/', $angkatan)) {
        $errors['angkatan'] = 'Tahun angkatan tidak valid.';
    }

    if (strlen($kataSandi) < 8) {
        $errors['kataSandi'] = 'Password minimal 8 karakter.';
    }

    if ($kataSandi !== ($_POST['ulangiKataSandi'] ?? '')) {
        $errors['ulangiKataSandi'] = 'Konfirmasi password tidak sama.';
    }

    // username & email nggak boleh kembar. ini bukan sinetron
    foreach ([
        'namaPengguna' => $namaPengguna,
        'email' => $email,
    ] as $kolom => $nilai) {
        if (isset($errors[$kolom])) {
            continue;
        }

        $stmt = $pdo->prepare("SELECT 1 FROM pengguna WHERE $kolom = ?");
        $stmt->execute([$nilai]);

        if ($stmt->fetch()) {
            $errors[$kolom] = (
                $kolom === 'email'
                    ? 'Email sudah terdaftar.'
                    : 'Username sudah dipakai.'
            );
        }
    }

    if (!$errors) {
        // Password disimpan dalam bentuk hash, bukan teks aslinya.
        $pdo->prepare(
            'INSERT INTO pengguna
             (nama, namaPengguna, email, npm, prodi, angkatan, kataSandi)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $nama,
            $namaPengguna,
            $email,
            $npm ?: null,
            $prodi ?: null,
            $angkatan ?: null,
            password_hash($kataSandi, PASSWORD_DEFAULT),
        ]);

        setPesan('ok', 'Registrasi berhasil! Silakan masuk dengan akun barumu.');
        redirect('masuk.php?u=' . urlencode($namaPengguna));
    }
}

// LOGIN
$masukan = $_POST['masukan'] ?? ($_GET['u'] ?? '');
$errorLogin = '';

if (!$modeDaftar && isPost()) {
    verifyCsrf();

    $masukan = strtolower(trim($masukan));

    $stmt = $pdo->prepare(
        'SELECT * FROM pengguna
         WHERE namaPengguna = ? OR email = ?
         LIMIT 1'
    );
    $stmt->execute([$masukan, $masukan]);
    $pengguna = $stmt->fetch();

    if ($pengguna && password_verify($_POST['kataSandi'] ?? '', $pengguna['kataSandi'])) {
        loginPengguna($pengguna);
        setPesan('ok', 'Selamat datang, ' . $pengguna['nama'] . '!');

        // Kembalikan pengguna ke halaman yang tadi ingin dibuka.
        $tujuan = $_SESSION['setelahMasuk'] ?? '';
        unset($_SESSION['setelahMasuk']);

        if (
            $tujuan &&
            strpos($tujuan, alamatDasar . '/') === 0 &&
            strpos($tujuan, '//') === false
        ) {
            header('Location: ' . $tujuan);
            exit;
        }

        redirect($pengguna['peran'] === 'admin' ? 'admin.php' : 'dashboard.php');
    }

    $errorLogin = 'Username/email atau password salah.';
}

tampilAtas($modeDaftar ? 'Daftar' : 'Masuk');
?>

<div class="auth-wrap">
<?php if ($modeDaftar): ?>
  <!-- ========== FORM DAFTAR ========== -->
  <div class="card auth-card wide reveal">
    <p class="eyebrow center">Mulai berburu</p>
    <h1>Buat akun <span class="neon">Spamaku!</span></h1>
    <p class="sub">Satu akun untuk mencari event dan menyelenggarakannya.</p>

    <?php if ($errors): ?>
      <div class="alert alert-bad">Periksa kembali isian yang ditandai.</div>
    <?php endif; ?>

    <form method="post"
          action="<?= url('masuk.php?daftar=1') ?>"
          class="form-grid cols-2"
          novalidate="novalidate">
      <?= createInputCsrf() ?>

      <div class="field full">
        <label for="nama">Nama lengkap <span class="req">*</span></label>
        <input class="input"
               type="text"
               id="nama"
               name="nama"
               value="<?= getNilaiLama('nama') ?>"
               required="required"
               maxlength="100" />
        <?= getPesanError($errors, 'nama') ?>
      </div>

      <div class="field">
        <label for="namaPengguna">Username <span class="req">*</span></label>
        <input class="input<?= isset($errors['namaPengguna']) ? ' is-invalid' : '' ?>"
               type="text"
               id="namaPengguna"
               name="namaPengguna"
               value="<?= getNilaiLama('namaPengguna') ?>"
               required="required"
               maxlength="30"
               autocomplete="username"
               data-check="namaPengguna" />
        <span class="field-msg<?= isset($errors['namaPengguna']) ? ' bad' : '' ?>"
              id="namaPenggunaPesan">
          <?= escape($errors['namaPengguna'] ?? '') ?>
        </span>
      </div>

      <div class="field">
        <label for="email">Email <span class="req">*</span></label>
        <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
               type="email"
               id="email"
               name="email"
               value="<?= getNilaiLama('email') ?>"
               required="required"
               maxlength="120"
               autocomplete="email"
               data-check="email" />
        <span class="field-msg<?= isset($errors['email']) ? ' bad' : '' ?>"
              id="emailPesan">
          <?= escape($errors['email'] ?? '') ?>
        </span>
      </div>

      <div class="field">
        <label for="npm">NPM</label>
        <input class="input"
               type="text"
               id="npm"
               name="npm"
               value="<?= getNilaiLama('npm') ?>"
               inputmode="numeric"
               maxlength="20" />
        <?= getPesanError($errors, 'npm') ?>
      </div>

      <div class="field">
        <label for="angkatan">Angkatan</label>
        <input class="input"
               type="number"
               id="angkatan"
               name="angkatan"
               value="<?= getNilaiLama('angkatan') ?>"
               min="2000"
               max="2099"
               placeholder="2025" />
        <?= getPesanError($errors, 'angkatan') ?>
      </div>

      <div class="field full">
        <label for="prodi">Program studi</label>
        <input class="input"
               type="text"
               id="prodi"
               name="prodi"
               value="<?= getNilaiLama('prodi') ?>"
               maxlength="80"
               placeholder="Informatika" />
      </div>

      <div class="field">
        <label for="kataSandi">Password <span class="req">*</span></label>
        <div class="pw-wrap">
          <input class="input"
                 type="password"
                 id="kataSandi"
                 name="kataSandi"
                 required="required"
                 minlength="8"
                 autocomplete="new-password" />
          <button type="button"
                  class="pw-toggle"
                  data-pw-toggle="kataSandi">
            LIHAT
          </button>
        </div>

        <span class="field-msg<?= isset($errors['kataSandi']) ? ' bad' : '' ?>"
              id="kataSandiPesan">
          <?= escape($errors['kataSandi'] ?? 'Minimal 8 karakter.') ?>
        </span>
      </div>

      <div class="field">
        <label for="ulangiKataSandi">Ulangi password <span class="req">*</span></label>
        <input class="input"
               type="password"
               id="ulangiKataSandi"
               name="ulangiKataSandi"
               required="required"
               autocomplete="new-password" />
        <span class="field-msg<?= isset($errors['ulangiKataSandi']) ? ' bad' : '' ?>"
              id="ulangiKataSandiPesan">
          <?= escape($errors['ulangiKataSandi'] ?? '') ?>
        </span>
      </div>

      <div class="field full">
        <button type="submit" class="btn btn-neon btn-lg btn-block">
          Daftar Sekarang
        </button>

        <p class="center small muted" style="margin-top:1rem">
          Sudah punya akun? <a href="<?= url('masuk.php') ?>">Masuk</a>
        </p>
      </div>
    </form>
  </div>

<?php else: ?>
  <!-- ========== FORM LOGIN ========== -->
  <div class="card auth-card reveal">
    <p class="eyebrow center">Selamat datang kembali</p>
    <h1>Masuk ke <span class="neon">Spamaku!</span></h1>
    <p class="sub">Lanjutkan perburuan poin SPAMA-mu.</p>

    <?php if ($errorLogin): ?>
      <div class="alert alert-bad"><?= escape($errorLogin) ?></div>
    <?php endif; ?>

    <form method="post"
          action="<?= url('masuk.php') ?>"
          class="form-grid">
      <?= createInputCsrf() ?>

      <div class="field">
        <label for="masukan">Username atau email</label>
        <input class="input"
               type="text"
               id="masukan"
               name="masukan"
               value="<?= escape($masukan) ?>"
               required="required"
               autocomplete="username"
               autofocus="autofocus" />
      </div>

      <div class="field">
        <label for="kataSandi">Password</label>
        <div class="pw-wrap">
          <input class="input"
                 type="password"
                 id="kataSandi"
                 name="kataSandi"
                 required="required"
                 autocomplete="current-password" />
          <button type="button"
                  class="pw-toggle"
                  data-pw-toggle="kataSandi">
            LIHAT
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-neon btn-lg btn-block">
        Masuk
      </button>
    </form>

    <p class="center small muted" style="margin-top:1rem">
      Belum punya akun? <a href="<?= url('masuk.php?daftar=1') ?>">Daftar gratis</a>
    </p>

    <!-- akun demo -->
    <div class="demo-box">
      <b>Akun demo:</b><br />
      Mahasiswa: <code>mahasiswa</code> / <code>password123</code><br />
      Penyelenggara: <code>panitia</code> / <code>password123</code><br />
      Admin: <code>admin</code> / <code>admin123</code>
    </div>
  </div>
<?php endif; ?>
</div>

<?php tampilBawah(); ?>
