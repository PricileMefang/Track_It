<?php
session_start();
require_once "config.php";
$user_id = $_SESSION["user_id"] ?? 1;
$username = $_SESSION["user_name"] ?? "User";

$success_message = ''; $error_message = '';

// ── CSV Export ─────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    $type = $_GET['export_csv'];
    if ($type === 'expenses') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="trackit_expenses_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Date','Description','Amount','Category','Flagged']);
        $stmt = $conn->prepare("SELECT e.id, e.expense_date, e.description, e.amount, ec.category_name, e.is_flagged_anomaly FROM expenses e LEFT JOIN expense_categories ec ON e.category_id=ec.id WHERE e.user_id=? ORDER BY e.expense_date DESC");
        $stmt->bind_param("i",$user_id); $stmt->execute(); $result=$stmt->get_result();
        while($row=$result->fetch_assoc()) fputcsv($out, [$row['id'],$row['expense_date'],$row['description'],$row['amount'],$row['category_name'],$row['is_flagged_anomaly']?'Yes':'No']);
        $stmt->close(); fclose($out); exit;
    }
    if ($type === 'income') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="trackit_income_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Date','Source','Amount']);
        $stmt = $conn->prepare("SELECT id, income_date, source, amount FROM income WHERE user_id=? ORDER BY income_date DESC");
        $stmt->bind_param("i",$user_id); $stmt->execute(); $result=$stmt->get_result();
        while($row=$result->fetch_assoc()) fputcsv($out, [$row['id'],$row['income_date'],$row['source'],$row['amount']]);
        $stmt->close(); fclose($out); exit;
    }
}

// ── JSON Export ────────────────────────────────────────────────
if (isset($_GET['export_json'])) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="trackit_backup_' . date('Y-m-d') . '.json"');
    $data = ['exported_at' => date('c'), 'user_id' => $user_id, 'expenses' => [], 'income' => [], 'invoices' => [], 'clients' => [], 'savings_goals' => []];
    foreach (['expenses'=>"SELECT e.*, ec.category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id=ec.id WHERE e.user_id=$user_id",
              'income'  =>"SELECT * FROM income WHERE user_id=$user_id",
              'invoices'=>"SELECT * FROM invoices WHERE user_id=$user_id",
              'clients' =>"SELECT * FROM clients WHERE user_id=$user_id",
              'savings_goals'=>"SELECT * FROM savings_goals WHERE user_id=$user_id"] as $key => $sql) {
        $r = $conn->query($sql);
        if($r) while($row=$r->fetch_assoc()) $data[$key][]=$row;
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// ── JSON Import ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_json'])) {
    $file = $_FILES['import_json']['tmp_name'];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!$data) {
        $error_message = "Invalid JSON file.";
    } else {
        $imported = 0;
        // Import expenses
        foreach(($data['expenses'] ?? []) as $e){
            $stmt = $conn->prepare("INSERT IGNORE INTO expenses (user_id, expense_date, description, amount, category_id) VALUES (?,?,?,?,1)");
            $stmt->bind_param("issdi",$user_id,$e['expense_date'],$e['description'],$e['amount']); $stmt->execute(); $imported++;
        }
        // Import income
        foreach(($data['income'] ?? []) as $inc){
            $stmt = $conn->prepare("INSERT IGNORE INTO income (user_id, category_id, amount, source, income_date) VALUES (?,1,?,?,?)");
            $stmt->bind_param("idss",$user_id,$inc['amount'],$inc['source'],$inc['income_date']); $stmt->execute(); $imported++;
        }
        $success_message = "Imported $imported records successfully!";
    }
}

// ── Clear Data ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_data'])) {
    $confirm = trim($_POST['confirm_clear'] ?? '');
    if ($confirm === 'DELETE') {
        $conn->query("DELETE FROM expenses WHERE user_id=$user_id");
        $conn->query("DELETE FROM income WHERE user_id=$user_id");
        $conn->query("DELETE FROM invoices WHERE user_id=$user_id");
        $conn->query("DELETE FROM savings_goals WHERE user_id=$user_id");
        $conn->query("DELETE FROM recurring_expenses WHERE user_id=$user_id");
        $success_message = "All data cleared successfully.";
    } else {
        $error_message = "Type DELETE to confirm data erasure.";
    }
}

$active_page = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings – TrackIt</title>
    <link rel="icon" type="image/png" href="TrackIt Logo copy.png">
    <link rel="stylesheet" href="trackit.css">
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <section id="top-bar">
            <div class="top-bar-content">
                <h1>Settings</h1>
                <div class="top-bar-user"><div class="user-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div><span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
                </div>
            </div>
        </section>
        <div class="page-body">
            <?php if($success_message): ?><div class="alert alert-success"><span class="alert-icon">✅</span><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
            <?php if($error_message): ?><div class="alert alert-error"><span class="alert-icon">❌</span><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

            <!-- EXPORT -->
            <div class="card">
                <div class="card-header"><span class="card-title">📤 Export Data</span></div>
                <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:1rem;">Download your data as CSV for spreadsheets, or as a full JSON backup.</p>
                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <a href="Settings.php?export_csv=expenses" class="btn btn-ghost">⬇ Expenses CSV</a>
                    <a href="Settings.php?export_csv=income"   class="btn btn-ghost">⬇ Income CSV</a>
                    <a href="Settings.php?export_json=1"       class="btn btn-primary">💾 Full JSON Backup</a>
                    <button onclick="window.print()" class="btn btn-ghost">🖨️ Print Report</button>
                </div>
            </div>

            <!-- IMPORT -->
            <div class="card">
                <div class="card-header"><span class="card-title">📥 Import Data (JSON)</span></div>
                <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:1rem;">Restore from a previously exported TrackIt JSON backup file.</p>
                <form method="POST" enctype="multipart/form-data" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                    <div class="custom-file-input">
                        <input type="file" name="import_json" id="import_json" accept=".json" required>
                        <label for="import_json">
                            <span class="upload-icon">📂</span> <span class="file-name-text">Choose JSON File...</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-success" style="padding:0.65rem 1.25rem;">Upload &amp; Import</button>
                </form>
            </div>

            <!-- AUTO-CATEGORIZATION RULES -->
            <div class="card">
                <div class="card-header"><span class="card-title">🤖 Auto-categorization Rules</span></div>
                <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:1rem;">When you add an expense without selecting a category, TrackIt checks these keywords to categorize it automatically.</p>
                <?php
                $rules = []; $cats_map = [];
                $r = $conn->query("SELECT * FROM category_rules WHERE user_id=$user_id ORDER BY keyword ASC");
                if($r) while($row=$r->fetch_assoc()) $rules[]=$row;
                $r = $conn->query("SELECT id, category_name FROM expense_categories ORDER BY category_name");
                if($r) while($row=$r->fetch_assoc()) $cats_map[$row['id']]=$row['category_name'];
                ?>
                <!-- Add rule form -->
                <form method="POST" action="settings_actions.php" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem;">
                    <input type="hidden" name="action" value="add_rule">
                    <div class="form-group">
                        <label class="form-label">Keyword</label>
                        <input type="text" name="keyword" class="form-control" placeholder="e.g. coffee" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="rule_category_id" class="form-control">
                            <?php foreach($cats_map as $cid=>$cname): ?>
                            <option value="<?php echo $cid; ?>"><?php echo htmlspecialchars($cname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Rule</button>
                </form>
                <div class="table-wrap">
                    <table class="trackit-table">
                        <thead><tr><th>Keyword</th><th>Maps to Category</th><th></th></tr></thead>
                        <tbody>
                        <?php if(empty($rules)): ?>
                            <tr class="empty-row"><td colspan="3">No custom rules yet.</td></tr>
                        <?php else: ?>
                        <?php foreach($rules as $rule): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($rule['keyword']); ?></code></td>
                                <td><?php echo htmlspecialchars($cats_map[$rule['category_id']] ?? 'Unknown'); ?></td>
                                <td>
                                    <a href="settings_actions.php?delete_rule=<?php echo $rule['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete rule?')">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DANGER ZONE -->
            <div class="card" style="border:1.5px solid var(--brand-danger);">
                <div class="card-header">
                    <span class="card-title" style="color:var(--brand-danger);">⚠️ Danger Zone</span>
                </div>
                <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:1rem;">This will permanently delete ALL your expenses, income, invoices, savings goals, and recurring expenses. This action cannot be undone.</p>
                <form method="POST" onsubmit="return confirm('Are you absolutely sure? This will erase all your data permanently!')">
                    <input type="hidden" name="clear_data" value="1">
                    <div class="form-grid" style="grid-template-columns:1fr auto;max-width:500px;">
                        <div class="form-group">
                            <label class="form-label">Type <strong>DELETE</strong> to confirm</label>
                            <input type="text" name="confirm_clear" class="form-control" placeholder="DELETE" required>
                        </div>
                        <div class="form-group" style="justify-content:flex-end;">
                            <label class="form-label" style="visibility:hidden;">Submit</label>
                            <button type="submit" class="btn btn-danger">🗑️ Clear All Data</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('import_json').addEventListener('change', function(e) {
    var fileName = e.target.files[0] ? e.target.files[0].name : "Choose JSON File...";
    e.target.nextElementSibling.querySelector('.file-name-text').textContent = fileName;
});
</script>
</body>
</html>