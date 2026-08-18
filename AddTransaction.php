<?php
session_start();
include 'config.php';
$user_id = $_SESSION["user_id"] ?? 1;
$username = $_SESSION["user_name"] ?? "User";

// ── Load categories ────────────────────────────────────────────
$categories = [];
$res = $conn->query("SELECT id, category_name, color_code FROM expense_categories ORDER BY category_name ASC");
if ($res) while ($row = $res->fetch_assoc()) $categories[] = $row;

// ── Auto-categorize helper ────────────────────────────────────
function auto_categorize($conn, $user_id, $description, $cat_id) {
    $desc_lower = strtolower($description);
    $stmt = $conn->prepare("SELECT category_id FROM category_rules WHERE user_id=? AND ? LIKE CONCAT('%', keyword, '%') LIMIT 1");
    $stmt->bind_param("is", $user_id, $desc_lower);
    $stmt->execute();
    $stmt->bind_result($cat_id);
    $found = $stmt->fetch();
    $stmt->close();
    return $found ? (int)$cat_id : null;
}

// ── Load recurring templates ──────────────────────────────────
$recurring_list = [];
$res = $conn->prepare("SELECT r.id, r.description, r.amount, r.recurrence_interval, r.next_occurrence, ec.category_name FROM recurring_expenses r LEFT JOIN expense_categories ec ON r.category_id=ec.id WHERE r.user_id=? ORDER BY r.next_occurrence ASC");
$res->bind_param("i", $user_id); $res->execute(); $result = $res->get_result();
while ($row = $result->fetch_assoc()) $recurring_list[] = $row;
$res->close();

$success_message = ''; $error_message = ''; $anomaly_warning = '';

// ── Handle delete recurring ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_recurring_id'])) {
    $del_id = (int)$_POST['delete_recurring_id'];
    $stmt = $conn->prepare("DELETE FROM recurring_expenses WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $del_id, $user_id); $stmt->execute(); $stmt->close();
    header("Location: AddTransaction.php?msg=recurring_deleted"); exit;
}

// ── Handle add recurring template ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_recurring'])) {
    $rdesc     = trim($_POST['recurring_desc'] ?? '');
    $ramount   = floatval($_POST['recurring_amount'] ?? 0);
    $rcat      = intval($_POST['recurring_category'] ?? 0);
    $rinterval = $_POST['recurring_interval'] ?? 'monthly';
    $rnext     = $_POST['recurring_next'] ?? date('Y-m-d');
    if ($rdesc && $ramount > 0 && $rcat > 0) {
        $stmt = $conn->prepare("INSERT INTO recurring_expenses (user_id, category_id, amount, description, recurrence_interval, next_occurrence) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("iiidss", $user_id, $rcat, $ramount, $rdesc, $rinterval, $rnext);
        $stmt->execute(); $stmt->close();
        $success_message = "Recurring expense template saved!";
    } else { $error_message = "Please fill in all recurring fields."; }
}

// ── Handle add/edit expense ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_date'])) {
    $date        = trim($_POST['transaction_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount      = floatval($_POST['amount'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $edit_id     = intval($_POST['edit_id'] ?? 0);

    // Auto-categorize if not selected
    if ($category_id === 0 && $description) {
        $auto_cat = auto_categorize($conn, $user_id, $description, $cat_id);
        if ($auto_cat) $category_id = $auto_cat;
    }

    if (!$date || !$description || $amount <= 0 || $category_id <= 0) {
        $error_message = "All fields are required.";
    } else {
        // ── Anomaly Detection ──────────────────────────────────
        $is_flagged = 0;
        $stmt = $conn->prepare("SELECT AVG(amount) FROM expenses WHERE user_id=? AND category_id=? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)");
        $stmt->bind_param("ii", $user_id, $category_id); $stmt->execute(); $stmt->bind_result($avg_amount); $stmt->fetch(); $stmt->close();
        if ($avg_amount > 0 && $amount > ($avg_amount * 2.5)) {
            $is_flagged = 1;
            $anomaly_warning = "⚠️ This expense (FCFA " . number_format($amount,0,',','.') . ") is more than 2.5× your usual spend (avg FCFA " . number_format($avg_amount,0,',','.') . ") in this category.";
        }

        if ($edit_id > 0) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE expenses SET expense_date=?, description=?, amount=?, category_id=?, is_flagged_anomaly=? WHERE id=? AND user_id=?");
            $stmt->bind_param("ssdiiiii", $date, $description, $amount, $category_id, $is_flagged, $edit_id, $user_id);
            if ($stmt->execute()) $success_message = "Expense updated!";
            else $error_message = "Error: " . $stmt->error;
            $stmt->close();
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO expenses (user_id, expense_date, description, amount, category_id, is_flagged_anomaly) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("issdii", $user_id, $date, $description, $amount, $category_id, $is_flagged);
            if ($stmt->execute()) {
                $success_message = "Expense added!";
                if ($is_flagged) $success_message .= " (Anomaly flagged)";
            } else { $error_message = "Error: " . $stmt->error; }
            $stmt->close();
        }
    }
}

// ── Load existing expense for editing ─────────────────────────
$edit_expense = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT id, expense_date, description, amount, category_id FROM expenses WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $eid, $user_id); $stmt->execute(); $result = $stmt->get_result();
    $edit_expense = $result->fetch_assoc(); $stmt->close();
}

// ── Handle delete expense ──────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $did, $user_id); $stmt->execute(); $stmt->close();
    header("Location: Expenses.php"); exit;
}

$active_page = 'expenses';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_expense ? 'Edit' : 'Add'; ?> Expense – TrackIt</title>
    <link rel="icon" type="image/png" href="TrackIt Logo copy.png">
    <link rel="stylesheet" href="trackit.css">
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <!-- Top Bar -->
        <section id="top-bar">
            <div class="top-bar-content">
                <h1><?php echo $edit_expense ? 'Edit Expense' : 'Add Expense'; ?></h1>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <a href="Expenses.php" class="btn btn-ghost btn-sm">← Back to Expenses</a>
                    <div class="top-bar-user" style="margin-left:0.5rem;"><div class="user-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div><span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
                    </div>
                </div>
            </div>
        </section>

        <div class="page-body">
            <?php if ($anomaly_warning): ?>
            <div class="alert alert-anomaly"><span class="alert-icon">⚠️</span><div><?php echo $anomaly_warning; ?></div></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
            <div class="alert alert-success"><span class="alert-icon">✅</span><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
            <div class="alert alert-error"><span class="alert-icon">❌</span><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- ADD / EDIT EXPENSE FORM -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><?php echo $edit_expense ? '✏️ Edit Expense' : '➕ New Expense'; ?></span>
                </div>
                <form method="POST" id="expenseForm">
                    <?php if ($edit_expense): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_expense['id']; ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="transaction_date" class="form-control" required
                                   value="<?php echo $edit_expense ? $edit_expense['expense_date'] : date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" id="descInput" class="form-control"
                                   placeholder="e.g. Coffee, Uber, Netflix…" required
                                   value="<?php echo $edit_expense ? htmlspecialchars($edit_expense['description']) : ''; ?>">
                            <small style="color:var(--text-muted);font-size:0.75rem;margin-top:0.25rem;display:block;">
                                Leave category as "Auto-detect" and we'll categorize it for you.
                            </small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (FCFA)</label>
                            <input type="number" name="amount" class="form-control" placeholder="0" step="1" min="1" required
                                   value="<?php echo $edit_expense ? $edit_expense['amount'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-control" id="categorySelect">
                                <option value="0">🤖 Auto-detect</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo ($edit_expense && $edit_expense['category_id']==$cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top:1.25rem;display:flex;gap:0.75rem;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_expense ? '💾 Save Changes' : '➕ Add Expense'; ?>
                        </button>
                        <a href="Expenses.php" class="btn btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- RECURRING EXPENSES -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">🔁 Recurring Expenses</span>
                </div>
                <!-- Add recurring template form -->
                <form method="POST" style="margin-bottom:1.5rem;">
                    <input type="hidden" name="add_recurring" value="1">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Description</label>
                            <input type="text" name="recurring_desc" class="form-control" placeholder="e.g. Monthly Rent" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (FCFA)</label>
                            <input type="number" name="recurring_amount" class="form-control" placeholder="0" step="1" min="1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="recurring_category" class="form-control">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Interval</label>
                            <select name="recurring_interval" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Next Occurrence</label>
                            <input type="date" name="recurring_next" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div style="margin-top:1rem;">
                        <button type="submit" class="btn btn-success">+ Add Recurring</button>
                    </div>
                </form>

                <!-- List of existing recurring -->
                <?php if (empty($recurring_list)): ?>
                <p style="color:var(--text-muted);font-size:0.875rem;">No recurring expenses set up yet.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="trackit-table">
                        <thead><tr><th>Description</th><th>Amount</th><th>Category</th><th>Interval</th><th>Next Date</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach($recurring_list as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['description']); ?></td>
                                <td>FCFA <?php echo number_format($r['amount'],0,',','.'); ?></td>
                                <td><?php echo htmlspecialchars($r['category_name'] ?? 'N/A'); ?></td>
                                <td style="text-transform:capitalize;"><?php echo $r['recurrence_interval']; ?></td>
                                <td><?php echo $r['next_occurrence']; ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this recurring expense?')">
                                        <input type="hidden" name="delete_recurring_id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div><!-- /.page-body -->
    </div><!-- /.main-wrapper -->
</div>

<script>
// Live auto-detect hint based on description keywords
const descInput = document.getElementById('descInput');
const catSelect = document.getElementById('categorySelect');

const autoRules = {
    'coffee':['Food & Dining'],'cafe':['Food & Dining'],'restaurant':['Food & Dining'],
    'lunch':['Food & Dining'],'dinner':['Food & Dining'],'food':['Food & Dining'],
    'grocery':['Food & Dining'],'groceries':['Food & Dining'],'breakfast':['Food & Dining'],
    'uber':['Transport'],'taxi':['Transport'],'bus':['Transport'],
    'fuel':['Transport'],'petrol':['Transport'],'bolt':['Transport'],
    'netflix':['Entertainment'],'cinema':['Entertainment'],'movie':['Entertainment'],
    'spotify':['Entertainment'],'game':['Entertainment'],
    'rent':['Rent'],'landlord':['Rent'],
};

descInput?.addEventListener('input', function() {
    if (catSelect.value !== '0') return; // don't override manual selection
    const val = this.value.toLowerCase();
    let matched = null;
    for (const [kw, _] of Object.entries(autoRules)) {
        if (val.includes(kw)) { matched = kw; break; }
    }
    const hint = document.getElementById('autoHint');
    if (matched && hint) hint.textContent = '🤖 Will be auto-categorized';
    else if (hint) hint.textContent = '';
});
</script>
</body>
</html>