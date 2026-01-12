<?php
session_start();
include 'config.php';

// 🔒 Protect page (admin only)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$admin_email = $_SESSION['email'] ?? '';

// Fetch admin name
$admin_name = '';
$stmt = $conn->prepare("SELECT fullname FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $admin_name = $res->fetch_assoc()['fullname'];
}
$stmt->close();

// Fetch all students
$students = [];
$student_result = $conn->query("
    SELECT id, fullname, email 
    FROM users 
    WHERE role='student' 
    ORDER BY fullname ASC
");
while ($row = $student_result->fetch_assoc()) {
    $students[] = $row;
}

// Fetch all employers
$employers = [];
$employer_result = $conn->query("
    SELECT id, fullname, email, company_name 
    FROM users 
    WHERE role='employer' 
    ORDER BY fullname ASC
");
while ($row = $employer_result->fetch_assoc()) {
    $employers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — StudWork</title>
<link rel="stylesheet" href="css/styles.css">
</head>
<body>

<header class="topbar">
    <div class="wrap">
        <div>
            <strong>STUDWORK</strong> — Admin Dashboard
        </div>
        <div class="user-info">
            <?= htmlspecialchars($admin_name) ?> |
            <a href="logout.php">Logout</a>
        </div>
    </div>
</header>

<div class="container">

<!-- STUDENTS -->
<div class="card">
<h3>Students</h3>

<table class="table">
<thead>
<tr>
    <th>Full Name</th>
    <th>Submitted Documents</th>
    <th>Jobs Applied</th>
</tr>
</thead>
<tbody>

<?php foreach ($students as $s): ?>
<tr>
<td><?= htmlspecialchars($s['fullname']) ?></td>

<td>
<?php
$docs = $conn->prepare("
    SELECT resume, valid_id, school_id, submitted_at
    FROM student_submissions
    WHERE student_email = ?
    ORDER BY submitted_at DESC
    LIMIT 1
");
$docs->bind_param("s", $s['email']);
$docs->execute();
$r = $docs->get_result();

if ($r->num_rows === 1) {
    $d = $r->fetch_assoc();
    echo "<a href='{$d['resume']}' target='_blank'>Resume</a><br>";
    echo "<a href='{$d['valid_id']}' target='_blank'>Valid ID</a><br>";
    echo "<a href='{$d['school_id']}' target='_blank'>School ID</a><br>";
    echo "<small>Submitted: ".date('M d, Y', strtotime($d['submitted_at']))."</small>";
} else {
    echo "No submission";
}
$docs->close();
?>
</td>

<td>
<?php
$jobs = $conn->prepare("
    SELECT jp.job_title
    FROM job_applications ja
    JOIN job_posts jp ON jp.id = ja.job_id
    WHERE ja.student_email = ?
");
$jobs->bind_param("s", $s['email']);
$jobs->execute();
$rj = $jobs->get_result();

if ($rj->num_rows > 0) {
    while ($j = $rj->fetch_assoc()) {
        echo htmlspecialchars($j['job_title']) . "<br>";
    }
} else {
    echo "No jobs applied";
}
$jobs->close();
?>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<!-- EMPLOYERS -->
<div class="card">
<h3>Employers</h3>

<table class="table">
<thead>
<tr>
    <th>Full Name</th>
    <th>Verified Documents</th>
    <th>Job Posts</th>
</tr>
</thead>
<tbody>

<?php foreach ($employers as $e): ?>
<tr>
<td><?= htmlspecialchars($e['fullname']) ?></td>

<td>
<?php
$docs = $conn->prepare("
    SELECT document_name, file_path
    FROM employer_documents
    WHERE employer_email = ?
");
$docs->bind_param("s", $e['email']);
$docs->execute();
$r = $docs->get_result();

if ($r->num_rows > 0) {
    while ($d = $r->fetch_assoc()) {
        echo "<a href='{$d['file_path']}' target='_blank'>{$d['document_name']}</a><br>";
    }
} else {
    echo "No documents";
}
$docs->close();
?>
</td>

<td>
<?php
$jobs = $conn->prepare("SELECT job_title FROM job_posts WHERE employer_email=?");
$jobs->bind_param("s", $e['email']);
$jobs->execute();
$rj = $jobs->get_result();

if ($rj->num_rows > 0) {
    while ($j = $rj->fetch_assoc()) {
        echo htmlspecialchars($j['job_title']) . "<br>";
    }
} else {
    echo "No job posts";
}
$jobs->close();
?>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

</div>

</body>
</html>

<?php $conn->close(); ?>
