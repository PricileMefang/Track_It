<?php
session_start();
require_once "config.php";
$user_id = $_SESSION["user_id"] ?? 1;
$username = $_SESSION["user_name"] ?? "User";
$success_message = ""; $error_message = "";

// ── Add invoice ───────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["client_name"])) {
    $client_name = trim($_POST["client_name"]);
    $amount      = floatval($_POST["amount"]);
    $status      = trim($_POST["status"]);
    $due_date    = trim($_POST["due_date"]);
    if ($amount > 0 && !empty($client_name) && !empty($due_date)) {
        $stmt = $conn->prepare("INSERT INTO invoices (user_id, client_name, amount, status, due_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdss", $user_id, $client_name, $amount, $status, $due_date);
        $success_message = $stmt->execute() ? "Invoice created!" : "Error: ".$stmt->error;
        $stmt->close();
    } else { $error_message = "Please fill in all fields correctly."; }
}

// ── Update status ─────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_status"])) {
    $inv_id    = intval($_POST["invoice_id"]);
    $new_status = trim($_POST["new_status"]);
    $stmt = $conn->prepare("UPDATE invoices SET status=? WHERE id=? AND user_id=?");
    $stmt->bind_param("sii", $new_status, $inv_id, $user_id);
    $success_message = $stmt->execute() ? "Status updated!" : "Error: ".$stmt->error;
    $stmt->close();
}

// ── Delete invoice ────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM invoices WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $did, $user_id); $stmt->execute(); $stmt->close();
    header("Location: Invoice.php"); exit;
}

// ── Fetch invoices ────────────────────────────────────────────
$filter = $_GET['status'] ?? '';
$q = "SELECT id, client_name, amount, status, due_date FROM invoices WHERE user_id=?";
$params = [$user_id]; $types = "i";
if (in_array($filter, ['Paid','Pending','Overdue'])) { $q .= " AND status=?"; $params[]=$filter; $types.="s"; }
$q .= " ORDER BY due_date ASC";
$stmt = $conn->prepare($q); $stmt->bind_param($types,...$params); $stmt->execute(); $result=$stmt->get_result();
$invoices=[]; while($row=$result->fetch_assoc()) $invoices[]=$row;
$stmt->close();

// ── Summary counts ─────────────────────────────────────────────
$stmt = $conn->prepare("SELECT status, COUNT(*) as cnt, IFNULL(SUM(amount),0) as total FROM invoices WHERE user_id=? GROUP BY status");
$stmt->bind_param("i",$user_id); $stmt->execute(); $result=$stmt->get_result();
$status_summary = []; while($row=$result->fetch_assoc()) $status_summary[$row['status']]=['cnt'=>$row['cnt'],'total'=>$row['total']];
$stmt->close();

$active_page = 'invoices';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices – TrackIt</title>
    <link rel="icon" type="image/png" href="TrackIt Logo copy.png">
    <link rel="stylesheet" href="trackit.css">
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <section id="top-bar">
            <div class="top-bar-content">
                <h1>My Invoices</h1>
                <div class="top-bar-user"><div class="user-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div><span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
                </div>
            </div>
        </section>

        <?php if($success_message): ?><div style="padding:0.75rem 2rem 0;"><div class="alert alert-success"><span class="alert-icon">✅</span><?php echo htmlspecialchars($success_message); ?></div></div><?php endif; ?>
        <?php if($error_message): ?><div style="padding:0.75rem 2rem 0;"><div class="alert alert-error"><span class="alert-icon">❌</span><?php echo htmlspecialchars($error_message); ?></div></div><?php endif; ?>

        <!-- Summary cards -->
        <div id="summary-cards">
            <?php foreach(['Paid'=>'income','Pending'=>'expenses','Overdue'=>'expenses'] as $st=>$cls): $s = $status_summary[$st] ?? ['cnt'=>0,'total'=>0]; ?>
            <div class="summary-card <?php echo $cls; ?>">
                <div class="card-icon"><?php echo $st==='Paid'?'✅':($st==='Pending'?'🕐':'🔴'); ?></div>
                <div>
                    <h3><?php echo $st; ?></h3>
                    <div class="card-value">FCFA <?php echo number_format($s['total'],0,',','.'); ?></div>
                    <div style="font-size:0.8rem;color:var(--text-muted);"><?php echo $s['cnt']; ?> invoice(s)</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="page-body">
            <!-- Create Invoice -->
            <div class="card">
                <div class="card-header"><span class="card-title">➕ Create New Invoice</span></div>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Client Name</label>
                            <input type="text" name="client_name" class="form-control" placeholder="Client Name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (FCFA)</label>
                            <input type="number" name="amount" class="form-control" placeholder="0" step="1" min="1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="Pending">Pending</option>
                                <option value="Paid">Paid</option>
                                <option value="Overdue">Overdue</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>
                    </div>
                    <div style="margin-top:1rem;"><button type="submit" class="btn btn-primary">📄 Create Invoice</button></div>
                </form>
            </div>

            <!-- Invoice Table -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">📋 Invoice List</span>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        <?php foreach([''=>'All','Paid'=>'Paid','Pending'=>'Pending','Overdue'=>'Overdue'] as $v=>$label): ?>
                        <a href="Invoice.php?status=<?php echo $v; ?>" class="btn <?php echo $filter===$v?'btn-primary':'btn-ghost'; ?> btn-sm"><?php echo $label; ?></a>
                        <?php endforeach; ?>
                        <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨️</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="trackit-table">
                        <thead>
                            <tr><th>Invoice #</th><th>Client</th><th>Amount</th><th>Status</th><th>Due Date</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if(empty($invoices)): ?>
                            <tr class="empty-row"><td colspan="6">No invoices found.</td></tr>
                            <?php else: ?>
                            <?php foreach($invoices as $inv):
                                $is_overdue = $inv['status']==='Pending' && $inv['due_date'] < date('Y-m-d');
                                $display_status = $is_overdue ? 'Overdue' : $inv['status'];
                            ?>
                            <tr>
                                <td style="font-family:monospace;font-weight:700;">#<?php echo str_pad($inv['id'],3,'0',STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($inv['client_name']); ?></td>
                                <td style="font-weight:600;">FCFA <?php echo number_format($inv['amount'],0,',','.'); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="new_status" class="form-control" style="padding:0.25rem 0.5rem;font-size:0.8rem;width:auto;" onchange="this.form.submit()">
                                            <?php foreach(['Pending','Paid','Overdue'] as $s): ?>
                                            <option value="<?php echo $s; ?>" <?php echo $inv['status']===$s?'selected':''; ?>><?php echo $s; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <?php if($is_overdue): ?><span class="badge badge-overdue" style="margin-left:4px;">Overdue!</span><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($inv['due_date']); ?></td>
                                <td>
                                    <div style="display:flex;gap:0.4rem;">
                                        <button type="button" onclick="showReceipt(<?php echo $inv['id']; ?>, '<?php echo addslashes($inv['client_name']); ?>', <?php echo $inv['amount']; ?>, '<?php echo $inv['status']; ?>', '<?php echo $inv['due_date']; ?>')" class="btn btn-ghost btn-sm" style="color:var(--brand-primary);border-color:var(--brand-primary);" title="Print Receipt">🧾 Receipt</button>
                                        <a href="Invoice.php?delete=<?php echo $inv['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete invoice #<?php echo str_pad($inv['id'],'3','0',STR_PAD_LEFT); ?>?')">🗑️</a>
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

<!-- Receipt Print Modal -->
<div id="receiptModal" class="modal-overlay">
    <div class="modal-box" style="max-width:420px;border-radius:var(--radius-md);border:1px solid var(--border);">
        <div class="modal-header" style="border-bottom:1px solid var(--border);padding-bottom:0.75rem;margin-bottom:1rem;">
            <span class="modal-title" style="font-weight:700;color:var(--text-primary);">🧾 Payment Receipt</span>
            <button onclick="closeReceiptModal()" class="modal-close">&times;</button>
        </div>
        <div id="receiptContent">
            <!-- Populated dynamically via JS -->
        </div>
        <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;gap:0.5rem;" class="no-print">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print</button>
            <button onclick="closeReceiptModal()" class="btn btn-ghost">Close</button>
        </div>
    </div>
</div>

<script>
function showReceipt(id, client, amount, status, dueDate) {
    const formattedAmount = Number(amount).toLocaleString('fr-FR');
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    let statusColor = '#10B981'; // green for Paid
    if (status === 'Pending') statusColor = '#F59E0B'; // amber
    if (status === 'Overdue') statusColor = '#EF4444'; // red

    const content = `
        <div class="receipt-card-content" style="font-family:'Inter', sans-serif;color:#1E1B4B;line-height:1.5;">
            <div style="text-align:center;margin-bottom:1.5rem;border-bottom:2px dashed #E5E7EB;padding-bottom:1.5rem;">
                <div style="font-size:1.5rem;font-weight:800;letter-spacing:-0.5px;color:#4F46E5;">Track<span style="color:#06B6D4;">It</span></div>
                <div style="font-size:0.8rem;color:#6B7280;margin-top:0.25rem;">Official Payment Receipt</div>
            </div>
            
            <div style="display:flex;justify-content:space-between;margin-bottom:1.5rem;font-size:0.85rem;">
                <div>
                    <div style="color:#9CA3AF;text-transform:uppercase;font-size:0.7rem;font-weight:700;letter-spacing:0.5px;">Billed To</div>
                    <div style="font-weight:600;font-size:1rem;margin-top:0.2rem;">${client}</div>
                </div>
                <div style="text-align:right;">
                    <div style="color:#9CA3AF;text-transform:uppercase;font-size:0.7rem;font-weight:700;letter-spacing:0.5px;">Receipt Info</div>
                    <div style="font-weight:600;margin-top:0.2rem;">Invoice #INV-${id.toString().padStart(3, '0')}</div>
                    <div style="color:#6B7280;font-size:0.8rem;margin-top:0.1rem;">Date: ${today}</div>
                </div>
            </div>

            <div style="background:#F3F4F8;border-radius:8px;padding:1rem;margin-bottom:1.5rem;font-size:0.9rem;">
                <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;border-bottom:1px solid #E5E7EB;padding-bottom:0.5rem;font-weight:600;color:#6B7280;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">
                    <span>Description</span>
                    <span>Amount</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-weight:500;padding:0.25rem 0;">
                    <span>Services Rendered / Project Deliverables</span>
                    <span style="font-weight:600;">FCFA ${formattedAmount}</span>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;border-top:2px solid #E5E7EB;padding-top:1rem;margin-bottom:1.5rem;">
                <div>
                    <span style="font-size:0.75rem;color:#9CA3AF;text-transform:uppercase;font-weight:700;letter-spacing:0.5px;display:block;">Payment Status</span>
                    <span style="display:inline-block;padding:0.25rem 0.6rem;border-radius:20px;font-size:0.75rem;font-weight:700;background:${statusColor}22;color:${statusColor};text-transform:uppercase;margin-top:0.25rem;">${status}</span>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:0.85rem;color:#6B7280;font-weight:500;">Total Amount</span>
                    <div style="font-size:1.5rem;font-weight:800;color:#1E1B4B;margin-top:0.1rem;">FCFA ${formattedAmount}</div>
                </div>
            </div>

            <div style="text-align:center;font-size:0.8rem;color:#9CA3AF;margin-top:2rem;border-top:1px solid #E5E7EB;padding-top:1rem;">
                Thank you for your business!
            </div>
        </div>
    `;
    document.getElementById('receiptContent').innerHTML = content;
    document.getElementById('receiptModal').classList.add('open');
}

function closeReceiptModal() {
    document.getElementById('receiptModal').classList.remove('open');
}

window.onbeforeprint = function() {
    if (document.getElementById('receiptModal').classList.contains('open')) {
        document.body.classList.add('printing-receipt');
    }
};

window.onafterprint = function() {
    document.body.classList.remove('printing-receipt');
};
</script>
</body>
</html>