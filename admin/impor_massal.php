<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();

$hasil = null; // ringkasan hasil impor, diisi setelah proses POST

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf_valid();

    $kelasId = (int)($_POST['kelas_id'] ?? 0);
    $statusDefault = ($_POST['status_default'] ?? 'draft') === 'published' ? 'published' : 'draft';

    $diimpor = 0;
    $dilewati = [];

    if ($kelasId <= 0) {
        set_flash('error', 'Pilih kelas tujuan terlebih dahulu.');
        redirect('impor_massal.php');
    }

    if (empty($_FILES['berkas_zip']['name'])) {
        set_flash('error', 'Unggah berkas .zip terlebih dahulu.');
        redirect('impor_massal.php');
    }

    if (!class_exists('ZipArchive')) {
        set_flash('error', 'Ekstensi PHP "zip" tidak aktif di server ini. Aktifkan ekstensi zip lalu coba lagi.');
        redirect('impor_massal.php');
    }

    $file = $_FILES['berkas_zip'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Gagal mengunggah berkas zip (kode error: ' . $file['error'] . ').');
        redirect('impor_massal.php');
    }

    $ukuranMb = $file['size'] / (1024 * 1024);
    if ($ukuranMb > MAX_UPLOAD_ZIP_MB) {
        set_flash('error', 'Ukuran zip melebihi batas ' . MAX_UPLOAD_ZIP_MB . ' MB. Coba pecah jadi beberapa zip lebih kecil (per kelas/per subfolder).');
        redirect('impor_massal.php');
    }

    $ekstensi = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ekstensi !== 'zip') {
        set_flash('error', 'Berkas harus berformat .zip.');
        redirect('impor_massal.php');
    }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        set_flash('error', 'Berkas zip tidak valid atau rusak.');
        redirect('impor_massal.php');
    }

    if (!is_dir(UPLOAD_DIR_QUIZZES)) {
        @mkdir(UPLOAD_DIR_QUIZZES, 0755, true);
    }

    $jumlahEntri = $zip->numFiles;
    $batasEntri = 300;   // batas jumlah entri yang diperiksa dalam satu zip
    $batasImpor = 150;   // batas jumlah latihan yang benar-benar diimpor dalam satu proses

    for ($i = 0; $i < min($jumlahEntri, $batasEntri) && $diimpor < $batasImpor; $i++) {
        $stat = $zip->statIndex($i);
        if (!$stat) continue;

        $namaAsliDiZip = $stat['name'];

        // Lewati folder, file tersembunyi, dan sampah metadata macOS
        if (substr($namaAsliDiZip, -1) === '/') continue;
        if (str_contains($namaAsliDiZip, '__MACOSX/')) continue;
        $namaFileSaja = basename($namaAsliDiZip);
        if ($namaFileSaja === '' || $namaFileSaja[0] === '.') continue;

        $ekstensiEntri = strtolower(pathinfo($namaFileSaja, PATHINFO_EXTENSION));
        if (!in_array($ekstensiEntri, ['html', 'htm'], true)) {
            $dilewati[] = $namaFileSaja . ' (bukan berkas HTML)';
            continue;
        }

        if ($stat['size'] > MAX_UPLOAD_LATIHAN_MB * 1024 * 1024) {
            $dilewati[] = $namaFileSaja . ' (ukuran melebihi ' . MAX_UPLOAD_LATIHAN_MB . ' MB)';
            continue;
        }

        $isi = $zip->getFromIndex($i);
        if ($isi === false || trim($isi) === '') {
            $dilewati[] = $namaFileSaja . ' (gagal dibaca atau kosong)';
            continue;
        }

        // Tulis ke berkas sementara untuk verifikasi tipe MIME sebenarnya (bukan hanya nama)
        $tmpPath = tempnam(sys_get_temp_dir(), 'impor_');
        file_put_contents($tmpPath, $isi);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $mimeDiterima = in_array($mime, ['text/html', 'text/plain', 'application/xhtml+xml'], true);
        if (!$mimeDiterima && $mime === 'application/octet-stream') {
            // finfo kadang salah menebak HTML sah yang membundel pustaka seperti
            // jsPDF sebagai berkas biner — periksa isinya langsung sebagai cadangan.
            $mimeDiterima = konten_tampak_html($isi);
        }

        if (!$mimeDiterima) {
            @unlink($tmpPath);
            $dilewati[] = $namaFileSaja . ' (tipe berkas tidak dikenali sebagai HTML: ' . $mime . ')';
            continue;
        }

        // Judul default: dari tag <title> jika ada, kalau tidak dari nama berkas
        $judul = pretty_dari_nama_file($namaFileSaja);
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $isi, $m)) {
            $judulTag = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            if ($judulTag !== '') $judul = $judulTag;
        }
        $judul = format_judul($judul);

        $namaFileBaru = nama_file_aman($namaFileSaja);
        if (!rename($tmpPath, UPLOAD_DIR_QUIZZES . $namaFileBaru)) {
            @unlink($tmpPath);
            $dilewati[] = $namaFileSaja . ' (gagal menyimpan ke server)';
            continue;
        }

        $slugDasar = buat_slug($judul);
        $slug = slug_unik('topik', $slugDasar, 'kelas_id', $kelasId);

        $stmt = $pdo->prepare(
            'INSERT INTO topik (kelas_id, tipe, judul, slug, file_path, emoji, status, dibuat_oleh)
             VALUES (:kelas_id, "latihan", :judul, :slug, :file_path, "📝", :status, :dibuat_oleh)'
        );
        $stmt->execute([
            'kelas_id' => $kelasId, 'judul' => $judul, 'slug' => $slug,
            'file_path' => $namaFileBaru, 'status' => $statusDefault, 'dibuat_oleh' => $admin['id'],
        ]);

        $diimpor++;
    }

    $zip->close();

    catat_log($admin['id'], 'impor_massal', $diimpor . ' latihan diimpor, ' . count($dilewati) . ' dilewati');

    $hasil = ['diimpor' => $diimpor, 'dilewati' => $dilewati, 'kelasId' => $kelasId];

    if ($diimpor > 0) {
        set_flash('sukses', $diimpor . ' latihan berhasil diimpor sebagai ' . ($statusDefault === 'published' ? 'terbit' : 'draf') . '. Silakan tinjau judul & urutannya di daftar Latihan & Materi.');
    } else {
        set_flash('error', 'Tidak ada berkas HTML yang berhasil diimpor dari zip ini. Periksa isi zip Anda.');
    }
}

function pretty_dari_nama_file(string $namaFile): string
{
    $tanpaEkstensi = pathinfo($namaFile, PATHINFO_FILENAME);
    return format_judul($tanpaEkstensi);
}

$pohon = $pdo->query(
    "SELECT j.id AS jenjang_id, j.nama AS jenjang_nama,
            m.id AS mapel_id, m.nama AS mapel_nama,
            k.id AS kelas_id, k.nama AS kelas_nama
     FROM jenjang j JOIN mapel m ON m.jenjang_id = j.id JOIN kelas k ON k.mapel_id = m.id
     ORDER BY j.urutan, m.urutan, k.urutan"
)->fetchAll();

$judul_admin = 'Impor Massal';
$menuAktif = 'impor';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="panel">
    <div class="panel-head"><h2>Cara Pakai</h2></div>
    <ol style="margin:0; padding-left:20px; color:var(--muted); font-size:14.5px; line-height:1.9;">
        <li>Di Google Drive, buka folder per <strong>kelas</strong> (misalnya "Kelas 4 Done") yang berisi banyak berkas <code>.html</code>.</li>
        <li>Pilih semua berkas di dalamnya (Ctrl/Cmd+A), lalu klik <strong>Download</strong> — Google Drive akan otomatis menggabungkannya jadi satu berkas <code>.zip</code>.</li>
        <li>Pastikan Jenjang, Mata Pelajaran, dan Kelas yang sesuai sudah dibuat di sistem ini (lewat menu Jenjang / Mata Pelajaran / Kelas).</li>
        <li>Unggah zip tersebut di bawah ini, pilih kelas tujuannya, lalu impor.</li>
        <li>Semua latihan akan masuk sebagai <strong>draf</strong> secara default — tinjau judul dan terbitkan satu per satu (atau ubah status massal) di menu <a href="topik.php?status=draft">Latihan & Materi</a>.</li>
    </ol>
</div>

<?php if ($hasil): ?>
<div class="panel">
    <div class="panel-head"><h2>Hasil Impor Terakhir</h2></div>
    <p><strong><?= (int)$hasil['diimpor'] ?></strong> latihan berhasil diimpor.</p>
    <?php if ($hasil['dilewati']): ?>
        <p style="margin-bottom:6px;"><strong><?= count($hasil['dilewati']) ?></strong> berkas dilewati:</p>
        <ul style="color:var(--muted); font-size:13.5px; max-height:220px; overflow-y:auto;">
            <?php foreach ($hasil['dilewati'] as $d): ?>
                <li><?= h($d) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <a href="topik.php?status=draft" class="btn btn-primary btn-sm">Tinjau Hasil Impor →</a>
</div>
<?php endif; ?>

<div class="panel" style="max-width:560px;">
    <div class="panel-head"><h2>Impor Zip Baru</h2></div>
    <?php if (!$pohon): ?>
        <p class="kosong-admin">Buat setidaknya satu Jenjang, Mata Pelajaran, dan Kelas terlebih dahulu.</p>
    <?php else: ?>
    <form method="post" enctype="multipart/form-data" class="form-grid" id="form-impor">
        <?= csrf_field() ?>
        <div class="form-2col">
            <div class="form-row">
                <label for="jenjang_pilih">Jenjang</label>
                <select id="jenjang_pilih" onchange="perbaruiPilihanMapel()"></select>
            </div>
            <div class="form-row">
                <label for="mapel_pilih">Mata Pelajaran</label>
                <select id="mapel_pilih" onchange="perbaruiPilihanKelas()"></select>
            </div>
        </div>
        <div class="form-row">
            <label for="kelas_id">Kelas Tujuan</label>
            <select id="kelas_id" name="kelas_id" required></select>
            <span class="bantuan">Semua latihan di dalam zip akan didaftarkan ke kelas ini.</span>
        </div>
        <div class="form-row">
            <label for="berkas_zip">Berkas .zip</label>
            <input type="file" id="berkas_zip" name="berkas_zip" accept=".zip" required>
            <span class="bantuan">Maksimal <?= MAX_UPLOAD_ZIP_MB ?> MB per zip. Berisi banyak berkas <code>.html</code> latihan soal.</span>
        </div>
        <div class="form-row">
            <label for="status_default">Status setelah diimpor</label>
            <select id="status_default" name="status_default">
                <option value="draft">Draf (disarankan — tinjau dulu sebelum tampil di situs)</option>
                <option value="published">Langsung Terbit</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mulai Impor</button>
        </div>
    </form>

    <script>
    var pohonData = <?= json_encode($pohon, JSON_UNESCAPED_UNICODE) ?>;
    function bangunPohon() {
        var jm = {};
        pohonData.forEach(function (row) {
            if (!jm[row.jenjang_id]) jm[row.jenjang_id] = { nama: row.jenjang_nama, mapel: {} };
            if (!jm[row.jenjang_id].mapel[row.mapel_id]) jm[row.jenjang_id].mapel[row.mapel_id] = { nama: row.mapel_nama, kelas: [] };
            jm[row.jenjang_id].mapel[row.mapel_id].kelas.push({ id: row.kelas_id, nama: row.kelas_nama });
        });
        return jm;
    }
    var pohon = bangunPohon();
    function isiJenjang() {
        var sel = document.getElementById('jenjang_pilih');
        sel.innerHTML = '';
        for (var jid in pohon) {
            var opt = document.createElement('option');
            opt.value = jid; opt.textContent = pohon[jid].nama;
            sel.appendChild(opt);
        }
    }
    function perbaruiPilihanMapel() {
        var jid = document.getElementById('jenjang_pilih').value;
        var sel = document.getElementById('mapel_pilih');
        sel.innerHTML = '';
        if (pohon[jid]) {
            for (var mid in pohon[jid].mapel) {
                var opt = document.createElement('option');
                opt.value = mid; opt.textContent = pohon[jid].mapel[mid].nama;
                sel.appendChild(opt);
            }
        }
        perbaruiPilihanKelas();
    }
    function perbaruiPilihanKelas() {
        var jid = document.getElementById('jenjang_pilih').value;
        var mid = document.getElementById('mapel_pilih').value;
        var sel = document.getElementById('kelas_id');
        sel.innerHTML = '';
        if (pohon[jid] && pohon[jid].mapel[mid]) {
            pohon[jid].mapel[mid].kelas.forEach(function (k) {
                var opt = document.createElement('option');
                opt.value = k.id; opt.textContent = k.nama;
                sel.appendChild(opt);
            });
        }
    }
    isiJenjang();
    perbaruiPilihanMapel();
    </script>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
