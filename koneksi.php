<?php
// koneksi.php

$host = '127.0.0.1'; // atau 'localhost'
$dbname = 'citra_rasa';
$user = 'root';
$pass = ''; // Sesuaikan dengan password database, jika ada

try {
    // Buat instance PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);

    // Set mode error PDO ke exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Opsi tambahan untuk memastikan koneksi berjalan baik
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // Tampilkan pesan error jika koneksi gagal
    die("Koneksi ke database gagal: " . $e->getMessage());
}
