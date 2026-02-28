<?php
// delete_submission.php

// Veritabanı Ayarları (Senin Sistemine Göre)
$host = '127.0.0.1';
$db   = 'bitirme_projesi';
$user = 'root';
$pass = '';
$port = "3307"; // <--- ÖNEMLİ: Senin port numaran

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Silme Sorgusu
        $sql = "DELETE FROM form_submissions WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        // Başarılıysa admin paneline geri dön ve mesaj ver
        header("Location: admin.php?msg=deleted");
        exit();

    } catch (PDOException $e) {
        echo "Hata: " . $e->getMessage();
    }
} else {
    echo "ID Bulunamadı!";
}
?>