</main>
<?php
// --- AMBIL NAMA SEKOLAH DARI DATABASE ---
$nama_sekolah_footer = 'Aplikasi Rapor Digital Anda'; // Default name
if (isset($koneksi)) { // Check if $koneksi is available
    $query_sekolah_footer = mysqli_query($koneksi, "SELECT nama_sekolah FROM sekolah WHERE id_sekolah = 1 LIMIT 1");
    if ($query_sekolah_footer && mysqli_num_rows($query_sekolah_footer) > 0) {
        $data_sekolah_footer = mysqli_fetch_assoc($query_sekolah_footer);
        $nama_sekolah_footer = $data_sekolah_footer['nama_sekolah'];
    }
}
?>
    <!-- FOOTER MODERN -->
    <style>
        .app-footer {
            background: #ffffff;
            border-top: 1px solid rgba(0,0,0,0.06);
            padding: 1.5rem 2.5rem;
            margin-top: auto; /* Push footer to bottom */
            position: relative;
            z-index: 10;
        }

        .footer-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .copyright-section {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #64748b; /* Slate-500 */
            font-weight: 500;
        }

        .school-name {
            color: #334155; /* Slate-700 */
            font-weight: 600;
        }

        .version-badge {
            background: rgba(38, 166, 154, 0.1); /* Teal transparent */
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-left: 12px;
            border: 1px solid rgba(38, 166, 154, 0.2);
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
        }
        
        .version-badge::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            background-color: var(--primary-color);
            border-radius: 50%;
            margin-right: 6px;
        }

        .footer-links {
            display: flex;
            gap: 2rem;
        }

        .footer-link-item {
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            position: relative;
        }

        .footer-link-item i {
            margin-right: 8px;
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .footer-link-item:hover {
            color: var(--primary-color);
        }

        .footer-link-item:hover i {
            transform: translateY(-2px);
        }
        
        /* Garis bawah animasi saat hover */
        .footer-link-item::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        
        .footer-link-item:hover::after {
            width: 100%;
        }

        @media (max-width: 768px) {
            .app-footer { padding: 1.5rem; }
            .footer-container { flex-direction: column; text-align: center; gap: 1.5rem; }
            .copyright-section { flex-direction: column; gap: 0.5rem; }
            .version-badge { margin-left: 0; margin-top: 5px; }
            .footer-links { gap: 1.5rem; }
        }
    </style>

    <footer class="app-footer">
        <div class="footer-container">
            <!-- Bagian Kiri: Copyright & Info Sekolah -->
            <div class="copyright-section">
                <span>&copy; <?php echo date("Y"); ?> Rapor Digital</span>
                <span class="d-none d-md-inline mx-2 text-muted">•</span>
                <span class="school-name"><?php echo htmlspecialchars($nama_sekolah_footer); ?></span>
                
                <!-- Badge Versi -->
                <?php if(isset($APP_VERSION)): ?>
                    <span class="version-badge" title="Versi Aplikasi"><?php echo htmlspecialchars($APP_VERSION); ?></span>
                <?php endif; ?>
            </div>

            <!-- Bagian Kanan: Link Eksternal -->
            <div class="footer-links">
                <a href="https://multischool.sch.id/index.php" class="footer-link-item" target="_blank">
                    <i class="bi bi-globe2"></i> Web Sekolah
                </a>
                <a href="https://multischool.sch.id" class="footer-link-item" target="_blank">
                    <i class="bi bi-cloud-check-fill"></i> Portal Digital
                </a>
            </div>
        </div>
    </footer>

    </div> <!-- End Content Div -->
</div> <!-- End Wrapper Div -->

<!-- SCRIPT PENDUKUNG -->
<script>
$(document).ready(function () {
    // --- Toggle Sidebar (Mobile & Desktop) ---
    $('#sidebarCollapse, #sidebarCollapseDesktop').on('click', function () {
        $('#sidebar').toggleClass('active');
        
        // Animasi icon saat toggle (Optional UX enhancement)
        const icon = $(this).find('i');
        if($('#sidebar').hasClass('active')) {
             // Jika sidebar tertutup (active class means hidden in this CSS logic usually)
        }
    });

    // Inisialisasi Tooltip Bootstrap (Agar hover info muncul)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php
// --- LOGIKA NOTIFIKASI SWEETALERT (TETAP SAMA) ---
if (isset($_SESSION['pesan'])) {
    echo "<script>
        if (typeof Swal !== 'undefined') {
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    let config = " . $_SESSION['pesan'] . ";
                    // Pastikan timer ada agar tidak menggantung selamanya jika user diam
                    if(!config.timer) config.timer = 3000; 
                    if(!config.timerProgressBar) config.timerProgressBar = true;
                    
                    Swal.fire(config);
                } catch (e) {
                    console.error('Error parsing SweetAlert config:', e);
                }
            });
        }
    </script>";
    unset($_SESSION['pesan']);
}
if (isset($_SESSION['pesan_error'])) {
     echo "<script>
        if (typeof Swal !== 'undefined') {
             document.addEventListener('DOMContentLoaded', function() {
                 try {
                     let config = " . $_SESSION['pesan_error'] . ";
                     Swal.fire(config);
                 } catch (e) {
                     console.error('Error parsing SweetAlert error config:', e);
                     Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan sistem.' });
                 }
             });
         }
     </script>";
    unset($_SESSION['pesan_error']);
}
?>

</body>
</html>