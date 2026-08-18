<?php
// Shared sidebar include - include this in every page
// Requires $active_page variable to be set before including
// e.g. $active_page = 'dashboard';
if (!isset($active_page))
    $active_page = '';
?>
<section id="sidebar">
    <div class="sidebar-logo">
        <a href="Dashboard.php" style="text-decoration:none;">
            <div class="logo-mark">T</div>
            <span class="logo-text">Track<strong>It</strong></span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-item <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
            <span class="nav-icon">⌂</span>
            <a href="Dashboard.php">Dashboard</a>
        </div>
        <div class="nav-item <?php echo $active_page === 'clients' ? 'active' : ''; ?>">
            <span class="nav-icon">👥</span>
            <a href="Clients.php">Clients</a>
        </div>
        <div class="nav-item <?php echo $active_page === 'income' ? 'active' : ''; ?>">
            <span class="nav-icon">↑</span>
            <a href="Income.php">Income</a>
        </div>
        <div class="nav-item <?php echo $active_page === 'expenses' ? 'active' : ''; ?>">
            <span class="nav-icon">↓</span>
            <a href="Expenses.php">Expenses</a>
        </div>
        <div class="nav-item <?php echo $active_page === 'invoices' ? 'active' : ''; ?>">
            <span class="nav-icon">📄</span>
            <a href="Invoice.php">Invoices</a>
        </div>
        <div class="nav-item <?php echo $active_page === 'budget' ? 'active' : ''; ?>">
            <span class="nav-icon">💰</span>
            <a href="Budget.php">Budget</a>
        </div>
        <div class="nav-item <?php echo $active_page === 'reports' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <a href="Reports.php">Reports</a>
        </div>
        <div class="nav-item <?php echo $active_page === 'settings' ? 'active' : ''; ?>">
            <span class="nav-icon">⚙</span>
            <a href="Settings.php">Settings</a>
        </div>
        <div class="nav-item nav-logout">
            <span class="nav-icon">⏻</span>
            <a href="Logout.php">Logout</a>
        </div>
    </nav>
</section>