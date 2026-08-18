<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "config.php";

/* REGISTER */
if (isset($_POST["register"])) {

    $name             = trim($_POST["full_name"] ?? "");
    $email            = trim($_POST["email"] ?? "");
    $password         = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($name === "" || $email === "" || $password === "" || $confirm_password === "") {
        $_SESSION["register_error"] = "All fields are required.";
        $_SESSION["active_form"] = "register";
        header("Location: SignUp.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["register_error"] = "Please enter a valid email address.";
        $_SESSION["active_form"] = "register";
        header("Location: SignUp.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION["register_error"] = "Passwords do not match.";
        $_SESSION["active_form"] = "register";
        header("Location: SignUp.php");
        exit();
    }

    if (strlen($password) < 8) {
        $_SESSION["register_error"] = "Password must be at least 8 characters.";
        $_SESSION["active_form"] = "register";
        header("Location: SignUp.php");
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {

        $_SESSION["register_error"] = "Email is already registered";
        $_SESSION["active_form"] = "register";
        $stmt->close();

    } else {

        $stmt->close();

        // Insert new user
        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
        );

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("sss", $name, $email, $hashedPassword);

        if ($stmt->execute()) {
            $_SESSION["success"] = "Registration successful. Please SignIn.";
        } else {
            $_SESSION["register_error"] = "Registration failed.";
            $_SESSION["active_form"] = "register";
        }

        $stmt->close();
    }

    header("Location: SignUp.php");
    exit();
}


/* LOGIN */
if (isset($_POST["SignIn"])) {

    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $_SESSION["SignIn_error"] = "Please enter both email and password.";
        $_SESSION["active_form"] = "SignIn";
        header("Location: SignIn.php");
        exit();
    }

    $stmt = $conn->prepare(
        "SELECT id, name, password FROM users WHERE email = ?"
    );

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];

            $stmt->close();
            header("Location: Dashboard.php");
            exit();

        } else {

            $_SESSION["SignIn_error"] = "Invalid email or password";
            $_SESSION["active_form"] = "SignIn";
        }

    } else {

        $_SESSION["SignIn_error"] = "Invalid email or password";
        $_SESSION["active_form"] = "SignIn";
    }

    $stmt->close();

    header("Location: SignIn.php");
    exit();
}