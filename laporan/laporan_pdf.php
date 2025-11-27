<?php
// ------- KONFIG DB (mysqli mandiri, tidak pakai koneksi.php PDO) -------
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'citra_rasa';

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  die('Gagal konek DB: '.$mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

// ------- PARAM FILTER -------
$kategori = $_GET['kategori'] ?? 'semua';           // semua|produksi|stok|pekerja_lepas|distribusi|jadwal
$periode  = $_GET['periode']  ?? 'bulanan';         // harian|mingguan|bulanan
$tanggal  = $_GET['tanggal']  ?? date('Y-m-01');    // anchor start

// ------- UTIL -------
function period_end($periode, $start) {
  switch ($periode) {
    case 'harian':   return date('Y-m-d', strtotime($start.' +1 day -1 second'));
    case 'mingguan': return date('Y-m-d', strtotime($start.' +7 days -1 second'));
    default:         return date('Y-m-t', strtotime($start));
  }
}
function label_periode($periode, $start) {
  if ($periode==='harian')   return date('d M Y', strtotime($start));
  if ($periode==='mingguan') return date('d M Y', strtotime($start)).' – '.date('d M Y', strtotime($start.' +6 days'));
  return date('F Y', strtotime($start));
}
function build_ranges($periode, $start, $rows=7) {
  $ranges = []; $cursor = $start;
  for ($i=0;$i<$rows;$i++) {
    $ranges[] = [
      'start' => date('Y-m-d', strtotime($cursor)),
      'end'   => period_end($periode, $cursor),
      'label' => label_periode($periode, $cursor),
    ];
    if ($periode==='harian')        $cursor = date('Y-m-d', strtotime($cursor.' +1 day'));
    elseif ($periode==='mingguan')  $cursor = date('Y-m-d', strtotime($cursor.' +7 days'));
    else                            $cursor = date('Y-m-01', strtotime($cursor.' +1 month'));
  }
  return $ranges;
}
function sc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ------- QUERY HELPERS -------
function scalar($db, $sql, $params = []) {
  $stmt = $db->prepare($sql);
  if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  $val = 0;
  if ($res && ($row = $res->fetch_row())) $val = (float)$row[0];
  $stmt->close();
  return $val;
}
function rows($db, $sql, $params = []) {
  $stmt = $db->prepare($sql);
  if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  if ($res) { while ($r = $res->fetch_assoc()) $out[] = $r; }
  $stmt->close();
  return $out;
}

// ------- BANGUN HTML --------
$title = 'LAPORAN';
$sub   = 'Periode: '.label_periode($periode, $tanggal).' (anchor: '.date('Y-m-d', strtotime($tanggal)).')';

ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= sc($title) ?></title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#111;margin:24px}
  h1{font-size:18px;margin:0 0 6px 0}
  h2{font-size:13px;margin:0 0 14px 0;color:#333}
  table{border-collapse:collapse;width:100%}
  th,td{border:1px solid #d1d5db;padding:6px 8px}
  thead th{background:#e5efff;text-align:left}
  tfoot td{font-weight:bold;background:#f3f4f6}
  .muted{color:#6b7280}
  .mb16{margin-bottom:16px}
  .right{text-align:right}
  .center{text-align:center}
</style>
</head>
<body>
  <h1><?= sc($title) ?></h1>
  <div class="muted mb16"><?= sc($sub) ?> • Dicetak: <?= date('d/m/Y H:i') ?></div>

<?php
if ($kategori === 'semua') {
  // Snapshot yg konstan untuk semua baris
  $stok_snapshot = scalar($mysqli, "
    SELECT IFNULL(SUM(jumlah_stok),0)
    FROM stok
    WHERE status_stok IN ('Sudah dipacking','Siap dipacking','Siap dikemas') AND jumlah_stok>0
  ");
  $pekerja_aktif = scalar($mysqli, "SELECT COUNT(*) FROM pekerja_lepas");

  $ranges = build_ranges($periode, $tanggal, 7);

  echo '<table><thead><tr>
          <th>Periode</th>
          <th class="right">Produksi (Kg)</th>
          <th class="right">Produk Jual (Kg)</th>
          <th class="right">Distribusi (Kg)</th>
          <th class="right">Stok (Kg)</th>
          <th class="right">Gaji Dibayar</th>
          <th class="right">Pekerja Aktif</th>
          <th class="right">Pesanan (Kg)</th>
        </tr></thead><tbody>';

  foreach ($ranges as $r) {
    $start = $r['start']; $end = $r['end'];

    // PRODUKSI & DIKEMAS pakai COALESCE(tgl_produksi, jadwal.tanggal)
    $produksi = scalar($mysqli, "
      SELECT IFNULL(SUM(pr.jumlah_produksi),0)
      FROM produksi pr
      LEFT JOIN jadwal j ON j.id_jadwal = pr.id_jadwal
      WHERE COALESCE(pr.tgl_produksi, j.tanggal) BETWEEN ? AND ?
    ", [$start, $end]);

    $produk_jual = scalar($mysqli, "
      SELECT IFNULL(SUM(pr.jumlah_dikemas),0)
      FROM produksi pr
      LEFT JOIN jadwal j ON j.id_jadwal = pr.id_jadwal
      WHERE COALESCE(pr.tgl_produksi, j.tanggal) BETWEEN ? AND ?
    ", [$start, $end]);

    $distribusi = scalar($mysqli, "
      SELECT IFNULL(SUM(jumlah_pesanan),0)
      FROM distribusi
      WHERE tanggal_pesanan BETWEEN ? AND ?
    ", [$start, $end]);

    $gaji = scalar($mysqli, "
      SELECT IFNULL(SUM(total_gaji),0)
      FROM riwayat_gaji
      WHERE tanggal BETWEEN ? AND ? AND (keterangan='Dibayar' OR keterangan='dibayar')
    ", [$start, $end]);

    $pesanan = $distribusi; // sama

    echo '<tr>'.
           '<td>'.sc($r['label']).'</td>'.
           '<td class="right">'.number_format($produksi).'</td>'.
           '<td class="right">'.number_format($produk_jual).'</td>'.
           '<td class="right">'.number_format($distribusi).'</td>'.
           '<td class="right">'.number_format($stok_snapshot).'</td>'.
           '<td class="right">Rp '.number_format($gaji,0,',','.').'</td>'.
           '<td class="right">'.(int)$pekerja_aktif.'</td>'.
           '<td class="right">'.number_format($pesanan).'</td>'.
         '</tr>';
  }
  echo '</tbody></table>';

} elseif ($kategori === 'produksi') {

  $start = $tanggal;
  $end   = period_end($periode, $tanggal);

  $rows = rows($mysqli, "
    SELECT p.nama_produk,
           pr.jumlah_produksi, pr.jumlah_dikemas, pr.jumlah_reject,
           COALESCE(pr.tgl_produksi, j.tanggal) AS tanggal_produksi
    FROM produksi pr
    JOIN produk p ON p.id_produk = pr.id_produk
    LEFT JOIN jadwal j ON j.id_jadwal = pr.id_jadwal
    WHERE COALESCE(pr.tgl_produksi, j.tanggal) BETWEEN ? AND ?
    ORDER BY tanggal_produksi DESC, pr.id_produksi DESC
  ", [$start, $end]);

  echo '<table><thead><tr>
          <th>No.</th>
          <th>Nama Produk</th>
          <th class="right">Produksi (Kg)</th>
          <th class="right">Dikemas (Kg)</th>
          <th class="right">Reject (Kg)</th>
          <th>Tanggal</th>
        </tr></thead><tbody>';
  if (!$rows) {
    echo '<tr><td colspan="6" class="center muted">Tidak ada data.</td></tr>';
  } else {
    $i=1;
    foreach ($rows as $r) {
      echo '<tr>'.
             '<td class="center">'.$i++.'.</td>'.
             '<td>'.sc($r['nama_produk']).'</td>'.
             '<td class="right">'.number_format($r['jumlah_produksi']).'</td>'.
             '<td class="right">'.number_format($r['jumlah_dikemas']).'</td>'.
             '<td class="right">'.number_format($r['jumlah_reject']).'</td>'.
             '<td>'.sc(date('d-m-Y', strtotime($r['tanggal_produksi']))).'</td>'.
           '</tr>';
    }
  }
  echo '</tbody></table>';

} elseif ($kategori === 'stok') {

  $rows = rows($mysqli, "
    SELECT s.id_stok, p.nama_produk, s.jumlah_stok, s.status_stok, s.id_produksi
    FROM stok s
    JOIN produk p ON p.id_produk = s.id_produk
    ORDER BY s.id_stok DESC
  ");

  echo '<table><thead><tr>
          <th>No.</th>
          <th>ID Stok</th>
          <th>Nama Produk</th>
          <th class="right">Jumlah (Kg)</th>
          <th>Status</th>
          <th>ID Produksi</th>
        </tr></thead><tbody>';
  if (!$rows) {
    echo '<tr><td colspan="6" class="center muted">Tidak ada data.</td></tr>';
  } else {
    $i=1;
    foreach ($rows as $r) {
      echo '<tr>'.
             '<td class="center">'.$i++.'.</td>'.
             '<td>'.(int)$r['id_stok'].'</td>'.
             '<td>'.sc($r['nama_produk']).'</td>'.
             '<td class="right">'.number_format($r['jumlah_stok']).'</td>'.
             '<td>'.sc($r['status_stok']).'</td>'.
             '<td>'.sc($r['id_produksi']).'</td>'.
           '</tr>';
    }
  }
  echo '</tbody></table>';

} elseif ($kategori === 'pekerja_lepas') {

  $start = $tanggal;
  $end   = period_end($periode, $tanggal);

  $rows = rows($mysqli, "
    SELECT pl.nama_pekerja, rg.tanggal, rg.berat_barang_kg, rg.tarif_per_kg, rg.total_gaji, rg.keterangan
    FROM riwayat_gaji rg
    JOIN pekerja_lepas pl ON pl.id_pekerja = rg.id_pekerja
    WHERE rg.tanggal BETWEEN ? AND ?
    ORDER BY rg.tanggal DESC, rg.id_gaji DESC
  ", [$start, $end]);

  echo '<table><thead><tr>
          <th>No.</th>
          <th>Nama Pekerja</th>
          <th>Tanggal</th>
          <th class="right">Berat (Kg)</th>
          <th class="right">Tarif/Kg</th>
          <th class="right">Total Gaji</th>
          <th>Keterangan</th>
        </tr></thead><tbody>';
  if (!$rows) {
    echo '<tr><td colspan="7" class="center muted">Tidak ada data.</td></tr>';
  } else {
    $i=1;
    foreach ($rows as $r) {
      echo '<tr>'.
             '<td class="center">'.$i++.'.</td>'.
             '<td>'.sc($r['nama_pekerja']).'</td>'.
             '<td>'.sc(date('d-m-Y', strtotime($r['tanggal']))).'</td>'.
             '<td class="right">'.number_format($r['berat_barang_kg']).'</td>'.
             '<td class="right">Rp '.number_format($r['tarif_per_kg'],0,',','.').'</td>'.
             '<td class="right">Rp '.number_format($r['total_gaji'],0,',','.').'</td>'.
             '<td>'.sc($r['keterangan']).'</td>'.
           '</tr>';
    }
  }
  echo '</tbody></table>';

} elseif ($kategori === 'distribusi') {

  $start = $tanggal;
  $end   = period_end($periode, $tanggal);

  $rows = rows($mysqli, "
    SELECT d.nama_distributor, d.alamat_distributor, p.nama_produk,
           d.jumlah_pesanan, d.tanggal_pesanan, d.status_pengiriman
    FROM distribusi d
    JOIN produk p ON p.id_produk = d.id_produk
    WHERE d.tanggal_pesanan BETWEEN ? AND ?
    ORDER BY d.tanggal_pesanan DESC, d.id_distribusi DESC
  ", [$start, $end]);

  echo '<table><thead><tr>
          <th>No.</th>
          <th>Distributor</th>
          <th>Alamat</th>
          <th>Produk</th>
          <th class="right">Jumlah (Kg)</th>
          <th>Tanggal</th>
          <th>Status</th>
        </tr></thead><tbody>';
  if (!$rows) {
    echo '<tr><td colspan="7" class="center muted">Tidak ada data.</td></tr>';
  } else {
    $i=1;
    foreach ($rows as $r) {
      echo '<tr>'.
             '<td class="center">'.$i++.'.</td>'.
             '<td>'.sc($r['nama_distributor']).'</td>'.
             '<td>'.sc($r['alamat_distributor']).'</td>'.
             '<td>'.sc($r['nama_produk']).'</td>'.
             '<td class="right">'.number_format($r['jumlah_pesanan']).'</td>'.
             '<td>'.sc(date('d-m-Y', strtotime($r['tanggal_pesanan']))).'</td>'.
             '<td>'.sc($r['status_pengiriman']).'</td>'.
           '</tr>';
    }
  }
  echo '</tbody></table>';

} elseif ($kategori === 'jadwal') {

  $start = $tanggal;
  $end   = period_end($periode, $tanggal);

  $rows = rows($mysqli, "
    SELECT tanggal, waktu_mulai, waktu_selesai, jenis_kegiatan
    FROM jadwal
    WHERE tanggal BETWEEN ? AND ?
    ORDER BY tanggal ASC, waktu_mulai ASC
  ", [$start, $end]);

  echo '<table><thead><tr>
          <th>No.</th>
          <th>Tanggal</th>
          <th>Waktu</th>
          <th>Jenis Kegiatan</th>
        </tr></thead><tbody>';
  if (!$rows) {
    echo '<tr><td colspan="4" class="center muted">Tidak ada data.</td></tr>';
  } else {
    $i=1;
    foreach ($rows as $r) {
      $jam = substr($r['waktu_mulai'],0,5).' - '.substr($r['waktu_selesai'],0,5);
      echo '<tr>'.
             '<td class="center">'.$i++.'.</td>'.
             '<td>'.sc(date('d-m-Y', strtotime($r['tanggal']))).'</td>'.
             '<td>'.sc($jam).'</td>'.
             '<td>'.sc($r['jenis_kegiatan']).'</td>'.
           '</tr>';
    }
  }
  echo '</tbody></table>';

} else {
  echo '<div class="muted">Kategori tidak dikenal.</div>';
}
?>
</body>
</html>
<?php
$html = ob_get_clean();

// ------- OUTPUT: mPDF jika ada, fallback ke HTML print -------
$autoload = __DIR__.'/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
}
if (class_exists('\\Mpdf\\Mpdf')) {
  try {
    $mpdf = new \Mpdf\Mpdf(['format' => 'A4-L']);
    $mpdf->WriteHTML($html);
    $fileName = 'Laporan_'.date('Ymd_His').'.pdf';
    $mpdf->Output($fileName, 'I'); // inline
    exit;
  } catch (\Throwable $e) {
    // fallback ke HTML kalau ada error mPDF
  }
}

// Fallback: tampilkan HTML dan auto-print
echo $html;
echo "<script>window.onload=function(){window.print();}</script>";
