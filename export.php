<?php
// export.php - Tüm verileri CSV olarak indirir
$host = '127.0.0.1'; $db = 'bitirme_projesi'; $user = 'root'; $pass = ''; $port = "3307";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    
    // Excel'de Türkçe karakter sorunu olmaması için BOM (Byte Order Mark) ekliyoruz
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ai_form_verileri_' . date('Ymd_Hi') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Türkçe karakter desteği
    
    // Sütun Başlıkları
    fputcsv($output, ['ID', 'Form Türü', 'Müşteri Detayları', 'Yapay Zeka Puanı', 'AI Analizi', 'Tarih'], ';');
    
    $stmt = $pdo->query("SELECT * FROM form_submissions ORDER BY created_at DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Form detaylarını düz metne çeviriyoruz
        $data = json_decode($row['submission_data'], true);
        $detailsStr = "";
        if($data) {
            foreach($data as $key => $val) {
                if($key != 'form_title') $detailsStr .= ucwords(str_replace('_', ' ', $key)) . ": " . $val . " | ";
            }
        }
        
        $score = $row['ai_score'] == -1 ? "Sipariş" : $row['ai_score'];
        
        fputcsv($output, [
            $row['id'], 
            $row['form_name'], 
            $row['submission_data'], // İstersen $detailsStr kullanabilirsin
            $score, 
            $row['ai_comment'], 
            $row['created_at']
        ], ';');
    }
    fclose($output);
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage();
}
?>