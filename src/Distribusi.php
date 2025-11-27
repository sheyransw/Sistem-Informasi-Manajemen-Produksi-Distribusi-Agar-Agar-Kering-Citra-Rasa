<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'auth.php';
include 'koneksi.php';

/**
 * Skema rujukan:
 * - produk(id_produk, nama_produk, ...)
 * - distribusi(
 *     id_distribusi PK AI,
 *     id_produk FK,
 *     jumlah_pesanan INT,
 *     tanggal_pesanan DATE,
 *     status_pengiriman VARCHAR,
 *     nama_distributor VARCHAR,
 *     alamat_distributor VARCHAR
 *   )
 */

/* Cegah akses langsung file */
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
  header("Location: ../Index.php?page=distribusi");
  exit;
}

/* =================== HANDLE FORM ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
      throw new Exception('Koneksi $pdo tidak tersedia. Periksa koneksi.php.');
    }

    if ($action === 'tambah') {
      $nama   = trim($_POST['nama_distributor'] ?? '');
      $alamat = trim($_POST['alamat_distributor'] ?? '');
      $tgl    = $_POST['tgl_pesanan'] ?? date('Y-m-d');
      $status = $_POST['status_pengiriman'] ?? 'Diproses';
      $details = $_POST['detail'] ?? [];

      if ($nama === '' || $alamat === '') throw new Exception('Nama & Alamat distributor wajib diisi.');
      if (!is_array($details) || count($details) === 0) throw new Exception('Tambahkan minimal 1 produk.');

      $stmt = $pdo->prepare("
        INSERT INTO distribusi
          (nama_distributor, alamat_distributor, id_produk, jumlah_pesanan, tanggal_pesanan, status_pengiriman)
        VALUES (?,?,?,?,?,?)
      ");

      $ok = 0;
      foreach ($details as $row) {
        $id_produk = (int)($row['id_produk'] ?? 0);
        $jumlah    = (int)($row['jumlah'] ?? 0);
        if ($id_produk > 0 && $jumlah > 0) {
          $stmt->execute([$nama, $alamat, $id_produk, $jumlah, $tgl, $status]);
          $ok++;
        }
      }
      if ($ok === 0) throw new Exception('Detail produk belum lengkap.');

      $_SESSION['notif'] = ['pesan' => 'Pesanan berhasil ditambahkan!', 'tipe' => 'sukses'];

    } elseif ($action === 'edit_grup') {
      // Edit seluruh grup: hapus baris lama (by key lama), insert ulang dengan header + detail baru
      $old_nama   = $_POST['key_nama_old']   ?? '';
      $old_alamat = $_POST['key_alamat_old'] ?? '';
      $old_tgl    = $_POST['key_tanggal_old']?? '';
      $old_status = $_POST['key_status_old'] ?? '';

      $nama   = trim($_POST['nama_distributor'] ?? '');
      $alamat = trim($_POST['alamat_distributor'] ?? '');
      $tgl    = $_POST['tgl_pesanan'] ?? date('Y-m-d');
      $status = $_POST['status_pengiriman'] ?? 'Diproses';
      $details = $_POST['detail'] ?? [];

      if ($nama === '' || $alamat === '') throw new Exception('Nama & Alamat wajib diisi.');
      if (!is_array($details) || count($details) === 0) throw new Exception('Tambahkan minimal 1 produk.');

      $pdo->beginTransaction();

      $pdo->prepare("DELETE FROM distribusi WHERE nama_distributor=? AND alamat_distributor=? AND tanggal_pesanan=? AND status_pengiriman=?")
          ->execute([$old_nama, $old_alamat, $old_tgl, $old_status]);

      $stmtIns = $pdo->prepare("
        INSERT INTO distribusi
          (nama_distributor, alamat_distributor, id_produk, jumlah_pesanan, tanggal_pesanan, status_pengiriman)
        VALUES (?,?,?,?,?,?)
      ");

      $inserted = 0;
      foreach ($details as $row) {
        $id_produk = (int)($row['id_produk'] ?? 0);
        $jumlah    = (int)($row['jumlah'] ?? 0);
        if ($id_produk > 0 && $jumlah > 0) {
          $stmtIns->execute([$nama, $alamat, $id_produk, $jumlah, $tgl, $status]);
          $inserted++;
        }
      }
      if ($inserted === 0) throw new Exception('Detail produk tidak valid.');

      $pdo->commit();
      $_SESSION['notif'] = ['pesan' => 'Grup berhasil diperbarui!', 'tipe' => 'sukses'];

    } elseif ($action === 'hapus_grup') {
      $old_nama   = $_POST['key_nama_old']   ?? '';
      $old_alamat = $_POST['key_alamat_old'] ?? '';
      $old_tgl    = $_POST['key_tanggal_old']?? '';
      $old_status = $_POST['key_status_old'] ?? '';

      $pdo->prepare("DELETE FROM distribusi WHERE nama_distributor=? AND alamat_distributor=? AND tanggal_pesanan=? AND status_pengiriman=?")
          ->execute([$old_nama, $old_alamat, $old_tgl, $old_status]);

      $_SESSION['notif'] = ['pesan' => 'Grup berhasil dihapus.', 'tipe' => 'sukses'];
    }
  } catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['notif'] = ['pesan' => 'Kesalahan: '.$e->getMessage(), 'tipe' => 'error'];
  }

  header("Location: Index.php?page=distribusi");
  exit;
}

/* =================== AMBIL DATA ==================== */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  die('<div style="padding:12px;color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;border-radius:6px;">
        Koneksi database ($pdo) tidak ditemukan.
      </div>');
}

$daftar_distribusi = $pdo->query("
  SELECT d.id_distribusi,
         d.nama_distributor, d.alamat_distributor,
         d.tanggal_pesanan, d.status_pengiriman,
         d.id_produk, d.jumlah_pesanan,
         p.nama_produk
    FROM distribusi d
    JOIN produk p ON d.id_produk = p.id_produk
 ORDER BY d.tanggal_pesanan DESC, d.nama_distributor ASC, d.id_distribusi ASC
")->fetchAll(PDO::FETCH_ASSOC);

$produk_options = $pdo->query("
  SELECT id_produk, nama_produk
    FROM produk
 ORDER BY nama_produk
")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 bg-gray-100">
  <section class="p-6 overflow-x-auto">
    <?php if (isset($_SESSION['notif'])): ?>
      <div class="mb-4 p-4 rounded-md text-white font-bold <?= $_SESSION['notif']['tipe'] === 'sukses' ? 'bg-green-600' : 'bg-red-600'; ?>">
        <?= htmlspecialchars($_SESSION['notif']['pesan']); ?>
      </div>
      <?php unset($_SESSION['notif']); ?>
    <?php endif; ?>

    <button id="btnTambah" class="mb-4 inline-flex items-center bg-orange-600 hover:bg-orange-700 text-white text-sm font-normal px-4 py-2 rounded" type="button">
      <i class="fas fa-plus"></i>&nbsp;Input Pesanan
    </button>

    <table class="w-full border border-gray-300 text-sm bg-white">
      <thead>
        <tr class="bg-[#FDF5CA] text-black text-left">
          <th class="border border-gray-300 px-3 py-2">No.</th>
          <th class="border border-gray-300 px-3 py-2">Distributor</th>
          <th class="border border-gray-300 px-3 py-2">Alamat</th>
          <th class="border border-gray-300 px-3 py-2">Tanggal</th>
          <th class="border border-gray-300 px-3 py-2">Produk</th>
          <th class="border border-gray-300 px-3 py-2">Jumlah (kg)</th>
          <th class="border border-gray-300 px-3 py-2">Status</th>
          <th class="border border-gray-300 px-3 py-2">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if (empty($daftar_distribusi)):
        ?>
          <tr>
            <td colspan="8" class="border border-gray-300 px-3 py-4 text-center text-gray-500">
              Belum ada data distribusi.
            </td>
          </tr>
        <?php
        else:
          // Kelompokkan berdasarkan (nama_distributor, alamat, tanggal, status)
          $groups = [];
          foreach ($daftar_distribusi as $row) {
            $key = $row['nama_distributor'].'|'.$row['alamat_distributor'].'|'.$row['tanggal_pesanan'].'|'.$row['status_pengiriman'];
            if (!isset($groups[$key])) {
              $groups[$key] = [
                'header' => [
                  'nama_distributor'  => $row['nama_distributor'],
                  'alamat_distributor'=> $row['alamat_distributor'],
                  'tanggal_pesanan'   => $row['tanggal_pesanan'],
                  'status_pengiriman' => $row['status_pengiriman']
                ],
                'rows' => []
              ];
            }
            $groups[$key]['rows'][] = $row;
          }

          $no = 1;
          foreach ($groups as $g):
            $rowspan = count($g['rows']);
            // siapkan data items untuk prefill edit (id_produk & jumlah)
            $items = array_map(function($r){
              return ['id_produk'=>(int)$r['id_produk'],'jumlah'=>(int)$r['jumlah_pesanan']];
            }, $g['rows']);
            $items_json = htmlspecialchars(json_encode($items), ENT_QUOTES);

            foreach ($g['rows'] as $idx => $r):
        ?>
          <tr>
            <?php if ($idx === 0): ?>
              <td class="border border-gray-300 px-3 py-2 align-top" rowspan="<?= $rowspan ?>"><?= $no ?>.</td>
              <td class="border border-gray-300 px-3 py-2 align-top" rowspan="<?= $rowspan ?>"><?= htmlspecialchars($g['header']['nama_distributor']) ?></td>
              <td class="border border-gray-300 px-3 py-2 align-top" rowspan="<?= $rowspan ?>"><?= htmlspecialchars($g['header']['alamat_distributor']) ?></td>
              <td class="border border-gray-300 px-3 py-2 align-top" rowspan="<?= $rowspan ?>"><?= htmlspecialchars($g['header']['tanggal_pesanan']) ?></td>
            <?php endif; ?>

            <!-- Per-produk -->
            <td class="border border-gray-300 px-3 py-2"><?= htmlspecialchars($r['nama_produk']) ?></td>
            <td class="border border-gray-300 px-3 py-2"><?= (int)$r['jumlah_pesanan'] ?></td>

            <?php if ($idx === 0): ?>
              <td class="border border-gray-300 px-3 py-2 align-top" rowspan="<?= $rowspan ?>"><?= htmlspecialchars($g['header']['status_pengiriman']) ?></td>
              <td class="border border-gray-300 px-3 py-2 align-top" rowspan="<?= $rowspan ?>">
                <button
                  class="btnEditGrup px-3 py-1 text-xs text-white rounded"
                  style="background-color:#1d4ed8;"
                  data-nama-old="<?= htmlspecialchars($g['header']['nama_distributor'], ENT_QUOTES) ?>"
                  data-alamat-old="<?= htmlspecialchars($g['header']['alamat_distributor'], ENT_QUOTES) ?>"
                  data-tanggal-old="<?= htmlspecialchars($g['header']['tanggal_pesanan'], ENT_QUOTES) ?>"
                  data-status-old="<?= htmlspecialchars($g['header']['status_pengiriman'], ENT_QUOTES) ?>"
                  data-items='<?= $items_json ?>'
                >Edit</button>

                <button
                  class="btnHapusGrup bg-red-700 text-white text-xs px-3 py-1 rounded ml-2"
                  data-nama-old="<?= htmlspecialchars($g['header']['nama_distributor'], ENT_QUOTES) ?>"
                  data-alamat-old="<?= htmlspecialchars($g['header']['alamat_distributor'], ENT_QUOTES) ?>"
                  data-tanggal-old="<?= htmlspecialchars($g['header']['tanggal_pesanan'], ENT_QUOTES) ?>"
                  data-status-old="<?= htmlspecialchars($g['header']['status_pengiriman'], ENT_QUOTES) ?>"
                >Hapus</button>
              </td>
            <?php endif; ?>
          </tr>
        <?php
            endforeach;
            $no++;
          endforeach;
        endif;
        ?>
      </tbody>
    </table>
  </section>

  <!-- MODAL TAMBAH / EDIT GRUP -->
  <div id="modalForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <form action="" method="POST" id="formDistribusi" class="bg-white p-6 rounded w-[700px] max-w-[92vw] relative shadow">
      <input type="hidden" name="action" id="formAction" value="tambah">

      <!-- kunci grup lama (untuk edit_grup / hapus_grup) -->
      <input type="hidden" name="key_nama_old" id="key_nama_old">
      <input type="hidden" name="key_alamat_old" id="key_alamat_old">
      <input type="hidden" name="key_tanggal_old" id="key_tanggal_old">
      <input type="hidden" name="key_status_old" id="key_status_old">

      <button type="button" class="btnClose absolute top-2 right-3 text-gray-600 hover:text-black text-xl font-bold" aria-label="Close">&times;</button>

      <h2 id="formTitle" class="text-lg font-semibold text-gray-800 mb-4">Tambah Pesanan</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm text-gray-700 mb-1">Nama Distributor</label>
          <input type="text" name="nama_distributor" id="formNama" required class="w-full border px-3 py-2 rounded">
        </div>
        <div>
          <label class="block text-sm text-gray-700 mb-1">Tanggal Pesanan</label>
          <input type="date" name="tgl_pesanan" id="formTanggal" class="w-full border px-3 py-2 rounded" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm text-gray-700 mb-1">Alamat Distributor</label>
          <input type="text" name="alamat_distributor" id="formAlamat" required class="w-full border px-3 py-2 rounded">
        </div>
        <div>
          <label class="block text-sm text-gray-700 mb-1">Status Pengiriman</label>
          <select name="status_pengiriman" id="formStatus" required class="w-full border px-3 py-2 rounded">
            <option value="">-- Pilih Status --</option>
            <option value="Diproses">Diproses</option>
            <option value="Dikirim">Dikirim</option>
            <option value="Selesai">Selesai</option>
          </select>
        </div>
      </div>

      <!-- DETAIL PRODUK (multi) -->
      <div class="mt-4">
        <div class="text-sm font-semibold text-gray-800 mb-2">Detail Produk</div>
        <div id="produkListMulti"></div>
        <button type="button" id="btnAddRow" class="mt-3 bg-orange-600 text-white text-xs px-3 py-1 rounded hover:bg-orange-700 transition-colors">+ Tambah Produk</button>
      </div>

      <div class="mt-5">
        <button type="submit" class="w-full bg-orange-600 text-white py-2 rounded hover:bg-orange-700 transition-colors">Simpan</button>
      </div>
    </form>
  </div>

  <!-- MODAL HAPUS GRUP -->
  <div id="modalHapusGrup" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="w-[340px] border border-gray-300 shadow-md p-6 bg-white rounded-md relative">
      <button type="button" id="btnCloseHapusGrup" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700" aria-label="Close modal">
        <i class="fas fa-times"></i>
      </button>
      <h2 class="font-semibold text-black mb-3 text-lg">Hapus Semua Item di Grup?</h2>
      <p class="text-gray-700 mb-5 text-sm">Seluruh item dengan header yang sama akan dihapus.</p>
      <form action="" method="POST" class="flex justify-end space-x-3">
        <input type="hidden" name="action" value="hapus_grup">
        <input type="hidden" name="key_nama_old" id="hg_nama">
        <input type="hidden" name="key_alamat_old" id="hg_alamat">
        <input type="hidden" name="key_tanggal_old" id="hg_tanggal">
        <input type="hidden" name="key_status_old" id="hg_status">
        <button type="button" id="btnCancelHapusGrup" class="border border-gray-300 text-gray-900 text-sm font-medium rounded px-4 py-2 hover:bg-gray-100">Batal</button>
        <button type="submit" class="bg-red-600 text-white text-sm font-medium rounded px-4 py-2 hover:bg-red-700">Ya, Hapus Grup</button>
      </form>
    </div>
  </div>
</main>

<script>
  // ===== Modal =====
  const modal          = document.getElementById('modalForm');
  const modalHapusGrup = document.getElementById('modalHapusGrup');

  const openModal  = () => modal.classList.remove('hidden');
  const closeModal = () => modal.classList.add('hidden');
  const closeModalHapusGrup = () => modalHapusGrup.classList.add('hidden');

  // close via tombol X & klik overlay
  document.addEventListener('click', (e) => {
    if (e.target.classList && e.target.classList.contains('btnClose')) closeModal();
    if (e.target === modal) closeModal();
    if (e.target === modalHapusGrup) closeModalHapusGrup();
  });

  // ====== Elemen form ======
  const btnTambah       = document.getElementById('btnTambah');
  const produkListMulti = document.getElementById('produkListMulti');

  // Options produk untuk baris dinamis
  const produkOptions = `<?php foreach ($produk_options as $p) {
    echo '<option value="'.(int)$p['id_produk'].'">'.htmlspecialchars($p['nama_produk']).'</option>';
  } ?>`;

  let rowCount = 0;
  function addRow(selectedProduk = '', jumlah = '') {
    rowCount++;
    const id = rowCount;
    const row = document.createElement('div');
    row.className = 'produk-row border border-gray-200 rounded p-3 mb-2';
    row.innerHTML = `
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <div>
          <label class="block text-sm text-gray-700 mb-1">Produk</label>
          <select name="detail[${id}][id_produk]" class="w-full border px-3 py-2 rounded" required>
            <option value="">-- Pilih Produk --</option>
            ${produkOptions}
          </select>
        </div>
        <div>
          <label class="block text-sm text-gray-700 mb-1">Jumlah (kg)</label>
          <input type="number" name="detail[${id}][jumlah]" min="1" class="w-full border px-3 py-2 rounded" required>
        </div>
        <div class="text-right md:text-left">
          <button type="button" class="bg-red-600 text-white text-xs px-3 py-1 rounded btnRemoveRow">Hapus</button>
        </div>
      </div>`;
    produkListMulti.appendChild(row);

    if (selectedProduk !== '') row.querySelector('select').value = String(selectedProduk);
    if (jumlah !== '') row.querySelector('input').value = String(jumlah);
  }

  function resetDetailArea() {
    produkListMulti.innerHTML = '';
    rowCount = 0;
  }

  // ===== Event Delegation untuk tombol dynamic =====
  // Tombol "+ Tambah Produk"
  document.addEventListener('click', function (e) {
    const addBtn = e.target.closest('#btnAddRow');
    if (addBtn) {
      e.preventDefault();
      addRow();
    }
  });
  // Tombol "Hapus" per baris produk
  document.addEventListener('click', function (e) {
    if (e.target && e.target.classList.contains('btnRemoveRow')) {
      e.preventDefault();
      const wrap = e.target.closest('.produk-row');
      if (wrap) wrap.remove();
    }
  });

  // ====== Tambah Grup ======
  btnTambah.onclick = () => {
    document.getElementById('formTitle').textContent = 'Tambah Pesanan';
    document.getElementById('formAction').value = 'tambah';

    // kosongkan kunci grup (bukan edit)
    ['key_nama_old','key_alamat_old','key_tanggal_old','key_status_old'].forEach(id => document.getElementById(id).value = '');

    // reset header
    document.getElementById('formNama').value = '';
    document.getElementById('formAlamat').value = '';
    document.getElementById('formTanggal').value = '<?= date('Y-m-d') ?>';
    document.getElementById('formStatus').value = '';

    // detail multi
    resetDetailArea();
    addRow();

    openModal();
  };

  // ====== Edit / Hapus Grup ======
  function attachGroupHandlers() {
    // Edit Grup
    document.querySelectorAll('.btnEditGrup').forEach(btn => {
      btn.onclick = () => {
        const n = btn.dataset.namaOld || '';
        const a = btn.dataset.alamatOld || '';
        const t = btn.dataset.tanggalOld || '';
        const s = btn.dataset.statusOld || '';
        const items = JSON.parse(btn.dataset.items || '[]'); // [{id_produk, jumlah}, ...]

        document.getElementById('formTitle').textContent = 'Edit Grup';
        document.getElementById('formAction').value = 'edit_grup';

        // isi header default = header lama
        document.getElementById('formNama').value    = n;
        document.getElementById('formAlamat').value  = a;
        document.getElementById('formTanggal').value = t;
        document.getElementById('formStatus').value  = s;

        // set kunci grup lama
        document.getElementById('key_nama_old').value   = n;
        document.getElementById('key_alamat_old').value = a;
        document.getElementById('key_tanggal_old').value= t;
        document.getElementById('key_status_old').value = s;

        // detail: prefill semua item & bisa tambah baris
        resetDetailArea();
        if (items.length) {
          items.forEach(it => addRow(it.id_produk, it.jumlah));
        } else {
          addRow();
        }

        openModal();
      };
    });

    // Hapus Grup
    document.querySelectorAll('.btnHapusGrup').forEach(btn => {
      btn.onclick = () => {
        document.getElementById('hg_nama').value   = btn.dataset.namaOld || '';
        document.getElementById('hg_alamat').value = btn.dataset.alamatOld || '';
        document.getElementById('hg_tanggal').value= btn.dataset.tanggalOld || '';
        document.getElementById('hg_status').value = btn.dataset.statusOld || '';
        modalHapusGrup.classList.remove('hidden');
      };
    });
  }
  attachGroupHandlers();

  document.getElementById('btnCloseHapusGrup').addEventListener('click', closeModalHapusGrup);
  document.getElementById('btnCancelHapusGrup').addEventListener('click', closeModalHapusGrup);

  // ====== Validasi submit ======
  document.getElementById('formDistribusi').addEventListener('submit', function(e) {
    const action = document.getElementById('formAction').value;
    if (action === 'tambah' || action === 'edit_grup') {
      const rows = [...document.querySelectorAll('.produk-row')];
      let ok = 0;
      rows.forEach(r => {
        const pr = r.querySelector('select');
        const j  = r.querySelector('input[type="number"]');
        if (pr && j && pr.value && parseInt(j.value||'0') > 0) ok++;
      });
      if (ok === 0) {
        e.preventDefault();
        alert('Tambahkan minimal 1 produk dengan jumlah yang benar.');
      }
    }
  });
</script>
