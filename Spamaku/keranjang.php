<?php

require 'inti.php';

$isAjax = !empty($_POST['ajax']);
if ($isAjax && !getPenggunaAktif()) {
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['berhasil' => false, 'alihkan' => url('masuk.php')]));
}

$saya = requireMasuk();
$idSaya = (int)$saya['id'];

// tombol hapus: masuk keranjang kalau belum ada, keluar kalau udah ada
if (isPost()) {
    verifyCsrf();

    $aksi = $_POST['aksi'] ?? '';
    $idAcara = (int)($_POST['idAcara'] ?? 0);

    if (!in_array($aksi, ['toggle', 'remove'], true) || $idAcara <= 0) {
        http_response_code(400);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode([
                'berhasil' => false,
                'pesan' => 'Permintaan tidak valid.',
            ]));
        }

        setPesan('bad', 'Permintaan keranjang tidak valid.');
        redirect('keranjang.php');
    }

    $stmt = $pdo->prepare(
        'DELETE FROM keranjang WHERE idPengguna = ? AND idAcara = ?'
    );
    $stmt->execute([$idSaya, $idAcara]);

    $inKeranjang = false;
    $berhasil = true;

    if ($stmt->rowCount() === 0 && $aksi === 'toggle') {
        // Hanya event yang sudah disetujui yang boleh disimpan.
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO keranjang (idPengguna, idAcara)
             SELECT ?, id FROM acara
             WHERE id = ? AND status = 'disetujui'"
        );
        $stmt->execute([$idSaya, $idAcara]);

        $inKeranjang = $stmt->rowCount() > 0;
        $berhasil = $inKeranjang;
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');

        exit(json_encode([
            'berhasil' => $berhasil,
            'inKeranjang' => $inKeranjang,
            'jumlah' => count(getIdKeranjang()),
        ]));
    }

    if (!$berhasil) {
        setPesan('bad', 'Event tidak ditemukan atau belum disetujui.');
    } else {
        setPesan(
            'ok',
            $inKeranjang
                ? 'Ditambahkan ke keranjang.'
                : 'Dihapus dari keranjang.'
        );
    }

    redirect('keranjang.php');
}

$stmt = $pdo->prepare(
    sqlAcara() .
    ' JOIN keranjang k ON k.idAcara = a.id
      WHERE k.idPengguna = ?
      ORDER BY a.tanggalMulai'
);
$stmt->execute([$idSaya]);
$isiKeranjang = $stmt->fetchAll();

// simulasi: "kalau semua diikuti, poinku jadi berapa ya?"
$kategori = getSemuaKategori();
$poinSekarang = getPoinPengguna($idSaya);
$tambah = array_fill_keys(array_keys($kategori), 0);
$totalBiaya = 0;

foreach ($isiKeranjang as $item) {
    if (isAcaraSelesai($item)) {
        continue;
    }

    $totalBiaya += (int)$item['biaya'];
    $tambah[$item['idKategori']] += (int)$item['poin'];
}

tampilAtas('Keranjang', 'keranjang');
?>

<div class="page-head">
  <div>
    <p class="eyebrow">Keranjang</p>
    <h1>Event incaranmu</h1>
    <p>
      <?= count($isiKeranjang) ?> event disimpan.
      Daftarnya lewat link resmi panitia masing-masing.
    </p>
  </div>
</div>

<?php if (!$isiKeranjang): ?>
  <div class="card empty">
    <div class="big">🛒</div>
    <p>
      Keranjangmu masih kosong.
      Tekan ♥ di kartu event untuk menyimpannya di sini.
    </p>
    <a href="<?= url('jelajah.php') ?>" class="btn btn-neon">
      Jelajah Event
    </a>
  </div>
<?php else: ?>
  <div class="grid grid-side">
    <div class="table-wrap reveal">
      <table class="table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Poin</th>
            <th>Biaya</th>
            <th></th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($isiKeranjang as $item):
              $isLewat = isAcaraSelesai($item);
          ?>
            <tr>
              <td>
                <a href="<?= url('jelajah.php?id=' . (int)$item['id']) ?>">
                  <b><?= escape($item['judul']) ?></b>
                </a>

                <div class="small muted">
                  <?= formatTanggal($item['tanggalMulai'], true) ?>
                  · <?= escape($item['namaOrganisasi']) ?>
                </div>

                <?php if ($isLewat): ?>
                  <span class="pill pill-bad">Sudah selesai</span>
                <?php endif; ?>
              </td>

              <td>
                <div class="badges"><?= renderBadgePoin($item) ?></div>
              </td>

              <td><?= formatRupiah((int)$item['biaya']) ?></td>

              <td>
                <div class="actions">
                  <?php if (!$isLewat && $item['linkDaftar']): ?>
                    <a href="<?= escape($item['linkDaftar']) ?>"
                       target="_blank"
                       rel="noopener"
                       class="btn btn-neon btn-sm">
                      Daftar ↗
                    </a>
                  <?php endif; ?>

                  <form method="post"
                        action="<?= url('keranjang.php') ?>"
                        class="inline">
                    <?= createInputCsrf() ?>
                    <input type="hidden" name="aksi" value="remove" />
                    <input type="hidden"
                           name="idAcara"
                           value="<?= (int)$item['id'] ?>" />
                    <button type="submit"
                            class="btn btn-danger btn-sm"
                            title="Hapus">
                      ✕
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <aside class="card sticky reveal">
      <h3>Ringkasan</h3>
      <p class="small muted">
        Kalau kamu mengikuti semua event di keranjang:
      </p>

      <div class="progress-list" style="gap:.8rem">
        <?php foreach ($kategori as $id => $k):
            $setelah = $poinSekarang[$id] + $tambah[$id];
        ?>
          <div class="progress-row" style="--c:<?= escape($k['warna']) ?>">
            <header>
              <b class="small"><?= escape($k['nama']) ?></b>
              <span>
                <?= $poinSekarang[$id] ?>
                <?= $tambah[$id]
                    ? ' <strong>+' . $tambah[$id] . '</strong>'
                    : '' ?>
                / <?= (int)$k['poinMinimum'] ?>
              </span>
            </header>

            <!-- Bar transparan menunjukkan poin setelah mengikuti event. -->
            <div class="progress" style="position:relative">
              <div class="progress__fill"
                   style="--p:<?= round(min(1, $setelah / $k['poinMinimum']), 3) ?>;opacity:.35;position:absolute;inset:0">
              </div>

              <div class="progress__fill pending"
                   style="--p:<?= round(min(1, $poinSekarang[$id] / $k['poinMinimum']), 3) ?>;position:relative">
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <hr />

      <p class="mb-0" style="display:flex;justify-content:space-between">
        <span>Total biaya</span>
        <b class="neon"><?= formatRupiah($totalBiaya) ?></b>
      </p>
    </aside>
  </div>
<?php endif; ?>

<?php tampilBawah(); ?>
