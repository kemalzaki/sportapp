<?php
require __DIR__.'/config/db.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/exporter.php';
require_role('admin');

$type   = $_GET['type']   ?? '';
$format = $_GET['format'] ?? 'csv';

$title = ''; $headers = []; $rows = [];

switch ($type) {
    case 'absensi':
        $jid = (int)($_GET['jadwal_id'] ?? 0);
        $j = $jid ? db_one("SELECT * FROM jadwal WHERE id=$1",[$jid]) : null;
        $title = 'Absensi '.($j['tanggal'] ?? '-').' - '.($j['jenis'] ?? '-');
        $headers = ['Nama','Status','Hadir','Keterangan','Telat (mnt)'];
        $data = db_all("SELECT u.nama, a.status, a.hadir, a.keterangan, a.telat_menit
                        FROM absensi a JOIN users u ON u.id=a.user_id
                        WHERE a.jadwal_id=$1 ORDER BY u.nama", [$jid]);
        foreach($data as $d) $rows[] = [$d['nama'], $d['status'] ?? '-', $d['hadir'], $d['keterangan'] ?? '', (int)($d['telat_menit'] ?? 0)];
        break;
    case 'members':
        $title='Daftar Member';
        $headers=['ID','Nama','Email','Role','XP','Level','Streak','Bergabung'];
        foreach(db_all("SELECT id,nama,email,role,xp,level,streak_minggu,created_at FROM users ORDER BY nama") as $d)
            $rows[]=[$d['id'],$d['nama'],$d['email'],$d['role'],$d['xp'],$d['level'],$d['streak_minggu'],$d['created_at']];
        break;
    case 'jadwal':
        $title='Daftar Jadwal';
        $headers=['Tanggal','Jenis','Tempat','Durasi','Koordinator'];
        foreach(db_all("SELECT j.tanggal,j.jenis,j.tempat,j.durasi_menit,u.nama AS koord
                        FROM jadwal j LEFT JOIN users u ON u.id=j.koordinator_id ORDER BY j.tanggal DESC") as $d)
            $rows[]=[$d['tanggal'],$d['jenis'],$d['tempat'],$d['durasi_menit'],$d['koord']];
        break;
    case 'tempat':
        $title='Daftar Tempat';
        $headers=['ID','Nama','Alamat','Lat','Lng'];
        foreach(db_all("SELECT id,nama,alamat,lat,lng FROM tempat ORDER BY nama") as $d)
            $rows[]=[$d['id'],$d['nama'],$d['alamat'] ?? '',$d['lat'],$d['lng']];
        break;
    case 'aktivitas':
        $title='Upload Aktivitas Harian';
        $headers=['Tanggal','User','Jenis','Durasi','Jarak (km)','Pace','Kalori'];
        foreach(db_all("SELECT uh.tanggal,u.nama,uh.jenis,uh.durasi_menit,uh.jarak_km,uh.pace,uh.kalori
                        FROM upload_harian uh JOIN users u ON u.id=uh.user_id ORDER BY uh.tanggal DESC") as $d)
            $rows[]=[$d['tanggal'],$d['nama'],$d['jenis'],$d['durasi_menit'],$d['jarak_km'],$d['pace'],$d['kalori']];
        break;
    case 'booking':
        $title='Booking Lapangan';
        $headers=['Tanggal','Jam Mulai','Jam Selesai','Tempat','User','Status','DP'];
        foreach(db_all("SELECT b.tanggal,b.jam_mulai,b.jam_selesai,t.nama AS tempat,u.nama,b.status,b.dp_status
                        FROM booking b JOIN tempat t ON t.id=b.tempat_id JOIN users u ON u.id=b.user_id
                        ORDER BY b.tanggal DESC") as $d)
            $rows[]=[$d['tanggal'],$d['jam_mulai'],$d['jam_selesai'],$d['tempat'],$d['nama'],$d['status'],$d['dp_status']];
        break;
    default:
        http_response_code(400); die('Tipe export tidak dikenali.');
}

$safe = preg_replace('/[^a-z0-9_-]+/i','_',$title);
if ($format === 'pdf') export_pdf_html($title, $headers, $rows);
else export_csv($safe, $headers, $rows);
