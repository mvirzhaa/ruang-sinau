<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$dataEdit = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM topik WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $dataEdit = $stmt->fetch();
    if (!$dataEdit) {
        set_flash('error', 'Konten tidak ditemukan.');
        redirect('topik.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf_valid();

    $kelasId = (int)($_POST['kelas_id'] ?? 0);
    $tipe = in_array($_POST['tipe'] ?? '', ['latihan', 'materi'], true) ? $_POST['tipe'] : 'latihan';
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $tingkat = in_array($_POST['tingkat'] ?? '', ['mudah', 'sedang', 'sulit'], true) ? $_POST['tingkat'] : null;
    $jumlahSoal = $_POST['jumlah_soal'] !== '' ? (int)$_POST['jumlah_soal'] : null;
    $emoji = trim($_POST['emoji'] ?? '') ?: ($tipe === 'materi' ? '📘' : '📝');
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $kontenMateri = $_POST['konten'] ?? '';

    if ($kelasId <= 0) $errors[] = 'Kelas wajib dipilih.';
    if ($judul === '') $errors[] = 'Judul wajib diisi.';

    $namaFileBaru = null;

    // ---------- Validasi & proses unggah file latihan (html) ----------
    if ($tipe === 'latihan') {
        if (!empty($_FILES['file_latihan']['name'])) {
            $file = $_FILES['file_latihan'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Gagal mengunggah berkas (kode error: ' . $file['error'] . ').';
            } else {
                $ekstensi = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $ukuranMb = $file['size'] / (1024 * 1024);

                if ($ekstensi !== 'html' && $ekstensi !== 'htm') {
                    $errors[] = 'Berkas latihan harus berformat .html.';
                } elseif ($ukuranMb > MAX_UPLOAD_LATIHAN_MB) {
                    $errors[] = 'Ukuran berkas melebihi batas ' . MAX_UPLOAD_LATIHAN_MB . ' MB.';
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    $mimeDiizinkan = ['text/html', 'text/plain', 'application/xhtml+xml'];

                    if (!in_array($mime, $mimeDiizinkan, true)) {
                        $errors[] = 'Jenis berkas tidak dikenali sebagai HTML (terdeteksi: ' . h($mime) . ').';
                    } else {
                        $namaFileBaru = nama_file_aman($file['name']);
                        if (!is_dir(UPLOAD_DIR_QUIZZES)) {
                            @mkdir(UPLOAD_DIR_QUIZZES, 0755, true);
                        }
                        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR_QUIZZES . $namaFileBaru)) {
                            $errors[] = 'Gagal menyimpan berkas ke server. Periksa hak akses folder uploads/quizzes.';
                            $namaFileBaru = null;
                        }
                    }
                }
            }
        } elseif (!$dataEdit || !$dataEdit['file_path']) {
            $errors[] = 'Unggah berkas HTML latihan soal.';
        }
    } else {
        // materi: sanitasi konten rich text sebelum disimpan
        $kontenMateri = sanitasi_html($kontenMateri);
        if (trim(strip_tags($kontenMateri)) === '') {
            $errors[] = 'Isi materi tidak boleh kosong.';
        }
    }

    if (!$errors) {
        $slugDasar = buat_slug($judul);
        $slug = slug_unik('topik', $slugDasar, 'kelas_id', $kelasId, $id ?: null);

        if ($id > 0) {
            // Jika ada file baru & tipe latihan, hapus file lama
            if ($tipe === 'latihan' && $namaFileBaru && $dataEdit['file_path']) {
                $lama = UPLOAD_DIR_QUIZZES . $dataEdit['file_path'];
                if (is_file($lama)) @unlink($lama);
            }
            $filePathAkhir = $tipe === 'latihan' ? ($namaFileBaru ?: $dataEdit['file_path']) : null;

            $stmt = $pdo->prepare(
                'UPDATE topik SET kelas_id=:kelas_id, tipe=:tipe, judul=:judul, slug=:slug, deskripsi=:deskripsi,
                    tingkat=:tingkat, jumlah_soal=:jumlah_soal, file_path=:file_path, konten=:konten,
                    emoji=:emoji, status=:status WHERE id=:id'
            );
            $stmt->execute([
                'kelas_id' => $kelasId, 'tipe' => $tipe, 'judul' => $judul, 'slug' => $slug,
                'deskripsi' => $deskripsi, 'tingkat' => $tipe === 'latihan' ? $tingkat : null,
                'jumlah_soal' => $tipe === 'latihan' ? $jumlahSoal : null,
                'file_path' => $filePathAkhir, 'konten' => $tipe === 'materi' ? $kontenMateri : null,
                'emoji' => $emoji, 'status' => $status, 'id' => $id,
            ]);
            catat_log($admin['id'], 'ubah_topik', $judul);
            set_flash('sukses', '"' . $judul . '" berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO topik (kelas_id, tipe, judul, slug, deskripsi, tingkat, jumlah_soal, file_path, konten, emoji, status, dibuat_oleh)
                 VALUES (:kelas_id, :tipe, :judul, :slug, :deskripsi, :tingkat, :jumlah_soal, :file_path, :konten, :emoji, :status, :dibuat_oleh)'
            );
            $stmt->execute([
                'kelas_id' => $kelasId, 'tipe' => $tipe, 'judul' => $judul, 'slug' => $slug,
                'deskripsi' => $deskripsi, 'tingkat' => $tipe === 'latihan' ? $tingkat : null,
                'jumlah_soal' => $tipe === 'latihan' ? $jumlahSoal : null,
                'file_path' => $tipe === 'latihan' ? $namaFileBaru : null,
                'konten' => $tipe === 'materi' ? $kontenMateri : null,
                'emoji' => $emoji, 'status' => $status, 'dibuat_oleh' => $admin['id'],
            ]);
            catat_log($admin['id'], 'tambah_topik', $judul);
            set_flash('sukses', '"' . $judul . '" berhasil ditambahkan.');
        }
        redirect('topik.php');
    }

    // Jika ada error tapi sudah terlanjur upload file baru, bersihkan agar tidak menumpuk sampah
    if ($namaFileBaru && is_file(UPLOAD_DIR_QUIZZES . $namaFileBaru)) {
        @unlink(UPLOAD_DIR_QUIZZES . $namaFileBaru);
    }

    // simpan input pengguna untuk ditampilkan ulang di form
    $dataEdit = array_merge($dataEdit ?? [], [
        'kelas_id' => $kelasId, 'tipe' => $tipe, 'judul' => $judul, 'deskripsi' => $deskripsi,
        'tingkat' => $tingkat, 'jumlah_soal' => $jumlahSoal, 'emoji' => $emoji, 'status' => $status,
        'konten' => $kontenMateri,
    ]);
}

// ---------- Data untuk pilihan kelas bertingkat (jenjang > mapel > kelas) ----------
$pohon = $pdo->query(
    "SELECT j.id AS jenjang_id, j.nama AS jenjang_nama,
            m.id AS mapel_id, m.nama AS mapel_nama,
            k.id AS kelas_id, k.nama AS kelas_nama
     FROM jenjang j
     JOIN mapel m ON m.jenjang_id = j.id
     JOIN kelas k ON k.mapel_id = m.id
     ORDER BY j.urutan, m.urutan, k.urutan"
)->fetchAll();

$judul_admin = $dataEdit ? 'Ubah Konten' : 'Tambah Konten Baru';
$menuAktif = 'topik';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if ($errors): ?>
    <div class="flash error">
        <?= h(implode(' ', $errors)) ?>
    </div>
<?php endif; ?>

<?php if (!$pohon): ?>
    <div class="panel">
        <p class="kosong-admin">Anda perlu membuat setidaknya satu Jenjang, Mata Pelajaran, dan Kelas
        sebelum bisa menambahkan latihan atau materi.
        <br><a href="jenjang.php" class="btn btn-primary" style="margin-top:12px;">Mulai dari Jenjang</a></p>
    </div>
<?php else: ?>

<form method="post" enctype="multipart/form-data" class="panel form-grid" id="form-topik">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)($dataEdit['id'] ?? 0) ?>">

    <div class="form-row">
        <label>Jenis Konten</label>
        <div style="display:flex; gap:16px;">
            <label style="display:flex; align-items:center; gap:6px; font-weight:600;">
                <input type="radio" name="tipe" value="latihan" onchange="gantiTipe('latihan')"
                    <?= (($dataEdit['tipe'] ?? 'latihan') === 'latihan') ? 'checked' : '' ?>> 📝 Latihan Soal (unggah HTML)
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-weight:600;">
                <input type="radio" name="tipe" value="materi" onchange="gantiTipe('materi')"
                    <?= (($dataEdit['tipe'] ?? '') === 'materi') ? 'checked' : '' ?>> 📘 Materi (tulis langsung)
            </label>
        </div>
    </div>

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
        <label for="kelas_id">Kelas</label>
        <select id="kelas_id" name="kelas_id" required></select>
    </div>

    <div class="form-row">
        <label for="judul">Judul</label>
        <input type="text" id="judul" name="judul" required placeholder="contoh: Latihan Pecahan & Desimal"
               value="<?= h($dataEdit['judul'] ?? '') ?>">
    </div>

    <div class="form-row">
        <label for="deskripsi">Deskripsi singkat (opsional)</label>
        <textarea id="deskripsi" name="deskripsi" style="min-height:70px;" placeholder="Ringkasan singkat yang tampil di halaman katalog"><?= h($dataEdit['deskripsi'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
        <label for="emoji">Ikon (emoji)</label>
        <input type="text" id="emoji" name="emoji" maxlength="10" value="<?= h($dataEdit['emoji'] ?? '') ?>">
    </div>

    <!-- ===== Blok khusus LATIHAN ===== -->
    <div id="blok-latihan" class="form-grid">
        <div class="form-2col">
            <div class="form-row">
                <label for="tingkat">Tingkat kesulitan</label>
                <select id="tingkat" name="tingkat">
                    <option value="">— tidak ditentukan —</option>
                    <?php foreach (['mudah' => 'Mudah', 'sedang' => 'Sedang', 'sulit' => 'Sulit'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($dataEdit['tingkat'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="jumlah_soal">Jumlah soal</label>
                <input type="number" id="jumlah_soal" name="jumlah_soal" min="0" value="<?= h((string)($dataEdit['jumlah_soal'] ?? '')) ?>">
            </div>
        </div>
        <div class="form-row">
            <label for="file_latihan">Berkas HTML Latihan Soal</label>
            <input type="file" id="file_latihan" name="file_latihan" accept=".html,.htm">
            <span class="bantuan">
                Unggah berkas HTML latihan soal yang sudah jadi (maksimal <?= MAX_UPLOAD_LATIHAN_MB ?> MB).
                <?php if (!empty($dataEdit['file_path'])): ?>
                    Saat ini terpasang: <strong><?= h($dataEdit['file_path']) ?></strong> — biarkan kosong untuk mempertahankan berkas ini.
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- ===== Blok khusus MATERI ===== -->
    <div id="blok-materi" class="form-grid">
        <div class="form-row">
            <label>Isi Materi</label>
            <div id="toolbar-editor" style="display:flex; gap:4px; flex-wrap:wrap; padding:8px; border:1.5px solid var(--line); border-bottom:none; border-radius:8px 8px 0 0; background:var(--bg);">
                <button type="button" class="btn btn-outline btn-sm" onclick="fmt('bold')"><b>B</b></button>
                <button type="button" class="btn btn-outline btn-sm" onclick="fmt('italic')"><i>I</i></button>
                <button type="button" class="btn btn-outline btn-sm" onclick="fmt('insertUnorderedList')">• List</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="fmt('insertOrderedList')">1. List</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="fmt('formatBlock', 'H2')">H2</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="fmt('formatBlock', 'H3')">H3</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="fmt('formatBlock', 'P')">P</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="buatTautan()">🔗 Tautan</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="sisipkanGambar()">🖼 Gambar</button>
            </div>
            <div id="editor" contenteditable="true"
                 style="min-height:280px; border:1.5px solid var(--line); border-radius:0 0 8px 8px; padding:16px; background:#fff;"><?= $dataEdit['konten'] ?? '' ?></div>
            <textarea name="konten" id="konten" style="display:none;"></textarea>
            <span class="bantuan">Gunakan alat di atas untuk memformat teks. Gambar hanya bisa disisipkan lewat tombol Gambar.</span>
        </div>
    </div>

    <div class="form-row">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="draft" <?= ($dataEdit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draf (belum tampil di situs)</option>
            <option value="published" <?= ($dataEdit['status'] ?? '') === 'published' ? 'selected' : '' ?>>Terbit (tampil di situs)</option>
        </select>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="btn-simpan"><?= $dataEdit ? 'Simpan Perubahan' : 'Simpan Konten' ?></button>
        <a href="topik.php" class="btn btn-outline">Batal</a>
    </div>
</form>

<script>
var pohonData = <?= json_encode($pohon, JSON_UNESCAPED_UNICODE) ?>;
var kelasTerpilihAwal = <?= (int)($dataEdit['kelas_id'] ?? 0) ?>;

function bangunPohon() {
    var jenjangMap = {};
    pohonData.forEach(function (row) {
        if (!jenjangMap[row.jenjang_id]) jenjangMap[row.jenjang_id] = { nama: row.jenjang_nama, mapel: {} };
        if (!jenjangMap[row.jenjang_id].mapel[row.mapel_id]) jenjangMap[row.jenjang_id].mapel[row.mapel_id] = { nama: row.mapel_nama, kelas: [] };
        jenjangMap[row.jenjang_id].mapel[row.mapel_id].kelas.push({ id: row.kelas_id, nama: row.kelas_nama });
    });
    return jenjangMap;
}
var pohon = bangunPohon();

function cariLokasiKelas(kelasId) {
    for (var jid in pohon) {
        for (var mid in pohon[jid].mapel) {
            var found = pohon[jid].mapel[mid].kelas.find(function (k) { return k.id == kelasId; });
            if (found) return { jenjangId: jid, mapelId: mid };
        }
    }
    return null;
}

function isiJenjang(jenjangTerpilih) {
    var sel = document.getElementById('jenjang_pilih');
    sel.innerHTML = '';
    for (var jid in pohon) {
        var opt = document.createElement('option');
        opt.value = jid; opt.textContent = pohon[jid].nama;
        if (jenjangTerpilih && jid == jenjangTerpilih) opt.selected = true;
        sel.appendChild(opt);
    }
}

function perbaruiPilihanMapel(mapelTerpilih, kelasTerpilih) {
    var jid = document.getElementById('jenjang_pilih').value;
    var sel = document.getElementById('mapel_pilih');
    sel.innerHTML = '';
    if (pohon[jid]) {
        for (var mid in pohon[jid].mapel) {
            var opt = document.createElement('option');
            opt.value = mid; opt.textContent = pohon[jid].mapel[mid].nama;
            if (mapelTerpilih && mid == mapelTerpilih) opt.selected = true;
            sel.appendChild(opt);
        }
    }
    perbaruiPilihanKelas(kelasTerpilih);
}

function perbaruiPilihanKelas(kelasTerpilih) {
    var jid = document.getElementById('jenjang_pilih').value;
    var mid = document.getElementById('mapel_pilih').value;
    var sel = document.getElementById('kelas_id');
    sel.innerHTML = '';
    if (pohon[jid] && pohon[jid].mapel[mid]) {
        pohon[jid].mapel[mid].kelas.forEach(function (k) {
            var opt = document.createElement('option');
            opt.value = k.id; opt.textContent = k.nama;
            if (kelasTerpilih && k.id == kelasTerpilih) opt.selected = true;
            sel.appendChild(opt);
        });
    }
}

// Inisialisasi berdasarkan kelas yang sedang diedit (jika ada)
var lokasiAwal = kelasTerpilihAwal ? cariLokasiKelas(kelasTerpilihAwal) : null;
isiJenjang(lokasiAwal ? lokasiAwal.jenjangId : null);
perbaruiPilihanMapel(lokasiAwal ? lokasiAwal.mapelId : null, kelasTerpilihAwal || null);

// ---------- Toggle blok latihan/materi ----------
function gantiTipe(tipe) {
    document.getElementById('blok-latihan').style.display = tipe === 'latihan' ? 'grid' : 'none';
    document.getElementById('blok-materi').style.display = tipe === 'materi' ? 'grid' : 'none';
}
var tipeAwal = document.querySelector('input[name=tipe]:checked');
gantiTipe(tipeAwal ? tipeAwal.value : 'latihan');

// ---------- Editor materi sederhana ----------
function fmt(perintah, nilai) {
    document.getElementById('editor').focus();
    document.execCommand(perintah, false, nilai || null);
}
function buatTautan() {
    var url = prompt('Masukkan URL tautan:');
    if (url) fmt('createLink', url);
}
function sisipkanGambar() {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/webp,image/gif';
    input.onchange = function () {
        if (!input.files.length) return;
        var fd = new FormData();
        fd.append('gambar', input.files[0]);
        fd.append('csrf_token', document.querySelector('input[name=csrf_token]').value);
        fetch('unggah_gambar.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.sukses) {
                    fmt('insertImage', data.url);
                } else {
                    alert(data.pesan || 'Gagal mengunggah gambar.');
                }
            })
            .catch(function () { alert('Gagal mengunggah gambar.'); });
    };
    input.click();
}

document.getElementById('form-topik').addEventListener('submit', function () {
    document.getElementById('konten').value = document.getElementById('editor').innerHTML;
});
</script>

<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
