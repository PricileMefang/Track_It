<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config.php";

$user_id = $_SESSION["user_id"] ?? 1;

// Fetch recurring expenses that are due today or in the past
$stmt = $conn->prepare("SELECT id, category_id, amount, description, recurrence_interval, next_occurrence FROM recurring_expenses WHERE user_id = ? AND next_occurrence <= CURDATE()");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $rec_id = $row['id'];
        $cat_id = $row['category_id'];
        $amount = $row['amount'];
        $desc = $row['description'];
        $interval = $row['recurrence_interval'];
        $occurrence_date = $row['next_occurrence'];

        // Insert into expenses
        $insert_stmt = $conn->prepare("INSERT INTO expenses (user_id, category_id, amount, description, expense_date, is_recurring_instance) VALUES (?, ?, ?, ?, ?, 1)");
        if ($insert_stmt) {
            $insert_stmt->bind_param("iidss", $user_id, $cat_id, $amount, $desc, $occurrence_date);
            $insert_stmt->execute();
            $insert_stmt->close();
        }

        // Calculate next occurrence
        $next_date = new DateTime($occurrence_date);
        if ($interval === 'weekly') {
            $next_date->modify('+1 week');
        } elseif ($interval === 'monthly') {
            $next_date->modify('+1 month');
        } elseif ($interval === 'yearly') {
            $next_date->modify('+1 year');
        }

        // Update recurring expense record
        $update_stmt = $conn->prepare("UPDATE recurring_expenses SET next_occurrence = ? WHERE id = ?");
        if ($update_stmt) {
            $formatted_date = $next_date->format('Y-m-d');
            $update_stmt->bind_param("si", $formatted_date, $rec_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
    $stmt->close();
}
?>
