<?php
/**
 * includes/invoice_email.php
 * Mengirim invoice HTML rapi ke email pembeli setelah pembayaran berhasil.
 * Tetap menyertakan tautan halaman invoice di aplikasi sehingga ketika
 * Midtrans di-close, pembeli masih bisa melihatnya.
 *
 * Fallback: bila mail() gagal, hanya catat ke error_log. Sistem tetap jalan.
 */
require_once __DIR__ . '/app_settings.php';

function rupiah_fmt($n) { return 'Rp '.number_format((int)$n, 0, ',', '.'); }

function kirim_invoice_email(array $order, array $items, ?string $hostBase = null): bool {
    if (empty($order['email_pemesan'])) return false;

    $from      = app_setting('invoice_email_from', 'no-reply@hapfam.local');
    $fromNama  = app_setting('invoice_email_nama', 'HapFam SportApp');
    $to        = trim($order['email_pemesan']);
    $kode      = $order['kode'] ?? '';
    $subject   = 'Invoice Pesanan #'.$kode.' — HapFam SportApp';

    $linkInvoice = ($hostBase ?: '') . '/jajanan.php?invoice='.urlencode($kode);

    ob_start(); ?>
<!doctype html><html><head><meta charset="utf-8"><title><?= htmlspecialchars($subject) ?></title></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.08);">
      <tr><td style="background:linear-gradient(135deg,#0ea5e9,#6366f1);padding:20px 24px;color:#fff;">
        <div style="font-size:20px;font-weight:700;">HapFam SportApp</div>
        <div style="font-size:13px;opacity:.85;">Invoice Pemesanan Jajanan</div>
      </td></tr>
      <tr><td style="padding:20px 24px;">
        <p>Halo <strong><?= htmlspecialchars($order['nama_pemesan']) ?></strong>,</p>
        <p>Berikut rincian pesanan Anda. Status pembayaran:
          <strong style="color:<?= ($order['payment_status']??'')==='paid'?'#16a34a':'#d97706' ?>;text-transform:uppercase;">
            <?= htmlspecialchars($order['payment_status'] ?? '-') ?>
          </strong>.
        </p>
        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:12px 0;">
          <tr><td style="color:#64748b;width:130px;">Kode Pesanan</td><td><strong><?= htmlspecialchars($kode) ?></strong></td></tr>
          <tr><td style="color:#64748b;">Tanggal</td><td><?= htmlspecialchars($order['created_at'] ?? '') ?></td></tr>
          <tr><td style="color:#64748b;">No. WA</td><td><?= htmlspecialchars($order['no_wa'] ?? '') ?></td></tr>
          <tr><td style="color:#64748b;">Alamat</td><td><?= nl2br(htmlspecialchars($order['alamat'] ?? '')) ?></td></tr>
        </table>

        <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px;border:1px solid #e2e8f0;border-radius:8px;">
          <thead><tr style="background:#f8fafc;">
            <th align="left">Produk</th><th align="right">Qty</th><th align="right">Harga</th><th align="right">Subtotal</th>
          </tr></thead>
          <tbody>
          <?php $sub=0; foreach ($items as $it):
            $row = (int)$it['harga'] * (int)$it['qty']; $sub += $row; ?>
            <tr style="border-top:1px solid #e2e8f0;">
              <td><?= htmlspecialchars($it['nama']) ?></td>
              <td align="right"><?= (int)$it['qty'] ?></td>
              <td align="right"><?= rupiah_fmt($it['harga']) ?></td>
              <td align="right"><?= rupiah_fmt($row) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <table width="100%" cellpadding="4" cellspacing="0" style="font-size:14px;margin-top:12px;">
          <tr><td align="right" style="color:#64748b;">Subtotal</td><td align="right" width="120"><?= rupiah_fmt($order['subtotal']) ?></td></tr>
          <tr><td align="right" style="color:#64748b;">Ongkir</td><td align="right"><?= rupiah_fmt($order['ongkir']) ?></td></tr>
          <tr><td align="right" style="color:#64748b;">Biaya Admin Midtrans</td><td align="right"><?= rupiah_fmt($order['biaya_admin'] ?? 0) ?></td></tr>
          <tr><td align="right" style="color:#64748b;">Biaya Aplikasi</td><td align="right"><?= rupiah_fmt($order['biaya_aplikasi'] ?? 0) ?></td></tr>
          <tr><td align="right" style="font-size:16px;font-weight:700;color:#0f172a;border-top:2px solid #e2e8f0;padding-top:8px;">TOTAL</td>
              <td align="right" style="font-size:16px;font-weight:700;color:#0ea5e9;border-top:2px solid #e2e8f0;padding-top:8px;">
                <?= rupiah_fmt($order['total']) ?></td></tr>
        </table>

        <div style="margin:18px 0;text-align:center;">
          <a href="<?= htmlspecialchars($linkInvoice) ?>"
             style="display:inline-block;background:#0ea5e9;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;">
            Lihat Invoice di Aplikasi
          </a>
        </div>
        <p style="font-size:12px;color:#64748b;">Jika tombol di atas tidak berfungsi, salin tautan: <br><?= htmlspecialchars($linkInvoice) ?></p>
        <p style="font-size:12px;color:#64748b;">Email ini dikirim otomatis. Jika tidak merasa memesan, abaikan saja.</p>
      </td></tr>
      <tr><td style="background:#f8fafc;padding:14px 24px;text-align:center;font-size:12px;color:#64748b;">
        &copy; <?= date('Y') ?> HapFam SportApp · By Yuk-Mari CyberLab
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
    <?php
    $html = ob_get_clean();

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: '.$fromNama.' <'.$from.'>',
        'Reply-To: '.$from,
        'X-Mailer: HapFam-SportApp/1.0',
    ];

    $ok = @mail($to, $subject, $html, implode("\r\n", $headers));
    if (!$ok) {
        error_log('[invoice_email] mail() gagal ke '.$to.' kode='.$kode);
        return false;
    }
    try {
        db_exec("UPDATE jajanan_pesanan SET invoice_sent_at=now() WHERE kode=$1", [$kode]);
    } catch (Throwable $e) {}
    return true;
}
