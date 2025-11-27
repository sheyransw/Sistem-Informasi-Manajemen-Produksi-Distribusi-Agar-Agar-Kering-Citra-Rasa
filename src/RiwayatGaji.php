<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'auth.php';
include 'koneksi.php';

// Update status pembayaran pekerja
function updateWorkerPaymentStatus($pdo, $id_pekerja)
{
    $sql_check = "SELECT COUNT(*) FROM riwayat_gaji WHERE id_pekerja = ? AND keterangan = 'Belum Dibayar'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id_pekerja]);
    $unpaid_count = $stmt_check->fetchColumn();
    $new_status = ($unpaid_count == 0) ? 'Dibayar' : 'Belum Dibayar';
    $sql_update = "UPDATE pekerja_lepas SET status_pembayaran = ? WHERE id_pekerja = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$new_status, $id_pekerja]);
}

if (!isset($_GET['id_pekerja']) || !is_numeric($_GET['id_pekerja'])) {
    header("Location: Index.php?page=pekerja");
    exit;
}
$id_pekerja = $_GET['id_pekerja'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'edit_gaji_riwayat':
                $sql = "UPDATE riwayat_gaji SET tanggal = ?, berat_barang_kg = ?, total_gaji = ?, keterangan = 'Dibayar' WHERE id_gaji = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['tanggal'],
                    $_POST['berat_barang_kg'],
                    $_POST['total_gaji'],
                    $_POST['id_gaji_edit']
                ]);
                updateWorkerPaymentStatus($pdo, $id_pekerja);
                $_SESSION['notif'] = ['pesan' => 'Gaji berhasil dibayar!', 'tipe' => 'sukses'];
                break;

            case 'hapus_gaji_riwayat':
                $sql = "DELETE FROM riwayat_gaji WHERE id_gaji = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_POST['id_gaji_hapus']]);
                updateWorkerPaymentStatus($pdo, $id_pekerja);
                $_SESSION['notif'] = ['pesan' => 'Satu entri riwayat gaji berhasil dihapus.', 'tipe' => 'sukses'];
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['notif'] = ['pesan' => 'Terjadi kesalahan database: ' . $e->getMessage(), 'tipe' => 'error'];
    }
    header("Location: Index.php?page=riwayat_gaji&id_pekerja=" . $id_pekerja);
    exit;
}

// Data pekerja
$stmt_pekerja = $pdo->prepare("SELECT nama_pekerja FROM pekerja_lepas WHERE id_pekerja = ?");
$stmt_pekerja->execute([$id_pekerja]);
$pekerja = $stmt_pekerja->fetch(PDO::FETCH_ASSOC);

if (!$pekerja) {
    $_SESSION['notif'] = ['pesan' => 'Pekerja tidak ditemukan.', 'tipe' => 'error'];
    header("Location: Index.php?page=pekerja");
    exit;
}

// Data riwayat gaji
$stmt_riwayat = $pdo->prepare("SELECT * FROM riwayat_gaji WHERE id_pekerja = ? ORDER BY tanggal DESC");
$stmt_riwayat->execute([$id_pekerja]);
$riwayat_list = $stmt_riwayat->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 bg-gray-100">
    <section class="p-6 overflow-x-auto">
        <?php if (isset($_SESSION['notif'])): ?>
            <div class="mb-4 p-4 rounded-md text-white font-bold <?php echo $_SESSION['notif']['tipe'] === 'sukses' ? 'bg-green-500' : 'bg-red-500'; ?>">
                <?php echo htmlspecialchars($_SESSION['notif']['pesan']); ?>
            </div>
        <?php unset($_SESSION['notif']); endif; ?>

        <div class="flex justify-between items-center mb-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Riwayat Gaji</h2>
                <p class="text-gray-600">Untuk: <?php echo htmlspecialchars($pekerja['nama_pekerja']); ?></p>
            </div>
            <a href="Index.php?page=pekerja" class="bg-gray-500 text-white text-sm font-normal px-4 py-2 rounded hover:bg-gray-600 transition-colors">&larr; Kembali ke Daftar Pekerja</a>
        </div>

        <table class="w-full border border-gray-300 text-sm bg-white text-left">
            <thead class="bg-gray-200">
                <tr>
                    <th class="p-2 border-b">Tanggal</th>
                    <th class="p-2 border-b">Berat (Kg)</th>
                    <th class="p-2 border-b">Total Gaji</th>
                    <th class="p-2 border-b">Keterangan</th>
                    <th class="p-2 border-b">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($riwayat_list)): ?>
                    <tr>
                        <td colspan="5" class="p-4 text-center text-gray-500">Tidak ada riwayat gaji untuk pekerja ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riwayat_list as $item): ?>
                        <tr class="border-b">
                            <td class="p-2"><?= htmlspecialchars(date('d M Y', strtotime($item['tanggal']))); ?></td>
                            <td class="p-2"><?= htmlspecialchars($item['berat_barang_kg']); ?> Kg</td>
                            <td class="p-2">Rp. <?= number_format($item['total_gaji'], 0, ',', '.'); ?></td>
                            <td class="p-2"><?= htmlspecialchars($item['keterangan']); ?></td>
                            <td class="p-2 space-x-1">
                                <button class="btnBayar bg-green-500 text-white text-xs px-2 py-1 rounded"
                                    data-id-gaji="<?= $item['id_gaji']?>" 
                                    data-tanggal="<?= $item['tanggal'] ?>"
                                    data-berat="<?= $item['berat_barang_kg'] ?>"
                                    data-total="<?= $item['total_gaji'] ?>"
                                    type="button">Bayar</button>
                                <button class="btnHapusGaji bg-red-500 text-white text-xs px-2 py-1 rounded"
                                    data-id-gaji="<?= $item['id_gaji']?>" type="button">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <!-- MODAL BAYAR -->
    <div id="modalBayarGaji" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 flex">
        <form action="Index.php?page=riwayat_gaji&id_pekerja=<?php echo $id_pekerja; ?>" method="POST" class="bg-white p-6 shadow-md rounded w-80 relative">
            <button type="button" class="btnClose absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-xl font-bold">&times;</button>
            <h2 class="text-black font-semibold text-lg mb-4">Bayar</h2>
            <input type="hidden" name="action" value="edit_gaji_riwayat">
            <input type="hidden" name="id_gaji_edit" id="id_gaji_edit">
            <input type="hidden" name="total_gaji" id="edit_total_gaji_hidden">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                <input type="date" name="tanggal" id="edit_tanggal" class="w-full px-3 py-2 border border-gray-300 rounded" required readonly />
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Berat (Kg)</label>
                <input type="number" step="0.1" name="berat_barang_kg" id="edit_berat_barang_kg" class="w-full px-3 py-2 border border-gray-300 rounded" required />
            </div>
            <div class="mb-6 text-sm">Total Gaji: <span class="font-mono font-bold" id="edit_total_gaji_display"></span></div>
            <button type="submit" class="w-full bg-green-600 text-white py-2 rounded">Bayar</button>
        </form>
    </div>

    <!-- MODAL HAPUS -->
    <div id="modalHapusGaji" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 flex">
        <div class="w-[320px] border p-6 bg-white rounded-md relative">
            <button type="button" class="btnClose absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-xl font-bold">&times;</button>
            <h2 class="font-semibold text-black mb-3 text-lg">Hapus Riwayat Gaji?</h2>
            <p class="text-gray-700 mb-5 text-sm">Anda yakin ingin menghapus entri riwayat ini?</p>
            <form action="Index.php?page=riwayat_gaji&id_pekerja=<?php echo $id_pekerja; ?>" method="POST" class="flex justify-end space-x-3">
                <input type="hidden" name="action" value="hapus_gaji_riwayat">
                <input type="hidden" name="id_gaji_hapus" id="id_gaji_hapus">
                <button type="button" class="btnCancelHapusGaji border border-gray-400 text-black text-sm font-medium rounded px-4 py-2">Batal</button>
                <button type="submit" class="bg-red-600 text-white text-sm font-medium rounded px-4 py-2">Ya, Hapus</button>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalBayarGaji = document.getElementById('modalBayarGaji');
    const modalHapusGaji = document.getElementById('modalHapusGaji');
    const openModal = (modal) => { if (modal) modal.classList.remove('hidden'); };
    const closeModal = (modal) => { if (modal) modal.classList.add('hidden'); };

    // Close modal buttons
    [modalBayarGaji, modalHapusGaji].forEach(modal => {
        if (!modal) return;
        modal.querySelector('.btnClose')?.addEventListener('click', () => closeModal(modal));
        modal.querySelector('.btnCancelHapusGaji')?.addEventListener('click', () => closeModal(modal));
        modal.addEventListener('click', e => { if (e.target === modal) closeModal(modal); });
    });

    // Modal Bayar
    document.querySelectorAll('.btnBayar').forEach(button => {
        button.addEventListener('click', e => {
            const data = e.target.closest('button').dataset;
            document.getElementById('id_gaji_edit').value = data.idGaji;
            document.getElementById('edit_tanggal').value = data.tanggal;
            document.getElementById('edit_berat_barang_kg').value = data.berat;
            document.getElementById('edit_total_gaji_hidden').value = data.total;
            document.getElementById('edit_total_gaji_display').textContent = 'Rp. ' + parseInt(data.total).toLocaleString('id-ID');

            const beratInput = document.getElementById('edit_berat_barang_kg');
            const totalHidden = document.getElementById('edit_total_gaji_hidden');
            const totalDisplay = document.getElementById('edit_total_gaji_display');
            const tarif = 2500;
            const updateTotal = () => {
                const berat = parseFloat(beratInput.value) || 0;
                const total = Math.round(berat * tarif);
                totalHidden.value = total;
                totalDisplay.textContent = 'Rp. ' + total.toLocaleString('id-ID');
            };
            beratInput.addEventListener('input', updateTotal);
            updateTotal();

            openModal(modalBayarGaji);
        });
    });

    // Modal Hapus
    document.querySelectorAll('.btnHapusGaji').forEach(button => {
        button.addEventListener('click', e => {
            const data = e.target.closest('button').dataset;
            document.getElementById('id_gaji_hapus').value = data.idGaji;
            openModal(modalHapusGaji);
        });
    });
});
</script>