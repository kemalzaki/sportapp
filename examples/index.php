<?php
/**
 * Contoh konversi index.php — pola HTMX layout splitter.
 * Yang berubah HANYA bagian header/footer; semua logic & query tetap.
 */
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/notifications.php';
require __DIR__.'/../includes/htmx.php';   // ← TAMBAH

send_security_headers(); enforce_session_timeout();
$u = current_user();
if (!$u && empty($_GET['guest'])) { htmx_redirect('/login.php'); }

// ... semua handler POST tetap sama ...

htmx_layout_start('Beranda');   // ← GANTI require header.php
?>

<div class="container py-3">
  <h1>Selamat datang, <?= htmlspecialchars($u['nama'] ?? 'Tamu') ?></h1>

  <!-- contoh chat dengan HTMX -->
  <form hx-post="/index.php" hx-target="#chat-list" hx-swap="afterbegin"
        hx-on::after-request="this.reset()">
    <input type="hidden" name="_action" value="chat_post">
    <div class="input-group">
      <input name="pesan" class="form-control" placeholder="Tulis pesan..." required>
      <button class="btn btn-primary">Kirim</button>
    </div>
  </form>

  <div id="chat-list" class="mt-3">
    <!-- isi feed di-render seperti biasa -->
  </div>
</div>

<?php
htmx_layout_end();   // ← GANTI require footer.php
