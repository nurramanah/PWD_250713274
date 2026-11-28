<?php

require 'inti.php';
$admin = requireAdmin();
$hal = $_GET['hal'] ?? 'ringkasan';
$kategori = getSemuaKategori();

// SEMUA AKSI POST (setujui, tolak, hapus, ganti peran) yahahahahahaha

if (isPost()) {
    verifyCsrf();

    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    // event
    if ($hal === 'acara') {
        if ($aksi === 'approve') {
            $pdo->prepare(
                "UPDATE acara
                 SET status='disetujui', catatanAdmin=NULL
                 WHERE id=?"
            )->execute([$id]);

            setPesan('ok', 'Event disetujui dan sekarang tampil di Jelajah.');

        } elseif ($aksi === 'reject') {
            $pdo->prepare(
                "UPDATE acara SET status='ditolak', catatanAdmin=? WHERE id=?"
            )->execute([
                trim($_POST['catatan'] ?? '') ?: 'Tidak memenuhi ketentuan.',
                $id,
            ]);

            setPesan('ok', 'Event ditolak.');

        } elseif ($aksi === 'delete') {
            $stmt = $pdo->prepare('SELECT poster FROM acara WHERE id = ?');
            $stmt->execute([$id]);
            $poster = $stmt->fetchColumn() ?: null;

            $hapus = $pdo->prepare('DELETE FROM acara WHERE id=?');
            $hapus->execute([$id]);

            if ($hapus->rowCount() > 0) {
                deleteUpload($poster, 'posters');
                setPesan('ok', 'Event dihapus.');
            } else {
                setPesan('warn', 'Event tidak ditemukan.');
            }
        }

        redirect(
            'admin.php?hal=acara&status=' .
            urlencode($_GET['status'] ?? 'menunggu')
        );
    }

    // organisasi
    if ($hal === 'organisasi') {
        if (in_array($aksi, ['disetujui', 'ditolak'], true)) {
            $pdo->prepare(
                'UPDATE organisasi SET status=? WHERE id=?'
            )->execute([$aksi, $id]);

            setPesan('ok', 'Status organisasi diubah menjadi ' . $aksi . '.');

        } elseif ($aksi === 'delete') {
            // Simpan nama poster sebelum organisasi dihapus karena event
            // ikut terhapus melalui ON DELETE CASCADE.
            $stmt = $pdo->prepare(
                'SELECT poster FROM acara WHERE idOrganisasi = ?'
            );
            $stmt->execute([$id]);
            $daftarPoster = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $hapus = $pdo->prepare(
                'DELETE FROM organisasi WHERE id=?'
            );
            $hapus->execute([$id]);

            if ($hapus->rowCount() > 0) {
                foreach ($daftarPoster as $poster) {
                    deleteUpload($poster ?: null, 'posters');
                }

                setPesan('ok', 'Organisasi beserta event-nya dihapus.');
            } else {
                setPesan('warn', 'Organisasi tidak ditemukan.');
            }
        }

        redirect('admin.php?hal=organisasi');
    }

    // pengguna
    if ($hal === 'pengguna') {
        if ($id === (int)$admin['id']) {
            // Admin tidak boleh mengubah atau menghapus akunnya sendiri.
            setPesan(
                'warn',
                'Kamu tidak bisa mengubah atau menghapus akunmu sendiri dari sini.'
            );

        } elseif ($aksi === 'role') {
            $pdo->prepare(
                'UPDATE pengguna SET peran=? WHERE id=?'
            )->execute([
                ($_POST['peran'] ?? '') === 'admin'
                    ? 'admin'
                    : 'mahasiswa',
                $id,
            ]);

            setPesan('ok', 'Peran pengguna diubah.');

        } elseif ($aksi === 'delete') {
            $stmt = $pdo->prepare(
                'SELECT foto FROM pengguna WHERE id=?'
            );
            $stmt->execute([$id]);
            $foto = $stmt->fetchColumn() ?: null;

            $hapus = $pdo->prepare(
                'DELETE FROM pengguna WHERE id=?'
            );
            $hapus->execute([$id]);

            if ($hapus->rowCount() > 0) {
                deleteUpload($foto, 'avatars');
                setPesan('ok', 'Pengguna dihapus.');
            } else {
                setPesan('warn', 'Pengguna tidak ditemukan.');
            }
        }

        redirect('admin.php?hal=pengguna');
    }
}

// tab status di halaman event
function tabStatus(
    string $hal,
    array $daftar,
    string $aktif,
    array $jumlah
): void {
    echo '<nav class="tabs">';

    foreach ($daftar as $kunci => $label) {
        echo '<a href="' . url("admin.php?hal=$hal&status=$kunci") . '" class="' .
            ($aktif === $kunci ? 'on' : '') . '">' .
            $label . ' (' . (int)($jumlah[$kunci] ?? 0) . ')</a>';
    }

    echo '</nav>';
}

// HALAMAN: VERIFIKASI EVENT
if ($hal === 'acara') {
    $status = in_array(
        $_GET['status'] ?? '',
        ['menunggu', 'disetujui', 'ditolak'],
        true
    ) ? $_GET['status'] : 'menunggu';

    $stmt = $pdo->prepare(
        sqlAcara() . ' WHERE a.status = ? ORDER BY a.tanggalMulai DESC'
    );
    $stmt->execute([$status]);

    $daftarAcara = $stmt->fetchAll();

    $jumlahPerStatus = $pdo->query(
        'SELECT status, COUNT(*) FROM acara GROUP BY status'
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    tampilAtas('Verifikasi Event', 'acara', 'admin');
    ?>

    <div class="page-head">
      <div>
        <p class="eyebrow">Admin</p>
        <h1>Event</h1>
      </div>
    </div>

    <?php
    tabStatus(
        'acara',
        [
            'menunggu' => 'Menunggu',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
        ],
        $status,
        $jumlahPerStatus
    );
    ?>

    <?php if ($daftarAcara): ?>
      <div class="table-wrap reveal">
        <table class="table">
          <thead>
            <tr>
              <th>Event</th>
              <th>Tanggal</th>
              <th>Poin</th>
              <th>Daftar</th>
              <th>Aksi</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($daftarAcara as $a):
                $urlAksi = url('admin.php?hal=acara&status=' . $status);
            ?>
              <tr>
                <td>
                  <a href="<?= url('jelajah.php?id=' . (int)$a['id']) ?>"
                     target="_blank"
                     rel="noopener">
                    <b><?= escape($a['judul']) ?></b>
                  </a>

                  <div class="small muted">
                    <?= escape($a['namaOrganisasi']) ?>
                    · <?= formatRupiah((int)$a['biaya']) ?>
                    · <?= escape($a['mode']) ?>
                  </div>

                  <?php if ($a['catatanAdmin']): ?>
                    <div class="small">
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

                <td class="small">
                  <?= $a['linkDaftar']
                      ? '<a href="' . escape($a['linkDaftar']) . '" target="_blank" rel="noopener">Link ↗</a>'
                      : '<span class="muted">-</span>' ?>
                </td>

                <td>
                  <div class="actions">
                    <?php if ($status !== 'disetujui'): ?>
                      <form method="post"
                            class="inline"
                            action="<?= $urlAksi ?>">
                        <?= createInputCsrf() ?>
                        <input type="hidden" name="aksi" value="approve" />
                        <input type="hidden"
                               name="id"
                               value="<?= (int)$a['id'] ?>" />
                        <button type="submit" class="btn btn-ok btn-sm">
                          ✓ Setujui
                        </button>
                      </form>
                    <?php endif; ?>

                    <?php if ($status !== 'ditolak'): ?>
                      <form method="post"
                            class="inline input-group"
                            action="<?= $urlAksi ?>">
                        <?= createInputCsrf() ?>
                        <input type="hidden" name="aksi" value="reject" />
                        <input type="hidden"
                               name="id"
                               value="<?= (int)$a['id'] ?>" />
                        <input class="input"
                               name="catatan"
                               placeholder="Alasan ditolak"
                               style="padding:.35rem .6rem;min-width:140px"
                               aria-label="Alasan" />
                        <button type="submit" class="btn btn-danger btn-sm">
                          Tolak
                        </button>
                      </form>
                    <?php endif; ?>

                    <a href="<?= url('penyelenggara.php?hal=form&id=' . (int)$a['id']) ?>"
                       class="btn btn-ghost btn-sm">
                      Edit
                    </a>

                    <form method="post"
                          class="inline"
                          action="<?= $urlAksi ?>"
                          data-confirm="Hapus event ini secara permanen?">
                      <?= createInputCsrf() ?>
                      <input type="hidden" name="aksi" value="delete" />
                      <input type="hidden"
                             name="id"
                             value="<?= (int)$a['id'] ?>" />
                      <button type="submit"
                              class="btn btn-ghost btn-sm"
                              title="Hapus">
                        🗑
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
        <div class="big">✅</div>
        <p>Tidak ada event di sini. Waktunya ngopi ☕</p>
      </div>
    <?php endif; ?>

    <?php
    tampilBawah();
    exit;
}

// HALAMAN: VERIFIKASI ORGANISASI
if ($hal === 'organisasi') {
    $daftarOrganisasi = $pdo->query(
        "SELECT o.*, u.nama AS pembuat, u.namaPengguna,
            (SELECT COUNT(*) FROM anggota a WHERE a.idOrganisasi = o.id) AS jumlahAnggota,
            (SELECT COUNT(*) FROM acara a WHERE a.idOrganisasi = o.id) AS jumlahAcara
         FROM organisasi o
         LEFT JOIN pengguna u ON u.id = o.dibuatOleh
         ORDER BY FIELD(o.status, 'menunggu', 'disetujui', 'ditolak'),
                  o.dibuatPada DESC"
    )->fetchAll();

    tampilAtas('Verifikasi Organisasi', 'organisasi', 'admin');
    ?>

    <div class="page-head">
      <div>
        <p class="eyebrow">Admin</p>
        <h1>Organisasi</h1>
        <p>Hanya organisasi yang disetujui yang bisa memasang event.</p>
      </div>
    </div>

    <div class="table-wrap reveal">
      <table class="table">
        <thead>
          <tr>
            <th>Organisasi</th>
            <th>Diajukan oleh</th>
            <th>Anggota</th>
            <th>Event</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($daftarOrganisasi as $o):
              $urlAksi = url('admin.php?hal=organisasi');
          ?>
            <tr>
              <td>
                <b><?= escape($o['nama']) ?></b>
                <div class="small muted">
                  <?= escape(mb_strimwidth($o['deskripsi'] ?? '', 0, 80, '…')) ?>
                </div>
              </td>

              <td class="small">
                <?= escape($o['pembuat'] ?? '-') ?>
                <br />
                <span class="muted">
                  @<?= escape($o['namaPengguna'] ?? '-') ?>
                </span>
              </td>

              <td><?= (int)$o['jumlahAnggota'] ?></td>
              <td><?= (int)$o['jumlahAcara'] ?></td>
              <td><?= renderLabelStatus($o['status']) ?></td>

              <td>
                <div class="actions">
                  <?php if ($o['status'] !== 'disetujui'): ?>
                    <form method="post"
                          class="inline"
                          action="<?= $urlAksi ?>">
                      <?= createInputCsrf() ?>
                      <input type="hidden" name="aksi" value="disetujui" />
                      <input type="hidden"
                             name="id"
                             value="<?= (int)$o['id'] ?>" />
                      <button type="submit" class="btn btn-ok btn-sm">
                        ✓ Setujui
                      </button>
                    </form>
                  <?php endif; ?>

                  <?php if ($o['status'] !== 'ditolak'): ?>
                    <form method="post"
                          class="inline"
                          action="<?= $urlAksi ?>">
                      <?= createInputCsrf() ?>
                      <input type="hidden" name="aksi" value="ditolak" />
                      <input type="hidden"
                             name="id"
                             value="<?= (int)$o['id'] ?>" />
                      <button type="submit" class="btn btn-danger btn-sm">
                        Tolak
                      </button>
                    </form>
                  <?php endif; ?>

                  <form method="post"
                        class="inline"
                        action="<?= $urlAksi ?>"
                        data-confirm="Hapus organisasi ini? Semua event-nya ikut terhapus.">
                    <?= createInputCsrf() ?>
                    <input type="hidden" name="aksi" value="delete" />
                    <input type="hidden"
                           name="id"
                           value="<?= (int)$o['id'] ?>" />
                    <button type="submit"
                            class="btn btn-ghost btn-sm"
                            title="Hapus">
                      🗑
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php
    tampilBawah();
    exit;
}

// HALAMAN: PENGGUNA
if ($hal === 'pengguna') {
    $cari = trim($_GET['q'] ?? '');

    $sql = "SELECT u.*,
            (SELECT COUNT(*) FROM keranjang k WHERE k.idPengguna = u.id) AS jumlahDisimpan
            FROM pengguna u";

    $param = [];

    if ($cari !== '') {
        $sql .= ' WHERE u.nama LIKE ? OR u.namaPengguna LIKE ? OR u.email LIKE ? OR u.npm LIKE ?';
        $param = array_fill(0, 4, "%$cari%");
    }

    $stmt = $pdo->prepare($sql . ' ORDER BY u.dibuatPada DESC');
    $stmt->execute($param);
    $daftarPengguna = $stmt->fetchAll();

    tampilAtas('Pengguna', 'pengguna', 'admin');
    ?>

    <div class="page-head">
      <div>
        <p class="eyebrow">Admin</p>
        <h1>Pengguna</h1>
        <p><?= count($daftarPengguna) ?> akun</p>
      </div>

      <form method="get"
            action="<?= url('admin.php') ?>"
            class="input-group">
        <input type="hidden" name="hal" value="pengguna" />
        <input class="input"
               type="search"
               name="q"
               value="<?= escape($cari) ?>"
               placeholder="Cari nama, username, NPM…"
               aria-label="Cari pengguna" />
        <button type="submit" class="btn btn-primary">Cari</button>
      </form>
    </div>

    <div class="table-wrap reveal">
      <table class="table">
        <thead>
          <tr>
            <th>Pengguna</th>
            <th>NPM / Prodi</th>
            <th>Poin</th>
            <th>Bergabung</th>
            <th>Peran</th>
            <th></th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($daftarPengguna as $u):
              $isDiriSendiri = (int)$u['id'] === (int)$admin['id'];
          ?>
            <tr>
              <td>
                <div class="user-cell">
                  <img src="<?= escape(getUrlAvatar($u)) ?>"
                       alt=""
                       class="avatar avatar-md"
                       width="44"
                       height="44" />

                  <div>
                    <b><?= escape($u['nama']) ?></b>
                    <?= $isDiriSendiri
                        ? ' <span class="muted small">(kamu)</span>'
                        : '' ?>

                    <div class="small muted">
                      @<?= escape($u['namaPengguna']) ?>
                      · <?= escape($u['email']) ?>
                    </div>
                  </div>
                </div>
              </td>

              <td class="small">
                <?= escape($u['npm'] ?: '-') ?>
                <br />
                <span class="muted"><?= escape($u['prodi'] ?: '') ?></span>
              </td>

              <td>
                <b><?= array_sum(getPoinPengguna((int)$u['id'])) ?></b>
                <span class="small muted">
                  (♥ <?= (int)$u['jumlahDisimpan'] ?>)
                </span>
              </td>

              <td class="small">
                <?= formatTanggal($u['dibuatPada']) ?>
              </td>

              <td>
                <?php if ($isDiriSendiri): ?>
                  <span class="pill pill-warn">admin</span>
                <?php else: ?>
                  <form method="post"
                        class="inline"
                        action="<?= url('admin.php?hal=pengguna') ?>">
                    <?= createInputCsrf() ?>
                    <input type="hidden" name="aksi" value="role" />
                    <input type="hidden"
                           name="id"
                           value="<?= (int)$u['id'] ?>" />

                    <select class="input"
                            name="peran"
                            data-auto-submit=""
                            style="padding:.35rem .5rem;width:auto"
                            aria-label="Peran">
                      <option value="mahasiswa"<?= terpilih($u['peran'], 'mahasiswa') ?>>
                        mahasiswa
                      </option>
                      <option value="admin"<?= terpilih($u['peran'], 'admin') ?>>
                        admin
                      </option>
                    </select>
                  </form>
                <?php endif; ?>
              </td>

              <td>
                <?php if (!$isDiriSendiri): ?>
                  <form method="post"
                        class="inline"
                        action="<?= url('admin.php?hal=pengguna') ?>"
                        data-confirm="Hapus akun <?= escape($u['namaPengguna']) ?>? Semua datanya ikut terhapus.">
                    <?= createInputCsrf() ?>
                    <input type="hidden" name="aksi" value="delete" />
                    <input type="hidden"
                           name="id"
                           value="<?= (int)$u['id'] ?>" />
                    <button type="submit" class="btn btn-danger btn-sm">
                      Hapus
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php
    tampilBawah();
    exit;
}

// HALAMAN: RINGKASAN (default)
$hitung = $pdo->query("SELECT
    (SELECT COUNT(*) FROM pengguna) AS pengguna,
    (SELECT COUNT(*) FROM acara WHERE status='disetujui') AS acara,
    (SELECT COUNT(*) FROM keranjang) AS disimpan,
    (SELECT COUNT(*) FROM organisasi WHERE status='disetujui') AS organisasi,
    (SELECT COUNT(*) FROM acara WHERE status='menunggu') AS antreanAcara,
    (SELECT COUNT(*) FROM organisasi WHERE status='menunggu') AS antreanOrganisasi")->fetch();

$acaraTerbaru = $pdo->query(
    "SELECT a.id, a.judul, o.nama AS namaOrganisasi
     FROM acara a
     JOIN organisasi o ON o.id = a.idOrganisasi
     WHERE a.status='menunggu'
     ORDER BY a.dibuatPada
     LIMIT 5"
)->fetchAll();

// rata-rata poin semua mahasiswa (dari angka SIKMA yang mereka isi).
$jumlahMahasiswa = (int)$pdo->query(
    "SELECT COUNT(*) FROM pengguna WHERE peran='mahasiswa'"
)->fetchColumn();

$total = array_fill_keys(array_keys($kategori), 0);

foreach ($pdo->query(
    "SELECT idKategori, SUM(poin) t
     FROM poinSikma
     GROUP BY idKategori"
) as $r) {
    $total[$r['idKategori']] = (int)$r['t'];
}

tampilAtas('Admin', 'ringkasan', 'admin');
?>

<div class="page-head">
  <div>
    <p class="eyebrow">Admin</p>
    <h1>Ringkasan</h1>
    <p>Verifikasi organisasi dan event dari sini.</p>
  </div>
</div>

<div class="stats" style="margin:0 0 1.5rem">
  <div class="stat reveal">
    <b><?= (int)$hitung['pengguna'] ?></b>
    <span>Pengguna</span>
  </div>

  <div class="stat reveal">
    <b><?= (int)$hitung['acara'] ?></b>
    <span>Event aktif</span>
  </div>

  <div class="stat reveal">
    <b><?= (int)$hitung['disimpan'] ?></b>
    <span>Event disimpan</span>
  </div>

  <div class="stat reveal">
    <b class="neon"><?= (int)$hitung['organisasi'] ?></b>
    <span>Organisasi aktif</span>
  </div>
</div>

<div class="grid grid-2">
  <?php
  foreach (
      [
          'acara' => ['antreanAcara', 'event'],
          'organisasi' => ['antreanOrganisasi', 'organisasi'],
      ] as $tujuan => [$kunci, $label]
  ):
  ?>
    <a href="<?= url('admin.php?hal=' . $tujuan) ?>"
       class="card glow-hover reveal"
       style="color:inherit;text-decoration:none">
      <p class="eyebrow">Antrean</p>
      <h2 class="mb-0">
        <?= (int)$hitung[$kunci] ?> <?= $label ?>
      </h2>
      <p class="muted mb-0">menunggu verifikasi →</p>
    </a>
  <?php endforeach; ?>
</div>

<div class="grid mt">
  <section class="card reveal">
    <h3>Event terbaru menunggu</h3>

    <?php if ($acaraTerbaru): ?>
      <ul class="list">
        <?php foreach ($acaraTerbaru as $r): ?>
          <li>
            <div>
              <a class="t"
                 href="<?= url('jelajah.php?id=' . (int)$r['id']) ?>">
                <?= escape($r['judul']) ?>
              </a>
              <div class="s"><?= escape($r['namaOrganisasi']) ?></div>
            </div>

            <a href="<?= url('admin.php?hal=acara') ?>"
               class="btn btn-ghost btn-sm">
              Tinjau
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted mb-0">Tidak ada antrean. 🎉</p>
    <?php endif; ?>
  </section>
</div>

<section class="card mt reveal">
  <h3>
    Rata-rata poin mahasiswa per kategori
    <span class="small muted">(dari SIKMA)</span>
  </h3>

  <div class="progress-list">
    <?php foreach ($kategori as $id => $k):
        $rataRata = $jumlahMahasiswa
            ? $total[$id] / $jumlahMahasiswa
            : 0;
    ?>
      <div class="progress-row" style="--c:<?= escape($k['warna']) ?>">
        <header>
          <b><?= escape($k['nama']) ?></b>
          <span>
            <strong><?= number_format($rataRata, 1, ',', '.') ?></strong>
            / <?= (int)$k['poinMinimum'] ?>
          </span>
        </header>

        <div class="progress">
          <div class="progress__fill pending"
               style="--p:<?= round(min(1, $rataRata / $k['poinMinimum']), 3) ?>">
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php tampilBawah(); ?>
