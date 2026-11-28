<?php

require 'inti.php';

$saya = requireMasuk();
$idSaya = (int)$saya['id'];
$hal = $_GET['hal'] ?? 'kelola';
$errors = [];


// HALAMAN: ORGANISASI
if ($hal === 'organisasi') {
    if (isPost()) {
        verifyCsrf();

        $aksi = $_POST['aksi'] ?? '';
        $idOrganisasi = (int)($_POST['idOrganisasi'] ?? 0);

        if ($aksi === 'create') {
            $nama = trim($_POST['nama'] ?? '');

            $stmt = $pdo->prepare(
                'SELECT 1 FROM organisasi WHERE nama = ?'
            );
            $stmt->execute([$nama]);

            if (mb_strlen($nama) < 3) {
                $errors['nama'] = 'Nama organisasi minimal 3 karakter.';
            } elseif ($stmt->fetch()) {
                $errors['nama'] = 'Organisasi dengan nama itu sudah ada.';
            }

            if (!$errors) {
                // Kedua INSERT harus berhasil bersama-sama agar organisasi tidak tanpa ketua.
                $statusAwal = $saya['peran'] === 'admin'
                    ? 'disetujui'
                    : 'menunggu';

                try {
                    $pdo->beginTransaction();

                    $pdo->prepare(
                        'INSERT INTO organisasi (nama, deskripsi, status, dibuatOleh)
                         VALUES (?, ?, ?, ?)'
                    )->execute([
                        $nama,
                        trim($_POST['deskripsi'] ?? '') ?: null,
                        $statusAwal,
                        $idSaya,
                    ]);

                    $idBaru = (int)$pdo->lastInsertId();

                    $pdo->prepare(
                        "INSERT INTO anggota (idOrganisasi, idPengguna, jabatan)
                         VALUES (?, ?, 'ketua')"
                    )->execute([$idBaru, $idSaya]);

                    $pdo->commit();

                    setPesan(
                        'ok',
                        $statusAwal === 'disetujui'
                            ? 'Organisasi dibuat dan langsung aktif.'
                            : 'Organisasi diajukan. Tunggu verifikasi admin sebelum memasang event.'
                    );

                    redirect('penyelenggara.php?hal=organisasi');

                } catch (Throwable $error) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $errors['umum'] = 'Organisasi gagal dibuat. Silakan coba lagi.';
                }
            }
        }

        // Aksi ini hanya boleh dilakukan oleh ketua organisasi.
        if (in_array($aksi, ['update', 'tambahAnggota', 'keluarkanAnggota'], true)) {
            if (!isAnggotaOrganisasi($idSaya, $idOrganisasi, true)) {
                setPesan('bad', 'Hanya ketua organisasi yang bisa melakukan ini.');

            } elseif ($aksi === 'update') {
                $pdo->prepare(
                    'UPDATE organisasi SET deskripsi = ? WHERE id = ?'
                )->execute([
                    trim($_POST['deskripsi'] ?? '') ?: null,
                    $idOrganisasi,
                ]);

                setPesan('ok', 'Deskripsi organisasi disimpan.');

            } elseif ($aksi === 'tambahAnggota') {
                $stmt = $pdo->prepare(
                    'SELECT id, nama FROM pengguna WHERE namaPengguna = ?'
                );
                $stmt->execute([
                    strtolower(trim($_POST['namaPengguna'] ?? '')),
                ]);

                $orang = $stmt->fetch();

                if (!$orang) {
                    setPesan(
                        'bad',
                        'User @' . ($_POST['namaPengguna'] ?? '') . ' tidak ditemukan.'
                    );
                } else {
                    $pdo->prepare(
                        'INSERT IGNORE INTO anggota (idOrganisasi, idPengguna)
                         VALUES (?, ?)'
                    )->execute([$idOrganisasi, $orang['id']]);

                    setPesan(
                        'ok',
                        $orang['nama'] . ' ditambahkan sebagai anggota.'
                    );
                }

            } else {
                $idAnggota = (int)($_POST['idPengguna'] ?? 0);

                if ($idAnggota === $idSaya) {
                    setPesan(
                        'warn',
                        'Ketua tidak bisa mengeluarkan dirinya sendiri. Resign dulu lah 😅'
                    );
                } else {
                    $pdo->prepare(
                        'DELETE FROM anggota
                         WHERE idOrganisasi = ? AND idPengguna = ?'
                    )->execute([$idOrganisasi, $idAnggota]);

                    setPesan('ok', 'Anggota dikeluarkan.');
                }
            }

            redirect('penyelenggara.php?hal=organisasi');
        }
    }

    $daftarOrganisasi = getOrganisasiPengguna($idSaya, false);
    $daftarAnggota = [];

    foreach ($daftarOrganisasi as $o) {
        $stmt = $pdo->prepare(
            "SELECT a.jabatan, u.id, u.nama, u.namaPengguna, u.foto
             FROM anggota a
             JOIN pengguna u ON u.id = a.idPengguna
             WHERE a.idOrganisasi = ?
             ORDER BY a.jabatan = 'ketua' DESC, u.nama"
        );

        $stmt->execute([$o['id']]);
        $daftarAnggota[$o['id']] = $stmt->fetchAll();
    }

    tampilAtas('Organisasi', 'organisasi', 'penyelenggara');
    ?>

    <div class="page-head">
      <div>
        <p class="eyebrow">Penyelenggara</p>
        <h1>Organisasi Saya</h1>
        <p>Event dipasang atas nama organisasi, jadi tetap aman walau pengurus berganti.</p>
      </div>
    </div>

    <div class="grid grid-side">
      <div class="grid" style="gap:1.25rem">
        <?php if (!$daftarOrganisasi): ?>
          <div class="card empty">
            <div class="big">🏛️</div>
            <p>
              Kamu belum tergabung di organisasi mana pun.<br />
              Buat organisasi baru, atau minta ketua organisasimu
              menambahkan username
              <b>@<?= escape($saya['namaPengguna']) ?></b>.
            </p>
          </div>
        <?php endif; ?>

        <?php foreach ($daftarOrganisasi as $o):
            $isKetua = $o['jabatan'] === 'ketua';
        ?>
          <section class="card reveal">
            <div class="card-title">
              <div>
                <h2 class="mb-0"><?= escape($o['nama']) ?></h2>
                <span class="small muted">
                  Kamu: <?= escape($o['jabatan']) ?>
                </span>
              </div>

              <?= renderLabelStatus($o['status']) ?>
            </div>

            <?php if ($o['status'] === 'menunggu'): ?>
              <div class="alert alert-warn small">
                Menunggu verifikasi admin. Event belum bisa dipasang.
              </div>
            <?php elseif ($o['status'] === 'ditolak'): ?>
              <div class="alert alert-bad small">
                Pengajuan ditolak admin. Hubungi admin untuk informasi lebih lanjut.
              </div>
            <?php endif; ?>

            <?php if ($isKetua): ?>
              <form method="post"
                    action="<?= url('penyelenggara.php?hal=organisasi') ?>"
                    class="form-grid">
                <?= createInputCsrf() ?>
                <input type="hidden" name="aksi" value="update" />
                <input type="hidden"
                       name="idOrganisasi"
                       value="<?= (int)$o['id'] ?>" />

                <div class="field">
                  <label for="desk<?= (int)$o['id'] ?>">Deskripsi</label>
                  <textarea class="input"
                            id="desk<?= (int)$o['id'] ?>"
                            name="deskripsi"
                            style="min-height:80px"><?= escape($o['deskripsi']) ?></textarea>
                </div>

                <div>
                  <button type="submit" class="btn btn-ghost btn-sm">
                    Simpan deskripsi
                  </button>
                </div>
              </form>
            <?php else: ?>
              <p class="muted">
                <?= escape($o['deskripsi'] ?: 'Belum ada deskripsi.') ?>
              </p>
            <?php endif; ?>

            <h3 class="mt">
              Anggota (<?= count($daftarAnggota[$o['id']]) ?>)
            </h3>

            <ul class="list">
              <?php foreach ($daftarAnggota[$o['id']] as $m): ?>
                <li>
                  <div class="user-cell">
                    <img src="<?= escape(getUrlAvatar($m)) ?>"
                         alt=""
                         class="avatar avatar-sm"
                         width="32"
                         height="32" />

                    <div>
                      <span class="t"><?= escape($m['nama']) ?></span>
                      <span class="s">
                        @<?= escape($m['namaPengguna']) ?>
                        · <?= escape($m['jabatan']) ?>
                      </span>
                    </div>
                  </div>

                  <?php if ($isKetua && (int)$m['id'] !== $idSaya): ?>
                    <form method="post"
                          action="<?= url('penyelenggara.php?hal=organisasi') ?>"
                          class="inline"
                          data-confirm="Keluarkan <?= escape($m['nama']) ?>?">
                      <?= createInputCsrf() ?>
                      <input type="hidden"
                             name="aksi"
                             value="keluarkanAnggota" />
                      <input type="hidden"
                             name="idOrganisasi"
                             value="<?= (int)$o['id'] ?>" />
                      <input type="hidden"
                             name="idPengguna"
                             value="<?= (int)$m['id'] ?>" />

                      <button type="submit" class="btn btn-danger btn-sm">
                        Keluarkan
                      </button>
                    </form>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>

            <?php if ($isKetua): ?>
              <form method="post"
                    action="<?= url('penyelenggara.php?hal=organisasi') ?>"
                    class="input-group mt">
                <?= createInputCsrf() ?>
                <input type="hidden" name="aksi" value="tambahAnggota" />
                <input type="hidden"
                       name="idOrganisasi"
                       value="<?= (int)$o['id'] ?>" />

                <input class="input"
                       name="namaPengguna"
                       placeholder="Tambah anggota lewat username"
                       required="required"
                       aria-label="Username anggota baru" />

                <button type="submit" class="btn btn-primary">
                  Tambah
                </button>
              </form>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>

      <aside class="card sticky reveal">
        <h2>Buat Organisasi</h2>
        <p class="small muted">
          Contoh: BEM, UKM, himpunan, atau kepanitiaan acara.
        </p>

        <?php if (isset($errors['umum'])): ?>
          <div class="alert alert-bad">
            <?= escape($errors['umum']) ?>
          </div>
        <?php endif; ?>

        <form method="post"
              action="<?= url('penyelenggara.php?hal=organisasi') ?>"
              class="form-grid">
          <?= createInputCsrf() ?>
          <input type="hidden" name="aksi" value="create" />

          <div class="field">
            <label for="nama">
              Nama organisasi <span class="req">*</span>
            </label>
            <input class="input"
                   id="nama"
                   name="nama"
                   value="<?= getNilaiLama('nama') ?>"
                   maxlength="100"
                   required="required" />
            <?= getPesanError($errors, 'nama') ?>
          </div>

          <div class="field">
            <label for="deskripsi">Deskripsi</label>
            <textarea class="input"
                      id="deskripsi"
                      name="deskripsi"
                      style="min-height:90px"><?= getNilaiLama('deskripsi') ?></textarea>
          </div>

          <button type="submit" class="btn btn-neon">
            Ajukan Organisasi
          </button>
        </form>
      </aside>
    </div>

    <?php
    tampilBawah();
    exit;
}


// HALAMAN: FORM EVENT (Create & Update)
if ($hal === 'form') {
    $kategori = getSemuaKategori();
    $id = (int)($_GET['id'] ?? 0);
    $acara = $id ? requirePengelolaAcara($id) : null;

    $daftarOrganisasi = getOrganisasiPengguna($idSaya);

    if ($acara && $saya['peran'] === 'admin') {
        // Admin boleh mengedit event organisasi mana saja.
        $stmt = $pdo->prepare(
            'SELECT * FROM organisasi WHERE id = ?'
        );
        $stmt->execute([$acara['idOrganisasi']]);
        $daftarOrganisasi[] = $stmt->fetch();
    }

    if (!$daftarOrganisasi) {
        setPesan(
            'warn',
            'Untuk membuat event, kamu harus jadi anggota organisasi yang SUDAH disetujui admin. Buat organisasi di bawah, lalu minta admin menyetujuinya.'
        );

        redirect('penyelenggara.php?hal=organisasi');
    }

    $daftarIdOrganisasi = array_map(
        'intval',
        array_column($daftarOrganisasi, 'id')
    );

    if (isPost()) {
        verifyCsrf();

        $data = [
            'idOrganisasi' => (int)($_POST['idOrganisasi'] ?? 0),
            'judul' => trim($_POST['judul'] ?? ''),
            'deskripsi' => trim($_POST['deskripsi'] ?? ''),
            'tanggalMulai' => $_POST['tanggalMulai'] ?? '',
            'tanggalSelesai' => $_POST['tanggalSelesai'] ?? '',
            'mode' => ($_POST['mode'] ?? '') === 'online'
                ? 'online'
                : 'offline',
            'lokasi' => trim($_POST['lokasi'] ?? ''),
            'lintang' => trim($_POST['lintang'] ?? ''),
            'bujur' => trim($_POST['bujur'] ?? ''),
            'biaya' => max(0, (int)($_POST['biaya'] ?? 0)),
            'linkDaftar' => trim($_POST['linkDaftar'] ?? ''),
            'idKategori' => (int)($_POST['idKategori'] ?? 0),
            'poin' => (int)($_POST['poin'] ?? 0),
            'jenisKeatmajayaan' => $_POST['jenisKeatmajayaan'] ?? '',
            'susulan' => !empty($_POST['susulan']) ? 1 : 0,
        ];

        // Jika bukan Keatmajayaan, kolom khusus Keatmajayaan dikosongkan.
        if (!isKeatmajayaan($data['idKategori'])) {
            $data['jenisKeatmajayaan'] = '';
            $data['susulan'] = 0;
        }

        // validasi
        $mulai = strtotime($data['tanggalMulai']);

        $selesai = $data['tanggalSelesai'] !== ''
            ? strtotime($data['tanggalSelesai'])
            : null;

        if (!in_array($data['idOrganisasi'], $daftarIdOrganisasi, true)) {
            $errors['idOrganisasi'] = 'Pilih organisasi.';
        }

        if (mb_strlen($data['judul']) < 5) {
            $errors['judul'] = 'Judul minimal 5 karakter.';
        }

        if (mb_strlen($data['deskripsi']) < 20) {
            $errors['deskripsi'] = 'Deskripsi minimal 20 karakter.';
        }

        if (!$mulai) {
            $errors['tanggalMulai'] = 'Tanggal mulai wajib diisi.';
        }

        if (
            $data['tanggalSelesai'] !== '' &&
            (!$selesai || ($mulai && $selesai < $mulai))
        ) {
            $errors['tanggalSelesai'] = 'Tanggal selesai harus setelah tanggal mulai.';
        }

        if ($data['lokasi'] === '') {
            $errors['lokasi'] = $data['mode'] === 'online'
                ? 'Isi platform (mis. Zoom).'
                : 'Lokasi wajib diisi.';
        }

        if (
            ($data['lintang'] !== '' || $data['bujur'] !== '') &&
            (
                !is_numeric($data['lintang']) ||
                !is_numeric($data['bujur']) ||
                abs((float)$data['lintang']) > 90 ||
                abs((float)$data['bujur']) > 180
            )
        ) {
            $errors['lintang'] = 'Koordinat tidak valid.';
        }

        // Link harus berupa URL http atau https.
        if (
            $data['linkDaftar'] !== '' &&
            (
                !filter_var($data['linkDaftar'], FILTER_VALIDATE_URL) ||
                !preg_match('#^https?://#i', $data['linkDaftar'])
            )
        ) {
            $errors['linkDaftar'] = 'Link harus berupa alamat web lengkap, contoh: https://forms.gle/abc123';
        }

        // Aturan: satu event hanya memberikan satu jenis poin SPAMA.
        if (!isset($kategori[$data['idKategori']])) {
            $errors['idKategori'] = 'Pilih satu jenis poin SPAMA.';
        }

        if ($data['poin'] < 1 || $data['poin'] > 50) {
            $errors['poin'] = 'Poin harus 1 sampai 50. Event tanpa poin? Siapa yang mau dateng 🥲';
        }

        // Keatmajayaan hanya dari kegiatan resmi yang ditentukan.
        if (
            isKeatmajayaan($data['idKategori']) &&
            !in_array($data['jenisKeatmajayaan'], daftarKeatmajayaan, true)
        ) {
            $errors['jenisKeatmajayaan'] = 'Poin Keatmajayaan hanya untuk LDPKM, UKM Fair, Jogja Istimewa, atau PKKMB. Pilih salah satu.';
        }

        // Poster lama tetap disimpan sampai perubahan event berhasil masuk database.
        $posterLama = $acara['poster'] ?? null;
        $poster = $posterLama;
        $posterBaru = null;

        if (!$errors && !empty($_POST['hapusPoster'])) {
            $poster = null;
        }

        if (!$errors && hasUpload('poster')) {
            try {
                $posterBaru = uploadGambar($_FILES['poster'], 'posters');
                $poster = $posterBaru;
            } catch (Exception $e) {
                $errors['poster'] = $e->getMessage();
            }
        }

        if (!$errors) {
            $tanpaKoordinat = $data['mode'] === 'online'
                || $data['lintang'] === '';

            $param = [
                $data['idOrganisasi'],
                $data['judul'],
                $data['deskripsi'],
                date('Y-m-d H:i:s', $mulai),
                $selesai ? date('Y-m-d H:i:s', $selesai) : null,
                $data['mode'],
                $data['lokasi'],
                $tanpaKoordinat ? null : (float)$data['lintang'],
                $tanpaKoordinat ? null : (float)$data['bujur'],
                $data['biaya'],
                $data['linkDaftar'] ?: null,
                $poster,
                $data['idKategori'],
                $data['poin'],
                $data['jenisKeatmajayaan'] ?: null,
                $data['susulan'],
            ];

            try {
                if ($acara) {
                    // Event yang ditolak dan diperbaiki diajukan ulang ke admin.
                    $param[] = (
                        $acara['status'] === 'ditolak' &&
                        $saya['peran'] !== 'admin'
                    ) ? 'menunggu' : $acara['status'];

                    $param[] = $id;

                    $pdo->prepare(
                        'UPDATE acara
                         SET idOrganisasi=?, judul=?, deskripsi=?, tanggalMulai=?,
                             tanggalSelesai=?, mode=?, lokasi=?, lintang=?, bujur=?,
                             biaya=?, linkDaftar=?, poster=?, idKategori=?, poin=?,
                             jenisKeatmajayaan=?, susulan=?, status=?
                         WHERE id=?'
                    )->execute($param);

                } else {
                    $pdo->prepare(
                        'INSERT INTO acara
                         (idOrganisasi, judul, deskripsi, tanggalMulai,
                          tanggalSelesai, mode, lokasi, lintang, bujur,
                          biaya, linkDaftar, poster, idKategori, poin,
                          jenisKeatmajayaan, susulan)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute($param);
                }

                if ($posterLama && $posterLama !== $poster) {
                    deleteUpload($posterLama, 'posters');
                }

                setPesan(
                    'ok',
                    $acara
                        ? 'Event diperbarui.'
                        : 'Event dibuat dan menunggu verifikasi admin.'
                );

                redirect('penyelenggara.php');

            } catch (Throwable $error) {
                if ($posterBaru) {
                    deleteUpload($posterBaru, 'posters');
                }

                $errors['umum'] = 'Event gagal disimpan. Periksa kembali data dan coba lagi.';
            }
        }
    }

    // Nilai untuk input datetime-local menggunakan format YYYY-MM-DDTHH:MM.
    $nilai = isPost()
        ? $_POST
        : ($acara ?? ['mode' => 'offline', 'biaya' => 0]);

    $keInput = fn($w) => $w
        ? date('Y-m-d\TH:i', strtotime($w))
        : '';

    $nilaiMulai = isPost()
        ? ($nilai['tanggalMulai'] ?? '')
        : $keInput($nilai['tanggalMulai'] ?? null);

    $nilaiSelesai = isPost()
        ? ($nilai['tanggalSelesai'] ?? '')
        : $keInput($nilai['tanggalSelesai'] ?? null);

    $isDaring = ($nilai['mode'] ?? 'offline') === 'online';
    $kategoriTerpilih = (int)($nilai['idKategori'] ?? 0);
    $isKaj = isKeatmajayaan($kategoriTerpilih);
    $urlPosterIni = $acara ? getUrlPoster($acara) : null;

    $adaPeta = is_numeric($nilai['lintang'] ?? '')
        && is_numeric($nilai['bujur'] ?? '');

    tampilAtas(
        $acara ? 'Edit Event' : 'Event Baru',
        $acara ? 'kelola' : 'form',
        'penyelenggara'
    );
    ?>

    <p class="small">
      <a href="<?= url('penyelenggara.php') ?>">← Kembali ke Kelola Event</a>
    </p>

    <div class="page-head">
      <div>
        <p class="eyebrow">Penyelenggara</p>
        <h1><?= $acara ? 'Edit Event' : 'Buat Event Baru' ?></h1>

        <?php if ($acara): ?>
          <p>Status saat ini: <?= renderLabelStatus($acara['status']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-bad">
        Ada isian yang perlu diperbaiki.
        <?= isset($errors['umum']) ? escape($errors['umum']) : '' ?>
      </div>
    <?php endif; ?>

    <form method="post"
          action="<?= url('penyelenggara.php?hal=form' . ($acara ? '&id=' . $id : '')) ?>"
          enctype="multipart/form-data">
      <?= createInputCsrf() ?>

      <div class="grid grid-side">
        <div class="grid" style="gap:1.25rem">
          <section class="card">
            <h2>Informasi utama</h2>

            <div class="form-grid cols-2">
              <div class="field full">
                <label for="judul">Judul event <span class="req">*</span></label>
                <input class="input"
                       id="judul"
                       name="judul"
                       value="<?= escape($nilai['judul'] ?? '') ?>"
                       maxlength="150"
                       required="required" />
                <?= getPesanError($errors, 'judul') ?>
              </div>

              <div class="field full">
                <label for="idOrganisasi">
                  Penyelenggara <span class="req">*</span>
                </label>

                <select class="input"
                        id="idOrganisasi"
                        name="idOrganisasi"
                        required="required">
                  <?php foreach ($daftarOrganisasi as $o): ?>
                    <option value="<?= (int)$o['id'] ?>"
                      <?= terpilih($nilai['idOrganisasi'] ?? '', $o['id']) ?>>
                      <?= escape($o['nama']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <?= getPesanError($errors, 'idOrganisasi') ?>
              </div>

              <div class="field full">
                <label for="deskripsi">
                  Deskripsi <span class="req">*</span>
                </label>
                <textarea class="input"
                          id="deskripsi"
                          name="deskripsi"
                          required="required"
                          style="min-height:160px"><?= escape($nilai['deskripsi'] ?? '') ?></textarea>
                <?= getPesanError($errors, 'deskripsi') ?>
              </div>

              <div class="field">
                <label for="tanggalMulai">
                  Mulai <span class="req">*</span>
                </label>
                <input class="input"
                       type="datetime-local"
                       id="tanggalMulai"
                       name="tanggalMulai"
                       value="<?= escape($nilaiMulai) ?>"
                       required="required" />
                <?= getPesanError($errors, 'tanggalMulai') ?>
              </div>

              <div class="field">
                <label for="tanggalSelesai">Selesai</label>
                <input class="input"
                       type="datetime-local"
                       id="tanggalSelesai"
                       name="tanggalSelesai"
                       value="<?= escape($nilaiSelesai) ?>" />
                <?= getPesanError($errors, 'tanggalSelesai') ?>
              </div>

              <div class="field">
                <label for="biaya">Biaya (Rp)</label>
                <input class="input"
                       type="number"
                       id="biaya"
                       name="biaya"
                       min="0"
                       step="1000"
                       value="<?= escape($nilai['biaya'] ?? 0) ?>" />
                <span class="hint">0 = gratis</span>
              </div>

              <div class="field">
                <label for="linkDaftar">Link pendaftaran</label>
                <input class="input"
                       type="url"
                       id="linkDaftar"
                       name="linkDaftar"
                       value="<?= escape($nilai['linkDaftar'] ?? '') ?>"
                       maxlength="255"
                       placeholder="https://forms.gle/..." />
                <span class="hint">
                  Google Form / web pendaftaran resmi panitia
                </span>
                <?= getPesanError($errors, 'linkDaftar') ?>
              </div>
            </div>
          </section>

          <section class="card">
            <h2>Poin SPAMA <span class="req">*</span></h2>

            <p class="small muted">
              Satu event hanya memberi <b>satu jenis</b> poin SPAMA.
              Pilih jenisnya, lalu isi jumlah poinnya.
            </p>

            <div class="kat-pilih"
                 role="radiogroup"
                 aria-label="Jenis poin SPAMA">
              <?php foreach ($kategori as $idKat => $k): ?>
                <label class="kat-opsi"
                       style="--c:<?= escape($k['warna']) ?>">
                  <input type="radio"
                         name="idKategori"
                         value="<?= (int)$idKat ?>"
                         required="required"
                         <?= $kategoriTerpilih === (int)$idKat
                             ? 'checked="checked"'
                             : '' ?>
                         data-kategori="<?= escape($k['singkatan']) ?>" />

                  <span class="kat-opsi__nama">
                    <?= escape($k['nama']) ?>
                  </span>

                  <span class="kat-opsi__kode">
                    <?= escape($k['singkatan']) ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>

            <?= getPesanError($errors, 'idKategori') ?>

            <div class="form-grid cols-2" style="margin-top:1rem">
              <div class="field">
                <label for="poin">
                  Jumlah poin <span class="req">*</span>
                </label>
                <input class="input"
                       type="number"
                       id="poin"
                       name="poin"
                       min="1"
                       max="50"
                       value="<?= escape($nilai['poin'] ?? '') ?>"
                       required="required"
                       placeholder="mis. 3" />
                <?= getPesanError($errors, 'poin') ?>
              </div>
            </div>

            <!-- Muncul hanya jika kategori Keatmajayaan dipilih. -->
            <div class="kaj-box<?= $isKaj ? '' : ' hidden' ?>" id="kajBox">
              <div class="field">
                <label for="jenisKeatmajayaan">
                  Kegiatan Keatmajayaan <span class="req">*</span>
                </label>

                <select class="input"
                        id="jenisKeatmajayaan"
                        name="jenisKeatmajayaan"
                        <?= $isKaj ? 'required="required"' : '' ?>>
                  <option value="">— Pilih kegiatan —</option>

                  <?php foreach (daftarKeatmajayaan as $jenis): ?>
                    <option value="<?= escape($jenis) ?>"
                      <?= terpilih($nilai['jenisKeatmajayaan'] ?? '', $jenis) ?>>
                      <?= escape($jenis) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <span class="hint">
                  Poin Keatmajayaan hanya bisa didapat dari LDPKM,
                  UKM Fair, Jogja Istimewa, dan PKKMB.
                </span>

                <?= getPesanError($errors, 'jenisKeatmajayaan') ?>
              </div>

              <label class="check">
                <input type="checkbox"
                       name="susulan"
                       value="1"
                       <?= !empty($nilai['susulan']) ? 'checked="checked"' : '' ?> />
                Ini kegiatan <b>susulan</b>
              </label>
            </div>
          </section>

          <section class="card">
            <h2>Tempat</h2>

            <div class="form-grid cols-2">
              <div class="field full">
                <span class="label">Jenis event</span>

                <div class="btn-row">
                  <label class="check">
                    <input type="radio"
                           name="mode"
                           value="offline"
                           <?= !$isDaring ? 'checked="checked"' : '' ?>
                           data-mode="" />
                    Offline
                  </label>

                  <label class="check">
                    <input type="radio"
                           name="mode"
                           value="online"
                           <?= $isDaring ? 'checked="checked"' : '' ?>
                           data-mode="" />
                    Online
                  </label>
                </div>
              </div>

              <div class="field full">
                <label for="lokasi" id="lokasiLabel">
                  <?= $isDaring ? 'Platform' : 'Nama lokasi' ?>
                  <span class="req">*</span>
                </label>

                <input class="input"
                       id="lokasi"
                       name="lokasi"
                       value="<?= escape($nilai['lokasi'] ?? '') ?>"
                       maxlength="200"
                       required="required"
                       placeholder="Gedung Bonaventura, Kampus 3" />
                <?= getPesanError($errors, 'lokasi') ?>
              </div>

              <div class="field full<?= $isDaring ? ' hidden' : '' ?>"
                   id="coordBox">
                <span class="label">
                  Koordinat peta (opsional, untuk fitur "event terdekat")
                </span>

                <div class="form-grid cols-2">
                  <input class="input"
                         id="lintang"
                         name="lintang"
                         value="<?= escape($nilai['lintang'] ?? '') ?>"
                         placeholder="Latitude, mis. -7.7795"
                         aria-label="Latitude" />

                  <input class="input"
                         id="bujur"
                         name="bujur"
                         value="<?= escape($nilai['bujur'] ?? '') ?>"
                         placeholder="Longitude, mis. 110.4148"
                         aria-label="Longitude" />
                </div>

                <div class="btn-row" style="margin-top:.5rem">
                  <button type="button"
                          class="btn btn-ghost btn-sm"
                          id="useMyLocation">
                    📍 Pakai lokasi saya
                  </button>

                  <span class="small muted" id="locMsg">
                    Atau salin koordinat dari Google Maps (klik kanan → koordinat).
                  </span>
                </div>

                <?= getPesanError($errors, 'lintang') ?>

                <?php if ($adaPeta):
                    $la = (float)$nilai['lintang'];
                    $lo = (float)$nilai['bujur'];
                ?>
                  <iframe id="mapPreview"
                          class="map-frame"
                          title="Pratinjau peta"
                          loading="lazy"
                          src="https://www.openstreetmap.org/export/embed.html?bbox=<?= $lo - .004 ?>,<?= $la - .004 ?>,<?= $lo + .004 ?>,<?= $la + .004 ?>&amp;layer=mapnik&amp;marker=<?= $la ?>,<?= $lo ?>">
                  </iframe>
                <?php else: ?>
                  <iframe id="mapPreview"
                          class="map-frame hidden"
                          title="Pratinjau peta"
                          loading="lazy">
                  </iframe>
                <?php endif; ?>
              </div>
            </div>
          </section>
        </div>

        <aside class="card sticky">
          <h2>Poster</h2>

          <div class="detail-hero"
               style="aspect-ratio:16/9;margin-bottom:1rem">
            <img id="posterPreview"
                 src="<?= escape($urlPosterIni ?? '') ?>"
                 alt=""
                 class="<?= $urlPosterIni ? '' : 'hidden' ?>" />

            <?php if (!$urlPosterIni): ?>
              <div class="poster-ph" id="posterPh">POSTER</div>
            <?php endif; ?>
          </div>

          <div class="field">
            <input class="input"
                   type="file"
                   id="poster"
                   name="poster"
                   accept="image/jpeg,image/png,image/webp"
                   data-preview="posterPreview" />

            <span class="hint">
              Rasio 16:9 disarankan. Maks 2 MB.
            </span>

            <?php if ($urlPosterIni): ?>
              <label class="check small">
                <input type="checkbox" name="hapusPoster" value="1" />
                Hapus poster
              </label>
            <?php endif; ?>

            <?= getPesanError($errors, 'poster') ?>
          </div>

          <hr />

          <?php if (!$acara): ?>
            <p class="small muted">
              Setelah disimpan, event akan diperiksa admin sebelum tampil
              di halaman Jelajah.
            </p>
          <?php endif; ?>

          <button type="submit" class="btn btn-neon btn-block btn-lg">
            <?= $acara ? 'Simpan Perubahan' : 'Ajukan Event' ?>
          </button>
        </aside>
      </div>
    </form>

    <?php
    tampilBawah();
    exit;
}


// HALAMAN UTAMA: KELOLA EVENT (Read + Delete)
if (isPost() && ($_POST['aksi'] ?? '') === 'delete') {
    verifyCsrf();

    $acara = requirePengelolaAcara((int)$_POST['id']);

    $pdo->prepare(
        'DELETE FROM acara WHERE id = ?'
    )->execute([$acara['id']]);

    deleteUpload($acara['poster'], 'posters');

    setPesan(
        'ok',
        'Event "' . $acara['judul'] . '" dihapus. Selamat jalan 🫡'
    );

    redirect('penyelenggara.php');
}

$daftarOrganisasi = getOrganisasiPengguna($idSaya);
$organisasiMenunggu = array_filter(
    getOrganisasiPengguna($idSaya, false),
    fn($o) => $o['status'] !== 'disetujui'
);

$filterOrganisasi = (int)($_GET['org'] ?? 0);
$daftarAcara = [];

if ($daftarOrganisasi) {
    $daftarIdOrganisasi = array_map(
        'intval',
        array_column($daftarOrganisasi, 'id')
    );

    if (in_array($filterOrganisasi, $daftarIdOrganisasi, true)) {
        $daftarIdOrganisasi = [$filterOrganisasi];
    }

    $tanda = implode(
        ',',
        array_fill(0, count($daftarIdOrganisasi), '?')
    );

    $stmt = $pdo->prepare(
        "SELECT a.*, o.nama AS namaOrganisasi,
            (SELECT COUNT(*) FROM keranjang k WHERE k.idAcara = a.id) AS disimpan
         FROM acara a
         JOIN organisasi o ON o.id = a.idOrganisasi
         WHERE a.idOrganisasi IN ($tanda)
         ORDER BY a.tanggalMulai DESC"
    );

    $stmt->execute($daftarIdOrganisasi);
    $daftarAcara = $stmt->fetchAll();
}

$hitung = [
    'total' => count($daftarAcara),
    'aktif' => 0,
    'menunggu' => 0,
    'disimpan' => 0,
];

foreach ($daftarAcara as $a) {
    if ($a['status'] === 'menunggu') {
        $hitung['menunggu']++;
    }

    if ($a['status'] === 'disetujui' && !isAcaraSelesai($a)) {
        $hitung['aktif']++;
    }

    $hitung['disimpan'] += (int)$a['disimpan'];
}

tampilAtas('Kelola Event', 'kelola', 'penyelenggara');
?>

<div class="page-head">
  <div>
    <p class="eyebrow">Penyelenggara</p>
    <h1>Kelola Event</h1>
    <p>
      Pasang event beserta link pendaftarannya supaya gampang ditemukan mahasiswa.
    </p>
  </div>

  <?php if ($daftarOrganisasi): ?>
    <a href="<?= url('penyelenggara.php?hal=form') ?>"
       class="btn btn-neon">
      + Event Baru
    </a>
  <?php endif; ?>
</div>

<?php if (!$daftarOrganisasi): ?>
  <div class="card empty reveal">
    <div class="big">🎪</div>
    <h2>Belum bisa memasang event</h2>
    <p>Event selalu dipasang atas nama organisasi. Caranya:</p>

    <ol class="langkah">
      <li>
        Buat organisasi di halaman <b>Organisasi</b> atau minta ketua
        organisasimu menambahkan username
        <b>@<?= escape($saya['namaPengguna']) ?></b>.
      </li>
      <li>
        Tunggu admin menyetujui organisasinya
        (admin: menu <b>Mode Admin → Organisasi</b>).
      </li>
      <li>
        Setelah disetujui, tombol <b>+ Event Baru</b> akan muncul di sini.
      </li>
    </ol>

    <?php foreach ($organisasiMenunggu as $o): ?>
      <p class="small">
        <?= escape($o['nama']) ?>:
        <?= renderLabelStatus($o['status']) ?>
      </p>
    <?php endforeach; ?>

    <a href="<?= url('penyelenggara.php?hal=organisasi') ?>"
       class="btn btn-neon">
      Buat / Lihat Organisasi
    </a>
  </div>

<?php else: ?>
  <div class="stats" style="margin:0 0 1.5rem">
    <div class="stat reveal">
      <b class="neon"><?= $hitung['total'] ?></b>
      <span>Total event</span>
    </div>

    <div class="stat reveal">
      <b><?= $hitung['aktif'] ?></b>
      <span>Aktif / mendatang</span>
    </div>

    <div class="stat reveal">
      <b><?= $hitung['menunggu'] ?></b>
      <span>Menunggu verifikasi</span>
    </div>

    <div class="stat reveal">
      <b><?= $hitung['disimpan'] ?></b>
      <span>Kali disimpan mahasiswa</span>
    </div>
  </div>

  <?php if (count($daftarOrganisasi) > 1): ?>
    <div class="chips">
      <a href="<?= url('penyelenggara.php') ?>"
         class="chip<?= !$filterOrganisasi ? ' on' : '' ?>">
        Semua organisasi
      </a>

      <?php foreach ($daftarOrganisasi as $o): ?>
        <a href="<?= url('penyelenggara.php?org=' . (int)$o['id']) ?>"
           class="chip<?= $filterOrganisasi === (int)$o['id'] ? ' on' : '' ?>">
          <?= escape($o['nama']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($daftarAcara): ?>
    <div class="table-wrap reveal">
      <table class="table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Tanggal</th>
            <th>Poin</th>
            <th>Disimpan</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($daftarAcara as $a): ?>
            <tr>
              <td>
                <a href="<?= url('jelajah.php?id=' . (int)$a['id']) ?>">
                  <b><?= escape($a['judul']) ?></b>
                </a>

                <div class="small muted">
                  <?= escape($a['namaOrganisasi']) ?>
                  <?= isAcaraSelesai($a) ? ' · selesai' : '' ?>
                </div>

                <?php if ($a['status'] === 'ditolak' && $a['catatanAdmin']): ?>
                  <div class="small" style="color:var(--bad)">
                    💬 <?= escape($a['catatanAdmin']) ?>
                  </div>
                <?php endif; ?>
              </td>

              <td class="small">
                <?= formatTanggal($a['tanggalMulai'], true) ?>
              </td>

              <td>
                <div class="badges">
                  <?= renderBadgePoin($a) . renderLabelKeatmajayaan($a) ?>
                </div>
              </td>

              <td class="small">♥ <?= (int)$a['disimpan'] ?></td>
              <td><?= renderLabelStatus($a['status']) ?></td>

              <td>
                <div class="actions">
                  <a href="<?= url('penyelenggara.php?hal=form&id=' . (int)$a['id']) ?>"
                     class="btn btn-ghost btn-sm">
                    Edit
                  </a>

                  <form method="post"
                        action="<?= url('penyelenggara.php') ?>"
                        class="inline"
                        data-confirm="Hapus event ini? Data pendaftar juga akan terhapus.">
                    <?= createInputCsrf() ?>
                    <input type="hidden" name="aksi" value="delete" />
                    <input type="hidden"
                           name="id"
                           value="<?= (int)$a['id'] ?>" />

                    <button type="submit" class="btn btn-danger btn-sm">
                      Hapus
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php else: ?>
    <div class="card empty">
      <div class="big">📅</div>
      <p>Belum ada event.</p>
      <a href="<?= url('penyelenggara.php?hal=form') ?>"
         class="btn btn-neon">
        Buat event pertama
      </a>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php tampilBawah(); ?>
