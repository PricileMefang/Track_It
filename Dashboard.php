<?php
session_start();
require_once "config.php";
require_once "process_recurring.php"; // Auto-log recurring expenses on every visit

$user_id = $_SESSION["user_id"] ?? 1;
$username = $_SESSION["user_name"] ?? "Guest";

// ── Total Income ──────────────────────────────────────────────
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM income WHERE user_id=?");
$stmt->bind_param("i",$user_id); $stmt->execute(); $stmt->bind_result($total_income); $stmt->fetch(); $stmt->close();

// ── Total Expenses ────────────────────────────────────────────
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=?");
$stmt->bind_param("i",$user_id); $stmt->execute(); $stmt->bind_result($total_expense); $stmt->fetch(); $stmt->close();

$net_balance = $total_income - $total_expense;

// ── Spending Prediction (Pace) ─────────────────────────────────
$cur_m = (int)date('m'); $cur_y = (int)date('Y'); $days_elapsed = (int)date('j'); $days_in_month = (int)date('t');
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
$stmt->bind_param("iii",$user_id,$cur_m,$cur_y); $stmt->execute(); $stmt->bind_result($this_month_spent); $stmt->fetch(); $stmt->close();

$daily_rate = $days_elapsed > 0 ? $this_month_spent / $days_elapsed : 0;
$projected_spend = round($daily_rate * $days_in_month);

$prev_m = $cur_m == 1 ? 12 : $cur_m-1; $prev_y = $cur_m == 1 ? $cur_y-1 : $cur_y;
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
$stmt->bind_param("iii",$user_id,$prev_m,$prev_y); $stmt->execute(); $stmt->bind_result($last_month_spent); $stmt->fetch(); $stmt->close();

// ── Monthly chart data (12 months this year) ──────────────────
$cur_year = date("Y");
$monthly_income  = array_fill(1, 12, 0);
$monthly_expense = array_fill(1, 12, 0);

$stmt = $conn->prepare("SELECT MONTH(income_date) as m, SUM(amount) as total FROM income WHERE user_id=? AND YEAR(income_date)=? GROUP BY MONTH(income_date)");
$stmt->bind_param("ii",$user_id,$cur_year); $stmt->execute(); $result=$stmt->get_result();
while($row=$result->fetch_assoc()) $monthly_income[$row['m']]=(float)$row['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT MONTH(expense_date) as m, SUM(amount) as total FROM expenses WHERE user_id=? AND YEAR(expense_date)=? GROUP BY MONTH(expense_date)");
$stmt->bind_param("ii",$user_id,$cur_year); $stmt->execute(); $result=$stmt->get_result();
while($row=$result->fetch_assoc()) $monthly_expense[$row['m']]=(float)$row['total'];
$stmt->close();

// ── Category breakdown this month ─────────────────────────────
$stmt = $conn->prepare("SELECT ec.category_name, ec.color_code, IFNULL(SUM(e.amount),0) as total FROM expense_categories ec INNER JOIN expenses e ON e.category_id=ec.id AND e.user_id=? AND MONTH(e.expense_date)=? AND YEAR(e.expense_date)=? GROUP BY ec.id ORDER BY total DESC LIMIT 6");
$stmt->bind_param("iii",$user_id,$cur_m,$cur_y); $stmt->execute(); $result=$stmt->get_result();
$cat_labels=[]; $cat_data=[]; $cat_colors=[];
while($row=$result->fetch_assoc()){
    $cat_labels[]=$row['category_name'];
    $cat_data[]=(float)$row['total'];
    $cat_colors[]=$row['color_code'] ?: '#4F46E5';
}
$stmt->close();

// ── Anomaly alerts ─────────────────────────────────────────────
$stmt = $conn->prepare("SELECT e.description, e.amount, e.expense_date, ec.category_name FROM expenses e JOIN expense_categories ec ON e.category_id=ec.id WHERE e.user_id=? AND e.is_flagged_anomaly=1 ORDER BY e.expense_date DESC LIMIT 5");
$stmt->bind_param("i",$user_id); $stmt->execute(); $result=$stmt->get_result();
$anomalies=[]; while($row=$result->fetch_assoc()) $anomalies[]=$row;
$stmt->close();

// ── Overdue invoices ───────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) FROM invoices WHERE user_id=? AND status='Overdue'");
$stmt->bind_param("i",$user_id); $stmt->execute(); $stmt->bind_result($overdue_count); $stmt->fetch(); $stmt->close();

// ── JSON for JS ───────────────────────────────────────────────
$income_data_json  = json_encode(array_values($monthly_income));
$expense_data_json = json_encode(array_values($monthly_expense));
$cat_labels_json   = json_encode($cat_labels);
$cat_data_json     = json_encode($cat_data);
$cat_colors_json   = json_encode($cat_colors);
$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – TrackIt</title>
    <meta name="description" content="TrackIt personal finance dashboard – view income, expenses, and smart spending insights.">
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
                <h1>Dashboard</h1>
                <div class="top-bar-user">
                    <div class="user-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div>
                    <span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
                </div>
            </div>
        </section>

        <!-- Alerts -->
        <?php if (!empty($anomalies)): ?>
        <div style="padding: 1rem 2rem 0;">
            <?php foreach($anomalies as $a): ?>
            <div class="alert alert-anomaly">
                <span class="alert-icon">⚠️</span>
                <div><strong>Anomaly detected:</strong> "<?php echo htmlspecialchars($a['description']); ?>" in <em><?php echo htmlspecialchars($a['category_name']); ?></em> was FCFA <?php echo number_format($a['amount'],0,',','.'); ?> – significantly above your usual spending on <?php echo $a['expense_date']; ?>.</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if($overdue_count > 0): ?>
        <div style="padding: 0.5rem 2rem 0;">
            <div class="alert alert-warning">
                <span class="alert-icon">📄</span>
                <div>You have <strong><?php echo $overdue_count; ?></strong> overdue invoice(s). <a href="Invoice.php" style="color:inherit;text-decoration:underline;">View now →</a></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card sc-balance">
                <div class="stat-card-label">Net Balance</div>
                <div class="stat-card-value" style="color:<?php echo $net_balance >= 0 ? 'var(--brand-success)' : 'var(--brand-danger)'; ?>">
                    FCFA <?php echo number_format(abs($net_balance),0,',','.'); ?>
                </div>
                <div class="stat-card-sub"><?php echo $net_balance >= 0 ? '▲ Surplus' : '▼ Deficit'; ?></div>
                <div class="stat-icon si-balance">💳</div>
            </div>
            <div class="stat-card sc-income">
                <div class="stat-card-label">Total Income</div>
                <div class="stat-card-value">FCFA <?php echo number_format($total_income,0,',','.'); ?></div>
                <div class="stat-card-sub">All time</div>
                <div class="stat-icon si-income">↑</div>
            </div>
            <div class="stat-card sc-expense">
                <div class="stat-card-label">Total Expenses</div>
                <div class="stat-card-value">FCFA <?php echo number_format($total_expense,0,',','.'); ?></div>
                <div class="stat-card-sub">All time</div>
                <div class="stat-icon si-expense">↓</div>
            </div>
            <div class="stat-card sc-pace">
                <div class="stat-card-label">Projected This Month</div>
                <div class="stat-card-value">FCFA <?php echo number_format($projected_spend,0,',','.'); ?></div>
                <div class="stat-card-sub">
                    <?php if($last_month_spent > 0): ?>
                        <?php $diff = $projected_spend - $last_month_spent; ?>
                        <?php echo $diff >= 0 ? '▲' : '▼'; ?>
                        FCFA <?php echo number_format(abs($diff),0,',','.'); ?> vs last month
                    <?php else: ?>
                        Based on <?php echo $days_elapsed; ?> days of data
                    <?php endif; ?>
                </div>
                <div class="stat-icon si-pace">📈</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="chart-section">
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span class="chart-card-title">Cash Flow – <?php echo $cur_year; ?></span>
                        <span class="chart-badge">Monthly</span>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="cashFlowChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span class="chart-card-title">Spending by Category</span>
                        <span class="chart-badge"><?php echo date('M Y'); ?></span>
                    </div>
                    <div class="chart-wrap-sm">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <?php if(empty($cat_labels)): ?>
                    <p style="text-align:center;color:var(--text-muted);font-size:0.85rem;margin-top:1rem;">No expense data this month.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div><!-- /.main-wrapper -->
</div><!-- /.dashboard-container -->

<script>
const incomeData   = <?php echo $income_data_json; ?>;
const expensesData = <?php echo $expense_data_json; ?>;
const catLabels    = <?php echo $cat_labels_json; ?>;
const catData      = <?php echo $cat_data_json; ?>;
const catColors    = <?php echo $cat_colors_json; ?>;

const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Cash Flow Chart
new Chart(document.getElementById('cashFlowChart'), {
    type: 'bar',
    data: {
        labels: months,
        datasets: [
            { label: 'Income',   data: incomeData,   backgroundColor: 'rgba(16,185,129,0.8)',  borderRadius: 6 },
            { label: 'Expenses', data: expensesData, backgroundColor: 'rgba(239,68,68,0.8)',   borderRadius: 6 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#F3F4F8' }, ticks: { callback: v => 'FCFA ' + v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});

// Category Doughnut Chart
if (catData.length > 0) {
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: catColors, borderWidth: 2, borderColor: '#fff' }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } },
                tooltip: { callbacks: { label: ctx => ctx.label + ': FCFA ' + ctx.parsed.toLocaleString() } }
            },
            cutout: '65%'
        }
    });
}
</script>
</body>
</html>