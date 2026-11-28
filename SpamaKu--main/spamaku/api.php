<?php

require 'inti.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$aksi = $_GET['aksi'] ?? '';

// CEK USERNAME / EMAIL (dipanggil tiap user berhenti ngetik)
if ($aksi === 'cek') {
    $kolom = $_GET['field'] ?? '';
    $nilai = strtolower(trim($_GET['value'] ?? ''));

    // whitelist kolom. kalau nggak, orang bisa iseng masukin SQL aneh-aneh ke sini
    if (!in_array($kolom, ['namaPengguna', 'email'], true) || $nilai === '') {
        exit(json_encode(['tersedia' => false, 'pesan' => '']));
    }
    if ($kolom === 'namaPengguna' && !preg_match('/^[a-z0-9_.]{3,30}$/', $nilai)) {
        exit(json_encode(['tersedia' => false, 'pesan' => '3–30 karakter: huruf kecil, angka, titik, garis bawah.']));
    }
    if ($kolom === 'email' && !filter_var($nilai, FILTER_VALIDATE_EMAIL)) {
        exit(json_encode(['tersedia' => false, 'pesan' => 'Format email belum valid.']));
    }

    $sql = "SELECT 1 FROM pengguna WHERE $kolom = ?";
    $param = [$nilai];

    if (!empty($_GET['exclude'])) {
        // Di halaman profil, akun sendiri jangan dihitung sebagai akun lain.
        $penggunaAktif = getPenggunaAktif();

        if ($penggunaAktif) {
            $sql .= ' AND id <> ?';
            $param[] = (int)$penggunaAktif['id'];
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($param);
    $terpakai = (bool)$stmt->fetch();
    $label = $kolom === 'email' ? 'Email' : 'Username';

    exit(json_encode([
        'tersedia' => !$terpakai,
        'pesan' => $terpakai ? "$label sudah dipakai." : "$label tersedia ✓",
    ], JSON_UNESCAPED_UNICODE));
}

// DATA HALAMAN UTAMA
if ($aksi === 'landing') {
    $statistik = $pdo->query("SELECT
        (SELECT COUNT(*) FROM acara WHERE status='disetujui' AND tanggalMulai >= NOW()) AS akanDatang,
        (SELECT COUNT(*) FROM organisasi WHERE status='disetujui') AS organisasi,
        (SELECT COUNT(*) FROM pengguna) AS pengguna,
        (SELECT COALESCE(SUM(poin),0) FROM acara WHERE status='disetujui' AND tanggalMulai >= NOW()) AS poin")->fetch();

    $kategori = array_values(array_map(fn($k) => [
        'id' => (int)$k['id'],
        'nama' => $k['nama'],
        'singkatan' => $k['singkatan'],
        'minimum' => (int)$k['poinMinimum'],
        'warna' => $k['warna'],
    ], getSemuaKategori()));

    $baris = $pdo->query(sqlAcara() . " WHERE a.status='disetujui' AND a.tanggalMulai >= NOW() ORDER BY a.tanggalMulai LIMIT 4")->fetchAll();
    $saya = getPenggunaAktif();
    $idKeranjang = getIdKeranjang();

    $acara = array_map(fn($a) => [
        'id' => (int)$a['id'],
        'judul' => $a['judul'],
        'organisasi' => $a['namaOrganisasi'],
        'tanggal' => formatTanggal($a['tanggalMulai']),
        'mode' => $a['mode'],
        'lokasi' => $a['lokasi'],
        'biaya' => formatRupiah((int)$a['biaya']),
        'totalPoin' => (int)$a['poin'],
        'poster' => getUrlPoster($a),
        'poin' => [[
            'kategori' => (int)$a['idKategori'],
            'poin' => (int)$a['poin'],
        ]],
        'keatmajayaan' => $a['jenisKeatmajayaan']
            ? $a['jenisKeatmajayaan'] . ($a['susulan'] ? ' · Susulan' : '')
            : null,
        'inKeranjang' => in_array((int)$a['id'], $idKeranjang, true),
    ], $baris);

    exit(json_encode([
        'statistik' => array_map('intval', $statistik),
        'kategori' => $kategori,
        'acara' => $acara,
        'pengguna' => $saya ? [
            'namaPengguna' => $saya['namaPengguna'],
            'foto' => getUrlAvatar($saya),
            'admin' => $saya['peran'] === 'admin',
            'keranjang' => count($idKeranjang),
        ] : null,
        'tokenCsrf' => $saya ? getTokenCsrf() : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

http_response_code(404);
echo json_encode(['pesan' => 'Aksi tidak dikenal. Kamu nyasar ya?']);
