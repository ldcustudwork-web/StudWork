<?php
session_start();
include 'config.php';

// 🔒 Protect page (employer only)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer') {
    header('Location: login.php');
    exit;
}

$isModal = isset($_GET['modal']);

// ❗ Database check
if ($conn->connect_error) {
    die("Database connection failed");
}

$employer_email = $_SESSION['email'];
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

// 🔐 Verify job belongs to employer
$check = $conn->prepare("
    SELECT id, job_title 
    FROM job_posts 
    WHERE id = ? AND employer_email = ?
");
$check->bind_param("is", $job_id, $employer_email);
$check->execute();
$job = $check->get_result()->fetch_assoc();
$check->close();

if (!$job) {
    die("Unauthorized access.");
}

// ================= HANDLE ACCEPT / REJECT =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {

    $application_id = intval($_POST['application_id']);
    $status = null;

    if (isset($_POST['accept'])) {
        $status = 'accepted';
    } elseif (isset($_POST['reject'])) {
        $status = 'rejected';
    }

    if ($status) {
        $stmt = $conn->prepare("
            UPDATE job_applications
            SET status = ?
            WHERE id = ? AND employer_email = ?
        ");
        $stmt->bind_param("sis", $status, $application_id, $employer_email);
        $stmt->execute();
        $stmt->close();
    }
}

// ================= FETCH APPLICANTS =================
$stmt = $conn->prepare("
    SELECT 
        ja.id AS application_id,
        ja.status,
        ja.applied_at,

        u.fullname,
        u.email AS student_email,

        ss.phone,
        ss.resume,
        ss.valid_id,
        ss.school_id

    FROM job_applications ja
    JOIN users u ON u.email = ja.student_email
    LEFT JOIN student_submissions ss ON ss.student_email = ja.student_email

    WHERE ja.job_id = ?
    ORDER BY ja.applied_at DESC
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$applicants = $stmt->get_result();
$stmt->close();

// ✅ Escape helper (PHP 8.1 safe)
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<?php if (!$isModal): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Applicants — <?= e($job['job_title']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="css/styles.css">
</head>
<body>
<a href="employer.php" class="back">← Back to Jobs</a>
<?php endif; ?>

<h2>Applicants for: <?= e($job['job_title']) ?></h2>

<?php if ($applicants->num_rows === 0): ?>
    <p>No applicants yet.</p>
<?php endif; ?>

<?php while ($row = $applicants->fetch_assoc()): ?>
<div class="card">

    <div class="applicant-header" style="    display: flex;
    flex-direction: column;
    gap: 5px;">
        <strong><?= e($row['fullname']) ?></strong>
        <small><?= e($row['student_email']) ?></small>
        <span class="status">
            Status:
            <span class="status-badge 
                <?= $row['status'] === 'accepted' ? 'status-green' : 
                   ($row['status'] === 'rejected' ? 'status-red' : 'status-orange') ?>">
                <?= strtoupper(e($row['status'])) ?>
            </span>
        </span>
    </div>

    <div class="applicant-details" style="    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-top: 10px;">
        <div class="detail-item">
            📞 <?= e($row['phone'] ?: 'N/A') ?>
        </div>
        <div class="detail-item">
            <?php if (!empty($row['resume'])): ?>
                📄 <a href="<?= e($row['resume']) ?>" target="_blank">Resume</a>
            <?php else: ?>
                📄 Resume: Not submitted
            <?php endif; ?>
        </div>
        <div class="detail-item">
            <?php if (!empty($row['valid_id'])): ?>
                🪪 <a href="<?= e($row['valid_id']) ?>" target="_blank">Valid ID</a>
            <?php else: ?>
                🪪 Valid ID: Not submitted
            <?php endif; ?>
        </div>
        <div class="detail-item">
            🏫 School ID: <?= e($row['school_id'] ?: 'N/A') ?>
        </div>
    </div>

    <?php if ($row['status'] === 'pending'): ?>
    <form method="POST" class="form-actions">
        <input type="hidden" name="application_id" value="<?= e($row['application_id']) ?>">
        <button type="submit" name="accept" class="accept">Accept</button>
        <button type="submit" name="reject" class="reject">Reject</button>
    </form>
    <?php endif; ?>

</div>
<?php endwhile; ?>

<?php if (!$isModal): ?>
</body>
</html>
<?php endif; ?>
