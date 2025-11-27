<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
include 'auth.php';
include 'koneksi.php';

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
  header("Location: ../Index.php?page=stok");
  exit;
}

// Ambil daftar produksi yg masih punya sisa stok
$produksi_list = $pdo->query("
  SELECT
    pr.id_produksi,
    pr.id_produk,
    pr.jumlah_dikemas,
    pr.tgl_produksi,
    p.nama_produk,
    pr.jumlah_dikemas -
      COALESCE((SELECT SUM(stok.jumlah_stok) FROM stok WHERE stok.id_produksi = pr.id_produksi), 0) AS sisa_dikemas
  FROM produksi pr
  JOIN produk p ON pr.id_produk = p.id_produk
  WHERE pr.jumlah_dikemas > 0
  HAVING sisa_dikemas > 0
  ORDER BY pr.tgl_produksi DESC
")->fetchAll(PDO::FETCH_ASSOC);

// CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id_admin = 1;

  try {
    switch ($action) {
      case 'tambah':
        $id_produksi = $_POST['id_produksi'];
        $id_produk = $_POST['id_produk'];
        $status_stok = $_POST['status_stok'];
        $jumlah_stok = (int)$_POST['jumlah_stok'];

        // Hitung sisa produksi aktual
        $data = $pdo->query("SELECT jumlah_dikemas -
          COALESCE((SELECT SUM(jumlah_stok) FROM stok WHERE id_produksi = $id_produksi), 0) AS sisa
          FROM produksi WHERE id_produksi = $id_produksi")->fetch();
        $sisa = (int)$data['sisa'];

        if ($jumlah_stok > $sisa) {
          $_SESSION['notif'] = ['pesan' => 'Jumlah stok melebihi sisa produksi!', 'tipe' => 'error'];
        } else {
          $sql = "INSERT INTO stok (id_produk, id_produksi, jumlah_stok, status_stok) VALUES (?, ?, ?, ?)";
          $stmt = $pdo->prepare($sql);
          $stmt->execute([$id_produk, $id_produksi, $jumlah_stok, $status_stok]);
          $_SESSION['notif'] = ['pesan' => 'Data stok dari produksi berhasil ditambahkan!', 'tipe' => 'sukses'];
        }
        break;
      case 'edit':
        $id_stok = $_POST['id_stok_edit'];
        $status_stok = $_POST['status_stok'];
        $jumlah_stok = (int)$_POST['jumlah_stok'];
        // Edit hanya boleh jika jumlah tidak melebihi sisa total (untuk kemudahan edit manual, tdk dibatasi ketat)
        $sql = "UPDATE stok SET status_stok = ?, jumlah_stok = ? WHERE id_stok = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status_stok, $jumlah_stok, $id_stok]);
        $_SESSION['notif'] = ['pesan' => 'Data stok berhasil diperbarui!', 'tipe' => 'sukses'];
        break;
      case 'hapus':
        $id_stok = $_POST['id_stok_hapus'];
        $sql = "DELETE FROM stok WHERE id_stok = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_stok]);
        $_SESSION['notif'] = ['pesan' => 'Data stok berhasil dihapus.', 'tipe' => 'sukses'];
        break;
    }
  } catch (PDOException $e) {
    $_SESSION['notif'] = ['pesan' => 'Terjadi kesalahan database: ' . $e->getMessage(), 'tipe' => 'error'];
  }
  header("Location: Index.php?page=stok");
  exit;
}

// Baca data stok
$sql_stok = "SELECT s.id_stok, s.id_produk, s.id_produksi, p.nama_produk, s.status_stok, s.jumlah_stok
             FROM stok s 
             JOIN produk p ON s.id_produk = p.id_produk 
             ORDER BY s.id_stok DESC";
$stok_list = $pdo->query($sql_stok)->fetchAll(PDO::FETCH_ASSOC);

$stok_summary = $pdo->query("SELECT status_stok, SUM(jumlah_stok) as total_jumlah 
                             FROM stok 
                             GROUP BY status_stok")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 bg-gray-100">
  <section class="p-6 overflow-x-auto">
    <?php if (isset($_SESSION['notif'])): ?>
      <div class="mb-4 flex items-center p-4 rounded-md font-bold shadow <?php
        echo $_SESSION['notif']['tipe'] === 'sukses'
          ? 'bg-green-500 text-white'
          : 'bg-red-500 text-white';
      ?>">
        <i class="fas <?php
          echo $_SESSION['notif']['tipe'] === 'sukses'
            ? 'fa-check-circle'
            : 'fa-exclamation-triangle';
        ?> mr-3 text-2xl"></i>
        <span><?php echo htmlspecialchars($_SESSION['notif']['pesan']); ?></span>
        <button onclick="this.parentNode.style.display='none'" class="ml-auto bg-transparent border-none text-white text-2xl font-bold opacity-80 hover:opacity-100">&times;</button>
      </div>
      <?php unset($_SESSION['notif']); ?>
    <?php endif; ?>

    <button id="btnTambahStok" class="mb-4 inline-flex items-center gap-2 bg-orange-600 text-white text-sm font-normal px-4 py-2 rounded hover:bg-orange-700 transition-colors" type="button">
      <i class="fas fa-plus"></i> Tambah Stok Dari Produksi
    </button>

    <table class="w-full border border-gray-300 text-sm bg-white">
      <thead class="bg-[#FDF5CA] sticky top-0 z-10">
        <tr class="text-black text-left">
          <th class="border border-gray-300 px-3 py-2">No.</th>
          <th class="border border-gray-300 px-3 py-2">ID Produksi</th>
          <th class="border border-gray-300 px-3 py-2">ID Produk</th>
          <th class="border border-gray-300 px-3 py-2">Nama Produk</th>
          <th class="border border-gray-300 px-3 py-2">Status Stok</th>
          <th class="border border-gray-300 px-3 py-2">Jumlah Stok</th>
          <th class="border border-gray-300 px-3 py-2">Aksi</th>
        </tr>
      </thead>
      <tbody class="text-left">
        <?php if (empty($stok_list)): ?>
          <tr>
            <td colspan="7" class="border border-gray-300 px-3 py-4 text-center text-gray-500">
              Belum ada data stok.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($stok_list as $index => $item): ?>
            <tr>
              <td class="border border-gray-300 px-3 py-2"><?php echo $index + 1; ?>.</td>
              <td class="border border-gray-300 px-3 py-2"><?php echo htmlspecialchars($item['id_produksi']); ?></td>
              <td class="border border-gray-300 px-3 py-2"><?php echo htmlspecialchars($item['id_produk']); ?></td>
              <td class="border border-gray-300 px-3 py-2"><?php echo htmlspecialchars($item['nama_produk']); ?></td>
              <td class="border border-gray-300 px-3 py-2"><?php echo htmlspecialchars($item['status_stok']); ?></td>
              <td class="border border-gray-300 px-3 py-2"><?php echo htmlspecialchars($item['jumlah_stok']); ?> kg</td>
              <td class="border border-gray-300 px-3 py-2 space-x-2">
                <button class="btnEdit bg-blue-700 text-white text-xs px-3 py-1 rounded"
                  data-id-stok="<?php echo $item['id_stok']; ?>"
                  data-id-produk="<?php echo $item['id_produk']; ?>"
                  data-id-produksi="<?php echo $item['id_produksi']; ?>"
                  data-nama-produk="<?php echo htmlspecialchars($item['nama_produk']); ?>"
                  data-status="<?php echo htmlspecialchars($item['status_stok']); ?>"
                  data-jumlah_stok="<?php echo $item['jumlah_stok']; ?>">Edit</button>
                <button class="btnHapus bg-red-700 text-white text-xs px-3 py-1 rounded"
                  data-id-stok="<?php echo $item['id_stok']; ?>">Hapus</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="mt-8">
      <h3 class="text-lg font-semibold mb-2 text-gray-700">Ringkasan Stok</h3>
      <table class="w-full max-w-md border border-gray-300 text-sm bg-white">
        <thead class="bg-[#FDF5CA] sticky top-0 z-10">
          <tr class="text-black text-left">
            <th class="border border-gray-300 px-3 py-2 w-12">No.</th>
            <th class="border border-gray-300 px-3 py-2">Status Stok</th>
            <th class="border border-gray-300 px-3 py-2">Total Jumlah Stok (kg)</th>
          </tr>
        </thead>
        <tbody class="text-left">
          <?php if (empty($stok_summary)): ?>
            <tr>
              <td colspan="3" class="border border-gray-300 px-3 py-4 text-center text-gray-500">Data ringkasan kosong.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($stok_summary as $index => $summary): ?>
              <tr>
                <td class="border border-gray-300 px-3 py-2"><?php echo $index + 1; ?>.</td>
                <td class="border border-gray-300 px-3 py-2"><?php echo htmlspecialchars($summary['status_stok']); ?></td>
                <td class="border border-gray-300 px-3 py-2"><?php echo htmlspecialchars($summary['total_jumlah']); ?> kg</td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- MODAL TAMBAH STOK DARI PRODUKSI -->
  <div id="modalTambah" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <form action="" method="POST" class="bg-white p-6 shadow-md rounded w-80 relative">
      <button type="button" class="btnClose absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-xl font-bold" aria-label="Close modal">&times;</button>
      <h2 class="text-black font-semibold text-lg mb-4">Tambah Stok Dari Produksi</h2>
      <input type="hidden" name="action" value="tambah">

      <div class="mb-4">
        <label for="id_produksi_tambah" class="block text-sm font-medium text-gray-700 mb-1">Pilih Slot Produksi</label>
        <select name="id_produksi" id="id_produksi_tambah" class="w-full px-3 py-2 border border-gray-300 rounded shadow-sm" required>
          <option value="" disabled selected>-- Pilih Produksi --</option>
          <?php foreach ($produksi_list as $p): ?>
            <option value="<?= $p['id_produksi'] ?>"
              data-id-produk="<?= $p['id_produk'] ?>"
              data-nama-produk="<?= htmlspecialchars($p['nama_produk']) ?>"
              data-jumlah-dikemas="<?= $p['jumlah_dikemas'] ?>"
              data-sisa-dikemas="<?= $p['sisa_dikemas'] ?>">
              <?= $p['nama_produk'] ?> | <?= date('d-m-Y', strtotime($p['tgl_produksi'])) ?> (Dikemas: <?= $p['jumlah_dikemas'] ?> kg, Sisa: <?= $p['sisa_dikemas'] ?> kg)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <input type="hidden" name="id_produk" id="id_produk_tambah_hidden">
      <div class="mb-2">
        <label>Nama Produk</label>
        <input type="text" id="nama_produk_auto" class="w-full px-3 py-2 bg-gray-100" readonly>
      </div>
      <div class="mb-2 flex gap-2">
        <div class="flex-1">
          <label>Jumlah Dikemas</label>
          <input type="text" id="jumlah_dikemas_auto" class="w-full px-3 py-2 bg-gray-100" readonly>
        </div>
        <div class="flex-1">
          <label>Sisa Produksi</label>
          <input type="text" id="sisa_dikemas_auto" class="w-full px-3 py-2 bg-gray-100" readonly>
        </div>
      </div>
      <div class="mb-2">
        <label>Jumlah Stok Diambil <span id="maxStokInfo" class="text-xs text-gray-500"></span></label>
        <input type="number" name="jumlah_stok" id="jumlah_stok_tambah" class="w-full px-3 py-2 border border-gray-300 rounded" min="1" required />
        <div id="jumlahError" class="text-xs text-red-600 mt-1 hidden"></div>
      </div>
      <div class="mb-4">
        <label>Status Stok</label>
        <select name="status_stok" id="status_stok_tambah" class="w-full px-3 py-2 border border-gray-300 rounded" required>
          <option value="" disabled selected>-- Pilih Status --</option>
          <option value="Siap dikemas">Siap dikemas</option>
          <option value="Siap dipacking">Siap dipacking</option>
          <option value="Sudah dipacking">Sudah dipacking</option>
          <option value="Reject">Reject</option>
        </select>
      </div>
      <button type="submit" name="submit" class="w-full bg-orange-600 text-white py-2 rounded hover:bg-orange-700 transition-colors">Simpan</button>
    </form>
  </div>

  <!-- MODAL EDIT -->
  <div id="modalEdit" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <form action="" method="POST" class="bg-white p-6 shadow-md rounded w-80 relative">
      <button type="button" class="btnClose absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-xl font-bold" aria-label="Close modal">&times;</button>
      <h2 class="text-black font-semibold text-lg mb-4">Edit Stok</h2>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id_stok_edit" id="id_stok_edit">

      <div class="mb-4">
        <label for="nama_produk_edit" class="block text-sm font-medium text-gray-700 mb-1">Nama Produk</label>
        <input type="text" id="nama_produk_edit" class="w-full px-3 py-2 border bg-gray-200 text-gray-500 rounded" readonly />
      </div>

      <div class="mb-4">
        <label for="status_stok_edit" class="block text-sm font-medium text-gray-700 mb-1">Status Stok</label>
        <select name="status_stok" id="status_stok_edit" class="w-full px-3 py-2 border border-gray-300 rounded shadow-sm" required>
          <option value="Siap dikemas">Siap dikemas</option>
          <option value="Siap dipacking">Siap dipacking</option>
          <option value="Sudah dipacking">Sudah dipacking</option>
          <option value="Reject">Reject</option>
        </select>
      </div>

      <div class="mb-6">
        <label for="jumlah_stok_edit" class="block text-sm font-medium text-gray-700 mb-1">Jumlah (kg)</label>
        <input type="number" name="jumlah_stok" id="jumlah_stok_edit" min="0" class="w-full px-3 py-2 border border-gray-300 rounded shadow-sm" required />
      </div>

      <button type="submit" name="submit" class="w-full bg-orange-600 text-white py-2 rounded hover:bg-orange-700 transition-colors">Simpan Perubahan</button>
    </form>
  </div>

  <!-- MODAL HAPUS -->
  <div id="modalHapus" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="w-[320px] border border-gray-300 shadow-md p-6 bg-white rounded-md relative">
      <button type="button" class="btnClose absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-xl font-bold" aria-label="Close modal">&times;</button>
      <h2 class="font-semibold text-black mb-3 text-lg">Konfirmasi Hapus</h2>
      <p class="text-gray-700 mb-5 text-sm leading-relaxed">Apakah Anda yakin akan menghapus data stok ini?</p>
      <form action="" method="POST" class="flex justify-end space-x-3">
        <input type="hidden" name="action" value="hapus">
        <input type="hidden" name="id_stok_hapus" id="id_stok_hapus">
        <button type="button" class="btnCancelHapus border border-gray-400 text-black text-sm font-medium rounded px-4 py-2 hover:bg-gray-100">Batal</button>
        <button type="submit" class="bg-red-600 text-white text-sm font-medium rounded px-4 py-2 hover:bg-red-700">Ya, Hapus</button>
      </form>
    </div>
  </div>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const modalTambah = document.getElementById('modalTambah');
    const modalEdit = document.getElementById('modalEdit');
    const modalHapus = document.getElementById('modalHapus');

    const btnTambahStok = document.getElementById('btnTambahStok');
    const btnCancelHapus = document.querySelector('.btnCancelHapus');
    const allModals = [modalTambah, modalEdit, modalHapus];

    const openModal = (modal) => modal.classList.remove('hidden');
    const closeModal = (modal) => modal.classList.add('hidden');

    btnTambahStok.addEventListener('click', () => openModal(modalTambah));
    btnCancelHapus.addEventListener('click', () => closeModal(modalHapus));

    allModals.forEach(modal => {
      modal.querySelector('.btnClose').addEventListener('click', () => closeModal(modal));
      modal.addEventListener('click', e => {
        if (e.target === modal) closeModal(modal);
      });
    });

    // Modal Tambah: isi otomatis & validasi jumlah stok
    var selectProd = document.getElementById('id_produksi_tambah');
    var jumlahStokInput = document.getElementById('jumlah_stok_tambah');
    var maxStokInfo = document.getElementById('maxStokInfo');
    var errorDiv = document.getElementById('jumlahError');

    selectProd.addEventListener('change', function(e) {
      var selected = e.target.selectedOptions[0];
      document.getElementById('jumlah_dikemas_auto').value = selected.dataset.jumlahDikemas || '';
      document.getElementById('sisa_dikemas_auto').value = selected.dataset.sisaDikemas || '';
      document.getElementById('nama_produk_auto').value = selected.dataset.namaProduk || '';
      document.getElementById('id_produk_tambah_hidden').value = selected.dataset.idProduk || '';
      jumlahStokInput.value = '';
      jumlahStokInput.max = selected.dataset.sisaDikemas || '';
      maxStokInfo.textContent = '(maks: ' + (selected.dataset.sisaDikemas || '-') + ' kg)';
      errorDiv.classList.add('hidden');
    });

    jumlahStokInput.addEventListener('input', function() {
      var max = parseInt(jumlahStokInput.max) || 0;
      if (parseInt(jumlahStokInput.value) > max) {
        errorDiv.textContent = 'Jumlah stok tidak boleh melebihi sisa produksi!';
        errorDiv.classList.remove('hidden');
      } else {
        errorDiv.classList.add('hidden');
      }
    });

    // Tombol Edit
    document.querySelectorAll('.btnEdit').forEach(button => {
      button.addEventListener('click', (e) => {
        const data = e.target.dataset;
        document.getElementById('id_stok_edit').value = data.idStok;
        document.getElementById('nama_produk_edit').value = `${data.namaProduk} (ID: ${data.idProduk})`;
        document.getElementById('status_stok_edit').value = data.status;
        document.getElementById('jumlah_stok_edit').value = data.jumlah_stok;
        openModal(modalEdit);
      });
    });

    // Tombol Hapus
    document.querySelectorAll('.btnHapus').forEach(button => {
      button.addEventListener('click', (e) => {
        const idStok = e.target.dataset.idStok;
        document.getElementById('id_stok_hapus').value = idStok;
        openModal(modalHapus);
      });
    });
  });
</script>
