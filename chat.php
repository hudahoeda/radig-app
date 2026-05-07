<?php
include 'header.php';
// Koneksi tidak di-include di sini karena semua data diambil via AJAX
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* --- MODERN VARIABLES --- */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-bg: #f3f4f6;
    --glass-bg: rgba(255, 255, 255, 0.95);
    --border-light: rgba(0, 0, 0, 0.05);
    --shadow-soft: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --text-dark: #1f2937;
    --text-muted: #9ca3af;
    --radius-xl: 24px;
    --radius-lg: 16px;
    --radius-md: 12px;
}

body {
    font-family: 'Poppins', sans-serif;
    background-color: #f9fafb; /* Latar belakang halaman lebih cerah */
}

/* --- MAIN CONTAINER --- */
.chat-app-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 15px;
    height: calc(100vh - 100px); /* Full height minus header/footer estimation */
    min-height: 600px;
}

.chat-card {
    background: #fff;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-soft);
    display: flex;
    overflow: hidden;
    height: 100%;
    position: relative;
    border: 1px solid white;
}

/* --- SIDEBAR (CONTACTS) --- */
.chat-sidebar {
    width: 350px;
    background-color: #fff;
    border-right: 1px solid var(--border-light);
    display: flex;
    flex-direction: column;
    z-index: 2;
    transition: transform 0.3s ease;
}

.sidebar-header {
    padding: 1.5rem;
    background: #fff;
}

.search-box {
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 12px 20px 12px 45px;
    border-radius: 50px;
    border: 1px solid #e5e7eb;
    background-color: #f9fafb;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.search-box input:focus {
    background-color: #fff;
    box-shadow: 0 0 0 4px rgba(118, 75, 162, 0.1);
    border-color: #764ba2;
    outline: none;
}

.search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.contact-list {
    flex: 1;
    overflow-y: auto;
    padding: 0 10px 10px 10px;
}

/* Scrollbar styling */
.contact-list::-webkit-scrollbar { width: 5px; }
.contact-list::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }

.contact-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    margin-bottom: 5px;
    border-radius: var(--radius-lg);
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.contact-item:hover {
    background-color: #f3f4f6;
}

.contact-item.active {
    background-color: #f0f5ff;
    border-color: #dbeafe;
}

.contact-avatar {
    position: relative;
}

.contact-avatar img {
    width: 50px;
    height: 50px;
    border-radius: 18px; /* Squircle shape */
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.contact-info {
    flex: 1;
    margin-left: 15px;
    overflow: hidden;
}

.contact-name {
    font-weight: 600;
    color: var(--text-dark);
    font-size: 0.95rem;
    margin-bottom: 2px;
}

.contact-role {
    font-size: 0.75rem;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

.contact-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    min-width: 60px;
}

.last-time {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-bottom: 5px;
}

.unread-badge {
    background: var(--primary-gradient);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(118, 75, 162, 0.3);
}

/* --- CHAT AREA --- */
.chat-window {
    flex: 1;
    display: flex;
    flex-direction: column;
    background-color: #fff;
    background-image: 
        radial-gradient(#e5e7eb 1px, transparent 1px), 
        radial-gradient(#e5e7eb 1px, transparent 1px);
    background-position: 0 0, 20px 20px;
    background-size: 40px 40px;
    position: relative;
}

/* Header */
.chat-header {
    padding: 1rem 1.5rem;
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
}

.back-btn {
    display: none; /* Hidden on desktop */
    margin-right: 15px;
    background: none;
    border: none;
    font-size: 1.2rem;
    color: var(--text-dark);
    padding: 5px;
}

.chat-header-info {
    display: flex;
    align-items: center;
}

.chat-header-info img {
    width: 45px;
    height: 45px;
    border-radius: 14px;
    margin-right: 15px;
}

/* Chat Body */
.chat-body {
    flex: 1;
    padding: 2rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.chat-body::-webkit-scrollbar { width: 5px; }
.chat-body::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }

/* Messages */
.message-group {
    display: flex;
    max-width: 75%;
}

.message-group.sent {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.message-group.received {
    align-self: flex-start;
}

.message-bubble {
    padding: 12px 18px;
    position: relative;
    font-size: 0.95rem;
    line-height: 1.5;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.sent .message-bubble {
    background: var(--primary-gradient);
    color: white;
    border-radius: 18px 18px 0 18px;
}

.received .message-bubble {
    background: white;
    color: var(--text-dark);
    border-radius: 18px 18px 18px 0;
    border: 1px solid #f3f4f6;
}

.message-time {
    font-size: 0.65rem;
    margin-top: 5px;
    opacity: 0.7;
    text-align: right;
}

.received .message-time { text-align: left; margin-left: 5px; color: #9ca3af; }
.sent .message-time { margin-right: 5px; color: #fff; }

/* Input Area */
.chat-input-wrapper {
    padding: 1.5rem;
    background: #fff;
    border-top: 1px solid var(--border-light);
}

.input-group-modern {
    background: #f3f4f6;
    border-radius: 50px;
    padding: 5px 8px 5px 20px;
    display: flex;
    align-items: center;
    transition: all 0.3s;
    border: 1px solid transparent;
}

.input-group-modern:focus-within {
    background: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border-color: #e5e7eb;
}

.input-group-modern input {
    border: none;
    background: transparent;
    flex: 1;
    padding: 10px 0;
    outline: none;
    color: var(--text-dark);
}

.btn-send-modern {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: none;
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s;
    margin-left: 10px;
    box-shadow: 0 4px 10px rgba(118, 75, 162, 0.3);
}

.btn-send-modern:hover {
    transform: scale(1.05);
}

.btn-send-modern:active {
    transform: scale(0.95);
}

/* Placeholder State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #9ca3af;
    text-align: center;
}
.empty-state i {
    font-size: 5rem;
    margin-bottom: 1rem;
    background: -webkit-linear-gradient(#667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    opacity: 0.5;
}

/* --- RESPONSIVE / MOBILE STYLES --- */
@media (max-width: 768px) {
    .chat-app-wrapper {
        padding: 0;
        height: calc(100vh - 70px); /* Adjust for mobile browser bars */
        margin-bottom: 0;
    }
    
    .chat-card {
        border-radius: 0;
        border: none;
    }

    .chat-sidebar {
        width: 100%;
        position: absolute;
        height: 100%;
        left: 0;
        top: 0;
    }

    .chat-window {
        position: absolute;
        width: 100%;
        height: 100%;
        left: 100%; /* Sembunyikan ke kanan secara default */
        top: 0;
        transition: left 0.3s ease-in-out;
        z-index: 5;
    }

    /* Kelas aktif untuk menampilkan chat di mobile */
    .chat-window.show-mobile {
        left: 0;
    }

    .back-btn {
        display: block; /* Tampilkan tombol back */
    }

    .message-group {
        max-width: 85%;
    }
    
    .sidebar-header h4 {
        font-size: 1.2rem;
    }
}
</style>

<div class="container-fluid p-0 p-lg-4">
    <div class="chat-app-wrapper">
        <div class="chat-card">
            
            <div class="chat-sidebar" id="sidebar-area">
                <div class="sidebar-header">
                    <h4 class="mb-3 fw-bold text-dark">Pesan</h4>
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="contact-search" placeholder="Cari Guru atau Siswa...">
                    </div>
                </div>
                <div class="contact-list" id="contacts-container">
                    <div class="text-center mt-5 text-muted">
                        <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                        <p class="small mt-2">Memuat kontak...</p>
                    </div>
                </div>
            </div>

            <div class="chat-window" id="chat-window-area">
                
                <div class="empty-state" id="chat-placeholder">
                    <i class="bi bi-chat-square-dots-fill"></i>
                    <h3 class="h5 fw-bold text-dark">Ruang Konsultasi</h3>
                    <p class="small">Pilih kontak untuk memulai percakapan</p>
                </div>

                <div id="chat-active-container" class="d-none h-100 flex-column">
                    <div class="chat-header">
                        <button class="back-btn" id="btn-back-mobile">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <div id="chat-header-content" class="chat-header-info">
                            </div>
                    </div>

                    <div class="chat-body" id="chat-body-content">
                        </div>

                    <div class="chat-input-wrapper">
                        <form id="message-form" class="input-group-modern">
                            <input type="text" id="message-input" placeholder="Tulis pesan..." autocomplete="off" required>
                            <button type="submit" class="btn-send-modern">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let activeContact = null;
    let pollingInterval = null;
    const currentUserId = '<?php echo $_SESSION['role'] == 'guru' ? $_SESSION['id_guru'] : $_SESSION['id_siswa']; ?>';
    const currentUserRole = '<?php echo $_SESSION['role']; ?>';

    // Helper: Format Waktu
    function formatMessageTime(dateTime) {
        return new Date(dateTime.replace(' ', 'T') + 'Z').toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    // Helper: UI Mobile Toggle
    function openChatMobile() {
        if ($(window).width() <= 768) {
            $('#chat-window-area').addClass('show-mobile');
        }
    }

    function closeChatMobile() {
        $('#chat-window-area').removeClass('show-mobile');
        // Reset active state di sidebar saat kembali
        $('.contact-item').removeClass('active');
        activeContact = null;
    }

    // Event Listener Tombol Back (Mobile)
    $('#btn-back-mobile').on('click', function() {
        closeChatMobile();
    });

    // 1. Load Contacts
    function loadContacts() {
        $.ajax({
            url: 'ajax_chat.php', type: 'GET', data: { action: 'get_contacts' }, dataType: 'json',
            success: function(response) {
                if (response.status !== 'success') return;
                const container = $('#contacts-container');
                container.empty();
                
                if (response.contacts.length === 0) {
                    container.html('<div class="p-4 text-center text-muted small">Belum ada kontak tersedia.</div>');
                    return;
                }

                response.contacts.forEach(contact => {
                    // Cek apakah item ini yang sedang aktif
                    const isActive = activeContact && activeContact.id == contact.contact_id && activeContact.role == contact.contact_role ? 'active' : '';
                    const unreadHtml = contact.unread_count > 0 ? `<div class="unread-badge">${contact.unread_count}</div>` : '';
                    
                    const html = `
                        <div class="contact-item ${isActive}" 
                             data-id="${contact.contact_id}" 
                             data-role="${contact.contact_role}" 
                             data-name="${contact.contact_name}" 
                             data-photo="${contact.contact_photo}">
                            <div class="contact-avatar">
                                <img src="${contact.contact_photo}" alt="User">
                            </div>
                            <div class="contact-info">
                                <div class="contact-name">${contact.contact_name}</div>
                                <div class="contact-role">${contact.contact_role}</div>
                            </div>
                            <div class="contact-meta">
                                ${unreadHtml}
                            </div>
                        </div>`;
                    container.append(html);
                });
            }
        });
    }

    // 2. Load Messages
    function loadMessages(contactId, contactRole, contactName, contactPhoto) {
        // UI Toggling
        $('#chat-placeholder').addClass('d-none');
        $('#chat-active-container').removeClass('d-none').addClass('d-flex');
        
        // Panggil helper UI Mobile
        openChatMobile();

        const chatBody = $('#chat-body-content');
        // Jangan hapus isi jika polling update, hanya jika ganti kontak (logic sederhana: check if empty or spinner needed)
        // Disini kita pakai spinner hanya jika chat body kosong
        if(chatBody.is(':empty')) {
            chatBody.html('<div class="m-auto text-center text-muted"><div class="spinner-border spinner-border-sm mb-2"></div><br>Memuat percakapan...</div>');
        }

        // Set Header
        const roleLabel = contactRole === 'guru' ? 'Guru Pembimbing' : 'Siswa';
        $('#chat-header-content').html(`
            <img src="${contactPhoto}" alt="${contactName}">
            <div>
                <div class="fw-bold text-dark">${contactName}</div>
                <div class="small text-muted">${roleLabel}</div>
            </div>
        `);

        $.ajax({
            url: 'ajax_chat.php', type: 'POST', data: { action: 'get_messages', contact_id: contactId, contact_role: contactRole }, dataType: 'json',
            success: function(response) {
                if (response.status !== 'success') return;
                
                chatBody.empty(); // Clear spinner/old messages
                
                if(response.messages.length === 0) {
                    chatBody.html('<div class="m-auto text-center small text-muted">Belum ada percakapan.<br>Sapa mereka sekarang!</div>');
                }

                response.messages.forEach(msg => {
                    const isMe = (msg.id_pengirim == currentUserId && msg.role_pengirim == currentUserRole);
                    const typeClass = isMe ? 'sent' : 'received';
                    
                    const msgHtml = `
                        <div class="message-group ${typeClass}">
                            <div class="message-bubble">
                                ${msg.isi_pesan.replace(/\n/g, '<br>')}
                                <div class="message-time">${formatMessageTime(msg.waktu_kirim)}</div>
                            </div>
                        </div>`;
                    chatBody.append(msgHtml);
                });
                
                // Auto scroll to bottom
                chatBody.scrollTop(chatBody[0].scrollHeight);
            }
        });
    }

    // 3. Click Contact
    $('#contacts-container').on('click', '.contact-item', function() {
        const item = $(this);
        
        // Set Active Data
        activeContact = {
            id: item.data('id'),
            role: item.data('role'),
            name: item.data('name'),
            photo: item.data('photo')
        };

        // UI Update (Desktop Sidebar)
        $('.contact-item').removeClass('active');
        item.addClass('active');

        // Remove badge visually immediately
        item.find('.unread-badge').remove();

        // Load Chat
        loadMessages(activeContact.id, activeContact.role, activeContact.name, activeContact.photo);
    });

    // 4. Send Message
    $('#message-form').on('submit', function(e) {
        e.preventDefault();
        if (!activeContact) return;
        
        const input = $('#message-input');
        const text = input.val().trim();
        if (text === '') return;

        $.ajax({
            url: 'ajax_chat.php', type: 'POST', 
            data: { action: 'send_message', receiver_id: activeContact.id, receiver_role: activeContact.role, message: text }, 
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    input.val('');
                    // Append manual agar instan
                    const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                    $('#chat-body-content').append(`
                        <div class="message-group sent">
                            <div class="message-bubble">
                                ${text.replace(/\n/g, '<br>')}
                                <div class="message-time">${time}</div>
                            </div>
                        </div>
                    `);
                    $('#chat-body-content').scrollTop($('#chat-body-content')[0].scrollHeight);
                } else {
                    alert('Gagal mengirim: ' + response.message);
                }
            }
        });
    });

    // 5. Search
    $('#contact-search').on('keyup', function() {
        const val = $(this).val().toLowerCase();
        $('.contact-item').each(function() {
            const name = $(this).data('name').toLowerCase();
            $(this).toggle(name.includes(val));
        });
    });

    // 6. Polling
    function startPolling() {
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(function() {
            // Refresh list kontak (untuk unread badge)
            // Note: Pada aplikasi real-time yang berat, sebaiknya pisah endpoint "check_new" vs "get_all"
            // Tapi sesuai instruksi tidak mengubah logika backend, kita pakai yang ada.
            
            // Simpan scroll position sidebar jika perlu, tapi disini kita replace HTML, 
            // jadi kita coba pertahankan state 'active' via logic di loadContacts
            
            // Kita load ulang kontak
            $.ajax({
                url: 'ajax_chat.php', type: 'GET', data: { action: 'get_contacts' }, dataType: 'json',
                success: function(response) {
                    // Update unread count saja atau redraw list? 
                    // Untuk simplisitas dan konsistensi data, kita redraw tapi pertahankan active class visual
                    // Namun agar tidak 'flicker', idealnya DOM manipulation parsial. 
                    // Karena batasan coding, kita redraw container tapi select ulang active item.
                    
                    // (Implementasi redraw ada di dalam function loadContacts yang dipanggil terpisah jika ingin full refresh)
                    // Untuk UX yang smooth tanpa flicker saat ngetik pesan, kita skip redraw list JIKA user sedang mengetik
                    // atau cukup load message jika chat terbuka.
                    
                    if (activeContact) {
                        // Silent update chat content
                        $.ajax({
                            url: 'ajax_chat.php', type: 'POST', data: { action: 'get_messages', contact_id: activeContact.id, contact_role: activeContact.role }, dataType: 'json',
                            success: function(resChat) {
                                if(resChat.status === 'success') {
                                    const chatBody = $('#chat-body-content');
                                    // Logic sederhana: jika jumlah pesan beda, append yang baru.
                                    // Karena struktur AJAX return all messages, kita replace saja innerHTML jika user tidak sedang scroll ke atas.
                                    // Disini kita replace saja demi konsistensi data.
                                    
                                    // Simpan isi input agar aman
                                    const currentScroll = chatBody.scrollTop();
                                    const maxScroll = chatBody[0].scrollHeight - chatBody.outerHeight();
                                    const isAtBottom = (maxScroll - currentScroll) < 50;

                                    // Render ulang (agak boros resource tapi aman secara logika tanpa ubah backend)
                                    let htmlBuffer = '';
                                    resChat.messages.forEach(msg => {
                                        const isMe = (msg.id_pengirim == currentUserId && msg.role_pengirim == currentUserRole);
                                        const typeClass = isMe ? 'sent' : 'received';
                                        htmlBuffer += `
                                            <div class="message-group ${typeClass}">
                                                <div class="message-bubble">
                                                    ${msg.isi_pesan.replace(/\n/g, '<br>')}
                                                    <div class="message-time">${formatMessageTime(msg.waktu_kirim)}</div>
                                                </div>
                                            </div>`;
                                    });
                                    
                                    // Bandingkan panjang string agar tidak redraw jika sama (mencegah flicker)
                                    if(chatBody.html().length !== htmlBuffer.length && htmlBuffer.length > 10) {
                                         chatBody.html(htmlBuffer);
                                         if(isAtBottom) chatBody.scrollTop(chatBody[0].scrollHeight);
                                    }
                                }
                            }
                        });
                    }
                }
            });

        }, 5000);
    }

    loadContacts();
    startPolling();
});
</script>

<?php include 'footer.php'; ?>