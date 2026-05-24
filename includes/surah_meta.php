<?php
/**
 * Metadata tambahan surah: tempat turun (Makkiyah / Madaniyah).
 * Sumber: konsensus ulama (29 surah Madaniyah).
 */
$ISLAMI_SURAH_MADANIYAH = [2,3,4,5,8,9,13,22,24,33,47,48,49,55,57,58,59,60,61,62,63,64,65,66,76,98,99,110,113,114];

function surah_tempat_turun(int $no): string {
    global $ISLAMI_SURAH_MADANIYAH;
    return in_array($no, $ISLAMI_SURAH_MADANIYAH, true) ? 'Madaniyah' : 'Makkiyah';
}
function surah_tempat_badge(int $no): string {
    $t = surah_tempat_turun($no);
    $cls = $t === 'Madaniyah' ? 'bg-info text-dark' : 'bg-warning text-dark';
    return '<span class="badge '.$cls.'" title="Tempat turun">'.$t.'</span>';
}
