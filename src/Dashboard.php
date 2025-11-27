<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include 'auth.php';
include 'koneksi.php';

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
  header("Location: ../Index.php?page=dashboard");
  exit;
}

// ======== LOGIKA CRUD ========

// Create & Update Jadwal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_jadwal'])) {
  $id_jadwal = $_POST['id_jadwal'] ?? null;
  $tanggal = $_POST['tanggal'];
  $waktu_mulai = $_POST['jam_mulai'];
  $waktu_selesai = $_POST['jam_selesai'];
  $jenis_kegiatan = $_POST['jenis_kegiatan'];
  $id_admin = 1; // Tetap simpan admin di database tapi tidak ditampilkan

  try {
    if (empty($id_jadwal)) {
      $sql = "INSERT INTO jadwal (tanggal, waktu_mulai, waktu_selesai, jenis_kegiatan, id_admin) VALUES (?, ?, ?, ?, ?)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$tanggal, $waktu_mulai, $waktu_selesai, $jenis_kegiatan, $id_admin]);
      $_SESSION['notif'] = ['pesan' => 'Jadwal baru berhasil ditambahkan!', 'tipe' => 'sukses'];
    } else {
      $sql = "UPDATE jadwal SET tanggal = ?, waktu_mulai = ?, waktu_selesai = ?, jenis_kegiatan = ? WHERE id_jadwal = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$tanggal, $waktu_mulai, $waktu_selesai, $jenis_kegiatan, $id_jadwal]);
      $_SESSION['notif'] = ['pesan' => 'Jadwal berhasil diperbarui!', 'tipe' => 'sukses'];
    }
  } catch (PDOException $e) {
    $_SESSION['notif'] = ['pesan' => 'Terjadi kesalahan: ' . $e->getMessage(), 'tipe' => 'error'];
  }
  header("Location: Index.php?page=dashboard");
  exit;
}

// Hapus Jadwal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_jadwal'])) {
  $id_jadwal = $_POST['id_jadwal_delete'];
  try {
    $sql = "DELETE FROM jadwal WHERE id_jadwal = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_jadwal]);
    $_SESSION['notif'] = ['pesan' => 'Jadwal berhasil dihapus.', 'tipe' => 'sukses'];
  } catch (PDOException $e) {
    $_SESSION['notif'] = ['pesan' => 'Gagal menghapus jadwal: ' . $e->getMessage(), 'tipe' => 'error'];
  }
  header("Location: Index.php?page=dashboard");
  exit;
}

// ======== DATA DASHBOARD ========
$totalPesanan = $pdo->query("SELECT SUM(jumlah_pesanan) FROM distribusi")->fetchColumn() ?: 0;
$totalProduksi = $pdo->query("SELECT SUM(jumlah_produksi) FROM produksi")->fetchColumn() ?: 0;
$totalStok = $pdo->query("SELECT SUM(jumlah_stok) FROM stok")->fetchColumn() ?: 0;
$sql = "
  SELECT COALESCE(SUM(rg.total_gaji), 0)
  FROM riwayat_gaji rg
  INNER JOIN pekerja_lepas pl ON pl.id_pekerja = rg.id_pekerja
  WHERE rg.keterangan = 'Belum Dibayar'
";
$totalGaji = (int) $pdo->query($sql)->fetchColumn();

// Query jadwal tanpa join ke admin
$jadwalList = $pdo->query("
  SELECT * FROM jadwal 
  ORDER BY tanggal DESC, waktu_mulai DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
  :root {
    --primary-color: #2e49b0;
    --success-color: #16a34a;
    --error-color: #dc2626;
  }
  .notif { display: flex; align-items: center; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem; color: white; font-weight: 500; }
  .notif-sukses { background-color: var(--success-color);}
  .notif-error { background-color: var(--error-color);}
  .notif-close-btn { margin-left: auto; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; opacity: 0.8;}
  .notif-close-btn:hover { opacity: 1;}
</style>

<section class="flex-1 p-6 lg:p-8 bg-gray-50">
  <div class="max-w-7xl mx-auto space-y-6">
    <?php if (isset($_SESSION['notif'])): ?>
      <div id="notification" class="notif <?php echo $_SESSION['notif']['tipe'] === 'sukses' ? 'notif-sukses' : 'notif-error'; ?>">
        <i class="fas <?php echo $_SESSION['notif']['tipe'] === 'sukses' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-3 text-xl"></i>
        <span><?php echo htmlspecialchars($_SESSION['notif']['pesan']); ?></span>
        <button class="notif-close-btn" onclick="document.getElementById('notification').style.display='none'">&times;</button>
      </div>
      <?php unset($_SESSION['notif']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
      <div class="lg:col-span-2 bg-white shadow-md rounded-lg p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold text-gray-800 select-none">Jadwal Harian</h2>
          <div class="flex space-x-2">
            <button id="openFormBtn" aria-label="Tambah jadwal baru" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-md flex items-center transition-colors duration-300">
              <i class="fas fa-plus mr-2"></i> Tambah
            </button>
            <button id="lihatSemuaJadwalBtn" class="bg-gray-200 hover:bg-gray-300 text-orange-900 font-semibold px-4 py-2 rounded-md border border-gray-300">Lihat Semua</button>
          </div>
        </div>
        
        <!-- Jadwal Table - Admin column removed -->
        <div class="overflow-x-auto max-h-[340px] overflow-y-auto rounded-md border border-gray-200">
          <table class="w-full text-sm text-left text-gray-700">
            <thead class="text-xs text-gray-700 uppercase bg-gray-100 sticky top-0 z-10">
              <tr>
                <th class="py-3 px-4">Tanggal</th>
                <th class="py-3 px-4">Waktu</th>
                <th class="py-3 px-4">Jenis Kegiatan</th>
                <th class="py-3 px-4 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($jadwalList)): ?>
                <tr class="bg-white border-b">
                  <td colspan="4" class="py-6 px-4 text-center text-gray-500">
                    <i class="fas fa-calendar-times fa-2x mb-2 text-gray-400"></i>
                    <p>Belum ada jadwal.</p>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($jadwalList as $jadwal): ?>
                  <tr class="bg-white border-b hover:bg-gray-50 transition-colors duration-200">
                    <td class="py-4 px-4 font-medium text-gray-900"><?php echo htmlspecialchars(date('d M Y', strtotime($jadwal['tanggal']))); ?></td>
                    <td class="py-4 px-4"><?php echo htmlspecialchars(date('H:i', strtotime($jadwal['waktu_mulai']))) . ' - ' . htmlspecialchars(date('H:i', strtotime($jadwal['waktu_selesai']))); ?></td>
                    <td class="py-4 px-4"><?php echo htmlspecialchars($jadwal['jenis_kegiatan']); ?></td>
                    <td class="py-4 px-4 text-center w-28">
                      <button class="text-blue-600 hover:text-blue-800 p-2 editBtn"
                        data-id="<?php echo $jadwal['id_jadwal']; ?>"
                        data-tanggal="<?php echo $jadwal['tanggal']; ?>"
                        data-mulai="<?php echo $jadwal['waktu_mulai']; ?>"
                        data-selesai="<?php echo $jadwal['waktu_selesai']; ?>"
                        data-kegiatan="<?php echo htmlspecialchars($jadwal['jenis_kegiatan']); ?>"
                        aria-label="Edit jadwal">
                        <i class="fas fa-pencil-alt text-orange-600"></i>

                      </button>
                      <button class="text-red-500 hover:text-red-700 p-2 deleteBtn"
                        data-id="<?php echo $jadwal['id_jadwal']; ?>"
                        aria-label="Delete jadwal">
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Stats Cards (unchanged) -->
      <div class="lg:col-span-1 space-y-6">
        <div onclick="window.location.href='Index.php?page=distribusi';" class="bg-white shadow-md rounded-lg p-5 flex items-center cursor-pointer hover:shadow-lg transition-shadow duration-300">
          <div class="bg-blue-100 text-blue-600 rounded-full p-4 mr-4">
            <i class="fas fa-box-open fa-2x"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-500 uppercase">Pesanan</h3>
            <p class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($totalPesanan); ?> <span class="text-lg font-normal">Kg</span></p>
          </div>
        </div>
        <div onclick="window.location.href='Index.php?page=produksi';" class="bg-white shadow-md rounded-lg p-5 flex items-center cursor-pointer hover:shadow-lg transition-shadow duration-300">
          <div class="bg-green-100 text-green-600 rounded-full p-4 mr-4">
            <i class="fas fa-industry fa-2x"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-500 uppercase">Produksi</h3>
            <p class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($totalProduksi); ?> <span class="text-lg font-normal">Kg</span></p>
          </div>
        </div>
        <div onclick="window.location.href='Index.php?page=stok';" class="bg-white shadow-md rounded-lg p-5 flex items-center cursor-pointer hover:shadow-lg transition-shadow duration-300">
          <div class="bg-yellow-100 text-yellow-600 rounded-full p-4 mr-4">
            <i class="fas fa-warehouse fa-2x"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-500 uppercase">Stok Harian</h3>
            <p class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($totalStok); ?> <span class="text-lg font-normal">Kg</span></p>
          </div>
        </div>
        <div onclick="window.location.href='Index.php?page=pekerja';" class="bg-white shadow-md rounded-lg p-5 flex items-center cursor-pointer hover:shadow-lg transition-shadow duration-300">
          <div class="bg-purple-100 text-purple-600 rounded-full p-4 mr-4">
            <i class="fas fa-money-bill-wave fa-2x"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-500 uppercase">Gaji Belum Dibayar</h3>
            <p class="text-2xl font-bold text-gray-800">Rp <?php echo number_format($totalGaji, 0, ',', '.'); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- MODAL JADWAL LENGKAP - Admin column removed -->
<div id="modalJadwalLengkap" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden">
  <div class="bg-white max-w-5xl w-full rounded-lg shadow-lg p-6 overflow-y-auto max-h-[90vh] relative">
    <h3 class="text-xl font-bold mb-4 text-blue-800">Jadwal Lengkap</h3>
    <button id="closeModalJadwalLengkap" class="absolute top-4 right-6 text-gray-500 hover:text-red-500 text-2xl font-bold">&times;</button>
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left text-gray-700">
        <thead class="text-xs text-gray-700 uppercase bg-gray-100 sticky top-0 z-10">
          <tr>
            <th class="py-3 px-4">Tanggal</th>
            <th class="py-3 px-4">Waktu</th>
            <th class="py-3 px-4">Jenis Kegiatan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jadwalList as $jadwal): ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="py-3 px-4"><?php echo htmlspecialchars(date('d M Y', strtotime($jadwal['tanggal']))); ?></td>
              <td class="py-3 px-4"><?php echo htmlspecialchars(date('H:i', strtotime($jadwal['waktu_mulai']))) . ' - ' . htmlspecialchars(date('H:i', strtotime($jadwal['waktu_selesai']))); ?></td>
              <td class="py-3 px-4"><?php echo htmlspecialchars($jadwal['jenis_kegiatan']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL JADWAL CRUD (unchanged) -->
<div id="modalOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="bg-white shadow-xl rounded-lg p-6 w-full max-w-md relative">
    <form id="jadwalForm" action="Index.php?page=dashboard" method="POST" aria-modal="true" role="dialog" aria-labelledby="formTitle">
      <h2 id="formTitle" class="text-xl font-bold text-gray-800 mb-6">Input Jadwal</h2>
      <input type="hidden" name="id_jadwal" id="id_jadwal">
      <div class="space-y-4">
        <div>
          <label for="tanggal" class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
          <input type="date" id="tanggal" name="tanggal" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Waktu</label>
          <div class="flex space-x-2 items-center">
            <input type="time" id="jam_mulai" name="jam_mulai" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Jam mulai" />
            <span class="text-gray-500">-</span>
            <input type="time" id="jam_selesai" name="jam_selesai" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Jam selesai" />
          </div>
        </div>
        <div>
          <label for="jenis_kegiatan" class="block text-sm font-medium text-gray-700 mb-1">Jenis Kegiatan</label>
          <select id="jenis_kegiatan" name="jenis_kegiatan" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="" disabled selected>Pilih Jenis Kegiatan</option>
            <option value="Produksi">Produksi</option>
            <option value="Pengemasan">Pengemasan</option>
            <option value="Distribusi">Distribusi</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end space-x-3 mt-8">
        <button type="button" id="cancelBtn" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors">Batal</button>
        <button type="submit" name="submit_jadwal" class="bg-orange-600 text-white px-4 py-2 rounded-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL DELETE (unchanged) -->
<div id="deleteDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm">
    <div class="flex items-start">
      <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
        <i class="fas fa-exclamation-triangle text-red-600"></i>
      </div>
      <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Konfirmasi Hapus</h3>
        <div class="mt-2">
          <p class="text-sm text-gray-500">Apakah Anda yakin ingin menghapus jadwal ini? Tindakan ini tidak dapat dibatalkan.</p>
        </div>
      </div>
    </div>
    <form action="Index.php?page=dashboard" method="POST" class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
      <input type="hidden" name="id_jadwal_delete" id="id_jadwal_delete">
      <button type="submit" name="delete_jadwal" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
        Ya, Hapus
      </button>
      <button type="button" id="cancelDeleteBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
        Batal
      </button>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    // CRUD Jadwal
    const modalOverlay = document.getElementById('modalOverlay');
    const openFormBtn = document.getElementById('openFormBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const form = document.getElementById('jadwalForm');
    const formTitle = document.getElementById('formTitle');
    const hiddenIdInput = document.getElementById('id_jadwal');
    const deleteDialog = document.getElementById('deleteDialog');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const hiddenIdDeleteInput = document.getElementById('id_jadwal_delete');
    const openModal = () => { modalOverlay.classList.remove('hidden'); };
    const closeModal = () => { modalOverlay.classList.add('hidden'); form.reset(); hiddenIdInput.value = ''; };
    const openDeleteDialog = () => { deleteDialog.classList.remove('hidden'); };
    const closeDeleteDialog = () => { deleteDialog.classList.add('hidden'); };
    openFormBtn.addEventListener('click', () => {
      form.reset(); hiddenIdInput.value = ''; formTitle.textContent = 'Input Jadwal Baru'; openModal(); document.getElementById('tanggal').focus();
    });
    document.querySelectorAll('.editBtn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const dataset = e.currentTarget.dataset;
        formTitle.textContent = 'Edit Jadwal';
        hiddenIdInput.value = dataset.id;
        form.tanggal.value = dataset.tanggal;
        form.jam_mulai.value = dataset.mulai;
        form.jam_selesai.value = dataset.selesai;
        form.jenis_kegiatan.value = dataset.kegiatan;
        openModal();
      });
    });
    document.querySelectorAll('.deleteBtn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const id = e.currentTarget.dataset.id;
        hiddenIdDeleteInput.value = id;
        openDeleteDialog();
      });
    });
    cancelBtn.addEventListener('click', closeModal);
    cancelDeleteBtn.addEventListener('click', closeDeleteDialog);
    modalOverlay.addEventListener('click', (e) => { if (e.target === modalOverlay) closeModal(); });
    deleteDialog.addEventListener('click', (e) => { if (e.target === deleteDialog) closeDeleteDialog(); });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (!modalOverlay.classList.contains('hidden')) closeModal();
        if (!deleteDialog.classList.contains('hidden')) closeDeleteDialog();
      }
    });
    const notification = document.getElementById('notification');
    if (notification) {
      setTimeout(() => {
        notification.style.transition = 'opacity 0.5s';
        notification.style.opacity = '0';
        setTimeout(() => notification.style.display = 'none', 500);
      }, 5000);
    }

    // === Lihat Semua Jadwal ===
    const lihatSemuaBtn = document.getElementById('lihatSemuaJadwalBtn');
    const modalLengkap = document.getElementById('modalJadwalLengkap');
    const closeModalLengkap = document.getElementById('closeModalJadwalLengkap');
    if (lihatSemuaBtn && modalLengkap && closeModalLengkap) {
      lihatSemuaBtn.addEventListener('click', () => modalLengkap.classList.remove('hidden'));
      closeModalLengkap.addEventListener('click', () => modalLengkap.classList.add('hidden'));
      modalLengkap.addEventListener('click', (e) => { if (e.target === modalLengkap) modalLengkap.classList.add('hidden'); });
    }
  });
</script>