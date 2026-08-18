<?php
session_start();
include 'config.php';
$user_id = $_SESSION["user_id"] ?? 1;
$username = $_SESSION["user_name"] ?? "User";

$cur_m = (int)date('m'); $cur_y = (int)date('Y');
$success_message = ''; $error_message = '';

// ── Handle set/update budget ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_budget'])) {
    $cat_id  = intval($_POST['category_id'] ?? 0);
    $amount  = floatval($_POST['budget_amount'] ?? 0);
    if ($cat_id > 0 && $amount > 0) {
        // Compute rollover from previous month
        $stmt = $conn->prepare("SELECT amount, rollover_amount FROM budgets WHERE user_id=? AND category_id=? AND month=? AND year=?");
        $prev_m = $cur_m == 1 ? 12 : $cur_m-1; $prev_y = $cur_m == 1 ? $cur_y-1 : $cur_y;
        $stmt->bind_param("iiii",$user_id,$cat_id,$prev_m,$prev_y); $stmt->execute(); $stmt->bind_result($prev_budget,$prev_rollover); $stmt->fetch(); $stmt->close();

        $prev_spent_stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=? AND category_id=? AND MONTH(expense_date)=? AND YEAR(expense_date)=?");
        $prev_spent_stmt->bind_param("iiii",$user_id,$cat_id,$prev_m,$prev_y); $prev_spent_stmt->execute(); $prev_spent_stmt->bind_result($prev_spent); $prev_spent_stmt->fetch(); $prev_spent_stmt->close();
        $rollover = max(0, (($prev_budget ?? 0) + ($prev_rollover ?? 0)) - $prev_spent);

        $stmt = $conn->prepare("INSERT INTO budgets (user_id,category_id,amount,month,year,rollover_amount) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount), rollover_amount=VALUES(rollover_amount)");
        $stmt->bind_param("iidiid",$user_id,$cat_id,$amount,$cur_m,$cur_y,$rollover);
        if ($stmt->execute()) $success_message = "Budget saved!";
        else $error_message = "Error: " . $stmt->error;
        $stmt->close();
    }
}

// ── Handle add funds to savings goal ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_funds'])) {
    $goal_id = intval($_POST['goal_id'] ?? 0);
    $funds   = floatval($_POST['funds_amount'] ?? 0);
    if ($goal_id > 0 && $funds > 0) {
        $stmt = $conn->prepare("UPDATE savings_goals SET saved_amount = LEAST(saved_amount + ?, target_amount) WHERE id=? AND user_id=?");
        $stmt->bind_param("dii", $funds, $goal_id, $user_id);
        if ($stmt->execute()) $success_message = "Funds added to goal!";
        $stmt->close();
    }
}

// ── Handle create savings goal ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_goal'])) {
    $gname  = trim($_POST['goal_name'] ?? '');
    $gtarget = floatval($_POST['target_amount'] ?? 0);
    $gdeadline = $_POST['deadline'] ?? null;
    $gcolor  = $_POST['goal_color'] ?? '#4F46E5';
    if ($gname && $gtarget > 0) {
        $stmt = $conn->prepare("INSERT INTO savings_goals (user_id, goal_name, target_amount, deadline, color) VALUES (?,?,?,?,?)");
        $stmt->bind_param("isdss",$user_id,$gname,$gtarget,$gdeadline,$gcolor);
        if ($stmt->execute()) $success_message = "Savings goal created!";
        $stmt->close();
    }
}

// ── Load budget data with rollover ────────────────────────────
$budget_items = [];
$total_budget = 0; $total_spent = 0;
$stmt = $conn->prepare("SELECT ec.id, ec.category_name, ec.color_code,
    IFNULL(b.amount,0) as budget_amount,
    IFNULL(b.rollover_amount,0) as rollover,
    IFNULL(SUM(e.amount),0) AS spent
    FROM expense_categories ec
    LEFT JOIN budgets b ON b.category_id=ec.id AND b.user_id=? AND b.month=? AND b.year=?
    LEFT JOIN expenses e ON e.category_id=ec.id AND e.user_id=? AND MONTH(e.expense_date)=? AND YEAR(e.expense_date)=?
    GROUP BY ec.id ORDER BY spent DESC");
$stmt->bind_param("iiiiii",$user_id,$cur_m,$cur_y,$user_id,$cur_m,$cur_y);
$stmt->execute(); $result=$stmt->get_result();
$alerts = [];
while($row=$result->fetch_assoc()){
    $budget    = floatval($row['budget_amount']) + floatval($row['rollover']);
    $spent     = floatval($row['spent']);
    $progress  = $budget > 0 ? min(100, ($spent/$budget)*100) : 0;
    $over      = $budget > 0 && $spent > $budget;
    $near      = $budget > 0 && $progress >= 80 && !$over;
    if ($over)  $alerts[] = "🔴 <strong>" . htmlspecialchars($row['category_name']) . "</strong>: Budget exceeded by FCFA " . number_format($spent-$budget,0,',','.');
    if ($near)  $alerts[] = "🟡 <strong>" . htmlspecialchars($row['category_name']) . "</strong>: " . round($progress) . "% of budget used.";
    $budget_items[] = [
        'id'=> $row['id'], 'name'=>$row['category_name'], 'color'=>$row['color_code']??'#4F46E5',
        'budget'=>$budget, 'spent'=>$spent, 'rollover'=>floatval($row['rollover']),
        'progress'=>$progress, 'over'=>$over, 'near'=>$near,
        'raw_budget'=>floatval($row['budget_amount'])
    ];
    $total_budget += $budget; $total_spent += $spent;
}
$stmt->close();

// ── Savings Goals ─────────────────────────────────────────────
$goals = [];
$res = $conn->prepare("SELECT * FROM savings_goals WHERE user_id=? ORDER BY user_id DESC");
$res->bind_param("i",$user_id); $res->execute(); $result=$res->get_result();
while($row=$result->fetch_assoc()) $goals[]=$row;
$res->close();

$overall_progress = $total_budget > 0 ? min(100, ($total_spent/$total_budget)*100) : 0;
$active_page = 'budget';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget & Goals – TrackIt</title>
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
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <h1>Budget &amp; Goals</h1>
                    <span style="color:var(--text-muted);font-size:0.875rem;"><?php echo date('F Y'); ?></span>
                </div>
                <div class="top-bar-user"><div class="user-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div><span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
                </div>
            </div>
        </section>

        <div class="page-body">
            <?php if($success_message): ?><div class="alert alert-success"><span class="alert-icon">✅</span><?php echo $success_message; ?></div><?php endif; ?>
            <?php if($error_message): ?><div class="alert alert-error"><span class="alert-icon">❌</span><?php echo $error_message; ?></div><?php endif; ?>
            <?php foreach($alerts as $a): ?><div class="alert alert-warning"><span class="alert-icon">⚠️</span><div><?php echo $a; ?></div></div><?php endforeach; ?>

            <!-- OVERALL SPENDING LIMIT -->
            <div class="card">
                <div class="card-header"><span class="card-title">📊 Overall Budget Utilization</span></div>
                <div style="display:flex;justify-content:space-between;font-size:0.9rem;margin-bottom:0.5rem;">
                    <span>Spent: <strong>FCFA <?php echo number_format($total_spent,0,',','.'); ?></strong></span>
                    <span>Budget: <strong>FCFA <?php echo number_format($total_budget,0,',','.'); ?></strong></span>
                    <span><?php echo round($overall_progress); ?>%</span>
                </div>
                <div class="progress-wrap" style="height:14px;">
                    <div class="progress-bar <?php echo $overall_progress>=90?'danger':''; ?>" style="width:<?php echo round($overall_progress); ?>%;"></div>
                </div>
                <p style="font-size:0.8rem;color:var(--text-muted);margin-top:0.4rem;">
                    Remaining: FCFA <?php echo number_format(max(0,$total_budget-$total_spent),0,',','.'); ?>
                </p>
            </div>

            <!-- SET BUDGET FORM -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">💰 Set Monthly Budget</span>
                </div>
                <form method="POST">
                    <input type="hidden" name="set_budget" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Choose category…</option>
                                <?php foreach($budget_items as $bi): ?>
                                <option value="<?php echo $bi['id']; ?>"><?php echo htmlspecialchars($bi['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Budget Amount (FCFA)</label>
                            <input type="number" name="budget_amount" class="form-control" placeholder="e.g. 50000" min="1" required>
                        </div>
                    </div>
                    <div style="margin-top:0.75rem;"><button type="submit" class="btn btn-primary">💾 Save Budget</button></div>
                </form>
            </div>

            <!-- BUDGET BY CATEGORY -->
            <div class="card">
                <div class="card-header"><span class="card-title">📂 Category Budgets</span></div>
                <?php if(empty($budget_items)): ?>
                <p style="color:var(--text-muted);">No categories found.</p>
                <?php else: ?>
                <?php foreach($budget_items as $item): ?>
                <div class="budget-item">
                    <div class="budget-item-row">
                        <span class="budget-cat-name">
                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo htmlspecialchars($item['color']); ?>;margin-right:6px;vertical-align:middle;"></span>
                            <?php echo htmlspecialchars($item['name']); ?>
                            <?php if($item['rollover'] > 0): ?><span style="font-size:0.72rem;color:var(--brand-success);margin-left:6px;">+FCFA <?php echo number_format($item['rollover'],0,',','.'); ?> rollover</span><?php endif; ?>
                        </span>
                        <span class="budget-amounts">
                            FCFA <?php echo number_format($item['spent'],0,',','.'); ?>
                            <?php if($item['budget'] > 0): ?> / FCFA <?php echo number_format($item['budget'],0,',','.'); ?><?php endif; ?>
                        </span>
                    </div>
                    <?php if($item['budget'] > 0): ?>
                    <div class="progress-wrap">
                        <div class="progress-bar <?php echo $item['over']?'danger':($item['near']?'':''); ?>" style="width:<?php echo round($item['progress']); ?>%;"></div>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.72rem;color:var(--text-muted);margin-top:0.2rem;">
                        <span><?php echo round($item['progress']); ?>% used</span>
                        <?php if($item['over']): ?><span style="color:var(--brand-danger);">Overspent!</span><?php else: ?><span>FCFA <?php echo number_format($item['budget']-$item['spent'],0,',','.'); ?> remaining</span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- SAVINGS GOALS -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">🎯 Savings Goals</span>
                    <button class="btn btn-ghost btn-sm" onclick="document.getElementById('goalForm').classList.toggle('hidden')">+ New Goal</button>
                </div>
                <div id="goalForm" class="hidden" style="margin-bottom:1.25rem;padding:1rem;background:var(--bg-page);border-radius:var(--radius-sm);">
                    <form method="POST">
                        <input type="hidden" name="create_goal" value="1">
                        <div class="form-grid">
                            <div class="form-group" style="grid-column:span 2;">
                                <label class="form-label">Goal Name</label>
                                <input type="text" name="goal_name" class="form-control" placeholder="e.g. Emergency Fund" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Target Amount (FCFA)</label>
                                <input type="number" name="target_amount" class="form-control" placeholder="500000" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Deadline (optional)</label>
                                <input type="date" name="deadline" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Color</label>
                                <input type="color" name="goal_color" class="form-control" value="#4F46E5" style="height:42px;padding:0.2rem;">
                            </div>
                        </div>
                        <div style="margin-top:0.75rem;display:flex;gap:0.5rem;">
                            <button type="submit" class="btn btn-primary">Create Goal</button>
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('goalForm').classList.add('hidden')">Cancel</button>
                        </div>
                    </form>
                </div>

                <?php if(empty($goals)): ?>
                <p style="color:var(--text-muted);font-size:0.875rem;">No savings goals yet. Create one above!</p>
                <?php else: ?>
                <div class="goals-grid">
                    <?php foreach($goals as $g):
                        $pct = $g['target_amount'] > 0 ? min(100, ($g['saved_amount']/$g['target_amount'])*100) : 0;
                    ?>
                    <div class="goal-card">
                        <div class="goal-card-name" style="color:<?php echo htmlspecialchars($g['color']??'#4F46E5'); ?>">
                            🎯 <?php echo htmlspecialchars($g['goal_name']); ?>
                        </div>
                        <?php if($g['deadline']): ?>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.4rem;">Deadline: <?php echo $g['deadline']; ?></div>
                        <?php endif; ?>
                        <div class="goal-amounts">
                            <span>Saved: FCFA <?php echo number_format($g['saved_amount'],0,',','.'); ?></span>
                            <span>Target: FCFA <?php echo number_format($g['target_amount'],0,',','.'); ?></span>
                        </div>
                        <div class="progress-wrap">
                            <div class="progress-bar" style="width:<?php echo round($pct); ?>%;background:<?php echo htmlspecialchars($g['color']??'#4F46E5'); ?>;"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;margin-bottom:0.75rem;">
                            <span><?php echo round($pct); ?>% complete</span>
                            <?php if($pct >= 100): ?><span style="color:var(--brand-success);">✅ Achieved!</span><?php else: ?><span>FCFA <?php echo number_format($g['target_amount']-$g['saved_amount'],0,',','.'); ?> to go</span><?php endif; ?>
                        </div>
                        <?php if($pct < 100): ?>
                        <form method="POST" style="display:flex;gap:0.5rem;align-items:center;">
                            <input type="hidden" name="goal_id" value="<?php echo $g['id']; ?>">
                            <input type="hidden" name="add_funds" value="1">
                            <input type="number" name="funds_amount" class="form-control" placeholder="Amount" style="flex:1;font-size:0.8rem;" min="1">
                            <button type="submit" class="btn btn-success btn-sm">Add Funds</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<style>.hidden{display:none!important;}</style>
</body>
</html>