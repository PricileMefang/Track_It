<?php
session_start();
$signIn_error = $_SESSION["SignIn_error"] ?? null;
unset($_SESSION["SignIn_error"], $_SESSION["register_error"], $_SESSION["success"], $_SESSION["active_form"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="SignUp.css">
    <link rel="icon" type="image/png" href="TrackIt Logo copy.png">
    <title>Sign In - TrackIt</title>
</head>
<body>
    <div class="signup-container">
        <img src="TrackIt Logo copy.png" alt="TrackIt Logo" class="header-logo">
        <h1>Sign In</h1>
        <?php if ($signIn_error): ?>
            <div class="message error-message"><?php echo htmlspecialchars($signIn_error); ?></div>
        <?php endif; ?>
        
        <form action="login_register.php" method="POST">
            <input type="hidden" name="SignIn" value="1">
            <!-- Email Address -->
            <div class="form-row full">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>

            <!-- Password -->
            <div class="form-row full">
                <label for="password">Password</label>
                <div class="password-input-container">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password"
                    >
                    <button type="button" class="toggle-password-btn" onclick="togglePassword('password')">
                        <img src="icons/Closed eye icon.png" alt="Toggle Password Visibility" class="eye-icon">
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit">Sign In</button>
        </form>

        <p>Don't have an account? <a href="SignUp.php">Sign Up Here!</a></p>
        <p>Back to <a href="index.html">Home</a></p>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
        }
    </script>
</body>
</html>
