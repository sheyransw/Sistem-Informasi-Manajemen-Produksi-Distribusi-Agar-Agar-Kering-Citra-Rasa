<?php
// Laporan.php — tabel saja + tombol Cetak PDF (path ke /laporan/laporan_pdf.php)
$koneksi = new mysqli("localhost", "root", "", "citra_rasa");
if ($koneksi->connect_errno) { die("Gagal konek DB: ".$koneksi->connect_error); }

$kategori = $_GET['kategori'] ?? 'semua';
$periode  = $_GET['periode']  ?? 'bulanan'; // harian|mingguan|bulanan
$tanggal  = $_GET['tanggal']  ?? date('Y-m-01');

// BASE URL project (mis. /fahmi_food) → link absolut ke /laporan/laporan_pdf.php
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // ex: /fahmi_food
$pdfUrl  = $baseUrl . '/laporan/laporan_pdf.php'
         . '?kategori=' . urlencode($kategori)
         . '&periode='  . urlencode($periode)
         . '&tanggal='  . urlencode($tanggal);

function periodEnd($periode, $start) {
  switch ($periode) {
    case 'harian':   return date('Y-m-d', strtotime($start.' +1 day -1 second'));
    case 'mingguan': return date('Y-m-d', strtotime($start.' +7 days -1 second'));
    default:         return date('Y-m-t', strtotime($start));
  }
}
function labelPeriode($periode, $start) {
  if ($periode==='harian')   return date('d M Y', strtotime($start));
  if ($periode==='mingguan') return date('d M Y', strtotime($start)).' – '.date('d M Y', strtotime($start.' +6 days'));
  return date('F Y', strtotime($start));
}
function buildRanges($periode, $start, $rows=7) {
  $ranges=[]; $cursor=$start;
  for($i=0;$i<$rows;$i++){
    $ranges[]=[
      'start'=>date('Y-m-d',strtotime($cursor)),
      'end'  =>date('Y-m-d',strtotime(periodEnd($periode,$cursor))),
      'label'=>labelPeriode($periode,$cursor),
    ];
    if ($periode==='harian')       $cursor = date('Y-m-d', strtotime($cursor.' +1 day'));
    elseif ($periode==='mingguan') $cursor = date('Y-m-d', strtotime($cursor.' +7 days'));
    else                           $cursor = date('Y-m-01', strtotime($cursor.' +1 month'));
  }
  return $ranges;
}
function qScalar($db,$sql){ $r=$db->query($sql); if(!$r)return 0; $row=$r->fetch_row(); return $row? (float)$row[0]:0; }
function qRows($db,$sql){ $out=[]; $r=$db->query($sql); if($r){ while($x=$r->fetch_assoc()) $out[]=$x; } return $out; }

$data = [];
$periodRows = [];

if ($kategori==='semua') {
  $ranges = buildRanges($periode, $tanggal, 7);
  foreach ($ranges as $r) {
    $start = $koneksi->real_escape_string($r['start']);
    $end   = $koneksi->real_escape_string($r['end']);

    // PRODUKSI & DIKEMAS: gunakan COALESCE(tgl_produksi, jadwal.tanggal)
    $produksi = qScalar(
      $koneksi,
      "SELECT IFNULL(SUM(pr.jumlah_produksi),0)
       FROM produksi pr
       LEFT JOIN jadwal j ON j.id_jadwal = pr.id_jadwal
       WHERE COALESCE(pr.tgl_produksi, j.tanggal) BETWEEN '$start' AND '$end'"
    );
    $produk_jual = qScalar(
      $koneksi,
      "SELECT IFNULL(SUM(pr.jumlah_dikemas),0)
       FROM produksi pr
       LEFT JOIN jadwal j ON j.id_jadwal = pr.id_jadwal
       WHERE COALESCE(pr.tgl_produksi, j.tanggal) BETWEEN '$start' AND '$end'"
    );

    $distribusi    = qScalar($koneksi, "SELECT IFNULL(SUM(jumlah_pesanan),0) FROM distribusi WHERE tanggal_pesanan IS NOT NULL AND tanggal_pesanan BETWEEN '$start' AND '$end'");
    $stok_snapshot = qScalar($koneksi, "SELECT IFNULL(SUM(jumlah_stok),0) FROM stok WHERE status_stok IN ('Sudah dipacking','Siap dipacking','Siap dikemas') AND jumlah_stok>0");
    $gaji          = qScalar($koneksi, "SELECT IFNULL(SUM(total_gaji),0) FROM riwayat_gaji WHERE tanggal BETWEEN '$start' AND '$end' AND (keterangan='Dibayar' OR keterangan='dibayar')");
    $pekerja       = qScalar($koneksi, "SELECT COUNT(*) FROM pekerja_lepas");
    $pesanan       = $distribusi;

    $periodRows[] = [
      'Periode'       => $r['label'],
      'Produksi'      => $produksi,
      'Produk Jual'   => $produk_jual,
      'Distribusi'    => $distribusi,
      'Stok'          => $stok_snapshot,
      'Gaji Dibayar'  => $gaji,
      'Pekerja Aktif' => (int)$pekerja,
      'Pesanan'       => $pesanan,
    ];
  }
} elseif ($kategori==='jadwal') {
  $end = periodEnd($periode, $tanggal);
  $data = qRows($koneksi, "
    SELECT tanggal, waktu_mulai, waktu_selesai, jenis_kegiatan
    FROM jadwal
    WHERE tanggal BETWEEN '{$koneksi->real_escape_string($tanggal)}' AND '{$koneksi->real_escape_string($end)}'
    ORDER BY tanggal ASC, waktu_mulai ASC
  ");
} else {
  $end = periodEnd($periode, $tanggal);
  switch ($kategori) {
    case 'produksi':
      $sql = "
        SELECT p.nama_produk,
               pr.jumlah_produksi,
               pr.jumlah_dikemas,
               pr.jumlah_reject,
               COALESCE(pr.tgl_produksi, j.tanggal) AS tanggal_produksi
        FROM produksi pr
        JOIN produk p   ON p.id_produk = pr.id_produk
        LEFT JOIN jadwal j ON j.id_jadwal = pr.id_jadwal
        WHERE COALESCE(pr.tgl_produksi, j.tanggal)
              BETWEEN '{$koneksi->real_escape_string($tanggal)}' AND '{$koneksi->real_escape_string($end)}'
        ORDER BY tanggal_produksi DESC, pr.id_produksi DESC";
      break;
    case 'stok':
      $sql = "
        SELECT s.id_stok, p.nama_produk, s.jumlah_stok, s.status_stok, s.id_produksi
        FROM stok s
        JOIN produk p ON p.id_produk = s.id_produk
        ORDER BY s.id_stok DESC";
      break;
    case 'pekerja_lepas':
      $sql = "
        SELECT pl.nama_pekerja, rg.tanggal, rg.berat_barang_kg, rg.tarif_per_kg, rg.total_gaji, rg.keterangan
        FROM riwayat_gaji rg
        JOIN pekerja_lepas pl ON pl.id_pekerja = rg.id_pekerja
        WHERE rg.tanggal BETWEEN '{$koneksi->real_escape_string($tanggal)}' AND '{$koneksi->real_escape_string($end)}'
        ORDER BY rg.tanggal DESC, rg.id_gaji DESC";
      break;
    case 'distribusi':
      $sql = "
        SELECT d.nama_distributor, d.alamat_distributor, p.nama_produk, d.jumlah_pesanan, d.tanggal_pesanan, d.status_pengiriman
        FROM distribusi d
        JOIN produk p ON p.id_produk = d.id_produk
        WHERE d.tanggal_pesanan IS NOT NULL
          AND d.tanggal_pesanan BETWEEN '{$koneksi->real_escape_string($tanggal)}' AND '{$koneksi->real_escape_string($end)}'
        ORDER BY d.tanggal_pesanan DESC, d.id_distribusi DESC";
      break;
    default: $sql = "";
  }
  if ($sql) $data = qRows($koneksi, $sql);
}
?>
<main class="flex-1 bg-gray-100 p-6">
  <section class="bg-white p-6 rounded-md shadow-md">
    <form method="GET" class="flex flex-wrap gap-4 items-end mb-6">
      <input type="hidden" name="page" value="laporan">
      <div class="flex flex-col">
        <label class="text-sm font-medium text-gray-600">Kategori</label>
        <select name="kategori" class="border border-gray-300 px-3 py-2 rounded w-56" onchange="this.form.submit()">
          <option value="semua"         <?= $kategori=='semua'?'selected':''; ?>>Semua</option>
          <option value="produksi"      <?= $kategori=='produksi'?'selected':''; ?>>Produksi</option>
          <option value="stok"          <?= $kategori=='stok'?'selected':''; ?>>Stok</option>
          <option value="pekerja_lepas" <?= $kategori=='pekerja_lepas'?'selected':''; ?>>Pekerja Lepas</option>
          <option value="distribusi"    <?= $kategori=='distribusi'?'selected':''; ?>>Distribusi</option>
          <option value="jadwal"        <?= $kategori=='jadwal'?'selected':''; ?>>Jadwal</option>
        </select>
      </div>
      <div class="flex flex-col">
        <label class="text-sm font-medium text-gray-600">Periode</label>
        <select name="periode" class="border border-gray-300 px-3 py-2 rounded w-56" onchange="this.form.submit()">
          <option value="harian"   <?= $periode=='harian'?'selected':''; ?>>Harian</option>
          <option value="mingguan" <?= $periode=='mingguan'?'selected':''; ?>>Mingguan</option>
          <option value="bulanan"  <?= $periode=='bulanan'?'selected':''; ?>>Bulanan</option>
        </select>
      </div>
      <div class="flex flex-col">
        <label class="text-sm font-medium text-gray-600">Tanggal Awal</label>
        <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" class="border border-gray-300 px-3 py-2 rounded w-56" onchange="this.form.submit()">
      </div>

      <div class="flex items-center gap-2">
        <!-- Tombol Cetak PDF (ke /laporan/laporan_pdf.php) -->
        <a
          href="<?= htmlspecialchars($pdfUrl) ?>"
          target="_blank" rel="noopener"
          class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 mt-1 inline-flex items-center"
          title="Cetak / Simpan PDF"
        >Cetak PDF</a>
      </div>
    </form>

    <?php if ($kategori==='semua'): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full border text-sm text-left bg-white">
          <thead class="bg-[#FDF5CA] text-gray-900">
            <tr>
              <th class="border border-gray-300 px-3 py-2">Periode</th>
              <th class="border border-gray-300 px-3 py-2">Produksi</th>
              <th class="border border-gray-300 px-3 py-2">Produk Jual</th>
              <th class="border border-gray-300 px-3 py-2">Distribusi</th>
              <th class="border border-gray-300 px-3 py-2">Stok</th>
              <th class="border border-gray-300 px-3 py-2">Gaji Dibayar</th>
              <th class="border border-gray-300 px-3 py-2">Pekerja Aktif</th>
              <th class="border border-gray-300 px-3 py-2">Pesanan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($periodRows as $r): ?>
            <tr>
              <td class="border px-3 py-2"><?= htmlspecialchars($r['Periode']) ?></td>
              <td class="border px-3 py-2"><?= number_format($r['Produksi']) ?> Kg</td>
              <td class="border px-3 py-2"><?= number_format($r['Produk Jual']) ?> Kg</td>
              <td class="border px-3 py-2"><?= number_format($r['Distribusi']) ?> Kg</td>
              <td class="border px-3 py-2"><?= number_format($r['Stok']) ?> Kg</td>
              <td class="border px-3 py-2">Rp <?= number_format($r['Gaji Dibayar'],0,',','.') ?></td>
              <td class="border px-3 py-2"><?= (int)$r['Pekerja Aktif'] ?></td>
              <td class="border px-3 py-2"><?= number_format($r['Pesanan']) ?> Kg</td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($periodRows)): ?>
              <tr><td colspan="8" class="border px-3 py-3 text-center text-gray-500">Tidak ada data.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($kategori==='jadwal'): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full border text-sm text-left bg-white">
          <thead class="bg-orange-[#FDF5CA] text-gray-900">
            <tr>
              <th class="border px-3 py-2">No.</th>
              <th class="border px-3 py-2">Tanggal</th>
              <th class="border px-3 py-2">Waktu</th>
              <th class="border px-3 py-2">Jenis Kegiatan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($data as $i=>$d): ?>
              <tr>
                <td class="border px-3 py-2"><?= $i+1 ?>.</td>
                <td class="border px-3 py-2"><?= htmlspecialchars(date('d-m-Y', strtotime($d['tanggal']))) ?></td>
                <td class="border px-3 py-2"><?= htmlspecialchars(substr($d['waktu_mulai'],0,5).' - '.substr($d['waktu_selesai'],0,5)) ?></td>
                <td class="border px-3 py-2"><?= htmlspecialchars($d['jenis_kegiatan']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($data)): ?>
              <tr><td colspan="4" class="border px-3 py-3 text-center text-gray-500">Tidak ada data.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php elseif (!empty($data)): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full border text-sm text-left bg-white">
          <thead class="bg-orange-[#FDF5CA] text-gray-900">
            <tr>
              <th class="border px-3 py-2">No.</th>
              <?php foreach(array_keys($data[0]) as $col): ?>
                <th class="border px-3 py-2"><?= htmlspecialchars(ucwords(str_replace('_',' ',$col))) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($data as $i=>$row): ?>
              <tr class="hover:bg-blue-50">
                <td class="border px-3 py-2"><?= $i+1 ?>.</td>
                <?php foreach ($row as $val): ?>
                  <td class="border px-3 py-2"><?= htmlspecialchars((string)$val) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php else: ?>
      <p class="mt-4 italic text-center text-gray-500">Data tidak ditemukan untuk kategori dan periode tersebut.</p>
    <?php endif; ?>
  </section>
</main>
