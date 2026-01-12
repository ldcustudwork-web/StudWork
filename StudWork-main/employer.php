<?php
session_start();
include 'config.php';

// 🔒 Protect page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer') {
    header('Location: login.php');
    exit;
}

// ✅ Fetch employer data
$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT fullname, company_name FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    header('Location: logout.php'); 
    exit;
}
$user = $result->fetch_assoc();
$fullname = $user['fullname'];
$company_name = $user['company_name'] ?? '';
$stmt->close();

$message = '';
$messageType = '';
$activeTab = $_POST['tab'] ?? 'dashboard';

// ===== Handle Credentials Upload =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credentials_submit'])) {
    $activeTab = 'profile';
    $telephone = $_POST['telephone'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    
    if (!$telephone || !$mobile) {
        $message = 'Please fill in all contact fields.';
        $messageType = 'error';
    } else {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $docs = [
            'hiring_doc' => 'Hiring & Onboarding Docs',
            'termination_doc' => 'Termination & Exit Docs',
            'contracts_doc' => 'Employment Contracts',
            'privacy_doc' => 'Privacy & Compliance Docs'
        ];

        $all_uploaded = true;

        foreach ($docs as $input_name => $label) {
            $file_path = null;

            if (!empty($_FILES[$input_name]['name'])) {
                $ext = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
                $file_path = $upload_dir . $email . "_" . $input_name . "_" . time() . "." . $ext;

                if (!move_uploaded_file($_FILES[$input_name]['tmp_name'], $file_path)) {
                    $all_uploaded = false;
                    continue;
                }
            }

            $check = $conn->prepare("SELECT id FROM employer_documents WHERE employer_email=? AND document_name=? LIMIT 1");
            $check->bind_param("ss", $email, $label);
            $check->execute();
            $res = $check->get_result();
            $check->close();

            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $doc_id = $row['id'];
                if ($file_path) {
                    $stmt = $conn->prepare("UPDATE employer_documents SET file_path=?, telephone=?, mobile=? WHERE id=?");
                    $stmt->bind_param("sssi", $file_path, $telephone, $mobile, $doc_id);
                } else {
                    $stmt = $conn->prepare("UPDATE employer_documents SET telephone=?, mobile=? WHERE id=?");
                    $stmt->bind_param("ssi", $telephone, $mobile, $doc_id);
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO employer_documents (employer_email, document_name, file_path, telephone, mobile) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $email, $label, $file_path, $telephone, $mobile);
            }

            if (!$stmt->execute()) {
                $all_uploaded = false;
            }
            $stmt->close();
        }

        if ($all_uploaded) {
            $message = 'Documents saved successfully!';
            $messageType = 'success';
        } else {
            $message = 'Some documents failed to upload. Please try again.';
            $messageType = 'error';
        }
    }
}

// ===== Handle Job Post Submission =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_submit'])) {
    $activeTab = 'jobs';
    $job_title = $_POST['job_title'] ?? '';
    $job_type = $_POST['job_type'] ?? '';
    $job_description = $_POST['job_description'] ?? '';

    if (!$job_title || !$job_type || !$job_description) {
        $message = 'Please fill in all job posting fields.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO job_posts (employer_email, job_title, job_type, job_description, posted_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $email, $job_title, $job_type, $job_description);
        if ($stmt->execute()) {
            $message = 'Job posted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error posting job. Please try again.';
            $messageType = 'error';
        }
        $stmt->close();
    }

    // ===== Handle Job Post Update =====
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_update'])) {
        $activeTab = 'jobs';
        $job_id = intval($_POST['job_id'] ?? 0);
        $job_title = $_POST['job_title'] ?? '';
        $job_type = $_POST['job_type'] ?? '';
        $job_description = $_POST['job_description'] ?? '';

        if ($job_id <= 0 || !$job_title || !$job_type || !$job_description) {
            $message = 'Please fill in all fields to update the job.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE job_posts SET job_title=?, job_type=?, job_description=?, posted_at=NOW() WHERE id=? AND employer_email=?");
            $stmt->bind_param("sssis", $job_title, $job_type, $job_description, $job_id, $email);
            if ($stmt->execute()) {
                $message = 'Job updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error updating job. Please try again.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }

    // ===== Handle Job Post Delete =====
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_delete'])) {
        $activeTab = 'jobs';
        $job_id = intval($_POST['job_id'] ?? 0);
        if ($job_id <= 0) {
            $message = 'Invalid job selected for deletion.';
            $messageType = 'error';
        } else {
            // remove related applications first (if any)
            $stmt = $conn->prepare("DELETE FROM job_applications WHERE job_id = ?");
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM job_posts WHERE id = ? AND employer_email = ?");
            $stmt->bind_param("is", $job_id, $email);
            if ($stmt->execute()) {
                $message = 'Job deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error deleting job. Please try again.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

// ===== Fetch Submitted Jobs =====
$jobs = [];
$stmt = $conn->prepare("
    SELECT 
        jp.id,
        jp.job_title,
        jp.job_type,
        jp.job_description,
        jp.posted_at,
        COUNT(ja.id) AS applicant_count
    FROM job_posts jp
    LEFT JOIN job_applications ja ON ja.job_id = jp.id
    WHERE jp.employer_email = ?
    GROUP BY jp.id
    ORDER BY jp.posted_at DESC
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $jobs[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Employer Dashboard — StudWork</title>
<link rel="stylesheet" href="css/styles.css">
</script>
<script>
function switchTab(tabName) {
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabName).classList.add('active');
    if (event && event.target) event.target.classList.add('active');
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

// Edit form handling
document.addEventListener('DOMContentLoaded', function(){
    const editForm = document.getElementById('edit-form');
    const editBtnList = document.querySelectorAll('.edit-btn');
    const cancelBtn = document.getElementById('cancel-edit');

    editBtnList.forEach(btn => {
        btn.addEventListener('click', function(){
            const id = this.dataset.id;
            const title = this.dataset.title || '';
            const type = this.dataset.type || '';
            const desc = this.dataset.desc || '';

            document.getElementById('edit-job-id').value = id;
            document.getElementById('edit-job-title').value = title;
            document.getElementById('edit-job-type').value = type;
            document.getElementById('edit-job-desc').value = desc;

            editForm.style.display = 'block';
            window.scrollTo({ top: editForm.offsetTop - 80, behavior: 'smooth' });
        });
    });

    if (cancelBtn) cancelBtn.addEventListener('click', function(){
        if (editForm) editForm.style.display = 'none';
    });
});
</script>
<script>
function openApplicantsModal(jobId) {
    const modal = document.getElementById('applicantsModal');
    const content = document.getElementById('applicantsModalContent');

    modal.style.display = 'flex';
    content.innerHTML = 'Loading applicants...';

    fetch('view_applicants.php?job_id=' + jobId + '&modal=1')
        .then(res => res.text())
        .then(html => content.innerHTML = html)
        .catch(() => content.innerHTML = 'Failed to load applicants.');
}

function closeApplicantsModal() {
    document.getElementById('applicantsModal').style.display = 'none';
}
</script>


<style>
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}

.modal-box {
    background: #fff;
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 10px;
    padding: 20px;
    position: relative;
}

.modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 22px;
    border: none;
    background: none;
    cursor: pointer;
}
</style>

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
        <div class="user-info">
            <span><?= htmlspecialchars($fullname) ?> • <?= htmlspecialchars($company_name) ?></span>
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
        <button class="tab-btn <?= $activeTab === 'dashboard' ? 'active' : '' ?>" onclick="switchTab('dashboard-tab'); this.classList.add('active'); document.querySelectorAll('.tab-btn').forEach(b => b !== this && b.classList.remove('active'))">
            📊 Dashboard
        </button>
        <button class="tab-btn <?= $activeTab === 'jobs' ? 'active' : '' ?>" onclick="switchTab('jobs-tab'); this.classList.add('active'); document.querySelectorAll('.tab-btn').forEach(b => b !== this && b.classList.remove('active'))">
            📋 Job Postings
        </button>
        <button class="tab-btn <?= $activeTab === 'profile' ? 'active' : '' ?>" onclick="switchTab('profile-tab'); this.classList.add('active'); document.querySelectorAll('.tab-btn').forEach(b => b !== this && b.classList.remove('active'))">
            🏢 Company Profile
        </button>
    </div>

    <!-- Dashboard Tab -->
    <div id="dashboard-tab" class="tab-content <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
        <div class="stats-row">
            <div class="stat-card">
                <h3><?= count($jobs) ?></h3>
                <p>Active Job Posts</p>
            </div>
            <div class="stat-card">
                <h3><?= array_sum(array_column($jobs, 'applicant_count')) ?></h3>
                <p>Total Applications</p>
            </div>
            <div class="stat-card">
                <h3><?= count(array_filter($jobs, fn($j) => $j['applicant_count'] > 0)) ?></h3>
                <p>Posts with Applicants</p>
            </div>
        </div>

        <div class="card-box form-wrapper">
            <h2 class="card-title">Quick Actions</h2>
            <div class="grid-2-tight">
                <a href="javascript:switchTab('jobs-tab'); document.querySelector('[onclick*=jobs-tab]').classList.add('active'); document.querySelectorAll('.tab-btn').forEach(b => !b.textContent.includes('Job') && b.classList.remove('active'));" class="action-btn">
                    ➕ Post a New Job
                </a>
                <a href="javascript:switchTab('profile-tab'); document.querySelector('[onclick*=profile-tab]').classList.add('active'); document.querySelectorAll('.tab-btn').forEach(b => !b.textContent.includes('Profile') && b.classList.remove('active'));" class="action-btn-secondary">
                    📝 Update Company Info
                </a>
            </div>
        </div>

        <div class="card-box form-wrapper">
            <h2 class="card-title">Recent Job Posts</h2>
            <?php if(empty($jobs)): ?>
                <div class="empty-state">
                    <p>You haven't posted any jobs yet. Click "Post a New Job" to get started!</p>
                </div>
            <?php else: ?>
                <div class="flex-col">
                    <?php foreach($jobs as $job): ?>
                        <div class="job-preview-card">
                            <div class="flex-between">
                                <div class="flex-1">
                                    <h4 class="job-card-title"><?= htmlspecialchars($job['job_title']) ?></h4>
                                    <div class="job-meta">
                                        <span class="job-meta-inline">📌 <?= htmlspecialchars($job['job_type']) ?></span>Posted: <?= date('M d, Y', strtotime($job['posted_at'])) ?>
                                    </div>
                                    <p class="job-card-description">
                                        <?= htmlspecialchars(substr($job['job_description'], 0, 100)) ?>...
                                    </p>
                                </div>
                                <div class="text-right margin-left-15">
                                    <div class="applicant-badge">
                                        👥 <?= $job['applicant_count'] ?> Applicant<?= $job['applicant_count'] !== 1 ? 's' : '' ?>
                                    </div>
                                   <button class="submit-btn" type="button"
        onclick="openApplicantsModal(<?= $job['id'] ?>)">
    View Details →
</button>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Job Postings Tab -->
    <div id="jobs-tab" class="tab-content <?= $activeTab === 'jobs' ? 'active' : '' ?>">
        <div class="card-container">
            <h2>Post a New Job</h2>
            <form method="POST" class="form-grid">
                <div>
                    <label class="form-label">Job Title *</label>
                    <input type="text" name="job_title" placeholder="e.g., Customer Service Representative" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Job Type *</label>
                    <select name="job_type" required class="form-select">
                        <option value="">Select job type</option>
                        <option value="Part-time">Part-time</option>
                    </select>
                </div>
                <div class="form-grid full-width">
                    <label class="form-label">Job Description *</label>
                    <textarea name="job_description" placeholder="Describe the job responsibilities, requirements, and benefits..." rows="6" required class="form-textarea"></textarea>
                </div>
                <div class="form-grid full-width text-right">
                    <button type="submit" name="job_submit" class="submit-btn">Post Job</button>
                </div>
            </form>
        </div>

        <!-- Edit form (hidden until an Edit action) -->
        <div id="edit-form" class="card-box form-wrapper" style="display:none;">
            <h2 class="card-title">Edit Job Post</h2>
            <form method="POST" id="job-edit-form" class="form-grid">
                <input type="hidden" name="job_id" id="edit-job-id" value="">
                <div>
                    <label class="form-label">Job Title *</label>
                    <input type="text" name="job_title" id="edit-job-title" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Job Type *</label>
                    <select name="job_type" id="edit-job-type" required class="form-select">
                        <option value="">Select job type</option>
                        <option value="Part-time">Part-time</option>
                    </select>
                </div>
                <div class="form-grid full-width">
                    <label class="form-label">Job Description *</label>
                    <textarea name="job_description" id="edit-job-desc" rows="5" required class="form-textarea"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" id="cancel-edit" class="btn btn-ghost">Cancel</button>
                    <button type="submit" name="job_update" class="submit-btn">Save Changes</button>
                </div>
            </form>
        </div>

        <div class="card-box">
            <h2 class="card-title">Your Job Posts</h2>

            <?php if(empty($jobs)): ?>
                <div class="empty-state">
                    <p>No job posts yet. Create your first job posting above!</p>
                </div>
            <?php else: ?>
                <div class="flex-col">
                    <?php foreach($jobs as $job): ?>
                        <div class="job-list-card">
                            <div class="flex-between">
                                <div class="flex-1">
                                    <h4 class="job-card-title"><?= htmlspecialchars($job['job_title']) ?></h4>
                                    <div class="job-meta">
                                        <span class="job-meta-inline">📌 <strong><?= htmlspecialchars($job['job_type']) ?></strong></span>
                                        Posted: <?= date('M d, Y', strtotime($job['posted_at'])) ?>
                                    </div>
                                    <p class="job-card-description"><?= htmlspecialchars($job['job_description']) ?></p>
                                </div>
                                <div class="text-right" style="margin-left:20px;min-width:150px;">
                                    <div class="applicant-badge">👥 <?= $job['applicant_count'] ?> Applicant<?= $job['applicant_count'] !== 1 ? 's' : '' ?></div>
                                    <div class="form-actions" style="margin-top:8px;align-items:flex-end;">
                                        <button type="button" class="btn edit-btn" 
                                            data-id="<?= $job['id'] ?>" 
                                            data-title="<?= htmlspecialchars($job['job_title'], ENT_QUOTES) ?>" 
                                            data-type="<?= htmlspecialchars($job['job_type'], ENT_QUOTES) ?>" 
                                            data-desc="<?= htmlspecialchars($job['job_description'], ENT_QUOTES) ?>">Edit</button>

                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this job post? This action cannot be undone.');">
                                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                            <button type="submit" name="job_delete" class="btn reject">Delete</button>
                                        </form>

                                    <button class="submit-btn" type="button"
        onclick="openApplicantsModal(<?= $job['id'] ?>)">
    View Details →
</button></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Company Profile Tab -->
    <div id="profile-tab" class="tab-content <?= $activeTab === 'profile' ? 'active' : '' ?>">
        <div class="card-container">
            <h2>Company Information</h2>
            <div class="contact-info-grid">
                <div class="contact-info-card">
                    <p class="contact-info-label">Contact Person</p>
                    <h4 class="contact-info-value"><?= htmlspecialchars($fullname) ?></h4>
                </div>
                <div class="contact-info-card">
                    <p class="contact-info-label">Company Name</p>
                    <h4 class="contact-info-value"><?= htmlspecialchars($company_name) ?></h4>
                </div>
                <div class="contact-info-card">
                    <p class="contact-info-label">Email</p>
                    <h4 class="contact-info-value"><?= htmlspecialchars($email) ?></h4>
                </div>
            </div>
        </div>

        <div class="card-container">
            <h2>Upload Company Documents</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="credentials_submit" value="1">
                
                <div class="form-grid margin-bottom-30">
                    <div class="upload-file-group">
                        <label>Hiring & Onboarding Docs</label>
                        <input type="file" name="hiring_doc" accept=".pdf,.doc,.docx,.jpg,.png">
                        <small>PDF or Office documents</small>
                    </div>
                    <div class="upload-file-group">
                        <label>Termination & Exit Docs</label>
                        <input type="file" name="termination_doc" accept=".pdf,.doc,.docx,.jpg,.png">
                        <small>PDF or Office documents</small>
                    </div>
                    <div class="upload-file-group">
                        <label>Employment Contracts</label>
                        <input type="file" name="contracts_doc" accept=".pdf,.doc,.docx,.jpg,.png">
                        <small>PDF or Office documents</small>
                    </div>
                    <div class="upload-file-group">
                        <label>Privacy & Compliance Docs</label>
                        <input type="file" name="privacy_doc" accept=".pdf,.doc,.docx,.jpg,.png">
                        <small>PDF or Office documents</small>
                    </div>
                </div>

                <div class="upload-contact-grid">
                    <div class="upload-contact-field">
                        <label>📞 Telephone Number</label>
                        <input type="text" name="telephone" placeholder="e.g., 02-1234567" required class="upload-contact-input">
                    </div>
                    <div class="upload-contact-field">
                        <label>📱 Mobile Number</label>
                        <input type="text" name="mobile" placeholder="e.g., 09171234567" required class="upload-contact-input">
                    </div>
                </div>

                <button type="submit" class="submit-btn">Save Company Profile</button>
            </form>
        </div>
    </div>
</main>

</body>
<!-- Applicants Modal -->
<div id="applicantsModal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeApplicantsModal()">×</button>
        <div id="applicantsModalContent" style="    display: flex;
    flex-direction: column;
    gap: 15px;">
            Loading applicants...
        </div>
    </div>
</div>

</html>
