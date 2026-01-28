<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>Painel Surfscale Real-Time</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .card-green { background-color: #d4edda; }
        .card-red { background-color: #f8d7da; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: center; }
    </style>
</head>
<body>
    <h1>Monitoramento de Escala (Hoje)</h1>
    
    <table>
        <thead>
            <tr>
                <th>Conjunto</th>
                <th>Gasto (FB)</th>
                <th>Vendas (Webhook)</th>
                <th>Receita (Real)</th>
                <th>CPA (Real)</th>
                <th>ROAS (Real)</th>
                <th>Ação Sugerida</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $dados = json_decode(file_get_contents('db/facebook_cache.json'), true) ?? [];
            
            foreach ($dados as $id => $d) {
                $classe = '';
                if ($d['roas'] >= 2) $classe = 'card-green';
                if ($d['spend'] > 50 && $d['vendas'] == 0) $classe = 'card-red';
                
                echo "<tr class='$classe'>";
                echo "<td>{$d['name']} <br><small>($id)</small></td>";
                echo "<td>R$ " . number_format($d['spend'], 2, ',', '.') . "</td>";
                echo "<td>{$d['vendas']}</td>";
                echo "<td>R$ " . number_format($d['receita'], 2, ',', '.') . "</td>";
                echo "<td>R$ " . number_format($d['cpa'], 2, ',', '.') . "</td>";
                echo "<td>" . number_format($d['roas'], 2) . "x</td>";
                echo "<td>" . ($d['roas'] >= 2 ? '🔥 ESCALAR' : '-') . "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</body>
</html>
