<?php
require __DIR__.'/../config/db.php';
require __DIR__.'/../includes/auth.php';
require __DIR__.'/../includes/htmx.php';

htmx_layout_start('Feed Islami');
?>
<div class="container py-3">
  <h2>Feed Islami</h2>

  <!-- Infinite scroll -->
  <div id="feed">
    <?php /* render 10 item pertama di sini */ ?>
  </div>

  <div hx-get="/feed_islami.php?page=2"
       hx-trigger="revealed"
       hx-swap="outerHTML"
       hx-target="this"
       class="text-center py-3">
    <div class="spinner-border spinner-border-sm"></div>
  </div>
</div>
<?php
htmx_layout_end();
