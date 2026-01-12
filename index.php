<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>StudWork — Find Part-Time Jobs While You Study</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php
        include 'config.php';
    ?>
    <header class="topbar">
<div class="wrap">
    <div class="brand-container">
        <div class="brand-logo">
            <svg viewBox="0 0 24 24">
                <text x="12" y="12" text-anchor="middle" dominant-baseline="middle" class="logo-text">SW</text>
            </svg>
        </div>
        <div class="brand">
            <div class="title">STUDWORK</div>
            <div class="tag">Connecting Students and Employers</div>
        </div>
    </div>
</div>
</header>

    <main class="main-wrap">
        <div class="hero-card">
            <h1>Find Part-Time Jobs While You Study</h1>
            <p class="lead">Employers can post openings. Students can sign up, browse, and apply — all right here.</p>

            <div class="hero-actions" role="group" aria-label="Primary actions">
                <a href="create-account.php" class="huge-btn primary">Create Account</a>
                <a href="login.php" class="huge-btn ghost">Log In</a>
            </div>
        </div>

        <div class="spacer-28"></div>

        <div class="features">
            <div class="feature">
                <h3>Browse Jobs</h3>
                <p>Students can view job offers that align with their skill sets.</p>
            </div>
            <div class="feature">
                <h3>Post Jobs</h3>
                <p>Companies and Employers can post job offers.</p>
            </div>
            <div class="feature">
                <h3>Role-based Access</h3>
                <p>Sign up as a Student or Employer.</p>
            </div>
        </div>
    </main>

    <footer class="footer-strip">
        <div class="user-agreement">
            <a href="user-agreement.html">User Agreement</a> | <a href="privacy-policy.html">Privacy Policy</a> | <a href="mailto:support@studwork.ph">Contact Support</a>
        </div>
    </footer>

    <script>
        document.getElementById('year').textContent = new Date().getFullYear();
        // Buttons are placeholders; links should navigate, other buttons show demo alert
        document.querySelectorAll('.huge-btn').forEach(b => {
            if (b.matches && b.matches('a[href]')) return; // skip anchors (they navigate)
            b.addEventListener('click', () => alert('Demo: action placeholder'));
        });
    </script>
</body>
</html>
