<?php 
include 'header.php'; 
include 'koneksi.php';

$id_mapel = isset($_GET['id_mapel']) ? (int)$_GET['id_mapel'] : 0;
// (Kode untuk mengambil nama mapel bisa ditambahkan di sini)
?>

<div class="container mt-4">
    <h1 class="mb-4">Tambah TP (Mode Admin)</h1>
    <div class="card shadow-sm">
        <div class="card-body">
            <form action="tp_aksi.php?aksi=tambah_admin" method="POST">
                <input type="hidden" name="id_mapel" value="<?php echo $id_mapel; ?>">
                
                <div class="mb-3">
                    <label for="id_guru_pembuat" class="form-label">Pilih Guru Pembuat TP</label>
                    <select class="form-select" id="id_guru_pembuat" name="id_guru_pembuat" required>
                        <option value="">-- Pilih Guru --</option>
                        <?php
                        $query_guru = mysqli_query($koneksi, "SELECT id_guru, nama_guru FROM guru WHERE role = 'guru_mapel' ORDER BY nama_guru");
                        while ($guru = mysqli_fetch_assoc($query_guru)) {
                            echo "<option value='{$guru['id_guru']}'>" . htmlspecialchars($guru['nama_guru']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="deskripsi_tp" class="form-label">Deskripsi Tujuan Pembelajaran</label>
                    <textarea class="form-control" id="deskripsi_tp" name="deskripsi_tp" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-floppy-fill"></i> Simpan TP</button>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>