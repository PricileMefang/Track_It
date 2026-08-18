<?php
// Settings actions handler
session_start();
require_once "config.php";
$user_id = $_SESSION["user_id"] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_rule') {
    $kw  = strtolower(trim($_POST['keyword'] ?? ''));
    $cid = intval($_POST['rule_category_id'] ?? 0);
    if ($kw && $cid > 0) {
        $stmt = $conn->prepare("INSERT IGNORE INTO category_rules (user_id, keyword, category_id) VALUES (?,?,?)");
        $stmt->bind_param("isi", $user_id, $kw, $cid); $stmt->execute(); $stmt->close();
    }
}

if (isset($_GET['delete_rule'])) {
    $rid = (int)$_GET['delete_rule'];
    $stmt = $conn->prepare("DELETE FROM category_rules WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $rid, $user_id); $stmt->execute(); $stmt->close();
}

header("Location: Settings.php"); exit;
