<?php
session_start();
include 'config.php';

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $company = trim($_POST['company_name'] ?? '');

    // Student fields
    $job1 = trim($_POST['job_pref_1'] ?? '');
    $job2 = trim($_POST['job_pref_2'] ?? '');
    $job3 = trim($_POST['job_pref_3'] ?? '');
    $job4 = trim($_POST['job_pref_4'] ?? '');
    $time_prefs = $_POST['time_pref'] ?? []; // preformatted string
    $locations = $_POST['location_text'] ?? [];

    // Validation
    if (!$fullname || !$email || !$password || !$confirm || !$role) {
        $message = "Please fill out all required fields.";
        $messageType = "error";
    } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } elseif ($role === "employer" && !$company) {
        $message = "Please provide your company or organization name.";
        $messageType = "error";
    } elseif ($role === "student") {
        if (!$job1 || !$job2 || !$job3 || !$job4) {
            $message = "Please complete all 4 job preferences.";
            $messageType = "error";
        } elseif (count($time_prefs) < 2 || count($locations) < 2) {
            $message = "Please add at least 2 time and 2 location preferences.";
            $messageType = "error";
        }
    }

    if (!$message) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        if ($role === "student") $company = null;

        $stmt = $conn->prepare(
            "INSERT INTO users (fullname, email, password, role, company_name)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssss", $fullname, $email, $hashed, $role, $company);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;

            if ($role === "student") {
                $pref = $conn->prepare(
                    "INSERT INTO user_preferences
                    (user_id, job_pref_1, job_pref_2, job_pref_3, job_pref_4,
                     time_preference, location_text)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $pref->bind_param(
                    "issssss",
                    $user_id,
                    $job1, $job2, $job3, $job4,
                    implode(' | ', $time_prefs),
                    implode(' | ', $locations)
                );
                $pref->execute();
                $pref->close();
            }

            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            $_SESSION['fullname'] = $fullname;

            header("Location: " . ($role === "student" ? "student.php" : "employer.php"));
            exit;
        } else {
            $message = $conn->errno === 1062 ? "Email already exists." : "Something went wrong. Please try again.";
            $messageType = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Account — StudWork</title>
<style>
:root{--maroon:#7a0000;--white:#fff;--muted:#fdf6f6;--accent:#b33a3a}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui;background:var(--muted);color:var(--maroon)}
.panel{max-width:600px;margin:40px auto;background:#fff;padding:22px;border-radius:14px}
label{display:block;margin-top:12px;font-weight:600}
input,select{width:100%;padding:10px;margin-top:6px;border-radius:8px;border:1px solid #ccc}
.actions{margin-top:16px;display:flex;gap:10px;justify-content:flex-end}
.btn{background:var(--maroon);color:#fff;border:none;padding:10px 14px;border-radius:10px;cursor:pointer;font-weight:700}
.btn.ghost{background:transparent;color:var(--maroon);border:1px solid var(--maroon)}
.msg{margin-top:12px;padding:10px;border-radius:8px}
.days label {
    display: flex;
    margin: 0;
    font-weight: 500;
    flex-direction: column;
}
.days label input{width:auto;}
.time-input{cursor:pointer;background:#f7f7f7}
.time-entry, .location-entry{border:1px solid #ddd;padding:10px;border-radius:8px}
.modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center}
.modal-content{background:#fff;padding:20px;border-radius:10px;width:90%;max-width:400px}
.modal-content h4{margin-top:0;margin-bottom:10px}
.modal-content label{font-weight:500}
#time-container input:last-child, #location-container input:last-child{margin-bottom:12px;}
#time-modal .days {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
}
</style>
</head>
<body>

<div class="panel">
<h1>Create an account</h1>
<p>Sign up as a Student or Employer to get started.</p>

<form method="POST" novalidate>

<label>Full name</label>
<input name="fullname" required>

<label>Email</label>
<input type="email" name="email" required>

<label>Password</label>
<input type="password" name="password" required>

<label>Confirm password</label>
<input type="password" name="confirm_password" required>

<label>I am a</label>
<select name="role" id="role" required>
<option value="">Select</option>
<option value="student">Student</option>
<option value="employer">Employer</option>
</select>

<div id="employer-fields" style="display:none">
<label>Company / Organization</label>
<input name="company_name">
</div>

<div id="student-fields" style="display:none;margin-top:16px;">

<h3>Job Preferences</h3>
<input name="job_pref_1" placeholder="Job Preference 1">
<input name="job_pref_2" placeholder="Job Preference 2">
<input name="job_pref_3" placeholder="Job Preference 3">
<input name="job_pref_4" placeholder="Job Preference 4">

<h3>Time Preferences</h3>
<div id="time-container">
<input type="text" name="time_pref[]" readonly class="time-input" placeholder="Click to select time & days">
<input type="text" name="time_pref[]" readonly class="time-input" placeholder="Click to select time & days">
<input type="text" name="time_pref[]" readonly class="time-input" placeholder="Click to select time & days">
<input type="text" name="time_pref[]" readonly class="time-input" placeholder="Click to select time & days">
</div>
<!-- <button type="button" class="btn ghost" onclick="addTimeInput()">+ Add Time Preference</button> -->

<h3>Location Preferences</h3>
<div id="location-container">
<input type="text" name="location_text[]" placeholder="City / Address" class="location-entry">
<input type="text" name="location_text[]" placeholder="City / Address" class="location-entry">
<input type="text" name="location_text[]" placeholder="City / Address" class="location-entry">
<input type="text" name="location_text[]" placeholder="City / Address" class="location-entry">
</div>
<!-- <button type="button" class="btn ghost" onclick="addLocationInput()">+ Add Location Preference</button> -->

</div>

<div class="actions">
<a href="index.php" class="btn ghost">Back</a>
<button class="btn">Create account</button>
</div>

<?php if ($message): ?>
<div class="msg <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

</form>
</div>

<!-- Modal for Time Picker -->
<div class="modal" id="time-modal">
<div class="modal-content">
<h4>Select Time & Days</h4>
<div class="time-container" style="display:flex;gap:10px;">
  <div style="flex:1;">
    <label>Start Time</label>
    <input type="time" id="modal-start">
  </div>
  <div style="flex:1;">
    <label>End Time</label>
    <input type="time" id="modal-end">
  </div>
</div>
<div class="days">
<label><input type="checkbox" value="Mon"> Mon</label>
<label><input type="checkbox" value="Tue"> Tue</label>
<label><input type="checkbox" value="Wed"> Wed</label>
<label><input type="checkbox" value="Thu"> Thu</label>
<label><input type="checkbox" value="Fri"> Fri</label>
<label><input type="checkbox" value="Sat"> Sat</label>
<label><input type="checkbox" value="Sun"> Sun</label>
</div>
<div style="margin-top:10px;text-align:right">
<button type="button" class="btn ghost" onclick="closeModal()">Cancel</button>
<button type="button" class="btn" onclick="saveTime()">Save</button>
</div>
</div>
</div>

<script>
const role = document.getElementById('role');
const employerFields = document.getElementById('employer-fields');
const studentFields = document.getElementById('student-fields');

function toggleFields(){
  employerFields.style.display = role.value==='employer'?'block':'none';
  studentFields.style.display = role.value==='student'?'block':'none';
}
role.addEventListener('change', toggleFields);
toggleFields();

// Time Inputs
let activeInput = null;
const modal = document.getElementById('time-modal');
const startInput = document.getElementById('modal-start');
const endInput = document.getElementById('modal-end');

document.querySelectorAll('.time-input').forEach(input=>{
    input.addEventListener('click', ()=>{
        activeInput = input;
        // prefill if existing
        const val = input.value;
        if(val){
            const parts = val.split(', ');
            const timeParts = parts[0].split(' - ');
            startInput.value = timeParts[0];
            endInput.value = timeParts[1];
            document.querySelectorAll('#time-modal .days input').forEach(cb=>{
                cb.checked = parts[1].includes(cb.value);
            });
        } else {
            startInput.value = '';
            endInput.value = '';
            document.querySelectorAll('#time-modal .days input').forEach(cb=>cb.checked=false);
        }
        modal.style.display='flex';
    });
});

function closeModal(){
    modal.style.display='none';
}

function saveTime(){
    const days = [...document.querySelectorAll('#time-modal .days input')]
        .filter(cb=>cb.checked).map(cb=>cb.value);
    if(!startInput.value || !endInput.value || days.length===0){
        alert("Please select start, end, and at least one day");
        return;
    }
    activeInput.value = `${startInput.value} - ${endInput.value}, ${days.join(', ')}`;
    closeModal();
}

// Add Time & Location
let timeCount = 2;
function addTimeInput(){
    if(timeCount>=4) return;
    const container = document.getElementById('time-container');
    const input = document.createElement('input');
    input.type='text';
    input.name='time_pref[]';
    input.readOnly=true;
    input.className='time-input';
    input.placeholder='Click to select time & days';
    container.appendChild(input);
    input.addEventListener('click', ()=>{
        activeInput = input;
        startInput.value='';
        endInput.value='';
        document.querySelectorAll('#time-modal .days input').forEach(cb=>cb.checked=false);
        modal.style.display='flex';
    });
    timeCount++;
}

let locationCount=2;
function addLocationInput(){
    if(locationCount>=4) return;
    const container=document.getElementById('location-container');
    const input=document.createElement('input');
    input.type='text';
    input.name='location_text[]';
    input.placeholder='City / Address';
    input.className='location-entry';
    container.appendChild(input);
    locationCount++;
}

// Helper: convert 24h "HH:MM" -> 12h "hh:mm AM/PM"
function to12Hour(time24) {
    if (!time24) return '';
    const [h, m] = time24.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 === 0 ? 12 : h % 12;
    return `${hour12}:${m.toString().padStart(2,'0')} ${ampm}`;
}

// Helper: convert 12h "hh:mm AM/PM" -> 24h "HH:MM"
function to24Hour(time12) {
    if (!time12) return '';
    const [time, ampm] = time12.split(' ');
    let [h, m] = time.split(':').map(Number);
    if (ampm === 'PM' && h < 12) h += 12;
    if (ampm === 'AM' && h === 12) h = 0;
    return `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`;
}

// Prefill modal when editing existing input
document.querySelectorAll('.time-input').forEach(input=>{
    input.addEventListener('click', ()=>{
        activeInput = input;
        const val = input.value;
        if(val){
            const parts = val.split(', ');
            const timeParts = parts[0].split(' - ');
            startInput.value = to24Hour(timeParts[0]);
            endInput.value = to24Hour(timeParts[1]);
            document.querySelectorAll('#time-modal .days input').forEach(cb=>{
                cb.checked = parts[1].includes(cb.value);
            });
        } else {
            startInput.value = '';
            endInput.value = '';
            document.querySelectorAll('#time-modal .days input').forEach(cb=>cb.checked=false);
        }
        modal.style.display='flex';
    });
});

// Array of days in order
const dayOrder = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];

// Helper: compress consecutive days
function compressDays(days){
    if(days.length===0) return '';
    // sort according to dayOrder
    const sorted = days.sort((a,b)=>dayOrder.indexOf(a)-dayOrder.indexOf(b));
    const result = [];
    let start = sorted[0];
    let prevIndex = dayOrder.indexOf(sorted[0]);

    for(let i=1;i<=sorted.length;i++){
        const curr = sorted[i];
        const currIndex = curr!==undefined ? dayOrder.indexOf(curr) : -1;
        if(currIndex === prevIndex + 1){
            // consecutive, continue
            prevIndex = currIndex;
        } else {
            // break in sequence
            if(prevIndex === dayOrder.indexOf(start)){
                result.push(start); // single day
            } else {
                result.push(`${start} to ${dayOrder[prevIndex]}`); // range
            }
            start = curr;
            prevIndex = currIndex;
        }
    }
    return result.join(', ');
}

// Save modal input to 12-hour format + compressed days
function saveTime(){
    const days = [...document.querySelectorAll('#time-modal .days input')]
        .filter(cb=>cb.checked).map(cb=>cb.value);
    if(!startInput.value || !endInput.value || days.length===0){
        alert("Please select start, end, and at least one day");
        return;
    }
    const start12 = to12Hour(startInput.value);
    const end12 = to12Hour(endInput.value);
    const dayDisplay = compressDays(days); // compress consecutive days
    activeInput.value = `${start12} - ${end12}, ${dayDisplay}`;
    closeModal();
}


</script>
</body>
</html>
