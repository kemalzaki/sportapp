<?php
// Helper export sederhana: CSV (Excel-compat) & PDF (HTML printable)
function export_csv(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'.csv"');
    echo "\xEF\xBB\xBF"; // BOM agar Excel kenal UTF-8
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

function export_pdf_html(string $title, array $headers, array $rows): void {
    // Render HTML printable yang otomatis trigger print -> Save as PDF (browser native)
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>'.htmlspecialchars($title).'</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:24px}h2{margin:0 0 12px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #888;padding:6px;text-align:left}th{background:#eee}@media print{.noprint{display:none}}</style>';
    echo '</head><body>';
    echo '<div class="noprint" style="margin-bottom:12px"><button onclick="window.print()">🖨️ Print / Save as PDF</button> <a href="javascript:history.back()">← Kembali</a></div>';
    echo '<h2>'.htmlspecialchars($title).'</h2>';
    echo '<table><thead><tr>';
    foreach ($headers as $h) echo '<th>'.htmlspecialchars($h).'</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($r as $c) echo '<td>'.htmlspecialchars((string)$c).'</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<script>setTimeout(()=>window.print(),300)</script>';
    echo '</body></html>';
    exit;
}
