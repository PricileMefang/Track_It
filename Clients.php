<?php
session_start();
require_once "config.php";
$user_id = $_SESSION["user_id"] ?? 1;
$username = $_SESSION["user_name"] ?? "User";
$success_message = ""; $error_message = "";

// ── Add client ────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["contact_name"])) {
    $contact_name = trim($_POST["contact_name"]);
    $phone_number = trim($_POST["phone_number"]);
    $company_name = trim($_POST["company_name"]);
    $email        = trim($_POST["email"]);
    $address      = trim($_POST["address"]);
    $edit_id      = intval($_POST["edit_id"] ?? 0);

    if (!empty($contact_name)) {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("UPDATE clients SET contact_name=?, phone_number=?, company_name=?, email=?, address=? WHERE id=? AND user_id=?");
            $stmt->bind_param("sssssii", $contact_name, $phone_number, $company_name, $email, $address, $edit_id, $user_id);
            $success_message = $stmt->execute() ? "Client updated!" : "Error: ".$stmt->error;
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO clients (user_id, contact_name, phone_number, company_name, email, address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $user_id, $contact_name, $phone_number, $company_name, $email, $address);
            $success_message = $stmt->execute() ? "Client added!" : "Error: ".$stmt->error;
            $stmt->close();
        }
    } else { $error_message = "Contact name is required."; }
}

// ── Delete ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM clients WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $did, $user_id); $stmt->execute(); $stmt->close();
    header("Location: Clients.php"); exit;
}

// ── Load for edit ─────────────────────────────────────────────
$edit_client = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $eid, $user_id); $stmt->execute(); $result=$stmt->get_result();
    $edit_client = $result->fetch_assoc(); $stmt->close();
}

// ── Fetch clients ─────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$q = "SELECT id, contact_name, phone_number, company_name, email, address FROM clients WHERE user_id=?";
$params = [$user_id]; $types = "i";
if ($search) { $q .= " AND (contact_name LIKE ? OR company_name LIKE ? OR email LIKE ?)"; $like = "%$search%"; $params[]=$like; $params[]=$like; $params[]=$like; $types.="sss"; }
$q .= " ORDER BY id DESC";
$stmt = $conn->prepare($q); $stmt->bind_param($types,...$params); $stmt->execute(); $result=$stmt->get_result();
$clients=[]; while($row=$result->fetch_assoc()) $clients[]=$row;
$stmt->close();

$active_page = 'clients';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients – TrackIt</title>
    <link rel="icon" type="image/png" href="TrackIt Logo copy.png">
    <link rel="stylesheet" href="trackit.css">
</head>
<body>
<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <section id="top-bar">
            <div class="top-bar-content">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <h1>Clients</h1>
                    <span style="color:var(--text-muted);font-size:0.875rem;"><?php echo count($clients); ?> total</span>
                </div>
                <div class="top-bar-user"><div class="user-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div><span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
                </div>
            </div>
        </section>

        <?php if($success_message): ?><div style="padding:0.75rem 2rem 0;"><div class="alert alert-success"><span class="alert-icon">✅</span><?php echo htmlspecialchars($success_message); ?></div></div><?php endif; ?>
        <?php if($error_message): ?><div style="padding:0.75rem 2rem 0;"><div class="alert alert-error"><span class="alert-icon">❌</span><?php echo htmlspecialchars($error_message); ?></div></div><?php endif; ?>

        <div class="page-body">
            <!-- Add / Edit Client -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><?php echo $edit_client ? '✏️ Edit Client' : '➕ Add New Client'; ?></span>
                </div>
                <form method="POST">
                    <?php if($edit_client): ?><input type="hidden" name="edit_id" value="<?php echo $edit_client['id']; ?>"><?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Contact Name *</label>
                            <input type="text" name="contact_name" class="form-control" placeholder="Full name" required
                                   value="<?php echo $edit_client ? htmlspecialchars($edit_client['contact_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone_number" class="form-control" placeholder="+237 6XX XXX XXX"
                                   value="<?php echo $edit_client ? htmlspecialchars($edit_client['phone_number']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company</label>
                            <input type="text" name="company_name" class="form-control" placeholder="Company name"
                                   value="<?php echo $edit_client ? htmlspecialchars($edit_client['company_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="email@example.com"
                                   value="<?php echo $edit_client ? htmlspecialchars($edit_client['email']) : ''; ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Street, City"
                                   value="<?php echo $edit_client ? htmlspecialchars($edit_client['address']) : ''; ?>">
                        </div>
                    </div>
                    <div style="margin-top:1rem;display:flex;gap:0.75rem;">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_client ? '💾 Save' : '+ Add Client'; ?></button>
                        <?php if($edit_client): ?><a href="Clients.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Search + Client List -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">👥 Client Directory</span>
                    <form method="GET" style="display:flex;gap:0.5rem;align-items:center;">
                        <input type="text" name="search" class="form-control" placeholder="Search…" style="width:220px;" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-ghost btn-sm">🔍</button>
                        <?php if($search): ?><a href="Clients.php" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
                    </form>
                </div>
                <div class="table-wrap">
                    <table class="trackit-table">
                        <thead>
                            <tr><th>#</th><th>Name</th><th>Company</th><th>Contact</th><th>Address</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clients)): ?>
                            <tr class="empty-row"><td colspan="6"><?php echo $search ? "No clients found for \"$search\"." : "No clients yet. Add your first one!"; ?></td></tr>
                            <?php else: ?>
                            <?php foreach($clients as $c):
                                $safe_name = htmlspecialchars($c['contact_name'], ENT_QUOTES);
                            ?>
                            <tr>
                                <td style="font-weight:700;color:var(--text-muted);"><?php echo $c['id']; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:0.6rem;">
                                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--brand-primary),var(--brand-accent));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.8rem;flex-shrink:0;">
                                            <?php echo strtoupper(substr($c['contact_name'],0,1)); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($c['contact_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($c['company_name'] ?: '—'); ?></td>
                                <td>
                                    <div style="font-size:0.82rem;">
                                        <?php if($c['email']): ?><div><?php echo htmlspecialchars($c['email']); ?></div><?php endif; ?>
                                        <?php if($c['phone_number']): ?><div style="color:var(--text-muted);"><?php echo htmlspecialchars($c['phone_number']); ?></div><?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-size:0.82rem;color:var(--text-muted);"><?php echo htmlspecialchars($c['address'] ?: '—'); ?></td>
                                <td>
                                    <div style="display:flex;gap:0.4rem;">
                                        <a href="Clients.php?edit=<?php echo $c['id']; ?>" class="btn btn-ghost btn-sm">✏️</a>
                                        <a href="Clients.php?delete=<?php echo $c['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete client <?php echo htmlspecialchars(addslashes($c['contact_name'])); ?>?')">🗑️</a>
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