</div>
</main>

<footer class="site-footer">
    <div class="wrap">
        <div>
            <strong><?= h(APP_NAME) ?></strong> — dibuat agar akses belajar terbuka untuk siapa saja.
        </div>
        <div class="footer-links">
            <a href="<?= h(url_publik('index.php')) ?>">Beranda</a>
            <a href="<?= h(url_publik('katalog.php')) ?>">Jelajahi</a>
            <a href="<?= h(url_publik('tentang.php')) ?>">Tentang</a>
            <a href="<?= h(url_publik('admin/login.php')) ?>">Masuk Admin</a>
        </div>
    </div>
</footer>

</body>
</html>
