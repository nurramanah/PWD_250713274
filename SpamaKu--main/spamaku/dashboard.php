<?php
require 'inti.php';
$saya = requireMasuk();
$idSaya = (int)$saya['id'];
$kategori = getSemuaKategori();

// simpan poin yang disalin dari SIKMA
if (isPost()) {
    verifyCsrf();

    $inputPoin = isset($_POST['poin']) && is_array($_POST['poin'])
        ? $_POST['poin']
        : [];

    $simpan = $pdo->prepare('INSERT INTO poinSikma (idPengguna, idKategori, poin) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE poin = VALUES(poin)');

    // Semua kategori disimpan dalam satu transaksi agar tidak ada data yang tersimpan setengah.
    $pdo->beginTransaction();

    try {
        foreach ($kategori as $id => $k) {
            $angka = max(0, min(200, (int)($inputPoin[$id] ?? 0)));
            $simpan->execute([$idSaya, (int)$id, $angka]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        setPesan('bad', 'Poin gagal disimpan. Silakan coba lagi.');
        redirect('dashboard.php');
    }

    setPesan('ok', 'Poin dari SIKMA sudah disimpan. Progresmu diperbarui!');
    redirect('dashboard.php');
}

$poinSaya = getPoinPengguna($idSaya);

// hitung progres. poin lebih di satu kategori NGGAK nutup kategori lain
$totalMinimum = 0;
$terhitung = 0;
$kurang = [];

foreach ($kategori as $id => $k) {
    $totalMinimum += $k['poinMinimum'];
    $terhitung += min($poinSaya[$id], $k['poinMinimum']);

    if ($poinSaya[$id] < $k['poinMinimum']) {
        $kurang[$id] = $k['poinMinimum'] - $poinSaya[$id];
    }
}

$persen = $totalMinimum
    ? round($terhitung / $totalMinimum * 100)
    : 0;

arsort($kurang);

// REKOMENDASI: event yang ngisi kategori yang masih bolong
$rekomendasi = [];

if ($kurang) {
    $daftarIdKurang = implode(',', array_map('intval', array_keys($kurang)));

    // Urutan: kategori yang paling kurang, poin terbesar, lalu tanggal terdekat.
    $stmt = $pdo->prepare("SELECT a.*, o.nama AS namaOrganisasi
        FROM acara a
        JOIN organisasi o ON o.id = a.idOrganisasi
        WHERE a.idKategori IN ($daftarIdKurang)
          AND a.status = 'disetujui'
          AND a.tanggalMulai >= NOW()
          AND a.id NOT IN (
              SELECT idAcara
              FROM keranjang
              WHERE idPengguna = ?
          )
        ORDER BY FIELD(a.idKategori, $daftarIdKurang),
                 a.poin DESC,
                 a.tanggalMulai
        LIMIT 3");

    $stmt->execute([$idSaya]);
    $rekomendasi = $stmt->fetchAll();
}

// ---- event di keranjang yang belum lewat ----
$stmt = $pdo->prepare("SELECT a.*
    FROM keranjang k
    JOIN acara a ON a.id = k.idAcara
    WHERE k.idPengguna = ?
      AND a.tanggalMulai >= NOW()
    ORDER BY a.tanggalMulai
    LIMIT 5");

$stmt->execute([$idSaya]);
$akanDatang = $stmt->fetchAll();

$idKeranjang = getIdKeranjang();

tampilAtas('Dashboard', 'dashboard');
?>

<div class="page-head">
  <div>
    <p class="eyebrow">Dashboard</p>
    <h1>Halo, <?= escape(explode(' ', $saya['nama'])[0]) ?> 👋</h1>
    <p>
      <?= $kurang
          ? 'Masih ada ' . count($kurang) . ' kategori yang belum terpenuhi. Semangat!'
          : 'Semua kategori SPAMA sudah terpenuhi. Selamat! 🎉' ?>
    </p>
  </div>
  <a href="<?= url('jelajah.php') ?>" class="btn btn-neon">Cari Event</a>
</div>

<div class="grid grid-side">
  <section class="card reveal">
    <div class="card-title">
      <h2>Progres SPAMA</h2>
      <a href="#formSikma" class="btn btn-ghost btn-sm">✎ Update dari SIKMA</a>
    </div>

    <div class="progress-list">
      <?php foreach ($kategori as $id => $k):
          $p = $poinSaya[$id];
          $rasio = min(1, $p / max(1, $k['poinMinimum']));
      ?>
        <div class="progress-row<?= $p >= $k['poinMinimum'] ? ' done' : '' ?>"
             style="--c:<?= escape($k['warna']) ?>">
          <header>
            <b><?= escape($k['nama']) ?></b>
            <span><strong><?= $p ?></strong> / <?= (int)$k['poinMinimum'] ?> poin</span>
          </header>

          <div class="progress"
               role="progressbar"
               aria-valuemin="0"
               aria-valuemax="<?= (int)$k['poinMinimum'] ?>"
               aria-valuenow="<?= $p ?>"
               aria-label="<?= escape($k['nama']) ?>">
            <div class="progress__fill pending" style="--p:<?= round($rasio, 3) ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="card center reveal">
    <h2>Total Kelulusan</h2>

    <div class="ring" style="--p:<?= $persen ?>">
      <div>
        <b><?= $persen ?>%</b>
        <span><?= $terhitung ?> / <?= $totalMinimum ?> poin</span>
      </div>
    </div>

    <p class="small muted mt">
      Poin yang melebihi minimum satu kategori tidak menutupi kategori lain.
    </p>

    <?php if ($kurang): $idFokus = array_key_first($kurang); ?>
      <div class="alert alert-warn small" style="text-align:left">
        Fokus berikutnya:
        <b><?= escape($kategori[$idFokus]['nama']) ?></b>,
        kurang <?= $kurang[$idFokus] ?> poin.
      </div>
    <?php endif; ?>
  </aside>
</div>

<?php if ($rekomendasi): ?>
<section class="section" style="margin-top:2.5rem">
  <div class="page-head">
    <div>
      <p class="eyebrow">Disarankan untukmu</p>
      <h2 class="mb-0">Event untuk kategori yang masih kurang</h2>
    </div>

    <a href="<?= url('jelajah.php?kategori=' . array_key_first($kurang)) ?>"
       class="btn btn-ghost btn-sm">
      Lihat lainnya →
    </a>
  </div>

  <div class="event-grid">
    <?php foreach ($rekomendasi as $acara) kartuAcara($acara, $idKeranjang); ?>
  </div>
</section>
<?php endif; ?>

<div class="grid grid-2" style="margin-top:2.5rem">
  <section class="card reveal">
    <div class="card-title">
      <h3>Event incaran terdekat</h3>
      <a href="<?= url('keranjang.php') ?>" class="small">Keranjang →</a>
    </div>

    <?php if ($akanDatang): ?>
      <ul class="list">
        <?php foreach ($akanDatang as $a): ?>
          <li>
            <div>
              <a class="t" href="<?= url('jelajah.php?id=' . (int)$a['id']) ?>">
                <?= escape($a['judul']) ?>
              </a>
              <div class="s"><?= formatTanggal($a['tanggalMulai'], true) ?></div>
            </div>

            <div class="badges"><?= renderBadgePoin($a) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">
        Belum ada event incaran.
        <a href="<?= url('jelajah.php') ?>">Jelajah event</a>
        lalu tekan ♥ buat nyimpen.
      </p>
    <?php endif; ?>
  </section>

  <section class="card reveal" id="formSikma">
    <div class="card-title">
      <h3>Update poin dari SIKMA</h3>
    </div>

    <p class="small muted">
      Poin resmi tercatat di SIKMA. Salin angkanya ke sini supaya Spamaku!
      bisa menghitung kekurangan dan memberi rekomendasi event.
    </p>

    <form method="post" action="<?= url('dashboard.php') ?>" class="form-grid">
      <?= createInputCsrf() ?>

      <div class="poin-inputs">
        <?php foreach ($kategori as $id => $k): ?>
          <label class="poin-input" style="--c:<?= escape($k['warna']) ?>">
            <span><?= escape($k['nama']) ?></span>
            <input class="input"
                   type="number"
                   name="poin[<?= (int)$id ?>]"
                   min="0"
                   max="200"
                   value="<?= $poinSaya[$id] ?>" />
          </label>
        <?php endforeach; ?>
      </div>

      <div>
        <button type="submit" class="btn btn-neon">Simpan Poin</button>
      </div>
    </form>
  </section>
</div>

<?php tampilBawah(); ?>
