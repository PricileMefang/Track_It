<?php
session_start();
include 'config.php';
$user_id = $_SESSION["user_id"] ?? 1;
$username = $_SESSION["user_name"] ?? "User";

// ── Handle POST (categories) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_category') {
        $cn = trim($_POST['category_name'] ?? '');
        $icon = trim($_POST['category_icon'] ?? '📦');
        $col = trim($_POST['color_code'] ?? '#4F46E5');
        if ($cn) {
            $stmt = $conn->prepare("INSERT INTO expense_categories (user_id, category_name, category_icon, color_code) VALUES (?,?,?,?)");
            $stmt->bind_param("isss", $user_id, $cn, $icon, $col);
            $stmt->execute();
            $stmt->close();
            $success_message = "Category '$cn' added!";
        }
    } elseif ($action === 'delete_category') {
        $cid = intval($_POST['category_id'] ?? 0);
        if ($cid > 0) {
            $stmt = $conn->prepare("DELETE FROM expense_categories WHERE id=? AND (user_id=? OR user_id IS NULL)");
            $stmt->bind_param("ii", $cid, $user_id);
            $stmt->execute();
            $stmt->close();
            $success_message = "Category deleted.";
        }
    }
}

// ── Totals ─────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($this_month_total);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($total_expense);
$stmt->fetch();
$stmt->close();

// ── Expenses list ──────────────────────────────────────────────
$filter_cat = intval($_GET['cat'] ?? 0);
$q = "SELECT e.id, e.expense_date, e.description, e.amount, e.is_flagged_anomaly, e.is_recurring_instance, ec.category_name, ec.color_code FROM expenses e LEFT JOIN expense_categories ec ON e.category_id=ec.id WHERE e.user_id=?";
$params = [$user_id];
$types = "i";
if ($filter_cat > 0) {
    $q .= " AND e.category_id=?";
    $params[] = $filter_cat;
    $types .= "i";
}
$q .= " ORDER BY e.expense_date DESC, e.id DESC LIMIT 100";
$stmt = $conn->prepare($q);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$expenses = [];
while ($row = $result->fetch_assoc())
    $expenses[] = $row;
$stmt->close();

// ── Categories ────────────────────────────────────────────────
$categories = [];
$res = $conn->query("SELECT * FROM expense_categories ORDER BY category_name ASC");
if ($res)
    while ($row = $res->fetch_assoc())
        $categories[] = $row;

$active_page = 'expenses';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses – TrackIt</title>
    <link rel="icon" type="image/png" href="TrackIt Logo copy.png">
    <link rel="stylesheet" href="trackit.css">
</head>

<body class="print-only-section">
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-wrapper">
            <section id="top-bar">
                <div class="top-bar-content">
                    <h1>Expenses</h1>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <a href="AddTransaction.php" class="btn btn-primary">+ Add Expense</a>
                        <div class="top-bar-user" style="margin-left:0.5rem;"><div class="user-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div><span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (!empty($success_message)): ?>
                <div style="padding:0.75rem 2rem 0;">
                    <div class="alert alert-success"><span
                            class="alert-icon">✅</span><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div id="summary-cards">
                <div class="summary-card expenses">
                    <div class="card-icon">↓</div>
                    <div>
                        <h3>This Month</h3>
                        <div class="card-value">FCFA <?php echo number_format($this_month_total, 0, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="summary-card expenses">
                    <div class="card-icon">📊</div>
                    <div>
                        <h3>All Time</h3>
                        <div class="card-value">FCFA <?php echo number_format($total_expense, 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>

            <div class="page-body">
                <!-- Filter by category -->
                <div class="card print-section">
                    <div class="card-header">
                        <span class="card-title">💳 Expense Transactions</span>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <select class="form-control" style="width:auto;font-size:0.85rem;"
                                onchange="location='Expenses.php?cat='+this.value">
                                <option value="0" <?php echo $filter_cat == 0 ? 'selected' : ''; ?>>All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $filter_cat == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="AddTransaction.php" class="btn btn-primary btn-sm">+ Add</a>
                            <button class="btn btn-ghost btn-sm" onclick="window.print()">🖨️ Print</button>
                            <a href="Settings.php?export_csv=expenses" class="btn btn-ghost btn-sm">⬇ CSV</a>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="trackit-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Flags</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($expenses)): ?>
                                    <tr class="empty-row">
                                        <td colspan="6">No expenses found. <a href="AddTransaction.php">Add your first one
                                                →</a></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($expenses as $e): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($e['expense_date']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($e['description']); ?>
                                                <?php if ($e['is_recurring_instance']): ?><span title="Auto-generated recurring"
                                                        style="font-size:0.75rem;color:var(--brand-accent);">🔁</span><?php endif; ?>
                                            </td>
                                            <td>
                                                <span
                                                    style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.8rem;">
                                                    <span
                                                        style="width:8px;height:8px;border-radius:50%;background:<?php echo htmlspecialchars($e['color_code'] ?? '#ccc'); ?>;display:inline-block;"></span>
                                                    <?php echo htmlspecialchars($e['category_name'] ?? 'Uncategorized'); ?>
                                                </span>
                                            </td>
                                            <td style="font-weight:600;color:var(--brand-danger);">
                                                − FCFA <?php echo number_format($e['amount'], 0, ',', '.'); ?>
                                            </td>
                                            <td>
                                                <?php if ($e['is_flagged_anomaly']): ?>
                                                    <span class="badge badge-flagged" title="Spending anomaly detected">⚠️
                                                        Anomaly</span>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);font-size:0.8rem;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:0.4rem;">
                                                    <a href="AddTransaction.php?edit=<?php echo $e['id']; ?>"
                                                        class="btn btn-ghost btn-sm">✏️ Edit</a>
                                                    <a href="AddTransaction.php?delete=<?php echo $e['id']; ?>"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Delete this expense?')">🗑️</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Custom Categories management -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">🏷️ Manage Categories</span>
                        <button class="btn btn-ghost btn-sm"
                            onclick="document.getElementById('catForm').classList.toggle('hidden')">+ Add
                            Category</button>
                    </div>
                    <div id="catForm" class="hidden"
                        style="margin-bottom:1.25rem;padding:1rem;background:var(--bg-page);border-radius:var(--radius-sm);">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_category">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Category Name</label>
                                    <input type="text" name="category_name" class="form-control"
                                        placeholder="e.g. Travel" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Icon Emoji</label>
                                    <input type="text" name="category_icon" class="form-control" placeholder="✈️"
                                        value="📦">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Color</label>
                                    <input type="color" name="color_code" class="form-control" value="#4F46E5"
                                        style="height:42px;padding:0.2rem;">
                                </div>
                            </div>
                            <div style="margin-top:0.75rem;display:flex;gap:0.5rem;">
                                <button type="submit" class="btn btn-primary">Save Category</button>
                                <button type="button" class="btn btn-ghost"
                                    onclick="document.getElementById('catForm').classList.add('hidden')">Cancel</button>
                            </div>
                        </form>
                    </div>
                    <div class="table-wrap">
                        <table class="trackit-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Color</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                        <td><span
                                                style="display:inline-block;width:16px;height:16px;border-radius:4px;background:<?php echo htmlspecialchars($cat['color_code'] ?? '#ccc'); ?>;vertical-align:middle;"></span>
                                            <?php echo htmlspecialchars($cat['color_code'] ?? ''); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;"
                                                onsubmit="return confirm('Delete this category?')">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .hidden {
            display: none !important;
        }
    </style>
</body>

</html>