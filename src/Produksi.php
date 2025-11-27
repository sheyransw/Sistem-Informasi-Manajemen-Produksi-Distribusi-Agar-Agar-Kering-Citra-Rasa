<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
include 'auth.php';
include 'koneksi.php';

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
  header("Location: ../Index.php?page=produksi");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id_admin = 1;

  try {
    switch ($action) {
      case 'tambah':
        $id_produk = $_POST['id_produk'];
        $id_jadwal = $_POST['id_jadwal'];
        $jumlah_produksi = (int)$_POST['jumlah_produksi'];
        $jumlah_dikemas = (int)$_POST['jumlah_dikemas'];
        $jumlah_reject = $jumlah_produksi - $jumlah_dikemas;

        if ($jumlah_dikemas > $jumlah_produksi) {
          $_SESSION['notif'] = ['pesan' => 'Jumlah dikemas tidak boleh melebihi jumlah produksi.', 'tipe' => 'error'];
        } else {
          $sql = "INSERT INTO produksi (id_produk, id_jadwal, jumlah_produksi, jumlah_dikemas, jumlah_reject, id_admin) VALUES (?, ?, ?, ?, ?, ?)";
          $stmt = $pdo->prepare($sql);
          $stmt->execute([$id_produk, $id_jadwal, $jumlah_produksi, $jumlah_dikemas, $jumlah_reject, $id_admin]);
          $_SESSION['notif'] = ['pesan' => 'Data produksi berhasil ditambahkan!', 'tipe' => 'sukses'];
        }
        break;

      case 'edit':
        $id_produksi = $_POST['id_produksi_edit'];
        $id_produk = $_POST['id_produk'];
        $id_jadwal = $_POST['id_jadwal'];
        $jumlah_produksi = (int)$_POST['jumlah_produksi'];
        $jumlah_dikemas = (int)$_POST['jumlah_dikemas'];
        $jumlah_reject = $jumlah_produksi - $jumlah_dikemas;

        if ($jumlah_dikemas > $jumlah_produksi) {
          $_SESSION['notif'] = ['pesan' => 'Jumlah dikemas tidak boleh melebihi jumlah produksi.', 'tipe' => 'error'];
        } else {
          $sql = "UPDATE produksi SET id_produk = ?, id_jadwal = ?, jumlah_produksi = ?, jumlah_dikemas = ?, jumlah_reject = ? WHERE id_produksi = ?";
          $stmt = $pdo->prepare($sql);
          $stmt->execute([$id_produk, $id_jadwal, $jumlah_produksi, $jumlah_dikemas, $jumlah_reject, $id_produksi]);
          $_SESSION['notif'] = ['pesan' => 'Data produksi berhasil diperbarui!', 'tipe' => 'sukses'];
        }
        break;

      case 'hapus':
        $id_produksi = $_POST['id_produksi_hapus'];
        $sql = "DELETE FROM produksi WHERE id_produksi = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_produksi]);
        $_SESSION['notif'] = ['pesan' => 'Data produksi berhasil dihapus.', 'tipe' => 'sukses'];
        break;
    }
  } catch (PDOException $e) {
    $_SESSION['notif'] = ['pesan' => 'Terjadi kesalahan database: ' . $e->getMessage(), 'tipe' => 'error'];
  }
  header("Location: Index.php?page=produksi");
  exit;
}

$sql_produksi = "SELECT prod.*, p.nama_produk, j.tanggal, j.waktu_mulai, j.waktu_selesai
                 FROM produksi prod
                 JOIN produk p ON prod.id_produk = p.id_produk
                 JOIN jadwal j ON prod.id_jadwal = j.id_jadwal
                 ORDER BY j.tanggal DESC";
$produksi_list = $pdo->query($sql_produksi)->fetchAll(PDO::FETCH_ASSOC);

$produk_options = $pdo->query("SELECT id_produk, nama_produk FROM produk ORDER BY nama_produk ASC")->fetchAll(PDO::FETCH_ASSOC);

$jadwal_opsi = $pdo->query("
  SELECT j.id_jadwal, j.tanggal, j.waktu_mulai, j.waktu_selesai
  FROM jadwal j
  WHERE j.jenis_kegiatan = 'Produksi'
    AND j.id_jadwal NOT IN (SELECT id_jadwal FROM produksi)
  ORDER BY j.tanggal DESC
")->fetchAll(PDO::FETCH_ASSOC);

$jadwal_all = $pdo->query("
  SELECT id_jadwal, tanggal, waktu_mulai, waktu_selesai
  FROM jadwal
  WHERE jenis_kegiatan = 'Produksi'
  ORDER BY tanggal DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totals = $pdo->query("SELECT 
                        SUM(jumlah_produksi) as total_produksi,
                        SUM(jumlah_dikemas) as total_dikemas,
                        SUM(jumlah_reject) as total_reject
                      FROM produksi")->fetch(PDO::FETCH_ASSOC);

$total_produksi = $totals['total_produksi'] ?? 0;
$total_dikemas = $totals['total_dikemas'] ?? 0;
$total_reject = $totals['total_reject'] ?? 0;
?>

<main class="flex-1 bg-gray-100">
  <section class="p-6 overflow-x-auto space-y-6">

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

    <button id="btnTambahProduk" type="button" class="inline-flex items-center gap-2 rounded-md bg-orange-600 px-4 py-2 text-white text-sm font-normal hover:bg-orange-700 transition-colors">
      <i class="fas fa-plus"></i> Tambah Produksi
    </button>

    <div class="overflow-x-auto mt-4">
      <table class="w-full border border-gray-300 text-sm border-collapse bg-white">
        <thead class="bg-[#FDF5CA] sticky top-0 z-10">
          <tr class="text-center text-xs font-normal text-black">
            <th class="border border-gray-300 px-2 py-1 w-12">No.</th>
            <th class="border border-gray-300 px-2 py-1 w-48">Nama Produk</th>
            <th class="border border-gray-300 px-2 py-1 w-32">Tanggal Jadwal</th>
            <th class="border border-gray-300 px-2 py-1 w-28">Jam Jadwal</th>
            <th class="border border-gray-300 px-2 py-1 w-28">Jumlah Produksi</th>
            <th class="border border-gray-300 px-2 py-1 w-28">Jumlah Dikemas</th>
            <th class="border border-gray-300 px-2 py-1 w-28">Jumlah Reject</th>
            <th class="border border-gray-300 px-2 py-1 w-24">Aksi</th>
          </tr>
        </thead>
        <tbody class="text-center text-xs text-black">
          <?php if (empty($produksi_list)): ?>
            <tr>
              <td colspan="8" class="border border-gray-300 px-2 py-4 text-center text-gray-500">
                Belum ada data produksi. Silakan tambahkan.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($produksi_list as $index => $item): ?>
              <tr>
                <td class="border border-gray-300 px-2 py-1"><?php echo $index + 1; ?>.</td>
                <td class="border border-gray-300 px-2 py-1"><?php echo htmlspecialchars($item["nama_produk"]); ?></td>
                <td class="border border-gray-300 px-2 py-1"><?php echo htmlspecialchars(date('d-m-Y', strtotime($item["tanggal"]))); ?></td>
                <td class="border border-gray-300 px-2 py-1"><?php echo htmlspecialchars(substr($item["waktu_mulai"],0,5) . ' - ' . substr($item["waktu_selesai"],0,5)); ?></td>
                <td class="border border-gray-300 px-2 py-1"><?php echo htmlspecialchars($item["jumlah_produksi"]); ?> kg</td>
                <td class="border border-gray-300 px-2 py-1"><?php echo htmlspecialchars($item["jumlah_dikemas"]); ?> kg</td>
                <td class="border border-gray-300 px-2 py-1"><?php echo htmlspecialchars($item["jumlah_reject"]); ?> kg</td>
                <td class="border border-gray-300 px-2 py-1 space-x-1">
                  <button type="button" class="btnEdit bg-[#3249b3] text-white text-xs px-3 py-0.5 rounded hover:bg-[#2a3b8a] transition-colors"
                    data-id-produksi="<?php echo $item['id_produksi']; ?>"
                    data-id-produk="<?php echo $item['id_produk']; ?>"
                    data-id-jadwal="<?php echo $item['id_jadwal']; ?>"
                    data-nama-produk="<?php echo htmlspecialchars($item['nama_produk']); ?>"
                    data-tanggal="<?php echo $item['tanggal']; ?>"
                    data-jam="<?php echo substr($item["waktu_mulai"],0,5) . ' - ' . substr($item["waktu_selesai"],0,5); ?>"
                    data-jumlah-produksi="<?php echo $item['jumlah_produksi']; ?>"
                    data-jumlah-dikemas="<?php echo $item['jumlah_dikemas']; ?>"
                    data-jumlah-reject="<?php echo $item['jumlah_reject']; ?>"
                  >Edit</button>
                  <button type="button" class="btnHapus bg-red-700 text-white text-xs px-3 py-0.5 rounded hover:bg-red-800 transition-colors"
                    data-id-produksi="<?php echo $item['id_produksi']; ?>">Hapus</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="font-semibold text-black mt-6 mb-1 text-sm">Ringkasan Produksi</div>
    <table class="w-full max-w-xs border border-gray-300 border-collapse text-xs text-black bg-white mt-1">
      <thead>
        <tr class="bg-[#FDF5CA] text-center font-normal">
          <th class="border border-gray-300 px-2 py-1" colspan="2">Jumlah</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="border border-gray-300 px-2 py-1 font-medium">Total Produksi</td>
          <td class="border border-gray-300 px-2 py-1 text-center"><?php echo $total_produksi; ?> kg</td>
        </tr>
        <tr>
          <td class="border border-gray-300 px-2 py-1 font-medium">Total Dikemas</td>
          <td class="border border-gray-300 px-2 py-1 text-center"><?php echo $total_dikemas; ?> kg</td>
        </tr>
        <tr>
          <td class="border border-gray-300 px-2 py-1 font-medium">Total Reject</td>
          <td class="border border-gray-300 px-2 py-1 text-center"><?php echo $total_reject; ?> kg</td>
        </tr>
      </tbody>
    </table>
  </section>
</main>

<!-- MODAL TAMBAH PRODUKSI -->
<div id="modalTambah" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <form action="" method="POST" class="w-80 bg-white p-6 rounded shadow-lg relative">
    <h2 class="text-black text-lg font-semibold mb-4">Input Produksi</h2>
    <input type="hidden" name="action" value="tambah">

    <div class="flex flex-col mb-3">
      <label for="id_produk" class="mb-1 text-sm font-medium text-gray-700">Nama Produk</label>
      <select name="id_produk" id="id_produk" class="w-full px-3 py-2 rounded border border-gray-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600" required>
        <option value="" disabled selected>-- Pilih Produk --</option>
        <?php foreach ($produk_options as $option): ?>
          <option value="<?php echo $option['id_produk']; ?>"><?php echo htmlspecialchars($option['nama_produk']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex flex-col mb-3">
      <label for="id_jadwal" class="mb-1 text-sm font-medium text-gray-700">Slot Jadwal Produksi</label>
      <select name="id_jadwal" id="id_jadwal" class="w-full px-3 py-2 rounded border border-gray-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600" required>
        <option value="" disabled selected>-- Pilih Slot Jadwal --</option>
        <?php foreach ($jadwal_opsi as $j): ?>
          <option value="<?= $j['id_jadwal'] ?>">
            <?= date('d M Y', strtotime($j['tanggal'])) ?> (<?= substr($j['waktu_mulai'],0,5) ?>-<?= substr($j['waktu_selesai'],0,5) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <input type="number" name="jumlah_produksi" id="jumlah_produksi" min="0" placeholder="Jumlah Produksi (kg)" class="w-full mb-3 px-3 py-2 rounded border border-gray-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600" required />
    <input type="number" name="jumlah_dikemas" id="jumlah_dikemas" min="0" placeholder="Jumlah Dikemas (kg)" class="w-full mb-3 px-3 py-2 rounded border border-gray-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600" required />
    <input type="number" name="jumlah_reject" id="jumlah_reject" readonly class="w-full mb-3 px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 shadow-sm" placeholder="Jumlah Reject (kg)" />

    <div class="text-xs text-gray-600 mb-3">Jumlah <b>reject</b> akan otomatis dihitung (<i>produksi - dikemas</i>).</div>
    <div class="text-red-500 text-xs mb-3 hidden error-message"></div>

    <button type="submit" name="submit" class="w-full bg-orange-600 text-white py-2 rounded hover:bg-orange-700 transition">Simpan</button>
    <button type="button" id="btnCloseTambah" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700" aria-label="Close modal"><i class="fas fa-times"></i></button>
  </form>
</div>

<!-- MODAL EDIT PRODUKSI -->
<div id="modalEdit" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <form id="formEdit" action="" method="POST" class="w-80 bg-white p-6 rounded shadow-lg relative">
    <h1 class="text-black text-lg font-semibold mb-4">Edit Produksi</h1>
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id_produksi_edit" id="id_produksi_edit">
    
    <div class="flex flex-col mb-3">
      <label for="edit_id_produk" class="mb-1 text-sm font-medium text-gray-700">Nama Produk</label>
      <select name="id_produk" id="edit_id_produk" class="w-full px-3 py-2 rounded border border-gray-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600" required>
        <option value="" disabled>-- Pilih Produk --</option>
        <?php foreach ($produk_options as $option): ?>
          <option value="<?php echo $option['id_produk']; ?>"><?php echo htmlspecialchars($option['nama_produk']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="flex flex-col mb-3">
      <label for="edit_id_jadwal" class="mb-1 text-sm font-medium text-gray-700">Slot Jadwal Produksi</label>
      <select name="id_jadwal" id="edit_id_jadwal" class="w-full px-3 py-2 rounded border border-gray-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600" required>
        <option value="" disabled selected>-- Pilih Slot Jadwal --</option>
        <?php foreach ($jadwal_all as $j): ?>
          <option value="<?= $j['id_jadwal'] ?>">
            <?= date('d M Y', strtotime($j['tanggal'])) ?> (<?= substr($j['waktu_mulai'],0,5) ?>-<?= substr($j['waktu_selesai'],0,5) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <input type="number" id="editJumlahProduksi" min="0" name="jumlah_produksi" placeholder="Jumlah Produksi (kg)" class="w-full mb-3 px-3 py-2 rounded border border-gray-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600" required />
    <input type="number" id="editJumlahDikemas" min="0" name="jumlah_dikemas" placeholder="Jumlah Dikemas (kg)" class="w-full mb-3 px-3 py-2 rounded border border-gray-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600" required />
    <input type="number" id="editJumlahReject" name="jumlah_reject" readonly class="w-full mb-3 px-3 py-2 bg-gray-100 text-gray-400 border border-gray-300 shadow-sm" placeholder="Jumlah Reject (kg)" />

    <div class="text-xs text-gray-600 mb-3">Jumlah <b>reject</b> otomatis dihitung (<i>produksi - dikemas</i>).</div>
    <div class="text-red-500 text-xs mb-3 hidden error-message"></div>

    <button type="submit" class="w-full bg-orange-600 text-white py-2 rounded hover:bg-orange-700 transition">Simpan Perubahan</button>
    <button type="button" id="btnCloseEdit" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700" aria-label="Close modal"><i class="fas fa-times"></i></button>
  </form>
</div>

<!-- MODAL HAPUS PRODUKSI -->
<div id="modalHapus" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="w-[320px] border border-gray-300 shadow-md p-6 bg-white rounded-md relative">
    <h2 class="font-semibold text-black mb-3 text-lg">Konfirmasi Hapus</h2>
    <p class="text-gray-700 mb-5 text-sm leading-relaxed">Apakah Anda yakin akan menghapus data produksi ini?</p>
    <form action="" method="POST" class="flex justify-end space-x-3">
      <input type="hidden" name="action" value="hapus">
      <input type="hidden" name="id_produksi_hapus" id="id_produksi_hapus">
      <button type="button" id="btnCancelHapus" class="border border-gray-400 text-black text-sm font-medium rounded px-4 py-2 hover:bg-gray-100">Batal</button>
      <button type="submit" class="bg-red-600 text-white text-sm font-medium rounded px-4 py-2 hover:bg-red-700">Ya, Hapus</button>
    </form>
    <button type="button" id="btnCloseHapus" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700" aria-label="Close modal"><i class="fas fa-times"></i></button>
  </div>
</div>

<script>
  // ===== AUTO-CALC REJECT: MODAL TAMBAH & EDIT =====
  function setupRejectAutoCalc(prefix='') {
    const prodInput = document.getElementById(prefix+'JumlahProduksi') || document.getElementById(prefix+'jumlah_produksi');
    const kemasInput = document.getElementById(prefix+'JumlahDikemas') || document.getElementById(prefix+'jumlah_dikemas');
    const rejectInput = document.getElementById(prefix+'JumlahReject') || document.getElementById(prefix+'jumlah_reject');
    if (!prodInput || !kemasInput || !rejectInput) return;
    function calc() {
      const prod = parseInt(prodInput.value) || 0;
      const kemas = parseInt(kemasInput.value) || 0;
      let reject = prod - kemas;
      rejectInput.value = reject >= 0 ? reject : 0;
    }
    prodInput.addEventListener('input', calc);
    kemasInput.addEventListener('input', calc);
    // Init
    calc();
  }

  // Modal TAMBAH
  setupRejectAutoCalc('');
  // Modal EDIT
  setupRejectAutoCalc('edit');

  document.addEventListener('DOMContentLoaded', function() {
    const modalTambah = document.getElementById('modalTambah');
    const modalEdit = document.getElementById('modalEdit');
    const modalHapus = document.getElementById('modalHapus');
    const btnTambah = document.getElementById('btnTambahProduk');
    const btnCloseTambah = document.getElementById('btnCloseTambah');
    const btnCloseEdit = document.getElementById('btnCloseEdit');
    const btnCloseHapus = document.getElementById('btnCloseHapus');
    const btnCancelHapus = document.getElementById('btnCancelHapus');

    function openModal(modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    function closeModal(modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }

    btnTambah.addEventListener('click', () => { openModal(modalTambah); setupRejectAutoCalc(''); });
    btnCloseTambah.addEventListener('click', () => closeModal(modalTambah));
    btnCloseEdit.addEventListener('click', () => closeModal(modalEdit));
    btnCloseHapus.addEventListener('click', () => closeModal(modalHapus));
    btnCancelHapus.addEventListener('click', () => closeModal(modalHapus));
    [modalTambah, modalEdit, modalHapus].forEach(modal => {
      modal.addEventListener('click', e => { if (e.target === modal) closeModal(modal); });
    });

    // --- Tombol Edit ---
    document.querySelectorAll('.btnEdit').forEach(button => {
      button.addEventListener('click', (e) => {
        const data = e.currentTarget.dataset;
        document.getElementById('id_produksi_edit').value = data.idProduksi;
        document.getElementById('edit_id_produk').value = data.idProduk;
        document.getElementById('edit_id_jadwal').value = data.idJadwal;
        document.getElementById('editJumlahProduksi').value = data.jumlahProduksi;
        document.getElementById('editJumlahDikemas').value = data.jumlahDikemas;
        document.getElementById('editJumlahReject').value = data.jumlahReject;
        setupRejectAutoCalc('edit');
        openModal(modalEdit);
      });
    });

    document.querySelectorAll('.btnHapus').forEach(button => {
      button.addEventListener('click', (e) => {
        const idProduksi = e.currentTarget.dataset.idProduksi;
        document.getElementById('id_produksi_hapus').value = idProduksi;
        openModal(modalHapus);
      });
    });

    // Validasi
    function validateQuantities(modal, prefix='') {
      const produksiInput = document.getElementById(prefix+'JumlahProduksi') || document.getElementById(prefix+'jumlah_produksi');
      const dikemasInput = document.getElementById(prefix+'JumlahDikemas') || document.getElementById(prefix+'jumlah_dikemas');
      const saveButton = modal.querySelector('button[type="submit"]');
      const errorMessage = modal.querySelector('.error-message');
      const check = () => {
        const produksi = parseInt(produksiInput.value) || 0;
        const dikemas = parseInt(dikemasInput.value) || 0;
        if (dikemas > produksi) {
          errorMessage.textContent = 'Jumlah dikemas tidak boleh melebihi produksi.';
          errorMessage.classList.remove('hidden');
          saveButton.disabled = true;
          saveButton.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
          errorMessage.classList.add('hidden');
          saveButton.disabled = false;
          saveButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
      };
      [produksiInput, dikemasInput].forEach(input => { input.addEventListener('input', check); });
    }
    validateQuantities(modalTambah, '');
    validateQuantities(modalEdit, 'edit');
  });
</script>