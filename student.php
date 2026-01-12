<?php
session_start();

// 🔒 Protect page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

// ✅ Database connection
include 'config.php';

// ✅ Fetch student data
$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header('Location: logout.php');
    exit;
}

$user = $result->fetch_assoc();
$userId = $user['id'];
$fullname = $user['fullname'];
$stmt->close();

$message = '';
$messageType = '';
$activeTab = $_POST['tab'] ?? 'browse';

// ✅ Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Handle credentials submission ---
    if (isset($_POST['submit_credentials'])) {
        $activeTab = 'profile';
        $phone = $_POST['phone'] ?? '';
        $email_form = $_POST['email_form'] ?? '';

        if (!$phone || !$email_form) {
            $message = 'Please fill in all fields.';
            $messageType = 'error';
        } elseif (!isset($_FILES['resume'], $_FILES['valid_id'], $_FILES['school_id'])) {
            $message = 'Please upload all required documents.';
            $messageType = 'error';
        } else {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $timestamp = time();
            $resume_path = $upload_dir . $timestamp . '_' . basename($_FILES['resume']['name']);
            $valid_id_path = $upload_dir . $timestamp . '_' . basename($_FILES['valid_id']['name']);
            $school_id_path = $upload_dir . $timestamp . '_' . basename($_FILES['school_id']['name']);

            if (move_uploaded_file($_FILES['resume']['tmp_name'], $resume_path) &&
                move_uploaded_file($_FILES['valid_id']['tmp_name'], $valid_id_path) &&
                move_uploaded_file($_FILES['school_id']['tmp_name'], $school_id_path)) {

                $stmt = $conn->prepare("INSERT INTO student_submissions 
                    (student_email, phone, email, resume, valid_id, school_id, submitted_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE phone=VALUES(phone), email=VALUES(email), resume=VALUES(resume), valid_id=VALUES(valid_id), school_id=VALUES(school_id), submitted_at=NOW()");
                $stmt->bind_param("ssssss", $email, $phone, $email_form, $resume_path, $valid_id_path, $school_id_path);
                if ($stmt->execute()) {
                    $message = 'Documents submitted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to save submission.';
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = 'Failed to upload files.';
                $messageType = 'error';
            }
        }
    }

    // --- Handle job application ---
    if (isset($_POST['apply_job'])) {
        $activeTab = 'applications';
        $job_id = intval($_POST['job_id']);
        $employer_email = $_POST['employer_email'];

        $check = $conn->prepare("SELECT id FROM job_applications WHERE job_id = ? AND student_email = ?");
        $check->bind_param("is", $job_id, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            $apply = $conn->prepare("INSERT INTO job_applications 
                (job_id, employer_email, student_email, status, applied_at) 
                VALUES (?, ?, ?, 'pending', NOW())");
            $apply->bind_param("iss", $job_id, $employer_email, $email);
            if ($apply->execute()) {
                $message = 'Application submitted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to submit application.';
                $messageType = 'error';
            }
            $apply->close();
        } else {
            $message = 'You have already applied for this job.';
            $messageType = 'error';
        }
        $check->close();
    }

    // --- Handle preferences submission ---
    if (isset($_POST['submit_preferences'])) {
        $activeTab = 'profile';
        $job_pref_1 = $_POST['job_pref_1'] ?? '';
        $job_pref_2 = $_POST['job_pref_2'] ?? '';
        $job_pref_3 = $_POST['job_pref_3'] ?? '';
        $job_pref_4 = $_POST['job_pref_4'] ?? '';
        $time_preference = implode(' | ', $_POST['time_preference'] ?? []);
        $location_text = implode(' | ', $_POST['location_text'] ?? []);

        $stmt = $conn->prepare("
            INSERT INTO user_preferences 
            (user_id, job_pref_1, job_pref_2, job_pref_3, job_pref_4, time_preference, location_text)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                job_pref_1=VALUES(job_pref_1), job_pref_2=VALUES(job_pref_2), job_pref_3=VALUES(job_pref_3), job_pref_4=VALUES(job_pref_4),
                time_preference=VALUES(time_preference), location_text=VALUES(location_text)
        ");
        $stmt->bind_param("issssss", $userId, $job_pref_1, $job_pref_2, $job_pref_3, $job_pref_4, $time_preference, $location_text);
        if ($stmt->execute()) {
            $message = 'Preferences saved successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to save preferences.';
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// ✅ Fetch jobs, applications, submissions
$jobs = [];
$jobStmt = $conn->prepare("SELECT id, employer_email, job_title, job_type, job_description, posted_at FROM job_posts ORDER BY posted_at DESC");
$jobStmt->execute();
$jobResult = $jobStmt->get_result();
while ($row = $jobResult->fetch_assoc()) $jobs[] = $row;
$jobStmt->close();

$applications = [];
$stmt = $conn->prepare("SELECT ja.job_id, ja.status FROM job_applications ja WHERE ja.student_email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $applications[$row['job_id']] = $row['status'];
$stmt->close();

$pending = [];
$stmt = $conn->prepare("SELECT ja.job_id, jp.job_title, jp.job_type, ja.status, ja.applied_at
                        FROM job_applications ja
                        JOIN job_posts jp ON jp.id = ja.job_id
                        WHERE ja.student_email = ? ORDER BY ja.applied_at DESC");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $pending[] = $row;
$stmt->close();

$submission = null;
$stmt = $conn->prepare("SELECT student_email, phone, email, resume, valid_id, school_id, submitted_at FROM student_submissions WHERE student_email = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) $submission = $res->fetch_assoc();
$stmt->close();

// ✅ Fetch preferences
$prefs = null;
$stmtPref = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ? LIMIT 1");
$stmtPref->bind_param("i", $userId);
$stmtPref->execute();
$resPref = $stmtPref->get_result();
if ($resPref->num_rows > 0) $prefs = $resPref->fetch_assoc();
$stmtPref->close();


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Student Dashboard — StudWork</title>
<link rel="stylesheet" href="css/styles.css">
<script>
function switchTab(tabName) {
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.style.display = 'none');
    
    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabName).style.display = 'block';
    event.target.classList.add('active');
}

function previewFile(input, previewId) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e){
            document.getElementById(previewId).src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
}
</script>
<style>
    .submitted-documents h3, .contact-info-section h3{    margin-bottom: 20px;}
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 3px solid #ddd;
    background: #ffffff;
    padding: 12px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    position: relative;
    z-index: 10;
}
.tab-btn {
    padding: 12px 20px;
    background: transparent;
    border: none;
    cursor: pointer;
    font-weight: 600;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    position: relative;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.95rem;
}
.tab-btn::before {
    content: '';
    position: absolute;
    bottom: -3px;
    left: 0;
    width: 0;
    height: 3px;
    background: linear-gradient(90deg, #7a0000, #b33a3a);
    transition: width 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.tab-btn.active {
    color: #7a0000;
    text-shadow: 0 2px 4px rgba(122, 0, 0, 0.1);
}
.tab-btn.active::before {
    width: 100%;
}
.tab-btn:hover {
    color: #7a0000;
    transform: translateY(-2px);
    background: rgba(122, 0, 0, 0.05);
    border-radius: 8px;
}
.tab-btn:hover::before {
    width: 100%;
}
.tab-content {
    display: none;
    background: #ffffff;
    padding: 25px;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    position: relative;
    z-index: 10;
    animation: slideIn 0.5s ease-out;
}
.tab-content.active {
    display: block;
}
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    text-align: center;
    flex: 1;
    min-width: 150px;
}
.stat-card h3 {
    margin: 0;
    font-size: 2rem;
    color: #7a0000;
}
.stat-card p {
    margin: 8px 0 0;
    color: #666;
    font-size: 0.9rem;
}
.stats-row {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}
.message {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid;
}
.message.success {
    background: #eaf8ef;
    border-left-color: #3ca66b;
    color: #0b6b34;
}
.message.error {
    background: #fff1f1;
    border-left-color: #d34b4b;
    color: #8b1e1e;
}

/* Preferences container */
.preferences-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 15px;
}

/* Individual preference card */
.preference-card {
    flex: 1;
    min-width: 250px;
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border: 1px solid #f0f0f0;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}


.preference-card h3 {
    font-size: 1.1rem;
    margin-bottom: 12px;
    color: #7a0000;
    display: flex;
    align-items: center;
    gap: 6px;
}

.preference-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.preference-list li {
    padding: 6px 0;
    color: #333;
    font-size: 0.95rem;
    border-bottom: 1px dashed #eee;
}

.preference-list li:last-child {
    border-bottom: none;
}

.no-preferences {
    color: #777;
    font-style: italic;
    margin-top: 15px;
}

</style>
</head>
<body>
<header class="topbar">
<div class="wrap">
    <div class="brand-container">
        <div class="brand-logo">
            <svg viewBox="0 0 24 24"><text x="12" y="12" text-anchor="middle" dominant-baseline="middle" class="logo-text">SW</text></svg>
        </div>
        <div class="brand">
            <div class="title">STUDWORK</div>
            <div class="tag">Connecting Students and Employers</div>
        </div>
    </div>
    <div class="user-info">
        <span>Welcome, <?= htmlspecialchars($fullname) ?></span>
        <a href="logout.php">Logout</a>
    </div>
</div>
</header>

<main class="main-wrap">
    <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="tabs">
        <button class="tab-btn <?= $activeTab === 'browse' ? 'active' : '' ?>" onclick="switchTab('browse-tab'); this.classList.add('active'); document.querySelectorAll('.tab-btn').forEach(b => b !== this && b.classList.remove('active'))">
            📋 Browse Jobs
        </button>
        <button class="tab-btn <?= $activeTab === 'applications' ? 'active' : '' ?>" onclick="switchTab('applications-tab'); this.classList.add('active'); document.querySelectorAll('.tab-btn').forEach(b => b !== this && b.classList.remove('active'))">
            📝 My Applications
        </button>
        <button class="tab-btn <?= $activeTab === 'profile' ? 'active' : '' ?>" onclick="switchTab('profile-tab'); this.classList.add('active'); document.querySelectorAll('.tab-btn').forEach(b => b !== this && b.classList.remove('active'))">
            👤 My Profile
        </button>
    </div>

    <!-- Browse Jobs Tab -->
    <div id="browse-tab" class="tab-content <?= $activeTab === 'browse' ? 'active' : '' ?>">
        <div class="stats-row">
            <div class="stat-card">
                <h3><?= count($jobs) ?></h3>
                <p>Available Jobs</p>
            </div>
            <div class="stat-card">
                <h3><?= count($pending) ?></h3>
                <p>Applications Submitted</p>
            </div>
            <div class="stat-card">
                <h3><?= count(array_filter($pending, fn($p) => $p['status'] === 'accepted')) ?></h3>
                <p>Accepted</p>
            </div>
        </div>

        <div class="job-container">
            <h2>Available Job Opportunities</h2>
            
            <?php if(empty($jobs)): ?>
                <div class="empty-state">
                    <p>No job postings available at the moment. Please check back soon!</p>
                </div>
            <?php else: ?>
                <div class="jobs-list">
                    <?php foreach($jobs as $job): ?>
                        <div class="job-card">
                            <div class="job-card-header">
                                <div class="job-card-content">
                                    <h4 class="job-card-title"><?= htmlspecialchars($job['job_title']) ?></h4>
                                    <div class="job-card-meta">
                                        <span>📌 <strong><?= htmlspecialchars($job['job_type']) ?></strong></span>
                                        <span>🏢 <?= htmlspecialchars($job['employer_email']) ?></span>
                                    </div>
                                    <p class="job-card-description"><?= htmlspecialchars(substr($job['job_description'], 0, 150)) ?>...</p>
                                    <small class="job-card-date">Posted: <?= date('M d, Y', strtotime($job['posted_at'])) ?></small>
                                </div>
                                <div class="job-card-action">
                                    <?php
                                    if(isset($applications[$job['id']])) {
                                        $status = $applications[$job['id']];
                                        $text = ucfirst($status);
                                        $statusClass = match($status) {
                                            'accepted' => 'status-accepted',
                                            'rejected' => 'status-rejected',
                                            default => 'status-pending'
                                        };
                                        echo "<div class='application-status-badge $statusClass'>$text</div>";
                                    } else {
                                    ?>
                                        <form method="POST">
                                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                            <input type="hidden" name="employer_email" value="<?= htmlspecialchars($job['employer_email']) ?>">
                                            <button type="submit" name="apply_job" class="submit-btn">Apply Now</button>
                                        </form>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Applications Tab -->
    <div id="applications-tab" class="tab-content <?= $activeTab === 'applications' ? 'active' : '' ?>">
        <div class="job-container">
            <h2>Application Status</h2>
            
            <?php if(empty($pending)): ?>
                <div class="empty-state">
                    <p>You haven't applied for any jobs yet. Browse available jobs to get started!</p>
                </div>
            <?php else: ?>
                <div class="applications-list">
                    <?php foreach($pending as $p): ?>
                        <div class="application-card">
                            <div class="application-card-content">
                                <h4><?= htmlspecialchars($p['job_title']) ?></h4>
                                <div class="application-card-meta">
                                    <?= htmlspecialchars($p['job_type']) ?> • Applied: <?= date('M d, Y', strtotime($p['applied_at'])) ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php
                                $status = $p['status'];
                                $text = ucfirst($status);
                                $statusClass = match($status) {
                                    'accepted' => 'status-accepted',
                                    'rejected' => 'status-rejected',
                                    default => 'status-pending'
                                };
                                ?>
                                <span class="application-status-badge <?= $statusClass ?>">
                                    <?= $text ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Profile Tab -->
    <div id="profile-tab" class="tab-content <?= $activeTab === 'profile' ? 'active' : '' ?>">
        <div class="card-box">
            <h2 class="card-heading-h2">My Profile & Documents</h2>
            
            <?php if($submission): ?>
                <div class="message success margin-bottom-20">
                    <strong>✓ Profile Complete</strong>
                    <p>Submitted on <?= date('M d, Y', strtotime($submission['submitted_at'])) ?></p>
                </div>

                <div class="submitted-documents">
                    <h3 class="card-heading">📋 Submitted Documents</h3>
                    <div class="documents-grid">
                        <div class="document-card">
                            <h4>📄 Resume</h4>
                            <a href="<?= htmlspecialchars($submission['resume']) ?>" target="_blank" class="document-download-link">Download</a>
                        </div>
                        <div class="document-card">
                            <h4>🪪 Valid ID</h4>
                            <a href="<?= htmlspecialchars($submission['valid_id']) ?>" target="_blank" class="document-download-link">Download</a>
                        </div>
                        <div class="document-card">
                            <h4>🏫 School ID</h4>
                            <a href="<?= htmlspecialchars($submission['school_id']) ?>" target="_blank" class="document-download-link">Download</a>
                        </div>
                    </div>
                </div>

                <div class="contact-info-section">
                    <h3 class="card-heading">👤 Contact Information</h3>
                    <div class="contact-info-grid">
                        <div class="contact-info-card">
                            <p class="contact-info-label">📞 Phone Number</p>
                            <p class="contact-info-value"><?= htmlspecialchars($submission['phone']) ?></p>
                        </div>
                        <div class="contact-info-card" style="border-left-color: #007c92;">
                            <p class="contact-info-label">📧 Email Address</p>
                            <p class="contact-info-value secondary"><?= htmlspecialchars($submission['email']) ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="profile-incomplete-warning">
                    <p><strong>⚠️ Profile Incomplete</strong></p>
                    <p>Please submit your documents and contact information to complete your profile.</p>
                    <button onclick="document.getElementById('profile-form').scrollIntoView({behavior: 'smooth'});" class="submit-btn">Go to Upload Section ↓</button>
                </div>

                <div id="profile-form" class="upload-form-section">
                    <h3>Upload Your Documents</h3>
                    <form method="POST" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="submit_credentials" value="1">
                        
                        <div class="form-grid margin-bottom-20">
                            <div class="upload-file-group">
                                <label>📄 Resume / CV *</label>
                                <input type="file" name="resume" accept=".pdf,.doc,.docx" required>
                                <small>PDF, DOC, or DOCX format</small>
                            </div>
                            <div class="upload-file-group">
                                <label>🪪 Valid ID *</label>
                                <input type="file" name="valid_id" accept=".jpg,.jpeg,.png,.pdf" required>
                                <small>JPG, PNG, or PDF format</small>
                            </div>
                            <div class="upload-file-group">
                                <label>🏫 School ID *</label>
                                <input type="file" name="school_id" accept=".jpg,.jpeg,.png,.pdf" required>
                                <small>JPG, PNG, or PDF format</small>
                            </div>
                        </div>

                        <div class="upload-contact-grid">
                            <div class="upload-contact-field">
                                <label>📱 Phone Number *</label>
                                <input type="text" name="phone" placeholder="e.g., +63 9123456789" required class="upload-contact-input">
                            </div>
                            <div class="upload-contact-field">
                                <label>📧 Email Address *</label>
                                <input type="email" name="email_form" placeholder="your@email.com" required class="upload-contact-input">
                            </div>
                        </div>

                        <button type="submit" class="submit-btn">Submit Profile</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

         <!-- New Preferences Card -->
        <!-- Preferences Card -->
<div class="card-box" style="margin-top: 30px;">
    <h2 class="card-heading-h2">My Preferences</h2>

    <?php
    // Fetch user preferences
    $stmtPref = $conn->prepare("SELECT job_pref_1, job_pref_2, job_pref_3, job_pref_4, time_preference, location_text FROM user_preferences WHERE user_id = (SELECT id FROM users WHERE email = ?) LIMIT 1");
    $stmtPref->bind_param("s", $email);
    $stmtPref->execute();
    $resPref = $stmtPref->get_result();
    $prefs = $resPref->fetch_assoc();
    $stmtPref->close();
    ?>

    <?php if($prefs): ?>
        <div class="preferences-container">

            <!-- Job Preferences -->
            <div class="preference-card">
                <h3>💼 Job Preferences</h3>
                <ul class="preference-list">
                    <?php for ($i=1; $i<=4; $i++): 
                        $pref = $prefs["job_pref_$i"];
                        if($pref): ?>
                            <li>• <?= htmlspecialchars($pref) ?></li>
                    <?php endif; endfor; ?>
                </ul>
            </div>

<!-- Time Preferences -->
<div class="preference-card">
    <h3>⏰ Time Preferences</h3>
    <ul class="preference-list">
        <?php
        // Helper function to compress consecutive days
        function compressDays($daysArray) {
            $dayOrder = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];
            // sort according to day order
            usort($daysArray, function($a, $b) use ($dayOrder) {
                return array_search($a, $dayOrder) - array_search($b, $dayOrder);
            });

            $result = [];
            $start = $daysArray[0];
            $prevIndex = array_search($start, $dayOrder);

            for ($i = 1; $i <= count($daysArray); $i++) {
                $curr = $daysArray[$i] ?? null;
                $currIndex = $curr !== null ? array_search($curr, $dayOrder) : -1;

                if ($currIndex === $prevIndex + 1) {
                    // consecutive, continue
                    $prevIndex = $currIndex;
                } else {
                    // break in sequence
                    if ($prevIndex === array_search($start, $dayOrder)) {
                        $result[] = $start; // single day
                    } else {
                        $result[] = "$start to " . $dayOrder[$prevIndex]; // range
                    }
                    $start = $curr;
                    $prevIndex = $currIndex;
                }
            }
            return implode(", ", $result);
        }

        $times = explode(' | ', $prefs['time_preference']);
        foreach($times as $time) {
            // Split stored value into time and days
            $firstCommaPos = strpos($time, ',');
            if ($firstCommaPos !== false) {
                $timeRange = substr($time, 0, $firstCommaPos);
                $daysStr = substr($time, $firstCommaPos + 2); // skip comma and space
                $daysArray = array_map('trim', explode(',', $daysStr));
                $daysDisplay = compressDays($daysArray);
            } else {
                $timeRange = $time;
                $daysDisplay = '';
            }

            // Convert 24-hour format to 12-hour AM/PM
            if (strpos($timeRange, ' - ') !== false) {
                [$start, $end] = explode(' - ', $timeRange);
                $start12 = date("g:i A", strtotime($start));
                $end12 = date("g:i A", strtotime($end));
                $timeRange = "$start12 - $end12";
            }

            echo "<li>• " . htmlspecialchars($timeRange);
            if($daysDisplay) echo ", " . htmlspecialchars($daysDisplay);
            echo "</li>";
        }
        ?>
    </ul>
</div>



            <!-- Location Preferences -->
            <div class="preference-card">
                <h3>📍 Location Preferences</h3>
                <ul class="preference-list">
                    <?php
                    $locations = explode(' | ', $prefs['location_text']);
                    foreach($locations as $loc) {
                        echo "<li>• ".htmlspecialchars($loc)."</li>";
                    }
                    ?>
                </ul>
            </div>

        </div>
    <?php else: ?>
        <p class="no-preferences">No preferences set yet. Please update your preferences to get personalized job suggestions.</p>
    <?php endif; ?>
</div>

    </div>
</main>

</body>
</html>
