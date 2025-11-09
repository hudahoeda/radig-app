<?php
include 'header.php';
include 'koneksi.php';

// Validasi role admin
if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Halaman ini khusus untuk Admin.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    
    #sortable-mapel .list-group-item {
        padding: 1rem 1.25rem;
        font-size: 1.05rem;
        font-weight: 500;
        border: 1px solid var(--border-color);
        margin-bottom: -1px; /* Menyatukan border */
    }
    #sortable-mapel .list-group-item:first-child {
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }
    #sortable-mapel .list-group-item:last-child {
        border-bottom-left-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;
    }
    
    /* Ikon drag */
    .drag-handle {
        cursor: grab;
        color: var(--text-muted);
        font-size: 1.5rem;
        transition: color 0.2s;
    }
    .drag-handle:hover {
        color: var(--primary-color);
    }

    /* Efek saat item di-drag */
    .sortable-ghost {
        opacity: 0.5;
        background: #e0f2f1; /* Light Teal */
    }
    .sortable-chosen {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        background-color: #fff;
    }

    .item-number {
        font-weight: 700;
        color: var(--secondary-color);
        width: 30px;
        text-align: center;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Susun Urutan Mata Pelajaran</h1>
        <p class="lead mb-0 opacity-75">Atur urutan penampilan mata pelajaran pada cetakan rapor.</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-list-ol me-2" style="color: var(--primary-color);"></i>Daftar Mata Pelajaran</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group" id="sortable-mapel">
                        <?php
                        // Ambil semua mapel, diurutkan berdasarkan urutan yang sudah ada
                        $query_mapel = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel, urutan FROM mata_pelajaran ORDER BY urutan ASC, nama_mapel ASC");
                        $no = 1;
                        while ($mapel = mysqli_fetch_assoc($query_mapel)) {
                            echo '<li class="list-group-item d-flex align-items-center" data-id="' . $mapel['id_mapel'] . '">';
                            echo '<span class="item-number me-3">' . $no++ . '.</span>';
                            echo '<i class="bi bi-grip-vertical drag-handle me-3"></i>';
                            echo '<span>' . htmlspecialchars($mapel['nama_mapel']) . '</span>';
                            echo '</li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-info-circle-fill fs-2 me-3 text-primary"></i>
                        <div>
                            <h5 class="mb-0">Petunjuk Penggunaan</h5>
                        </div>
                    </div>
                    <p class="text-muted">
                        Seret dan lepas (drag and drop) item pada daftar di samping untuk mengubah urutannya.
                    </p>
                    <ol class="list-group list-group-numbered list-group-flush">
                        <li class="list-group-item">Klik dan tahan ikon <i class="bi bi-grip-vertical"></i> pada item yang ingin dipindahkan.</li>
                        <li class="list-group-item">Seret item ke posisi yang Anda inginkan.</li>
                        <li class="list-group-item">Lepaskan klik mouse. Perubahan akan tersimpan secara otomatis.</li>
                    </ol>
                    <div class="alert alert-success mt-3">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Urutan akan langsung tersimpan di database setiap kali Anda selesai memindahkan item.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('sortable-mapel');
    
    // Inisialisasi SortableJS
    var sortable = Sortable.create(el, {
        animation: 150, // Animasi saat diseret
        handle: '.drag-handle', // Elemen yang bisa disentuh untuk menyeret
        ghostClass: 'sortable-ghost', // Class untuk item bayangan
        chosenClass: 'sortable-chosen', // Class untuk item yang sedang di-drag
        onEnd: function (evt) {
            var urutanMapel = [];
            const items = el.querySelectorAll('li');
            
            // Update nomor urut di tampilan
            items.forEach(function(item, index) {
                urutanMapel.push(item.getAttribute('data-id'));
                item.querySelector('.item-number').textContent = (index + 1) + '.';
            });

            // Kirim urutan baru ke server menggunakan AJAX (Fetch API)
            fetch('mapel_aksi.php?aksi=simpan_urutan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'urutan=' + JSON.stringify(urutanMapel)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Tampilkan notifikasi sukses "toast"
                    const Toast = Swal.mixin({
                      toast: true,
                      position: 'top-end',
                      showConfirmButton: false,
                      timer: 2000,
                      timerProgressBar: true
                    });
                    Toast.fire({
                      icon: 'success',
                      title: 'Urutan berhasil disimpan!'
                    });
                } else {
                    Swal.fire('Gagal!', 'Terjadi kesalahan saat menyimpan urutan.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Gagal!', 'Terjadi masalah koneksi.', 'error');
            });
        }
    });
});
</script>

<?php include 'footer.php'; ?>