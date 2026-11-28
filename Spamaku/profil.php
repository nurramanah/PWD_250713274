<?php

require 'inti.php';
$saya = requireMasuk();
$errors = [];

if (isPost()) {
    verifyCsrf();
    $aksi = $_POST['aksi'] ?? '';

    // UPDATE PROFIL
    if ($aksi === 'profile') {
        $data = [
            'nama' => trim($_POST['nama'] ?? ''),
            'namaPengguna' => strtolower(trim($_POST['namaPengguna'] ?? '')),
            'email' => strtolower(trim($_POST['email'] ?? '')),
            'npm' => trim($_POST['npm'] ?? ''),
            'prodi' => trim($_POST['prodi'] ?? ''),
            'angkatan' => trim($_POST['angkatan'] ?? ''),
            'bio' => trim($_POST['bio'] ?? ''),
        ];

        if (mb_strlen($data['nama']) < 3) {
            $errors['nama'] = 'Nama minimal 3 karakter.';
        }

        if (!preg_match('/^[a-z0-9_.]{3,30}$/', $data['namaPengguna'])) {
            $errors['namaPengguna'] = 'Username tidak valid.';
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email tidak valid.';
        }

        if ($data['npm'] !== '' && !preg_match('/^[0-9]{5,20}$/', $data['npm'])) {
            $errors['npm'] = 'NPM hanya angka.';
        }

        if ($data['angkatan'] !== '' && !preg_match('/^20[0-9]{2}$/', $data['angkatan'])) {
            $errors['angkatan'] = 'Tahun tidak valid.';
        }

        if (mb_strlen($data['bio']) > 255) {
            $errors['bio'] = 'Bio maksimal 255 karakter. Ini bio, bukan skripsi.';
        }

        // username/email baru nggak boleh nyerobot punya orang lain
        foreach (['namaPengguna', 'email'] as $kolom) {
            if (isset($errors[$kolom])) {
                continue;
            }

            $stmt = $pdo->prepare(
                "SELECT 1 FROM pengguna WHERE $kolom = ? AND id <> ?"
            );
            $stmt->execute([$data[$kolom], $saya['id']]);

            if ($stmt->fetch()) {
                $errors[$kolom] = ($kolom === 'email' ? 'Email' : 'Username')
                    . ' sudah dipakai akun lain.';
            }
        }

        // Simpan nama foto lama dulu. File lama baru dihapus setelah database berhasil diperbarui.
        $fotoLama = $saya['foto'];
        $foto = $fotoLama;
        $fotoBaru = null;

        if (!$errors && !empty($_POST['hapusFoto'])) {
            $foto = null;
        }

        if (!$errors && hasUpload('foto')) {
            try {
                $fotoBaru = uploadGambar($_FILES['foto'], 'avatars');
                $foto = $fotoBaru;
            } catch (Exception $e) {
                $errors['foto'] = $e->getMessage();
            }
        }

        if (!$errors) {
            try {
                $pdo->prepare(
                    'UPDATE pengguna
                     SET nama=?, namaPengguna=?, email=?, npm=?,
                         prodi=?, angkatan=?, bio=?, foto=?
                     WHERE id=?'
                )->execute([
                    $data['nama'],
                    $data['namaPengguna'],
                    $data['email'],
                    $data['npm'] ?: null,
                    $data['prodi'] ?: null,
                    $data['angkatan'] ?: null,
                    $data['bio'] ?: null,
                    $foto,
                    $saya['id'],
                ]);

                if ($fotoLama && $fotoLama !== $foto) {
                    deleteUpload($fotoLama, 'avatars');
                }

                setPesan('ok', 'Profil berhasil diperbarui.');
                redirect('profil.php');
            } catch (Throwable $error) {
                // Jika penyimpanan database gagal, file baru tidak dibiarkan menjadi file yatim.
                if ($fotoBaru) {
                    deleteUpload($fotoBaru, 'avatars');
                }

                $errors['umum'] = 'Profil gagal diperbarui. Periksa kembali data dan coba lagi.';
            }
        }
    }

    // GANTI PASSWORD
    if ($aksi === 'password') {
        $baru = $_POST['kataSandiBaru'] ?? '';

        if (!password_verify($_POST['kataSandiLama'] ?? '', $saya['kataSandi'])) {
            $errors['kataSandiLama'] = 'Password lama salah.';
        }

        if (strlen($baru) < 8) {
            $errors['kataSandiBaru'] = 'Password baru minimal 8 karakter.';
        }

        if ($baru !== ($_POST['ulangiKataSandiBaru'] ?? '')) {
            $errors['ulangiKataSandiBaru'] = 'Konfirmasi tidak sama.';
        }

        if (!$errors) {
            $pdo->prepare(
                'UPDATE pengguna SET kataSandi = ? WHERE id = ?'
            )->execute([
                password_hash($baru, PASSWORD_DEFAULT),
                $saya['id'],
            ]);

            setPesan('ok', 'Password berhasil diganti. Jangan sampai lupa lagi ya 😉');
            redirect('profil.php');
        }
    }
}

// data buat kartu profil di kiri
$poinSaya = getPoinPengguna((int)$saya['id']);

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM keranjang WHERE idPengguna = ?'
);
$stmt->execute([$saya['id']]);
$jumlahAcara = (int)$stmt->fetchColumn();

$daftarOrganisasi = getOrganisasiPengguna((int)$saya['id'], false);
$nilai = (isPost() && ($_POST['aksi'] ?? '') === 'profile')
    ? $_POST
    : $saya;

tampilAtas('Profil Saya');
?>

<div class="page-head">
  <div>
    <p class="eyebrow">Akun</p>
    <h1>Profil Saya</h1>
  </div>
</div>

<div class="grid grid-side" style="grid-template-columns: minmax(260px,1fr) minmax(0,2fr)">
  <aside class="card profile-card reveal">
    <img src="<?= escape(getUrlAvatar($saya)) ?>"
         alt="Foto profil"
         class="avatar avatar-lg"
         width="120"
         height="120" />

    <h2 class="mb-0"><?= escape($saya['nama']) ?></h2>

    <p class="muted">
      @<?= escape($saya['namaPengguna']) ?>
      <?= $saya['peran'] === 'admin'
          ? ' · <span class="pill pill-warn">Admin</span>'
          : '' ?>
    </p>

    <?php if ($saya['bio']): ?>
      <p><?= escape($saya['bio']) ?></p>
    <?php endif; ?>

    <p class="small muted mb-0">
      <?= escape($saya['prodi'] ?: 'Prodi belum diisi') ?>
      <?= $saya['angkatan'] ? ' · ' . escape($saya['angkatan']) : '' ?>
      <br />
      <?= $saya['npm'] ? 'NPM ' . escape($saya['npm']) : '' ?>
    </p>

    <hr />

    <div class="grid grid-2" style="gap:.5rem">
      <div class="stat">
        <b class="neon"><?= array_sum($poinSaya) ?></b>
        <span>Total poin</span>
      </div>

      <div class="stat">
        <b><?= $jumlahAcara ?></b>
        <span>Event disimpan</span>
      </div>
    </div>

    <?php if ($daftarOrganisasi): ?>
      <hr />

      <p class="label" style="text-align:left">Organisasi</p>

      <ul class="list small" style="text-align:left">
        <?php foreach ($daftarOrganisasi as $o): ?>
          <li>
            <span>
              <?= escape($o['nama']) ?>
              <span class="muted">(<?= escape($o['jabatan']) ?>)</span>
            </span>
            <?= renderLabelStatus($o['status']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </aside>

  <div class="grid" style="gap:1.25rem">
    <section class="card reveal">
      <h2>Edit Profil</h2>

      <?php if (isset($errors['umum'])): ?>
        <div class="alert alert-bad"><?= escape($errors['umum']) ?></div>
      <?php endif; ?>

      <form method="post"
            action="<?= url('profil.php') ?>"
            enctype="multipart/form-data"
            class="form-grid cols-2">
        <?= createInputCsrf() ?>
        <input type="hidden" name="aksi" value="profile" />

        <div class="field full" style="flex-direction:row;align-items:center;gap:1rem">
          <div class="avatar-upload">
            <img src="<?= escape(getUrlAvatar($saya)) ?>"
                 alt=""
                 id="fotoPreview"
                 class="avatar"
                 width="84"
                 height="84"
                 style="width:84px;height:84px" />

            <label for="foto" title="Ganti foto">📷</label>
          </div>

          <div>
            <input type="file"
                   id="foto"
                   name="foto"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   data-preview="fotoPreview"
                   class="sr-only" />

            <p class="label mb-0">Foto profil</p>
            <p class="hint small muted mb-0">
              JPG/PNG/WEBP, maks 2 MB. Klik ikon kamera.
            </p>

            <?php if ($saya['foto']): ?>
              <label class="check small">
                <input type="checkbox" name="hapusFoto" value="1" />
                Hapus foto
              </label>
            <?php endif; ?>

            <?= getPesanError($errors, 'foto') ?>
          </div>
        </div>

        <div class="field full">
          <label for="nama">Nama lengkap</label>
          <input class="input"
                 id="nama"
                 name="nama"
                 value="<?= escape($nilai['nama'] ?? '') ?>"
                 required="required"
                 maxlength="100" />
          <?= getPesanError($errors, 'nama') ?>
        </div>

        <div class="field">
          <label for="namaPengguna">Username</label>
          <input class="input"
                 id="namaPengguna"
                 name="namaPengguna"
                 value="<?= escape($nilai['namaPengguna'] ?? '') ?>"
                 required="required"
                 maxlength="30"
                 data-check="namaPengguna"
                 data-exclude="1" />
          <span class="field-msg<?= isset($errors['namaPengguna']) ? ' bad' : '' ?>"
                id="namaPenggunaPesan">
            <?= escape($errors['namaPengguna'] ?? '') ?>
          </span>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input class="input"
                 type="email"
                 id="email"
                 name="email"
                 value="<?= escape($nilai['email'] ?? '') ?>"
                 required="required"
                 maxlength="120"
                 data-check="email"
                 data-exclude="1" />
          <span class="field-msg<?= isset($errors['email']) ? ' bad' : '' ?>"
                id="emailPesan">
            <?= escape($errors['email'] ?? '') ?>
          </span>
        </div>

        <div class="field">
          <label for="npm">NPM</label>
          <input class="input"
                 id="npm"
                 name="npm"
                 value="<?= escape($nilai['npm'] ?? '') ?>"
                 maxlength="20"
                 inputmode="numeric" />
          <?= getPesanError($errors, 'npm') ?>
        </div>

        <div class="field">
          <label for="angkatan">Angkatan</label>
          <input class="input"
                 type="number"
                 id="angkatan"
                 name="angkatan"
                 value="<?= escape($nilai['angkatan'] ?? '') ?>"
                 min="2000"
                 max="2099" />
          <?= getPesanError($errors, 'angkatan') ?>
        </div>

        <div class="field full">
          <label for="prodi">Program studi</label>
          <input class="input"
                 id="prodi"
                 name="prodi"
                 value="<?= escape($nilai['prodi'] ?? '') ?>"
                 maxlength="80" />
        </div>

        <div class="field full">
          <label for="bio">Bio singkat</label>
          <textarea class="input"
                    id="bio"
                    name="bio"
                    maxlength="255"
                    style="min-height:80px"><?= escape($nilai['bio'] ?? '') ?></textarea>
          <?= getPesanError($errors, 'bio') ?>
        </div>

        <div class="field full">
          <button type="submit" class="btn btn-neon">
            Simpan Perubahan
          </button>
        </div>
      </form>
    </section>

    <section class="card reveal">
      <h2>Ganti Password</h2>

      <form method="post"
            action="<?= url('profil.php') ?>"
            class="form-grid cols-2">
        <?= createInputCsrf() ?>
        <input type="hidden" name="aksi" value="password" />

        <div class="field full">
          <label for="kataSandiLama">Password lama</label>
          <input class="input"
                 type="password"
                 id="kataSandiLama"
                 name="kataSandiLama"
                 required="required"
                 autocomplete="current-password" />
          <?= getPesanError($errors, 'kataSandiLama') ?>
        </div>

        <div class="field">
          <label for="kataSandiBaru">Password baru</label>
          <input class="input"
                 type="password"
                 id="kataSandiBaru"
                 name="kataSandiBaru"
                 required="required"
                 minlength="8"
                 autocomplete="new-password" />
          <?= getPesanError($errors, 'kataSandiBaru') ?>
        </div>

        <div class="field">
          <label for="ulangiKataSandiBaru">Ulangi password baru</label>
          <input class="input"
                 type="password"
                 id="ulangiKataSandiBaru"
                 name="ulangiKataSandiBaru"
                 required="required"
                 autocomplete="new-password" />
          <?= getPesanError($errors, 'ulangiKataSandiBaru') ?>
        </div>

        <div class="field full">
          <button type="submit" class="btn btn-primary">
            Ganti Password
          </button>
        </div>
      </form>
    </section>
  </div>
</div>

<?php tampilBawah(); ?>
