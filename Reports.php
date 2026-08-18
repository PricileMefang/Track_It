<?php
session_start();
require_once "config.php";
$user_id = $_SESSION["user_id"] ?? 1;
$username = $_SESSION["user_name"] ?? "User";

$cur_m = (int)date('m'); $cur_y = (int)date('Y');

// ── 12-month trend data (January to December of the current year) ──
$trend_labels = []; $trend_income = []; $trend_expense = [];
$y = (int)date('Y');
for ($m = 1; $m <= 12; $m++) {
    $trend_labels[] = date('M Y', mktime(0, 0, 0, $m, 1, $y));

    $stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM income WHERE user_id=? AND MONTH(income_date)=? AND YEAR(income_date)=?");
    $stmt->bind_param("iii",$user_id,$m,$y); $stmt->execute(); $stmt->bind_result($inc); $stmt->fetch(); $stmt->close();
    $trend_income[] = (float)$inc;

    $stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
    $stmt->bind_param("iii",$user_id,$m,$y); $stmt->execute(); $stmt->bind_result($exp); $stmt->fetch(); $stmt->close();
    $trend_expense[] = (float)$exp;
}

// ── Month-over-month by category ──────────────────────────────
$prev_m = $cur_m == 1 ? 12 : $cur_m - 1;
$prev_y = $cur_m == 1 ? $cur_y - 1 : $cur_y;

$stmt = $conn->prepare("SELECT ec.category_name, ec.color_code,
    IFNULL(SUM(CASE WHEN MONTH(e.expense_date)=? AND YEAR(e.expense_date)=? THEN e.amount ELSE 0 END),0) as cur_month,
    IFNULL(SUM(CASE WHEN MONTH(e.expense_date)=? AND YEAR(e.expense_date)=? THEN e.amount ELSE 0 END),0) as prev_month
    FROM expense_categories ec
    LEFT JOIN expenses e ON e.category_id=ec.id AND e.user_id=?
    GROUP BY ec.id ORDER BY cur_month DESC LIMIT 10");
$stmt->bind_param("iiiii",$cur_m,$cur_y,$prev_m,$prev_y,$user_id);
$stmt->execute(); $result=$stmt->get_result();
$mom_data = []; while($row=$result->fetch_assoc()) $mom_data[]=$row;
$stmt->close();

// ── Income vs Expenses this month ──────────────────────────────
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM income WHERE user_id=? AND MONTH(income_date)=? AND YEAR(income_date)=?");
$stmt->bind_param("iii",$user_id,$cur_m,$cur_y); $stmt->execute(); $stmt->bind_result($cur_income); $stmt->fetch(); $stmt->close();
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
$stmt->bind_param("iii",$user_id,$cur_m,$cur_y); $stmt->execute(); $stmt->bind_result($cur_expense); $stmt->fetch(); $stmt->close();

// ── Category breakdown this month ────────────────────────────
$stmt = $conn->prepare("SELECT ec.category_name, ec.color_code, IFNULL(SUM(e.amount),0) as total FROM expense_categories ec INNER JOIN expenses e ON e.category_id=ec.id AND e.user_id=? AND MONTH(e.expense_date)=? AND YEAR(e.expense_date)=? GROUP BY ec.id ORDER BY total DESC");
$stmt->bind_param("iii",$user_id,$cur_m,$cur_y); $stmt->execute(); $result=$stmt->get_result();
$breakdown_labels=[]; $breakdown_data=[]; $breakdown_colors=[];
while($row=$result->fetch_assoc()){
    $breakdown_labels[]=$row['category_name']; $breakdown_data[]=(float)$row['total']; $breakdown_colors[]=$row['color_code']??'#4F46E5';
}
$stmt->close();

$active_page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports – TrackIt</title>
    <meta name="description" content="TrackIt financial reports – spending trends, category breakdowns, and month-over-month comparisons.">
    <link rel="icon" type="image/png" href="TrackIt Logo copy.png">
    <link rel="stylesheet" href="trackit.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <section id="top-bar">
            <div class="top-bar-content">
                <h1>Reports &amp; Insights</h1>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <a href="Settings.php?export_csv=expenses" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
                    <button class="btn btn-ghost btn-sm" onclick="window.print()">🖨️ Print</button>
                    <div class="top-bar-user" style="margin-left:0.5rem;"><div class="user-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div><span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
                    </div>
                </div>
            </div>
        </section>

        <div class="page-body">

            <!-- Income vs Expenses summary this month -->
            <div class="stats-grid" style="padding:0 0 1rem;">
                <div class="stat-card sc-income">
                    <div class="stat-card-label">Income This Month</div>
                    <div class="stat-card-value">FCFA <?php echo number_format($cur_income,0,',','.'); ?></div>
                    <div class="stat-icon si-income">↑</div>
                </div>
                <div class="stat-card sc-expense">
                    <div class="stat-card-label">Expenses This Month</div>
                    <div class="stat-card-value">FCFA <?php echo number_format($cur_expense,0,',','.'); ?></div>
                    <div class="stat-icon si-expense">↓</div>
                </div>
                <div class="stat-card <?php echo ($cur_income-$cur_expense)>=0?'sc-income':'sc-expense'; ?>">
                    <div class="stat-card-label">Net This Month</div>
                    <div class="stat-card-value">FCFA <?php echo number_format(abs($cur_income-$cur_expense),0,',','.'); ?></div>
                    <div class="stat-card-sub"><?php echo ($cur_income-$cur_expense)>=0?'▲ Surplus':'▼ Deficit'; ?></div>
                    <div class="stat-icon <?php echo ($cur_income-$cur_expense)>=0?'si-income':'si-expense'; ?>">≡</div>
                </div>
            </div>

            <!-- Charts row 1 -->
            <div class="charts-grid" style="margin-bottom:1.25rem;">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span class="chart-card-title">📈 12-Month Spending Trend</span>
                        <span class="chart-badge">Income vs Expenses</span>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span class="chart-card-title">🥧 Category Breakdown</span>
                        <span class="chart-badge"><?php echo date('M Y'); ?></span>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="breakdownChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Month-over-month comparison table -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">📊 Month-over-Month by Category</span>
                    <span style="font-size:0.8rem;color:var(--text-muted);"><?php echo date('M Y', mktime(0,0,0,$prev_m,1,$prev_y)); ?> vs <?php echo date('M Y'); ?></span>
                </div>
                <div class="table-wrap">
                    <table class="trackit-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Last Month</th>
                                <th>This Month</th>
                                <th>Change</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(empty($mom_data)): ?>
                            <tr class="empty-row"><td colspan="4">No data available yet.</td></tr>
                        <?php else: ?>
                            <?php foreach($mom_data as $row):
                                $diff = $row['cur_month'] - $row['prev_month'];
                                $pct  = $row['prev_month'] > 0 ? ($diff/$row['prev_month'])*100 : ($row['cur_month'] > 0 ? 100 : 0);
                                if($row['cur_month'] == 0 && $row['prev_month'] == 0) continue;
                            ?>
                            <tr>
                                <td>
                                    <span style="display:inline-flex;align-items:center;gap:0.4rem;">
                                        <span style="width:10px;height:10px;border-radius:50%;background:<?php echo htmlspecialchars($row['color_code']??'#ccc'); ?>;display:inline-block;flex-shrink:0;"></span>
                                        <?php echo htmlspecialchars($row['category_name']); ?>
                                    </span>
                                </td>
                                <td>FCFA <?php echo number_format($row['prev_month'],0,',','.'); ?></td>
                                <td>FCFA <?php echo number_format($row['cur_month'],0,',','.'); ?></td>
                                <td>
                                    <?php if($diff > 0): ?>
                                    <span style="color:var(--brand-danger);font-weight:600;">▲ +<?php echo round(abs($pct)); ?>%</span>
                                    <?php elseif($diff < 0): ?>
                                    <span style="color:var(--brand-success);font-weight:600;">▼ −<?php echo round(abs($pct)); ?>%</span>
                                    <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /.page-body -->
    </div>
</div>

<script>
const trendLabels  = <?php echo json_encode($trend_labels); ?>;
const trendIncome  = <?php echo json_encode($trend_income); ?>;
const trendExpense = <?php echo json_encode($trend_expense); ?>;
const bkLabels = <?php echo json_encode($breakdown_labels); ?>;
const bkData   = <?php echo json_encode($breakdown_data); ?>;
const bkColors = <?php echo json_encode($breakdown_colors); ?>;

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [
            { label:'Income',   data:trendIncome,  fill:true, backgroundColor:'rgba(16,185,129,0.1)', borderColor:'#10B981', tension:0.4, pointRadius:4 },
            { label:'Expenses', data:trendExpense, fill:true, backgroundColor:'rgba(239,68,68,0.08)',  borderColor:'#EF4444', tension:0.4, pointRadius:4 }
        ]
    },
    options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'top', labels:{ usePointStyle:true, boxWidth:8 } } },
        scales:{
            y:{ beginAtZero:true, grid:{ color:'#F3F4F8' }, ticks:{ callback: v=>'FCFA '+v.toLocaleString() } },
            x:{ grid:{ display:false } }
        }
    }
});

if(bkData.length > 0){
    new Chart(document.getElementById('breakdownChart'),{
        type:'doughnut',
        data:{ labels:bkLabels, datasets:[{ data:bkData, backgroundColor:bkColors, borderWidth:2, borderColor:'#fff' }] },
        options:{
            responsive:true, maintainAspectRatio:false, cutout:'60%',
            plugins:{
                legend:{ position:'bottom', labels:{ usePointStyle:true, boxWidth:8, font:{size:11} } },
                tooltip:{ callbacks:{ label: ctx => ctx.label+': FCFA '+ctx.parsed.toLocaleString() } }
            }
        }
    });
}
</script>
</body>
</html>