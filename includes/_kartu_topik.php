<?php
/**
 * Partial kartu topik. Membutuhkan variabel $t (baris topik + join
 * kelas/mapel/jenjang) sudah tersedia di scope pemanggil.
 */
$urlTopik = url_publik('topik.php?slug=' . urlencode($t['slug']) . '&kelas=' . urlencode($t['kelas_slug']));
?>
<a href="<?= h($urlTopik) ?>" class="kartu">
    <span class="emoji"><?= h($t['emoji'] ?: ($t['tipe'] === 'materi' ? '📘' : '📝')) ?></span>
    <h3><?= h($t['judul']) ?></h3>
    <p class="ket"><?= h($t['jenjang_nama']) ?> · <?= h($t['mapel_nama']) ?> · <?= h($t['kelas_nama']) ?></p>
    <div class="meta">
        <?php if ($t['tipe'] === 'materi'): ?>
            <span class="badge materi">📘 Materi</span>
        <?php else: ?>
            <span class="badge">📝 <?= (int)($t['jumlah_soal'] ?? 0) ?: '—' ?> soal</span>
            <?php if (!empty($t['tingkat'])): ?>
                <span class="badge <?= h($t['tingkat']) ?>"><?= h(ucfirst($t['tingkat'])) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</a>
