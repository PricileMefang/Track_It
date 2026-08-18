<?php
session_start();
require_once "config.php";
$user_id = $_SESSION["user_id"] ?? 1;
$username = $_SESSION["user_name"] ?? "User";
$success_message = ''; $error_message = '';

// ── Handle add income ──────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["amount"])) {
    $amount      = floatval($_POST["amount"]);
    $source      = trim($_POST["source"]);
    $income_date = trim($_POST["income_date"]);
    $edit_id     = intval($_POST["edit_id"] ?? 0);

    if ($amount > 0 && !empty($source) && !empty($income_date)) {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("UPDATE income SET amount=?, source=?, income_date=? WHERE id=? AND user_id=?");
            $stmt->bind_param("dssii", $amount, $source, $income_date, $edit_id, $user_id);
            $success_message = $stmt->execute() ? "Income updated!" : "Error: ".$stmt->error;
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO income (user_id, category_id, amount, source, income_date) VALUES (?, 1, ?, ?, ?)");
            $stmt->bind_param("idss", $user_id, $amount, $source, $income_date);
            $success_message = $stmt->execute() ? "Income added!" : "Error: ".$stmt->error;
            $stmt->close();
        }
    } else { $error_message = "Please fill in all fields correctly."; }
}

// ── Handle delete income ───────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM income WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $did, $user_id); $stmt->execute(); $stmt->close();
    header("Location: Income.php"); exit;
}

// ── Load edit record ───────────────────────────────────────────
$edit_income = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT id, income_date, source, amount FROM income WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $eid, $user_id); $stmt->execute(); $result=$stmt->get_result();
    $edit_income = $result->fetch_assoc(); $stmt->close();
}

// ── Totals ─────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM income WHERE user_id=? AND MONTH(income_date)=MONTH(CURDATE()) AND YEAR(income_date)=YEAR(CURDATE())");
$stmt->bind_param("i",$user_id); $stmt->execute(); $stmt->bind_result($this_month_income); $stmt->fetch(); $stmt->close();

$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM income WHERE user_id=?");
$stmt->bind_param("i",$user_id); $stmt->execute(); $stmt->bind_result($total_income); $stmt->fetch(); $stmt->close();

// ── Income list ────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, source, amount, income_date FROM income WHERE user_id=? ORDER BY income_date DESC LIMIT 100");
$stmt->bind_param("i",$user_id); $stmt->execute(); $result=$stmt->get_result();
$incomes=[]; while($row=$result->fetch_assoc()) $incomes[]=$row;
$stmt->close();

$active_page = 'income';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income – TrackIt</title>
    <link rel="icon" type="image/png" href="TrackIt Logo copy.png">
    <link rel="stylesheet" href="trackit.css">
</head>
<body class="print-only-section">
<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <section id="top-bar">
            <div class="top-bar-content">
                <h1>Income</h1>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <a href="Settings.php?export_csv=income" class="btn btn-ghost btn-sm">⬇ CSV</a>
                    <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨️ Print</button>
                    <div class="top-bar-user" style="margin-left:0.5rem;"><div class="user-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div><span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
                    </div>
                </div>
            </div>
        </section>

        <?php if($success_message): ?><div style="padding:0.75rem 2rem 0;"><div class="alert alert-success"><span class="alert-icon">✅</span><?php echo htmlspecialchars($success_message); ?></div></div><?php endif; ?>
        <?php if($error_message): ?><div style="padding:0.75rem 2rem 0;"><div class="alert alert-error"><span class="alert-icon">❌</span><?php echo htmlspecialchars($error_message); ?></div></div><?php endif; ?>

        <!-- Summary Cards -->
        <div id="summary-cards">
            <div class="summary-card income">
                <div class="card-icon">↑</div>
                <div>
                    <h3>This Month</h3>
                    <div class="card-value">FCFA <?php echo number_format($this_month_income,0,',','.'); ?></div>
                </div>
            </div>
            <div class="summary-card income">
                <div class="card-icon">💰</div>
                <div>
                    <h3>All Time</h3>
                    <div class="card-value">FCFA <?php echo number_format($total_income,0,',','.'); ?></div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <!-- Add / Edit Form -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><?php echo $edit_income ? '✏️ Edit Income' : '➕ Log Income'; ?></span>
                </div>
                <form method="POST">
                    <?php if($edit_income): ?><input type="hidden" name="edit_id" value="<?php echo $edit_income['id']; ?>"><?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="income_date" class="form-control" required
                                   value="<?php echo $edit_income ? $edit_income['income_date'] : date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Source / Description</label>
                            <input type="text" name="source" class="form-control" placeholder="e.g. Client Invoice #001" required
                                   value="<?php echo $edit_income ? htmlspecialchars($edit_income['source']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (FCFA)</label>
                            <input type="number" name="amount" class="form-control" placeholder="0" step="1" min="1" required
                                   value="<?php echo $edit_income ? $edit_income['amount'] : ''; ?>">
                        </div>
                    </div>
                    <div style="margin-top:1rem;display:flex;gap:0.75rem;">
                        <button type="submit" class="btn btn-success"><?php echo $edit_income ? '💾 Save' : '➕ Add Income'; ?></button>
                        <?php if($edit_income): ?><a href="Income.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Income List -->
            <div class="card print-section">
                <div class="card-header">
                    <span class="card-title">📋 Income Records</span>
                    <a href="Settings.php?export_csv=income" class="btn btn-ghost btn-sm">⬇ Export</a>
                </div>
                <div class="table-wrap">
                    <table class="trackit-table">
                        <thead>
                            <tr><th>Date</th><th>Source</th><th>Amount</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if(empty($incomes)): ?>
                            <tr class="empty-row"><td colspan="4">No income records found.</td></tr>
                            <?php else: ?>
                            <?php foreach($incomes as $inc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($inc['income_date']); ?></td>
                                <td><?php echo htmlspecialchars($inc['source']); ?></td>
                                <td style="font-weight:600;color:var(--brand-success);">+ FCFA <?php echo number_format($inc['amount'],0,',','.'); ?></td>
                                <td>
                                    <div style="display:flex;gap:0.4rem;">
                                        <a href="Income.php?edit=<?php echo $inc['id']; ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>
                                        <a href="Income.php?delete=<?php echo $inc['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this income record?')">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>