<?php

require 'inti.php';
$saya = getPenggunaAktif();
$kategori = getSemuaKategori();

// BAGIAN 1: DETAIL EVENT (kalau ada ?id=)
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $pdo->prepare(
        'SELECT a.*, o.nama AS namaOrganisasi, o.deskripsi AS deskripsiOrganisasi
         FROM acara a
         JOIN organisasi o ON o.id = a.idOrganisasi
         WHERE a.id = ?'
    );
    $stmt->execute([$id]);
    $acara = $stmt->fetch();

    $bisaKelola = $acara && $saya && (
        $saya['peran'] === 'admin' ||
        isAnggotaOrganisasi((int)$saya['id'], (int)$acara['idOrganisasi'])
    );

    // Event yang belum disetujui admin cuma boleh dilihat panitianya sendiri.
    if (!$acara || ($acara['status'] !== 'disetujui' && !$bisaKelola)) {
        http_response_code(404);
        tampilAtas('Event tidak ditemukan');

        echo '<div class="card empty">
                <div class="big">🕳️</div>
                <p>Event tidak ditemukan. Mungkin udah dihapus, atau emang nggak pernah ada.</p>
                <a class="btn btn-ghost btn-sm" href="' . url('jelajah.php') . '">
                  Kembali ke Jelajah
                </a>
              </div>';

        tampilBawah();
        exit;
    }

    $isLewat = isAcaraSelesai($acara);
    $inKeranjang = in_array($id, getIdKeranjang(), true);
    $pertama = $kategori[(int)$acara['idKategori']] ?? null;
    $poster = getUrlPoster($acara);

    tampilAtas($acara['judul'], 'jelajah');
    ?>

    <p class="small">
      <a href="<?= url('jelajah.php') ?>">← Kembali ke Jelajah</a>
    </p>

    <?php if ($acara['status'] !== 'disetujui'): ?>
      <div class="alert alert-warn">
        Pratinjau penyelenggara — status event:
        <?= renderLabelStatus($acara['status']) ?>
        <?= $acara['catatanAdmin']
            ? '· Catatan admin: ' . escape($acara['catatanAdmin'])
            : '' ?>
      </div>
    <?php endif; ?>

    <div class="detail-hero reveal">
      <?php if ($poster): ?>
        <img src="<?= escape($poster) ?>"
             alt="Poster <?= escape($acara['judul']) ?>" />
      <?php else: ?>
        <div class="poster-ph"
             style="--c:<?= escape($pertama['warna'] ?? '#F3AD1B') ?>">
          <?= escape($pertama['singkatan'] ?? 'EVT') ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="grid grid-side">
      <article class="card reveal">
        <div class="badges mb">
          <?= renderBadgePoin($acara) ?>
          <?= renderLabelKeatmajayaan($acara) ?>
        </div>

        <h1><?= escape($acara['judul']) ?></h1>
        <p class="muted">
          oleh <b><?= escape($acara['namaOrganisasi']) ?></b>
        </p>

        <hr />

        <h3>Tentang event</h3>
        <p class="prose"><?= escape($acara['deskripsi']) ?></p>

        <?php if ($acara['mode'] === 'offline' && $acara['lintang'] !== null):
            $la = (float)$acara['lintang'];
            $lo = (float)$acara['bujur'];
            $d = 0.004;
        ?>
          <h3 class="mt">Lokasi</h3>
          <p class="muted mb-0"><?= escape($acara['lokasi']) ?></p>

          <iframe class="map-frame"
                  title="Peta lokasi"
                  loading="lazy"
                  src="https://www.openstreetmap.org/export/embed.html?bbox=<?= $lo - $d ?>,<?= $la - $d ?>,<?= $lo + $d ?>,<?= $la + $d ?>&amp;layer=mapnik&amp;marker=<?= $la ?>,<?= $lo ?>">
          </iframe>

          <p class="small mt-0">
            <a href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= $la ?>,<?= $lo ?>"
               target="_blank"
               rel="noopener">
              Buka rute di Google Maps ↗
            </a>
          </p>
        <?php endif; ?>

        <?php if ($acara['deskripsiOrganisasi']): ?>
          <h3 class="mt">Tentang penyelenggara</h3>
          <p class="muted mb-0">
            <?= escape($acara['deskripsiOrganisasi']) ?>
          </p>
        <?php endif; ?>
      </article>

      <aside class="card sticky reveal">
        <ul class="info-list">
          <li>
            <span>📅</span>
            <div>
              <small>Tanggal</small>
              <?= formatTanggal($acara['tanggalMulai'], true) ?>

              <?php if ($acara['tanggalSelesai']): ?>
                <br />
                <span class="muted small">
                  s.d. <?= formatTanggal($acara['tanggalSelesai'], true) ?>
                </span>
              <?php endif; ?>
            </div>
          </li>

          <li>
            <span><?= $acara['mode'] === 'online' ? '💻' : '📍' ?></span>
            <div>
              <small><?= $acara['mode'] === 'online' ? 'Online' : 'Lokasi' ?></small>
              <?= escape($acara['lokasi'] ?: '-') ?>
            </div>
          </li>

          <li>
            <span>💰</span>
            <div>
              <small>Biaya</small>
              <b class="neon"><?= formatRupiah((int)$acara['biaya']) ?></b>
            </div>
          </li>

          <li>
            <span>⭐</span>
            <div>
              <small>Poin SPAMA</small>
              <?= (int)$acara['poin'] ?> poin
              <?= escape($pertama['nama'] ?? '') ?>
              <?= $acara['jenisKeatmajayaan']
                  ? ' (' . escape($acara['jenisKeatmajayaan'])
                    . ($acara['susulan'] ? ', susulan' : '')
                    . ')'
                  : '' ?>
            </div>
          </li>
        </ul>

        <hr />

        <?php if ($isLewat): ?>
          <div class="alert alert-info mb-0">
            Event ini sudah selesai. Kalau kamu ikut, jangan lupa cek poinnya
            di SIKMA lalu update di Dashboard.
          </div>

        <?php elseif ($acara['status'] === 'disetujui'): ?>

          <?php if ($acara['linkDaftar']): ?>
            <a href="<?= escape($acara['linkDaftar']) ?>"
               target="_blank"
               rel="noopener"
               class="btn btn-neon btn-block btn-lg">
              Daftar di Link Panitia ↗
            </a>
          <?php else: ?>
            <div class="alert alert-info mb-0">
              Info pendaftaran belum dicantumkan panitia. Pantau terus ya.
            </div>
          <?php endif; ?>

          <?php if ($saya): ?>
            <form method="post"
                  action="<?= url('keranjang.php') ?>"
                  data-fav=""
                  style="margin-top:.6rem">
              <?= createInputCsrf() ?>
              <input type="hidden" name="aksi" value="toggle" />
              <input type="hidden" name="idAcara" value="<?= $id ?>" />

              <button type="submit"
                      class="btn btn-ghost btn-block<?= $inKeranjang ? ' on' : '' ?>">
                <span data-fav-label="">
                  <?= $inKeranjang ? 'Di Keranjang ✓' : '+ Keranjang' ?>
                </span>
              </button>
            </form>
          <?php else: ?>
            <p class="small muted center" style="margin:.6rem 0 0">
              <a href="<?= url('masuk.php') ?>">Masuk</a>
              untuk menyimpan event ke keranjang.
            </p>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($bisaKelola): ?>
          <hr />
          <div class="btn-row">
            <a href="<?= url('penyelenggara.php?hal=form&id=' . $id) ?>"
               class="btn btn-primary btn-sm">
              Edit Event
            </a>
          </div>
        <?php endif; ?>
      </aside>
    </div>

    <?php
    tampilBawah();
    exit;
}

// BAGIAN 2: DAFTAR EVENT + FILTER
$cari = trim($_GET['q'] ?? '');
$idKategori = (int)($_GET['kategori'] ?? 0);
$waktu = $_GET['waktu'] ?? 'mendatang';
$biaya = $_GET['biaya'] ?? '';
$urutan = $_GET['sort'] ?? 'tanggal';

$lintang = is_numeric($_GET['lat'] ?? '') ? (float)$_GET['lat'] : null;
$bujur = is_numeric($_GET['lng'] ?? '') ? (float)$_GET['lng'] : null;

$adaLokasi = $lintang !== null
    && $bujur !== null
    && abs($lintang) <= 90
    && abs($bujur) <= 180;

if ($urutan === 'jarak' && !$adaLokasi) {
    $urutan = 'tanggal';
}

// query dirakit dengan parameter agar aman dari SQL injection
$kolom = "a.*, o.nama AS namaOrganisasi";
$param = [];

if ($adaLokasi) {
    // Rumus Haversine untuk menghitung jarak berdasarkan koordinat.
    $kolom .= ", CASE
        WHEN a.lintang IS NULL THEN NULL
        ELSE 6371 * ACOS(LEAST(1,
            COS(RADIANS(?)) * COS(RADIANS(a.lintang))
            * COS(RADIANS(a.bujur) - RADIANS(?))
            + SIN(RADIANS(?)) * SIN(RADIANS(a.lintang))
        ))
        END AS jarak";

    array_push($param, $lintang, $bujur, $lintang);
}

$syarat = ["a.status = 'disetujui'"];

if ($cari !== '') {
    $syarat[] = '(a.judul LIKE ? OR a.deskripsi LIKE ? OR o.nama LIKE ?)';
    array_push($param, "%$cari%", "%$cari%", "%$cari%");
}

if (isset($kategori[$idKategori])) {
    $syarat[] = 'a.idKategori = ?';
    $param[] = $idKategori;
}

if ($waktu === 'mendatang') {
    $syarat[] = 'COALESCE(a.tanggalSelesai, a.tanggalMulai) >= NOW()';
}

if ($waktu === 'selesai') {
    $syarat[] = 'COALESCE(a.tanggalSelesai, a.tanggalMulai) < NOW()';
}

if ($biaya === 'gratis') {
    $syarat[] = 'a.biaya = 0';
}

if ($biaya === 'berbayar') {
    $syarat[] = 'a.biaya > 0';
}

$urutanSql = [
    'tanggal' => $waktu === 'selesai'
        ? 'a.tanggalMulai DESC'
        : 'a.tanggalMulai ASC',
    'poin' => 'a.poin DESC, a.tanggalMulai ASC',
    'terbaru' => 'a.dibuatPada DESC',
    'jarak' => 'jarak IS NULL, jarak ASC',
][$urutan] ?? 'a.tanggalMulai ASC';

$stmt = $pdo->prepare(
    "SELECT $kolom
     FROM acara a
     JOIN organisasi o ON o.id = a.idOrganisasi
     WHERE " . implode(' AND ', $syarat) . "
     ORDER BY $urutanSql"
);
$stmt->execute($param);

$daftarAcara = $stmt->fetchAll();
$idKeranjang = getIdKeranjang();

// bikin URL filter baru tapi filter lain tetap kebawa
function urlFilter(array $ubah): string
{
    $param = array_filter(
        array_merge($_GET, $ubah),
        fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0'
    );

    return url(
        'jelajah.php' . ($param ? '?' . http_build_query($param) : '')
    );
}

tampilAtas('Jelajah Event', 'jelajah');
?>

<div class="page-head">
  <div>
    <p class="eyebrow">Jelajah</p>
    <h1>Temukan event untuk SPAMA-mu</h1>
    <p><?= count($daftarAcara) ?> event ditemukan</p>
  </div>

  <div class="btn-row">
    <button type="button"
            class="btn <?= $adaLokasi ? 'btn-neon' : 'btn-ghost' ?>"
            id="geoBtn">
      📍 <?= $adaLokasi ? 'Urut terdekat aktif' : 'Event terdekat' ?>
    </button>

    <?php if ($adaLokasi): ?>
      <a href="<?= urlFilter(['lat' => '', 'lng' => '', 'sort' => '']) ?>"
         class="btn btn-ghost btn-sm">
        Matikan lokasi
      </a>
    <?php endif; ?>
  </div>
</div>

<p class="geo-status" id="geoStatus"></p>

<div class="chips">
  <a href="<?= urlFilter(['kategori' => '']) ?>"
     class="chip<?= !$idKategori ? ' on' : '' ?>">
    Semua
  </a>

  <?php foreach ($kategori as $k): ?>
    <a href="<?= urlFilter(['kategori' => $k['id']]) ?>"
       class="chip<?= $idKategori === (int)$k['id'] ? ' on' : '' ?>"
       style="--c:<?= escape($k['warna']) ?>">
      <?= escape($k['nama']) ?>
    </a>
  <?php endforeach; ?>
</div>

<form method="get" action="<?= url('jelajah.php') ?>" class="toolbar">
  <?php if ($idKategori): ?>
    <input type="hidden" name="kategori" value="<?= $idKategori ?>" />
  <?php endif; ?>

  <?php if ($adaLokasi): ?>
    <input type="hidden" name="lat" value="<?= escape($lintang) ?>" />
    <input type="hidden" name="lng" value="<?= escape($bujur) ?>" />
  <?php endif; ?>

  <input class="input"
         type="search"
         name="q"
         value="<?= escape($cari) ?>"
         placeholder="Cari judul, deskripsi, atau organisasi…"
         aria-label="Cari event" />

  <select class="input" name="waktu" aria-label="Waktu">
    <option value="mendatang"<?= terpilih($waktu, 'mendatang') ?>>
      Mendatang
    </option>
    <option value="selesai"<?= terpilih($waktu, 'selesai') ?>>
      Sudah selesai
    </option>
    <option value="semua"<?= terpilih($waktu, 'semua') ?>>
      Semua waktu
    </option>
  </select>

  <select class="input" name="biaya" aria-label="Biaya">
    <option value="">Semua biaya</option>
    <option value="gratis"<?= terpilih($biaya, 'gratis') ?>>
      Gratis
    </option>
    <option value="berbayar"<?= terpilih($biaya, 'berbayar') ?>>
      Berbayar
    </option>
  </select>

  <select class="input" name="sort" aria-label="Urutkan">
    <option value="tanggal"<?= terpilih($urutan, 'tanggal') ?>>
      Urut: tanggal
    </option>
    <option value="poin"<?= terpilih($urutan, 'poin') ?>>
      Urut: poin terbanyak
    </option>
    <option value="terbaru"<?= terpilih($urutan, 'terbaru') ?>>
      Urut: baru ditambahkan
    </option>

    <?php if ($adaLokasi): ?>
      <option value="jarak"<?= terpilih($urutan, 'jarak') ?>>
        Urut: jarak terdekat
      </option>
    <?php endif; ?>
  </select>

  <button type="submit" class="btn btn-primary">Terapkan</button>
</form>

<?php if ($daftarAcara): ?>
  <div class="event-grid">
    <?php foreach ($daftarAcara as $acara): ?>
      <?php kartuAcara($acara, $idKeranjang); ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card empty">
    <div class="big">🔍</div>
    <p>
      Tidak ada event yang cocok dengan filtermu.
      Coba longgarin dikit filternya.
    </p>
    <a href="<?= url('jelajah.php') ?>" class="btn btn-ghost btn-sm">
      Reset filter
    </a>
  </div>
<?php endif; ?>

<?php tampilBawah(); ?>
