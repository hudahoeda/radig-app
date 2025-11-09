<?php
include 'header.php';
// Koneksi tidak di-include di sini karena semua data diambil via AJAX
?>

<style>
/* FONT DAN WARNA UTAMA */
:root {
    --chat-bg-pattern: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23d4eaf7' fill-opacity='0.4' fill-rule='evenodd'%3E%3Cpath d='M0 40L40 0H20L0 20M40 40V20L20 40'/%3E%3C/g%3E%3C/svg%3E");
    --bubble-sent-bg: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    --bubble-received-bg: #ffffff;
    --contact-active-bg: linear-gradient(90deg, #e0f2f1, #b2dfdb);
}

/* LAYOUT UTAMA APLIKASI CHAT */
.chat-app-container {
    height: calc(100vh - 180px); /* Disesuaikan agar tidak terlalu memakan layar */
    max-height: 700px;
    display: flex;
    overflow: hidden;
    border-radius: 1rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    background-color: #fff;
    border: 1px solid var(--border-color);
}

/* [KIRI] SIDEBAR KONTAK */
.contact-sidebar {
    width: 320px;
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    background-color: #fcfcfc;
}
.contact-sidebar-header {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}
.contact-search-input {
    border-radius: 50px;
}
.contact-list {
    flex-grow: 1;
    overflow-y: auto;
}
.contact-item {
    display: flex;
    align-items: center;
    padding: 0.8rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s;
    position: relative;
}
.contact-item:hover {
    background-color: #f1f3f5;
}
.contact-item.active {
    background-image: var(--contact-active-bg);
}
.contact-item.active .contact-name {
    color: var(--secondary-color);
}
.contact-avatar img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 15px;
    border: 2px solid #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.contact-details {
    overflow: hidden;
    width: 100%;
}
.contact-name {
    font-weight: 600;
    white-space: nowrap;
}
.last-message {
    font-size: 0.8rem;
    color: #6c757d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.contact-meta {
    margin-left: auto;
    text-align: right;
    font-size: 0.75rem;
    color: #6c757d;
    flex-shrink: 0;
}
.unread-badge {
    background-color: var(--primary-color);
    color: white;
    font-weight: 600;
    border-radius: 50px;
    padding: 0.2em 0.5em;
    font-size: 0.7rem;
    margin-top: 5px;
}

/* [KANAN] JENDELA PERCAKAPAN */
.conversation-window {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    background-color: #e9ecef;
    background-image: var(--chat-bg-pattern);
}
.conversation-header {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    background-color: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.conversation-body {
    flex-grow: 1;
    padding: 1.5rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.conversation-placeholder {
    margin: auto;
    text-align: center;
    color: var(--text-muted);
}
.conversation-placeholder .icon-placeholder {
    font-size: 6rem;
    color: #ced4da;
}

/* Gelembung Pesan (Message Bubbles) */
.message-group {
    display: flex;
    margin-bottom: 1rem;
    align-items: flex-end;
}
.message-group .bubble {
    max-width: 75%;
    padding: 0.75rem 1rem;
    border-radius: 1.25rem;
    line-height: 1.4;
    position: relative;
    box-shadow: 0 2px 5px rgba(0,0,0,0.08);
}
.message-group .bubble-meta {
    font-size: 0.7rem;
    color: #888;
    margin-top: 4px;
}

.message-group.sent {
    justify-content: flex-end;
}
.message-group.sent .bubble {
    background-image: var(--bubble-sent-bg);
    color: white;
    border-bottom-right-radius: 0.5rem;
}
.message-group.sent .bubble-meta {
    text-align: right;
    padding-right: 5px;
}

.message-group.received {
    justify-content: flex-start;
}
.message-group.received .bubble {
    background: var(--bubble-received-bg);
    border-bottom-left-radius: 0.5rem;
}
.message-group.received .bubble-meta {
    padding-left: 5px;
}

/* Input Area */
.chat-input-area {
    padding: 1rem;
    background-color: #f8f9fa;
    border-top: 1px solid var(--border-color);
}
.chat-input-area .form-control {
    border-radius: 50px;
    padding: 0.75rem 1.25rem;
    height: auto;
}
.chat-input-area .btn-send {
    border-radius: 50%;
    width: 50px;
    height: 50px;
    background-image: var(--bubble-sent-bg);
    border: none;
}
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-chat-left-text-fill me-2"></i>Ruang Konsultasi Guru Wali</h1>
    </div>
    
    <div class="chat-app-container">
        <!-- Kolom Kiri: Sidebar Kontak -->
        <div class="contact-sidebar">
            <div class="contact-sidebar-header">
                <input type="text" id="contact-search" class="form-control contact-search-input" placeholder="Cari kontak...">
            </div>
            <div class="contact-list" id="contacts-container">
                <div class="p-5 text-center text-muted"><div class="spinner-border spinner-border-sm"></div> Memuat...</div>
            </div>
        </div>

        <!-- Kolom Kanan: Jendela Percakapan -->
        <div class="conversation-window">
            <div class="conversation-placeholder" id="chat-placeholder">
                <div class="icon-placeholder"><i class="bi bi-send-check"></i></div>
                <h4 class="mt-3">Selamat Datang di Ruang Konsultasi!</h4>
                <p>Pilih kontak di sebelah kiri untuk melihat percakapan Anda.</p>
            </div>

            <div id="chat-area" class="d-none" style="display: flex; flex-direction: column; height: 100%;">
                <div class="conversation-header" id="chat-header-content"></div>
                <div class="conversation-body" id="chat-body-content"></div>
                <div class="chat-input-area">
                    <form id="message-form" class="d-flex align-items-center">
                        <input type="text" id="message-input" class="form-control me-2" placeholder="Ketik pesan Anda di sini..." autocomplete="off" required>
                        <button class="btn btn-primary btn-send" type="submit"><i class="bi bi-send-fill"></i></button>
                    </form>
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

    // Helper function untuk format waktu
    function formatMessageTime(dateTime) {
        return new Date(dateTime.replace(' ', 'T') + 'Z').toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    // 1. Memuat daftar kontak
    function loadContacts() {
        $.ajax({
            url: 'ajax_chat.php', type: 'GET', data: { action: 'get_contacts' }, dataType: 'json',
            success: function(response) {
                if (response.status !== 'success') return;
                const container = $('#contacts-container');
                container.empty();
                if (response.contacts.length === 0) {
                    container.html('<div class="p-3 text-center text-muted">Tidak ada kontak ditemukan.</div>');
                    return;
                }
                response.contacts.forEach(contact => {
                    const unreadBadge = contact.unread_count > 0 ? `<div class="unread-badge">${contact.unread_count}</div>` : '';
                    const contactHtml = `
                        <div class="contact-item" data-contact-id="${contact.contact_id}" data-contact-role="${contact.contact_role}" data-contact-name="${contact.contact_name}" data-contact-photo="${contact.contact_photo}">
                            <div class="contact-avatar"><img src="${contact.contact_photo}" alt="${contact.contact_name}"></div>
                            <div class="contact-details">
                                <div class="contact-name">${contact.contact_name}</div>
                                <div class="last-message"></div>
                            </div>
                            <div class="contact-meta">
                                <div class="last-message-time"></div>
                                ${unreadBadge}
                            </div>
                        </div>`;
                    container.append(contactHtml);
                });
            }
        });
    }

    // 2. Memuat pesan dalam percakapan
    function loadMessages(contactId, contactRole, contactName, contactPhoto) {
        $('#chat-placeholder').addClass('d-none');
        $('#chat-area').removeClass('d-none');
        const chatBody = $('#chat-body-content');
        chatBody.html('<div class="m-auto text-center"><div class="spinner-border"></div></div>');
        
        $.ajax({
            url: 'ajax_chat.php', type: 'POST', data: { action: 'get_messages', contact_id: contactId, contact_role: contactRole }, dataType: 'json',
            success: function(response) {
                if (response.status !== 'success') return;
                
                $('#chat-header-content').html(`
                    <div class="contact-avatar"><img src="${contactPhoto}" alt="${contactName}"></div>
                    <div class="contact-details">
                        <div class="contact-name">${contactName}</div>
                        <small class="text-muted">${contactRole === 'guru' ? 'Guru Wali Anda' : 'Siswa Bimbingan'}</small>
                    </div>`);
                
                chatBody.empty();
                let lastSenderId = null;
                response.messages.forEach(msg => {
                    const messageType = (msg.id_pengirim == currentUserId && msg.role_pengirim == currentUserRole) ? 'sent' : 'received';
                    const time = formatMessageTime(msg.waktu_kirim);
                    const showAvatar = (lastSenderId !== (msg.role_pengirim + msg.id_pengirim));

                    const messageHtml = `
                        <div class="message-group ${messageType}">
                            <div>
                                <div class="bubble">${msg.isi_pesan.replace(/\n/g, '<br>')}</div>
                                <div class="bubble-meta">${time}</div>
                            </div>
                        </div>`;
                    chatBody.append(messageHtml);
                    lastSenderId = msg.role_pengirim + msg.id_pengirim;
                });
                chatBody.scrollTop(chatBody[0].scrollHeight);
            }
        });
    }
    
    // 3. Event handler: klik kontak
    $('#contacts-container').on('click', '.contact-item', function() {
        const contactItem = $(this);
        activeContact = {
            id: contactItem.data('contact-id'),
            role: contactItem.data('contact-role'),
            name: contactItem.data('contact-name'),
            photo: contactItem.data('contact-photo')
        };
        $('.contact-item').removeClass('active');
        contactItem.addClass('active');
        contactItem.find('.unread-badge').remove();
        loadMessages(activeContact.id, activeContact.role, activeContact.name, activeContact.photo);
    });

    // 4. Event handler: kirim pesan
    $('#message-form').on('submit', function(e) {
        e.preventDefault();
        if (!activeContact) return;
        const messageInput = $('#message-input');
        const messageText = messageInput.val();
        if (messageText.trim() === '') return;

        $.ajax({
            url: 'ajax_chat.php', type: 'POST', data: { action: 'send_message', receiver_id: activeContact.id, receiver_role: activeContact.role, message: messageText }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    messageInput.val('');
                    const chatBody = $('#chat-body-content');
                    const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                    chatBody.append(`
                        <div class="message-group sent">
                            <div>
                                <div class="bubble">${messageText.replace(/\n/g, '<br>')}</div>
                                <div class="bubble-meta">${time}</div>
                            </div>
                        </div>`);
                    chatBody.scrollTop(chatBody[0].scrollHeight);
                } else { alert('Gagal mengirim pesan: ' + response.message); }
            }
        });
    });
    
    // 5. Event handler: pencarian kontak
    $('#contact-search').on('keyup', function() {
        const filter = $(this).val().toLowerCase();
        $('.contact-item').each(function() {
            const name = $(this).data('contact-name').toLowerCase();
            if (name.includes(filter)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // 6. Polling untuk pesan baru
    function startPolling() {
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(function() {
            if (activeContact) {
                loadMessages(activeContact.id, activeContact.role, activeContact.name, activeContact.photo);
            }
            loadContacts();
        }, 7000); // Cek setiap 7 detik
    }

    // Panggil fungsi awal
    loadContacts();
    startPolling();
});
</script>

<?php include 'footer.php'; ?>

