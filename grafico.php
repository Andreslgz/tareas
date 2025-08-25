<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

// Endpoint para imágenes agrupadas por mes (para el gráfico)
if (isset($_GET['api']) && $_GET['api'] === 'imagenes') {
    require 'db.php';
    $sql = "SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, imagen FROM tareas WHERE imagen IS NOT NULL AND imagen != '' AND estado = 'SI'";
    $stmt = $pdo->query($sql);
    $imagenes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mes = $row['mes'];
        if (!isset($imagenes[$mes])) $imagenes[$mes] = [];
        $imagenes[$mes][] = $row['imagen'];
    }
    header('Content-Type: application/json');
    echo json_encode($imagenes);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gráfico de Producción</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-green-50 min-h-screen">
    <header class="bg-white shadow sticky top-0 z-10">
        <div class="max-w-screen-lg mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-xl md:text-2xl font-bold text-green-700 flex items-center gap-2"><i class="fas fa-chart-bar"></i> Gráfico de Producción</h1>
            <a href="index.php" class="text-green-600 font-semibold hover:underline flex items-center space-x-1">
                <i class="fas fa-arrow-left"></i>
                <span>Volver</span>
            </a>
        </div>
    </header>
    <main class="max-w-screen-lg mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-lg font-bold text-green-700 mb-4 flex items-center gap-2"><i class="fas fa-chart-bar"></i> Producción mensual</h2>
            <canvas id="graficoProduccion" height="80"></canvas>
        </div>


    </main>
    <script>
        // Solo gráfico de barras, sin galería ni imágenes
        fetch('index.php?api=produccion')
            .then(res => res.json())
            .then(datos => {
                const labels = datos.map(d => d.mes);
                const values = datos.map(d => d.total);
                const ctx = document.getElementById('graficoProduccion').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Tareas terminadas',
                            data: values,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: 'rgba(5, 150, 105, 1)',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            title: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            });
    </script>
</body>
</html>
