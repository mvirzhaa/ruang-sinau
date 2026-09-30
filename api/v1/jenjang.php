<?php
require_once __DIR__ . '/_bootstrap.php';

wajib_metode('GET');
$pdo = db();

$daftar = $pdo->query('SELECT id, nama, slug, deskripsi, urutan FROM jenjang ORDER BY urutan, nama')->fetchAll();

json_ok(['jenjang' => $daftar]);
