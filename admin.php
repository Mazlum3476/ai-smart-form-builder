<?php
// --------------------------------------------------------------------------
// 1. VERİTABANI BAĞLANTISI VE İSTATİSTİKLER
// --------------------------------------------------------------------------
$host = '127.0.0.1';
$db   = 'bitirme_projesi';
$user = 'root';
$pass = '';
$port = "3307";

$total_submissions = 0;
$last_update = "-";
$pdo = null;
$error_message = "";

// GRAFİK İÇİN DEĞİŞKENLER
$counts = ['siparis' => 0, 'sicak' => 0, 'normal' => 0, 'soguk' => 0, 'yok' => 0];
$dailyLabels = [];
$dailyCounts = [];

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmtCount = $pdo->query("SELECT COUNT(*) FROM form_submissions");
    $total_submissions = $stmtCount->fetchColumn();

    $stmtLast = $pdo->query("SELECT created_at FROM form_submissions ORDER BY created_at DESC LIMIT 1");
    $last_date = $stmtLast->fetchColumn();
    if($last_date) {
        $last_update = date("d.m.Y H:i", strtotime($last_date));
    }

    // 🔥 PASTA GRAFİĞİ İÇİN VERİ TOPLAMA 🔥
    $stmtScores = $pdo->query("SELECT ai_score FROM form_submissions");
    while($s = $stmtScores->fetchColumn()) {
        if ($s === null) $counts['yok']++;
        elseif ($s == -1) $counts['siparis']++;
        elseif ($s >= 80) $counts['sicak']++;
        elseif ($s >= 50) $counts['normal']++;
        else $counts['soguk']++;
    }

    // 🔥 ÇUBUK GRAFİK İÇİN GÜNLÜK VERİ TOPLAMA (Son 7 Gün) 🔥
    $stmtDaily = $pdo->query("SELECT DATE(created_at) as d, COUNT(*) as c FROM form_submissions GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 7");
    $dailyData = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);
    foreach(array_reverse($dailyData) as $row) {
        $dailyLabels[] = date("d.m", strtotime($row['d']));
        $dailyCounts[] = $row['c'];
    }

} catch (PDOException $e) {
    $error_message = "Veritabanı Hatası: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Form - Yönetici Paneli</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <nav class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-blue-600 text-white p-2 rounded-lg shadow-lg shadow-blue-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3m3-3v3" /></svg>
                </div>
                <span class="text-xl font-bold text-slate-800 tracking-tight">AI Form <span class="text-blue-600">Admin</span></span>
            </div>
            <a href="index.html" class="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white px-5 py-2.5 rounded-full font-medium transition duration-200 shadow-md">
                <span>Form Oluşturucuya Dön</span>
            </a>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8">
        
        <?php if(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm">Kayıt başarıyla silindi.</div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="text-slate-600 font-bold mb-4 flex items-center gap-2">🎯 Müşteri Potansiyel Dağılımı</h3>
                <div class="relative h-64 w-full flex justify-center">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                <h3 class="text-slate-600 font-bold mb-4 flex items-center gap-2">📈 Son Günlerdeki Başvurular</h3>
                <div class="relative h-64 w-full">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 mb-6 items-center">
            <span class="text-sm font-bold text-slate-500 uppercase tracking-wide mr-2">Filtrele:</span>
            <button onclick="filterTable('all')" class="filter-btn active bg-slate-800 text-white px-4 py-2 rounded-full text-sm font-medium transition hover:shadow-md">Tümü</button>
            <button onclick="filterTable('sicak')" class="filter-btn bg-white border border-green-200 text-green-700 hover:bg-green-50 px-4 py-2 rounded-full text-sm font-medium transition shadow-sm">🔥 Sıcak (80+)</button>
            <button onclick="filterTable('siparis')" class="filter-btn bg-white border border-purple-200 text-purple-700 hover:bg-purple-50 px-4 py-2 rounded-full text-sm font-medium transition shadow-sm">📦 Siparişler</button>
            <button onclick="filterTable('normal')" class="filter-btn bg-white border border-yellow-200 text-yellow-700 hover:bg-yellow-50 px-4 py-2 rounded-full text-sm font-medium transition shadow-sm">☀️ Normal (50-79)</button>
            <button onclick="filterTable('soguk')" class="filter-btn bg-white border border-red-200 text-red-600 hover:bg-red-50 px-4 py-2 rounded-full text-sm font-medium transition shadow-sm">❄️ Soğuk (<50)</button>

             <a href="export.php" class="ml-auto bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-full text-sm font-bold shadow-md transition flex items-center gap-2">
               📥 Excel Olarak İndir
             </a>

        </div>


        <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-slate-700 flex items-center gap-2">📄 Gelen Başvurular</h2>
                <span class="bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full"><?php echo $total_submissions; ?> Kayıt</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="dataTable">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                            <th class="px-6 py-4 border-b">ID</th>
                            <th class="px-6 py-4 border-b">Form Türü</th>
                            <th class="px-6 py-4 border-b w-1/3">Cevaplar</th>
                            <th class="px-6 py-4 border-b">Tarih</th>
                            <th class="px-6 py-4 border-b w-1/4">Yapay Zeka 🤖</th>
                            <th class="px-6 py-4 border-b text-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        if ($pdo) {
                            $stmt = $pdo->query("SELECT * FROM form_submissions ORDER BY created_at DESC");

                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $data = json_decode($row['submission_data'], true);
                                $detailsHtml = "<div class='flex flex-wrap gap-2'>";
                                
                                if ($data) {
                                    foreach ($data as $key => $value) {
                                        if ($key != "form_title") {
                                            $niceKey = ucwords(str_replace("_", " ", $key));
                                            $detailsHtml .= "<div class='bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm'><span class='block text-xs font-bold text-slate-400 uppercase'>$niceKey</span><span class='font-medium text-slate-800'>$value</span></div>";
                                        }
                                    }
                                }
                                $detailsHtml .= "</div>";

                                $aiScore = $row['ai_score'];
                                $aiComment = $row['ai_comment'];
                                $badgeClass = ""; $icon = ""; $scoreText = ""; $category = "yok";
                                
                                if ($aiScore !== null) {
                                    if ($aiScore == -1) {
                                        $category = "siparis"; $badgeClass = "bg-purple-100 text-purple-800 border-purple-200"; $icon = "📦 Sipariş";
                                    } elseif ($aiScore >= 80) {
                                        $category = "sicak"; $badgeClass = "bg-green-100 text-green-800 border-green-200"; $icon = "🔥 Yüksek"; $scoreText = " (%$aiScore)";
                                    } elseif ($aiScore >= 50) {
                                        $category = "normal"; $badgeClass = "bg-yellow-100 text-yellow-800 border-yellow-200"; $icon = "☀️ Normal"; $scoreText = " (%$aiScore)";
                                    } else {
                                        $category = "soguk"; $badgeClass = "bg-red-50 text-red-600 border-red-100"; $icon = "❄️ Düşük"; $scoreText = " (%$aiScore)";
                                    }

                                    $aiHtml = "<div class='flex flex-col gap-2'><span class='inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold border $badgeClass w-fit'>$icon$scoreText</span><p class='text-xs text-slate-600 italic bg-slate-50 p-2 rounded-lg border border-slate-200'>\"$aiComment\"</p></div>";
                                } else {
                                    $aiHtml = "<span class='text-xs text-slate-400 bg-slate-50 px-2 py-1 rounded border'>Bekliyor</span>";
                                }

                                $datePretty = date("d.m.Y", strtotime($row['created_at']));
                                $timePretty = date("H:i", strtotime($row['created_at']));

                                echo "<tr class='hover:bg-blue-50/30 transition duration-150 data-row' data-category='$category'>";
                                echo "<td class='px-6 py-4 font-mono text-sm text-slate-400'>#{$row['id']}</td>";
                                echo "<td class='px-6 py-4 font-bold text-blue-900'>{$row['form_name']}</td>";
                                echo "<td class='px-6 py-4'>$detailsHtml</td>";
                                echo "<td class='px-6 py-4 text-sm text-slate-500'><div class='font-medium'>$datePretty</div><div class='text-xs'>$timePretty</div></td>";
                                echo "<td class='px-6 py-4'>$aiHtml</td>";
                                
                                // 🔥 SİLME BUTONU GERİ GELDİ 🔥
                                echo "<td class='px-6 py-4 text-center'>
                                        <a href='delete_submission.php?id={$row['id']}' 
                                           onclick='return confirm(\"Bu kaydı silmek istediğine emin misin?\")' 
                                           class='text-slate-300 hover:text-red-600 transition p-2 rounded-full hover:bg-red-50 inline-block' title='Sil'>
                                           <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-6 h-6'>
                                              <path stroke-linecap='round' stroke-linejoin='round' d='M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0' />
                                            </svg>
                                        </a>
                                      </td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // 1. FİLTRELEME MANTIĞI
        function filterTable(category) {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('bg-slate-800', 'text-white', 'shadow-md');
                if(!btn.classList.contains('bg-white')) btn.classList.add('bg-white');
            });
            event.currentTarget.classList.remove('bg-white');
            event.currentTarget.classList.add('bg-slate-800', 'text-white', 'shadow-md');

            document.querySelectorAll('.data-row').forEach(row => {
                if(category === 'all' || row.getAttribute('data-category') === category) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // 2. CHART.JS GRAFİKLERİ
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        const barCtx = document.getElementById('barChart').getContext('2d');

        const pieData = [
            <?php echo $counts['sicak']; ?>, 
            <?php echo $counts['normal']; ?>, 
            <?php echo $counts['soguk']; ?>, 
            <?php echo $counts['siparis']; ?>
        ];
        
        const barLabels = <?php echo json_encode($dailyLabels); ?>;
        const barData = <?php echo json_encode($dailyCounts); ?>;

        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sıcak (80+)', 'Normal (50-79)', 'Soğuk (<50)', 'Siparişler'],
                datasets: [{
                    data: pieData,
                    backgroundColor: ['#22c55e', '#eab308', '#ef4444', '#a855f7'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: barLabels.length > 0 ? barLabels : ['Veri Yok'],
                datasets: [{
                    label: 'Günlük Başvuru Sayısı',
                    data: barData.length > 0 ? barData : [0],
                    backgroundColor: '#3b82f6',
                    borderRadius: 6
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    </script>

</body>
</html>