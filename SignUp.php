<?php
session_start();
$signIn_error = $_SESSION["SignIn_error"] ?? null;
$register_error = $_SESSION["register_error"] ?? null;
$success = $_SESSION["success"] ?? null;
unset($_SESSION["SignIn_error"], $_SESSION["register_error"], $_SESSION["success"], $_SESSION["active_form"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="SignUp.css">
    <link rel="icon" type="image/png" href="TrackIt Logo copy.png">
    <title>Sign Up - TrackIt</title>
</head>
<body>
    <div class="signup-container">
        <img src="TrackIt Logo copy.png" alt="TrackIt Logo" class="header-logo">
        
        <?php if ($register_error): ?>
            <div class="message error-message"><?php echo htmlspecialchars($register_error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="message success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="login_register.php" method="POST">
            <!-- Full Name and Business Name Side-by-Side -->
            <div class="form-row">
                <div>
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" placeholder="John Doe" required>
                </div>
                <div>
                    <label for="business_name">Business Name</label>
                    <input type="text" id="business_name" name="business_name" placeholder="Your Company" required>
                </div>
            </div>

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
                        required
                        minlength="8"
                    >
                    <button type="button" class="toggle-password-btn" onclick="togglePassword('password')">
                        <img src="icons/Closed eye icon.png" alt="Toggle Password Visibility" class="eye-icon">
                    </button>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-row full">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-input-container">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                    <button type="button" class="toggle-password-btn" onclick="togglePassword('confirm_password')">
                        <img src="icons/Closed eye icon.png" alt="Toggle Password Visibility" class="eye-icon">
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" name="register">Create Account</button>
        </form>

        <p>Already have an account? <a href="SignIn.php">Sign In Here!</a></p>
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