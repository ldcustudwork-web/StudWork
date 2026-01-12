<?php
session_start();

$message = '';
$messageType = '';

// ✅ DATABASE CONNECTION
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $message = 'Please provide both email and password.';
        $messageType = 'error';
    } else {

        // ✅ FETCH USER BY EMAIL
        $stmt = $conn->prepare("SELECT fullname, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // ✅ VERIFY PASSWORD
            if (password_verify($password, $user['password'])) {

                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['fullname'] = $user['fullname'];

                // ✅ ROLE-BASED REDIRECT
                switch ($user['role']) {
                    case 'student':
                        header("Location: student.php");
                        exit;
                    case 'employer':
                        header("Location: employer.php");
                        exit;
                    case 'admin':
                        header("Location: admin.php");
                        exit;
                    default:
                        $message = 'Invalid user role.';
                        $messageType = 'error';
                }

            } else {
                $message = 'Invalid email or password.';
                $messageType = 'error';
            }

        } else {
            $message = 'Invalid email or password.';
            $messageType = 'error';
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Log In — StudWork</title>
<link rel="stylesheet" href="css/styles.css">
</head>
<body>

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

<div class="pattern">
<main class="main-wrap">
<div class="panel">
<h1>Log In</h1>
<p class="lead">Sign in to your StudWork account.</p>

<form method="POST" action="">
<label>Email</label>
<input type="email" name="email" required>

<label>Password</label>
<input type="password" name="password" required>

<div class="actions">
       <button type="button" class="btn ghost" onclick="window.location.href='index.php'">Back</button>
    <button type="submit" class="btn">Log In</button>
</div>

<?php if ($message): ?>
<div class="msg <?= $messageType ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>
</form>
</div>
    </main>
    </div>

    <footer class="footer-strip">
        <div class="user-agreement">
            <a href="user-agreement.html">User Agreement</a> | <a href="privacy-policy.html">Privacy Policy</a> | <a href="mailto:support@studwork.ph">Contact Support</a>
        </div>
    </footer>

</body>
</html>
