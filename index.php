<?php
// --- 1. ERROR REPORTING ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// --- 2. LOGIN CHECK ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

include 'db.php'; 
date_default_timezone_set('Asia/Kolkata');

// --- 3. MANUAL LOCK LOGIC ---
$cutoff_time = strtotime("7:00 PM");
$current_time = time();
$is_manual_locked = ($current_time > $cutoff_time);
$disabled_attr = $is_manual_locked ? 'disabled' : ''; 

// --- 4. GET DATES ---
$available_dates = [];
if ($conn) {
    $date_result = $conn->query("SELECT DISTINCT attendance_date FROM attendance_log ORDER BY attendance_date DESC");
    if ($date_result) {
        while ($row = $date_result->fetch_assoc()) {
            $available_dates[] = date('m/d/Y', strtotime($row['attendance_date']));
        }
        $date_result->close();
    }
}

$today_str = date('m/d/Y');
if (!in_array($today_str, $available_dates)) {
    array_unshift($available_dates, $today_str);
}

$selected_date_str = $_GET['date'] ?? $today_str;
$selected_date_sql = date('Y-m-d', strtotime($selected_date_str));

// --- 5. GET DASHBOARD & REPORT DATA ---
// $full_attendance = [];
// $stats = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];

// if ($conn) {
//     $sql = "SELECT s.roll_no, s.name, s.department, s.year, s.address,
//                     a.attendance_date, a.attendance_time, a.status
//             FROM students s
//             LEFT JOIN attendance_log a ON s.roll_no = a.student_roll AND a.attendance_date = ?
//             ORDER BY s.roll_no";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("s", $selected_date_sql);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     while ($row = $result->fetch_assoc()) {
//         $stats['total']++;
//         $status = "Absent"; 
//         if ($row['status']) {
//             $status = $row['status'];
//             if ($status == 'Present') $stats['present']++;
//             if ($status == 'Late') $stats['late']++;
//         } else {
//             $stats['absent']++;
//         }
//         $full_attendance[] = [
//             "Date" => $row['attendance_date'] ? date('m/d/Y', strtotime($row['attendance_date'])) : "N/A",
//             "Time" => $row['attendance_time'] ? date('h:i A', strtotime($row['attendance_time'])) : "N/A",
//             "Roll" => $row['roll_no'], "Name" => $row['name'], "Dept" => $row['department'],
//             "Year" => $row['year'], "Status" => $status
//         ];
//     }
//     $stmt->close();
// }

// $stats['today_total'] = $stats['present'] + $stats['late'];
// $stats['percentage'] = ($stats['total'] > 0) ? round((($stats['present'] + $stats['late']) / $stats['total']) * 100) : 0;
// --- 5. GET DASHBOARD & REPORT DATA ---
$full_attendance = [];
// Counters
$stats = [
    'total_students' => 0, 
    'on_time' => 0, 
    'late' => 0, 
    'absent' => 0,
    'total_attended' => 0
];

$late_cutoff_time = strtotime("12:30 PM");

if ($conn) {
    $sql = "SELECT s.roll_no, s.name, s.department, s.year, 
                   a.attendance_date, a.attendance_time 
            FROM students s
            LEFT JOIN attendance_log a ON s.roll_no = a.student_roll AND a.attendance_date = ?
            ORDER BY s.roll_no";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_date_sql);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $stats['total_students']++; // Total class size
        
        $status_label = "Absent"; 
        $display_time = "N/A";
        $display_date = "N/A";

        if ($row['attendance_date']) {
            // Student is here (Present or Late)
            $stats['total_attended']++;
            
            $entry_time_ts = strtotime($row['attendance_time']);
            $display_time = date('h:i A', $entry_time_ts);
            $display_date = date('m/d/Y', strtotime($row['attendance_date']));

            // CHECK TIME (12:30 PM Rule)
            if ($entry_time_ts > $late_cutoff_time) {
                $status_label = "Late";
                $stats['late']++;
            } else {
                $status_label = "Present"; // Meaning "On Time"
                $stats['on_time']++;
            }
        } else {
            $stats['absent']++;
        }

        // Add to Table List
        $full_attendance[] = [
            "Date" => $display_date,
            "Time" => $display_time,
            "Roll" => $row['roll_no'], 
            "Name" => $row['name'], 
            "Dept" => $row['department'],
            "Year" => $row['year'], 
            "Status" => $status_label
        ];
    }
    $stmt->close();
}

// Calculate Rate based on Total Attended (On Time + Late)
if ($stats['total_students'] > 0) {
    $stats['percentage'] = round(($stats['total_attended'] / $stats['total_students']) * 100);
} else {
    $stats['percentage'] = 0;
}
// --- 6. GET MANUAL ENTRY DATA ---
$manual_form_data = [];
if ($conn) {
    $today_sql = date('Y-m-d');
    $sql_manual = "SELECT s.roll_no, s.name, s.department, a.status
                   FROM students s
                   LEFT JOIN attendance_log a ON s.roll_no = a.student_roll AND a.attendance_date = ?
                   ORDER BY s.roll_no";
    $stmt_manual = $conn->prepare($sql_manual);
    $stmt_manual->bind_param("s", $today_sql);
    $stmt_manual->execute();
    $result_manual = $stmt_manual->get_result();
    while ($row = $result_manual->fetch_assoc()) {
        $manual_form_data[] = $row;
    }
    $stmt_manual->close();
}

// -------------------------------------------------------
// A. PREPARE MONTHLY CHART DATA
// -------------------------------------------------------
// $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
// $chartData = [];
// foreach($months as $m) { $chartData[$m] = ['Present' => 0, 'Late' => 0]; }

// $sqlChart = "SELECT DATE_FORMAT(attendance_date, '%M') as m_name, status, COUNT(*) as cnt 
//              FROM attendance_log 
//              GROUP BY m_name, status";
// $resultChart = $conn->query($sqlChart);
// if ($resultChart) {
//     while($row = $resultChart->fetch_assoc()) {
//         $chartData[$row['m_name']][$row['status']] = $row['cnt'];
//     }
// }
// -------------------------------------------------------
// A. PREPARE MONTHLY CHART DATA (Present vs Absent)
// -------------------------------------------------------
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$chartData = [];

// 1. Initialize Array
foreach($months as $m) { 
    $chartData[$m] = ['Present' => 0, 'Absent' => 0]; 
}

// 2. Get Total Student Count
$total_students = 0;
$stu_res = $conn->query("SELECT COUNT(*) as c FROM students");
if($stu_res) {
    $total_students = $stu_res->fetch_assoc()['c'];
}

// 3. Get Raw Attendance Counts (Present/Late)
$raw_attendance = [];
$sqlChart = "SELECT DATE_FORMAT(attendance_date, '%M') as m_name, COUNT(*) as total_attended 
             FROM attendance_log 
             WHERE status IN ('Present', 'Late') 
             GROUP BY m_name";
$resultChart = $conn->query($sqlChart);
while($row = $resultChart->fetch_assoc()) {
    $raw_attendance[$row['m_name']] = $row['total_attended'];
}

// 4. Get Number of "Working Days" per Month (Days attendance was taken)
$working_days = [];
$sqlDays = "SELECT DATE_FORMAT(attendance_date, '%M') as m_name, COUNT(DISTINCT attendance_date) as days 
            FROM attendance_log 
            GROUP BY m_name";
$resultDays = $conn->query($sqlDays);
while($row = $resultDays->fetch_assoc()) {
    $working_days[$row['m_name']] = $row['days'];
}

// 5. Calculate Final Stats (Present vs Absent)
foreach($months as $m) {
    $days_in_month = $working_days[$m] ?? 0;
    $total_possible = $total_students * $days_in_month;
    
    $actual_present = $raw_attendance[$m] ?? 0; // Present + Late
    $calculated_absent = max(0, $total_possible - $actual_present);

    $chartData[$m] = [
        'Present' => $actual_present,
        'Absent' => $calculated_absent
    ];
}

// -------------------------------------------------------
// B. PREPARE ADVANCED AI CONTEXT
// -------------------------------------------------------
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime("-1 days"));

$sqlAI = "SELECT 
            s.roll_no,
            s.name,
            (SELECT COUNT(*) FROM attendance_log WHERE student_roll = s.roll_no) as total_days,
            (SELECT COUNT(*) FROM attendance_log WHERE student_roll = s.roll_no AND status IN ('Present', 'Late')) as attended_days,
            (SELECT status FROM attendance_log WHERE student_roll = s.roll_no AND attendance_date = '$today' LIMIT 1) as status_today,
            (SELECT status FROM attendance_log WHERE student_roll = s.roll_no AND attendance_date = '$yesterday' LIMIT 1) as status_yesterday
          FROM students s";

$resultAI = $conn->query($sqlAI);
$aiData = [];
if ($resultAI) {
    while($row = $resultAI->fetch_assoc()) {
        $pct = 0;
        if ($row['total_days'] > 0) {
            $pct = round(($row['attended_days'] / $row['total_days']) * 100, 1);
        }
        $aiData[] = [
            'roll'  => $row['roll_no'],
            'name'  => $row['name'],
            'pct'   => $pct,
            'today' => $row['status_today'] ?? 'Absent',
            'yest'  => $row['status_yesterday'] ?? 'Absent'
        ];
    }
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RFID Dashboard | Upasthita</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --glass-bg: rgba(255, 255, 255, 0.8);
            --glass-border: 1px solid rgba(255, 255, 255, 0.6);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05);
            --text-primary: #212529;
            --text-secondary: #495057;
            --nav-bg: rgba(255, 255, 255, 0.95);
            --input-bg: #ffffff;
            --table-hover: rgba(0,0,0,0.03);
            --modal-bg: #ffffff;
        }
        [data-theme="dark"] {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: 1px solid rgba(255, 255, 255, 0.1);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            --text-primary: #ffffff;
            --text-secondary: #cbd5e1;
            --nav-bg: rgba(15, 17, 42, 0.85);
            --input-bg: rgba(0,0,0,0.3);
            --table-hover: rgba(255,255,255,0.03);
            --modal-bg: rgba(20, 20, 35, 0.95);
        }
        body { font-family: 'Poppins', sans-serif; background: var(--bg-gradient); color: var(--text-primary); min-height: 100vh; transition: background 0.3s ease, color 0.3s ease; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(12px); border: var(--glass-border); border-radius: 16px; box-shadow: var(--glass-shadow); }
        .navbar { background: var(--nav-bg) !important; backdrop-filter: blur(10px); border-bottom: var(--glass-border); }
        .navbar-brand, .nav-link, .navbar-text { color: var(--text-primary) !important; }
        .nav-link.active { color: #0d6efd !important; font-weight: 600; }
        .card-metric { padding: 1.5rem; height: 100%; }
        .metric-value { font-size: 2.5rem; font-weight: 700; margin: 0.5rem 0; }
        .metric-label { color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        .table-glass { color: var(--text-secondary); margin-bottom: 0; }
        .table-glass th { background: rgba(0,0,0,0.05); color: var(--text-primary); border-bottom: var(--glass-border); font-weight: 600; }
        .table-glass td { border-bottom: var(--glass-border); background: transparent; color: var(--text-secondary); }
        .form-control, .form-select { background: var(--input-bg); border:1px solid rgba(143, 148, 251, 0.3);; color: var(--text-primary); }
        [data-theme="dark"] .form-select option { background-color: #1e1b4b; color: white; }
        .modal-content.glass-modal { background: var(--modal-bg); backdrop-filter: blur(16px); border: var(--glass-border); color: var(--text-primary); }
        [data-theme="dark"] .btn-close { filter: invert(1); }
        .badge-status { padding: 0.5em 0.8em; border-radius: 6px; }
        .bg-present { background: rgba(25, 135, 84, 0.2); color: #198754; border: 1px solid rgba(25, 135, 84, 0.3); }
        [data-theme="dark"] .bg-present { color: #75b798; }
        .bg-late { background: rgba(255, 193, 7, 0.2); color: #997404; border: 1px solid rgba(255, 193, 7, 0.3); }
        [data-theme="dark"] .bg-late { color: #ffca2c; }
        .bg-absent { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3); }
        [data-theme="dark"] .bg-absent { color: #ea868f; }
        .content-section { display: none; animation: fadeIn 0.4s ease; }
        .content-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        /* --- AI BUTTON ANIMATIONS --- */
@keyframes pulse-red {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); transform: scale(1); }
    70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); transform: scale(1.1); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); transform: scale(1); }
}

@keyframes wave-blue {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Class for when Microphone is active (Listening) */
.listening-mode {
    animation: pulse-red 1.5s infinite;
    background-color: #EF4444 !important; /* Red */
}

/* Class for when AI is talking */
.speaking-mode {
    animation: wave-blue 1s infinite ease-in-out;
    background-color: #4f46e5 !important; /* Blue */
    box-shadow: 0 0 20px rgba(79, 70, 229, 0.6);
}
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="#home"><i class="bi bi-exclude me-2 text-primary"></i>Upasthita</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 mx-lg-4">
                <li class="nav-item"><a class="nav-link active" href="#home">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="#reports">Reports</a></li> 
                <li class="nav-item"><a class="nav-link" href="#students">Students</a></li>
                <li class="nav-item"><a class="nav-link" href="#manual">Manual Entry</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm rounded-circle me-2 border" id="theme-toggle"><i class="bi bi-sun-fill" id="theme-icon"></i></button>
                <span class="navbar-text me-2 d-none d-lg-block small">Hi, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                <a href="sync.php" class="btn btn-sm btn-success" onclick="return confirm('Sync data?')"><i class="bi bi-cloud-sync-fill"></i> Sync</a>
                <a href="run_eod_report.php" class="btn btn-sm btn-warning text-dark fw-bold" onclick="return confirm('Publish today?')"><i class="bi bi-upload"></i> Publish</a>
                <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <!-- DASHBOARD SECTION (Contains BOTH Charts) -->
    <!-- <section id="home" class="content-section active">
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6"><div class="glass-card card-metric border-start border-4 border-success"><div class="metric-label">Present</div><div class="metric-value text-success"><?= $stats['today_total'] ?></div></div></div>
            <div class="col-lg-3 col-md-6"><div class="glass-card card-metric border-start border-4 border-info"><div class="metric-label">Rate</div><div class="metric-value text-info"><?= $stats['percentage'] ?>%</div></div></div>
            <div class="col-lg-3 col-md-6"><div class="glass-card card-metric border-start border-4 border-danger"><div class="metric-label">Absent</div><div class="metric-value text-danger"><?= $stats['absent'] ?></div></div></div>
            <div class="col-lg-3 col-md-6"><div class="glass-card card-metric border-start border-4 border-warning"><div class="metric-label">Total</div><div class="metric-value text-warning"><?= $stats['total'] ?></div></div></div>
        </div> -->
        <section id="home" class="content-section active">
        <div class="row g-3 mb-4 row-cols-1 row-cols-md-3 row-cols-lg-5">
            
            <div class="col">
                <div class="glass-card card-metric border-start border-4 border-success">
                    <div class="metric-label text-success"><i class="bi bi-check-circle-fill me-1"></i> On Time</div>
                    <div class="metric-value text-success"><?= $stats['on_time'] ?></div>
                </div>
            </div>

            <div class="col">
                <div class="glass-card card-metric border-start border-4 border-warning">
                    <div class="metric-label text-warning"><i class="bi bi-exclamation-circle-fill me-1"></i> Late</div>
                    <div class="metric-value text-warning"><?= $stats['late'] ?></div>
                </div>
            </div>

            <div class="col">
                <div class="glass-card card-metric border-start border-4 border-danger">
                    <div class="metric-label text-danger"><i class="bi bi-x-circle-fill me-1"></i> Absent</div>
                    <div class="metric-value text-danger"><?= $stats['absent'] ?></div>
                </div>
            </div>

            <div class="col">
                <div class="glass-card card-metric border-start border-4 border-info">
                    <div class="metric-label text-info"><i class="bi bi-pie-chart-fill me-1"></i> Rate</div>
                    <div class="metric-value text-info"><?= $stats['percentage'] ?>%</div>
                </div>
            </div>

            <div class="col">
                <div class="glass-card card-metric border-start border-4 border-secondary">
                    <div class="metric-label text-secondary"><i class="bi bi-people-fill me-1"></i> Total</div>
                    <div class="metric-value text-secondary"><?= $stats['total_students'] ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- DAILY CHART -->
            <div class="col-lg-7">
                <div class="glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold">Daily Attendance</h5>
                        <span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars($selected_date_str) ?></span>
                    </div>
                    <div style="height: 300px;"><canvas id="attendanceChart"></canvas></div>
                </div>
            </div>

            <!-- MONTHLY CHART (Only here) -->
            <div class="col-lg-5">
                <div class="glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold">Monthly Overview</h5>
                        <select id="monthSelector" onchange="updateMonthlyChart()" class="form-select form-select-sm w-auto">
                            <?php 
                            $current = date('F');
                            foreach($months as $m) {
                                $sel = ($m == $current) ? 'selected' : '';
                                echo "<option value='$m' $sel>$m</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div style="height: 300px; position: relative;"><canvas id="statusChart"></canvas></div>
                </div>
            </div>
        </div>
    </section>

    <!-- REPORTS SECTION -->
    <section id="reports" class="content-section">
         <div class="glass-card p-4 mb-4">
            <div class="row align-items-center mb-4">
                <div class="col-md-6"><h5 class="mb-0 fw-bold">Detailed Log</h5></div>
                <div class="col-md-6">
                    <form method="GET" action="index.php#reports" class="d-flex justify-content-md-end gap-2 align-items-center">
                        <label class="text-secondary small">Date:</label>
                        <select name="date" class="form-select w-auto form-select-sm">
                            <?php foreach ($available_dates as $date_str): ?>
                                <option value="<?= htmlspecialchars($date_str) ?>" <?= ($date_str == $selected_date_str) ? 'selected' : '' ?>><?= htmlspecialchars($date_str) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Load</button>
                        <a href="download.php?date=<?= urlencode($selected_date_str) ?>" class="btn btn-outline-success btn-sm" target="_blank"><i class="bi bi-download"></i> CSV</a>
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-glass align-middle">
                    <thead><tr><th>Time</th><th>Roll No</th><th>Name</th><th>Dept</th><th>Year</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if(empty($full_attendance)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No data available.</td></tr>
                        <?php else: ?>
                            <?php foreach ($full_attendance as $row): 
                                $badgeClass = "bg-absent";
                                if ($row['Status'] == "Present") $badgeClass = "bg-present";
                                if ($row['Status'] == "Late") $badgeClass = "bg-late";
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['Time']) ?></td>
                                <td class="font-monospace"><?= htmlspecialchars($row['Roll']) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($row['Name']) ?></td>
                                <td><?= htmlspecialchars($row['Dept']) ?></td>
                                <td><?= htmlspecialchars($row['Year']) ?></td>
                                <td><span class="badge badge-status <?= $badgeClass ?>"><?= htmlspecialchars($row['Status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- STUDENTS SECTION -->
    <section id="students" class="content-section">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">Student Master List</h4>
                <button class="btn btn-primary" onclick="openAddModal()"><i class="bi bi-person-plus-fill"></i> Add New</button>
            </div>
            <div class="table-responsive">
                <table class="table table-glass align-middle">
                    <thead><tr><th>Roll No</th><th>Name</th><th>Dept</th><th>Year</th><th>Address</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <?php
                        if ($conn) {
                            $master_res = $conn->query("SELECT * FROM students ORDER BY roll_no ASC");
                            if ($master_res && $master_res->num_rows > 0):
                                while ($s = $master_res->fetch_assoc()):
                        ?>
                        <tr>
                            <td class="font-monospace"><?= htmlspecialchars($s['roll_no']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars($s['department']) ?></td>
                            <td><?= htmlspecialchars($s['year']) ?></td>
                            <td><small><?= htmlspecialchars($s['address']) ?></small></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-info me-1" onclick='openEditModal(<?= json_encode($s) ?>)'><i class="bi bi-pencil-square"></i></button>
                                <form action="student_action.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="roll_no" value="<?= $s['roll_no'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?><tr><td colspan="6" class="text-center">No students found.</td></tr><?php endif; } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- MANUAL ENTRY SECTION -->
    <section id="manual" class="content-section">
        <div class="glass-card p-4 mx-auto" style="max-width: 1000px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">Manual Attendance (<?= $today_str ?>)</h4>
                <?= $is_manual_locked ? '<span class="badge bg-danger">LOCKED</span>' : '<span class="badge bg-success">ACTIVE</span>' ?>

            </div>
            <div class="text-muted small" style="margin-top: -10px;">
    <i>Manual update locked after 7:00 PM</i>
    <br>
</div>
            <form action="submit-manual.php" method="POST">
                <div class="table-responsive mb-4" style="max-height: 60vh; overflow-y: auto;">
                    <table class="table table-glass align-middle">
                        <thead class="sticky-top" style="background: var(--nav-bg); z-index: 2;"><tr><th width="50">Mark</th><th>Roll</th><th>Name</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($manual_form_data as $student): 
                                $status = $student['status'] ?? "Absent";
                                $is_checked = ($status == "Present" || $status == "Late");
                                $badgeClass = ($status == "Present") ? "bg-present" : (($status == "Late") ? "bg-late" : "bg-absent");
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input type="hidden" name="student_roll[]" value="<?= htmlspecialchars($student['roll_no']) ?>">
                                    <input class="form-check-input" type="checkbox" name="status[<?= htmlspecialchars($student['roll_no']) ?>]" value="Present" <?= $is_checked ? 'checked' : '' ?> <?= $disabled_attr ?>>
                                </td>
                                <td><?= htmlspecialchars($student['roll_no']) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($student['name']) ?></td>
                                <td><span class="badge badge-status <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end"><button type="submit" class="btn btn-success" <?= $disabled_attr ?>>Save Changes</button></div>
            </form>
        </div>
    </section>

    <!-- CONTACT SECTION -->
    <section id="contact" class="content-section">
        <div class="row justify-content-center"><div class="col-md-6"><div class="glass-card p-5">
            <h4 class="fw-bold mb-4 text-center">Support</h4>
            <form action="submit-contact.php" method="POST">
                <div class="mb-3"><label>Name</label><input type="text" class="form-control" name="contact_name" required></div>
                <div class="mb-3"><label>Email</label><input type="email" class="form-control" name="contact_email" required></div>
                <div class="mb-3"><label>Message</label><textarea class="form-control" name="contact_message" rows="3" required></textarea></div>
                <button type="submit" class="btn btn-primary w-100">Send</button>
            </form>
        </div></div></div>
    </section>

</div>

<!-- MODAL -->
<div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header"><h5 class="modal-title" id="modalTitle">Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form action="student_action.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <div class="mb-3"><label>Roll No</label><input type="text" class="form-control" name="roll_no" id="inputRoll" required></div>
                    <div class="mb-3"><label>Name</label><input type="text" class="form-control" name="name" id="inputName" required></div>
                    <div class="row g-2">
    <div class="col-6 mb-3">
        <label class="form-label">Department</label>
        <select class="form-select" name="department" id="inputDept" required>
            <option value="" selected disabled>Select Department</option>
            <option value="CSE">Computer Science (CSE)</option>
            <option value="IT">Information Technology (IT)</option>
            <option value="ECE">Electronics & Comm. (ECE)</option>
            <option value="EE">Electrical Engineering (EE)</option>
            <option value="ME">Mechanical Engineering (ME)</option>
            <option value="CE">Civil Engineering (CE)</option>
            <option value="AIML">AI & Machine Learning</option>
            <option value="DS">Data Science</option>
            <option value="IOT">Internet of Things (IoT)</option>
            <option value="AUTO">Automobile Engineering</option>
            <option value="BIO">Biotechnology</option>
        </select>
    </div>

    <div class="col-6 mb-3">
        <label class="form-label">Year</label>
        <select class="form-select" name="year" id="inputYear">
            <option>1st Year</option>
            <option>2nd Year</option>
            <option>3rd Year</option>
            <option>4th Year</option>
        </select>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Address</label>
    <textarea class="form-control" name="address" id="inputAddr" rows="2"></textarea>
</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<!-- AI ASSISTANT -->
<div style="position: fixed; bottom: 30px; right: 30px; z-index: 1000; display: flex; flex-direction: column; align-items: flex-end;">
    <div id="aiBubble" style="display: none; background: var(--glass-bg); backdrop-filter: blur(12px); padding: 15px; border-radius: 15px 15px 0 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); width: 280px; margin-bottom: 10px; border: var(--glass-border); color: var(--text-primary);">
        <div style="font-size: 12px; font-weight: bold; color: #4f46e5; margin-bottom: 5px;">Upasthita AI</div>
        <p id="aiResponse" style="margin: 0; font-size: 14px;">Listening...</p>
    </div>
    <button onclick="toggleVoice()" id="micBtn" style="width: 60px; height: 60px; border-radius: 50%; background: #4f46e5; border: none; color: white; font-size: 24px; cursor: pointer; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4); transition: transform 0.2s;"><i class="fas fa-microphone"></i></button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
    // 1. CONFIGURATION
    const dailyStats = <?php echo json_encode($stats); ?>;
    const monthlyChartData = <?php echo json_encode($chartData); ?>;
    const aiContext = <?php echo json_encode($aiData); ?>;
    const GEMINI_API_KEY = "<?= htmlspecialchars($_ENV['GEMINI_API_KEY'] ?? '') ?>"; // YOUR API KEY

    // 2. MAIN LOGIC
    document.addEventListener("DOMContentLoaded", function() {
        // THEME
        const html = document.documentElement;
        const toggleBtn = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateIcon(savedTheme);

        toggleBtn.addEventListener('click', () => {
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateIcon(next);
            updateChartsTheme();
        });
        function updateIcon(t) { themeIcon.className = t === 'light' ? 'bi bi-sun-fill' : 'bi bi-moon-fill'; }

        // DAILY CHART
        // let dailyChart = new Chart(document.getElementById('attendanceChart').getContext('2d'), {
        //     type: 'bar',
        //     data: {
        //         labels: ['Present', 'Late', 'Absent'],
        //         datasets: [{ label: 'Students', data: [dailyStats.present, dailyStats.late, dailyStats.absent], backgroundColor: ['#198754', '#ffc107', '#dc3545'], borderRadius: 6 }]
        //     },
        //     options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } }
        // });
        let dailyChart = new Chart(document.getElementById('attendanceChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['On Time', 'Late', 'Absent'],
        datasets: [{ 
            label: 'Students', 
            // Use the new PHP variables here
            data: [<?php echo $stats['on_time']; ?>, <?php echo $stats['late']; ?>, <?php echo $stats['absent']; ?>], 
            backgroundColor: ['#198754', '#ffc107', '#dc3545'], 
            borderRadius: 6 
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        scales: { 
            y: { beginAtZero: true, ticks: { precision: 0 } }, 
            x: { grid: { display: false } } 
        }, 
        plugins: { legend: { display: false } } 
    }
});

        // MONTHLY CHART
        // let monthlyChart = null;
        // window.updateMonthlyChart = function() {
        //     const month = document.getElementById('monthSelector').value;
        //     const data = monthlyChartData[month];
        //     if(monthlyChart) {
        //         monthlyChart.data.datasets[0].data = [data.Present, data.Late];
        //         monthlyChart.update();
        //     }
        // }

        // const mCtx = document.getElementById('statusChart').getContext('2d');
        // const mData = monthlyChartData[document.getElementById('monthSelector').value];
        // monthlyChart = new Chart(mCtx, {
        //     type: 'doughnut',
        //     data: {
        //         labels: ['Present', 'Late'],
        //         datasets: [{ data: [mData.Present, mData.Late], backgroundColor: ['#10B981', '#F59E0B'], borderWidth: 0, hoverOffset: 10 }]
        //     },
        //     options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '70%' }
        // });

        // function updateChartsTheme() {
        //     const isDark = html.getAttribute('data-theme') === 'dark';
        //     const color = isDark ? '#cbd5e1' : '#495057';
        //     dailyChart.options.scales.y.ticks.color = color;
        //     dailyChart.options.scales.x.ticks.color = color;
        //     dailyChart.update();
        // }
        // updateChartsTheme();
        // MONTHLY CHART (Present vs Absent)
let monthlyChart = null;

window.updateMonthlyChart = function() {
    const month = document.getElementById('monthSelector').value;
    const data = monthlyChartData[month];
    
    if(monthlyChart) {
        // Update Data: [Present, Absent]
        monthlyChart.data.datasets[0].data = [data.Present, data.Absent];
        monthlyChart.update();
    }
}

const mCtx = document.getElementById('statusChart').getContext('2d');
const mData = monthlyChartData[document.getElementById('monthSelector').value];

monthlyChart = new Chart(mCtx, {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent'], // Changed Labels
        datasets: [{ 
            data: [mData.Present, mData.Absent], 
            // Colors: Green for Present, Red for Absent
            backgroundColor: ['#198754', '#dc3545'], 
            borderWidth: 0, 
            hoverOffset: 10 
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { 
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.raw || 0;
                        let total = context.chart._metasets[context.datasetIndex].total;
                        let percentage = Math.round((value / total) * 100) + '%';
                        return label + ': ' + value + ' (' + percentage + ')';
                    }
                }
            }
        }, 
        cutout: '70%' 
    }
});

        // NAVIGATION
        const navLinks = document.querySelectorAll('.navbar .nav-link');
        const sections = document.querySelectorAll('.content-section');
        function showSection(hash) {
            if(!hash) hash='#home';
            if(!document.querySelector(hash)) hash='#home';
            sections.forEach(s => s.classList.remove('active'));
            navLinks.forEach(l => l.classList.remove('active'));
            document.querySelector(hash).classList.add('active');
            const link = document.querySelector(`.navbar .nav-link[href="${hash}"]`);
            if(link) link.classList.add('active');
        }
        navLinks.forEach(l => l.addEventListener('click', function(e) {
            const h = this.getAttribute('href');
            if(h.startsWith('#')) { e.preventDefault(); history.pushState(null,null,h); showSection(h); }
        }));
        showSection(window.location.hash);
        window.addEventListener('popstate', () => showSection(window.location.hash));
    });

    // 3. MODALS
    function openAddModal() {
        const m = new bootstrap.Modal(document.getElementById('studentModal'));
        document.getElementById('modalTitle').innerText = "Add Student";
        document.getElementById('formAction').value = "add";
        document.getElementById('inputRoll').value = ""; document.getElementById('inputRoll').readOnly = false;
        document.getElementById('inputName').value = ""; document.getElementById('inputDept').value = "";
        m.show();
    }
    function openEditModal(d) {
        const m = new bootstrap.Modal(document.getElementById('studentModal'));
        document.getElementById('modalTitle').innerText = "Edit Student";
        document.getElementById('formAction').value = "edit";
        document.getElementById('inputRoll').value = d.roll_no; document.getElementById('inputRoll').readOnly = true;
        document.getElementById('inputName').value = d.name; document.getElementById('inputDept').value = d.department;
        document.getElementById('inputYear').value = d.year; document.getElementById('inputAddr').value = d.address;
        m.show();
    }

    // 4. AI LOGIC (Advanced)
    // const micBtn = document.getElementById('micBtn');
    // const bubble = document.getElementById('aiBubble');
    // const responseText = document.getElementById('aiResponse');
    // let recognition;

    // if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
    //     const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    //     recognition = new SpeechRecognition();
    //     recognition.continuous = false;
    //     recognition.lang = 'en-US';

    //     recognition.onstart = () => { micBtn.style.background = "#EF4444"; bubble.style.display = "block"; responseText.innerText = "Listening..."; };
    //     recognition.onend = () => { micBtn.style.background = "#4f46e5"; };
    //     recognition.onresult = (event) => {
    //         const t = event.results[0][0].transcript;
    //         responseText.innerHTML = `<strong>You:</strong> ${t}<br>...`;
    //         askGemini(t);
    //     };
    // }
// 4. AI LOGIC (Final Polish)
const micBtn = document.getElementById('micBtn');
const bubble = document.getElementById('aiBubble');
const responseText = document.getElementById('aiResponse');

let recognition;
let isSpeaking = false;
let isListening = false;
let autoHideTimer; // To cancel the auto-hide if you click

// --- INITIALIZATION ---
if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();
    recognition.continuous = false;
    recognition.lang = 'en-US';

    // 1. MIC STARTS
    recognition.onstart = () => {
        clearTimeout(autoHideTimer); // Don't hide if we just started
        isListening = true;
        isSpeaking = false;
        
        // UI: Red Pulse
        micBtn.className = "listening-mode"; // Reset classes to just this one
        micBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        
        bubble.style.display = "block";
        responseText.innerText = "Listening...";
    };

    // 2. MIC STOPS
    recognition.onend = () => {
        isListening = false;
        micBtn.classList.remove('listening-mode');
        // If we aren't moving to "Thinking" (no result), hide bubble
        if (responseText.innerText === "Listening...") {
            fullReset();
        }
    };

    // 3. SPEECH CAPTURED
    recognition.onresult = (event) => {
        const t = event.results[0][0].transcript;
        responseText.innerHTML = `<strong>You:</strong> ${t}<br>...Thinking...`;
        askGemini(t);
    };
}

// --- TOGGLE BUTTON ---
function toggleVoice() {
    // If AI is Talking -> STOP EVERYTHING
    if (isSpeaking) {
        fullReset();
        return;
    }
    // If Mic is On -> STOP MIC
    if (isListening) {
        recognition.stop();
        return;
    }
    // If Idle -> START MIC
    fullReset(); // Safety clear
    if(recognition) recognition.start();
}

// --- HELPER: NUCLEAR RESET (Hides Everything) ---
function fullReset() {
    // 1. Stop Audio & Mic
    window.speechSynthesis.cancel();
    if(recognition) try { recognition.stop(); } catch(e){}

    // 2. Reset State
    isSpeaking = false;
    isListening = false;

    // 3. Reset Button UI
    micBtn.className = ""; // Remove all animation classes
    micBtn.style.backgroundColor = "#4f46e5"; // Blue
    micBtn.innerHTML = '<i class="fas fa-microphone"></i>';

    // 4. HIDE THE TEXT BUBBLE
    bubble.style.display = "none";
    responseText.innerText = "";
}

// --- CLICK OUTSIDE TO CLOSE ---
document.addEventListener('click', function(event) {
    const isClickInsideMic = micBtn.contains(event.target);
    const isClickInsideBubble = bubble.contains(event.target);

    // If you clicked ANYWHERE except the Mic or the Bubble...
    if (!isClickInsideMic && !isClickInsideBubble) {
        if (isSpeaking || isListening || bubble.style.display === "block") {
            fullReset(); // Hide everything immediately
        }
    }
});
    // function toggleVoice() { if(recognition) recognition.start(); }

    async function askGemini(query) {
        const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${GEMINI_API_KEY}`;
        
        // Prompt Instructions: Tells Gemini how to read your DB data
    //     const prompt = `
    //         You are an attendance admin assistant. 
    //         Here is the current data JSON: ${JSON.stringify(aiContext)}.
            
    //         Data Definitions:
    //         - 'roll': The Student Roll Number
    //         - 'today': Their status today ('Present', 'Late', or 'Absent')
    //         - 'late_days': Total times they were late
            
    //         Answer these questions based on the JSON:
    //         1. "Who is late today?" -> List roll numbers where 'today' == 'Late'.
    //         2. "Who is present?" -> List roll numbers where 'today' == 'Present'.
    //         3. "Who is absent?" -> List roll numbers where 'today' == 'Absent'.
    //         4. "Who is frequently late?" -> List roll numbers with high 'late_days'.
            
    //         User Query: ${query}
    //         Keep the answer short and conversational.
    //     `;

    //     try {
    //         const response = await fetch(url, {
    //             method: 'POST',
    //             headers: { 'Content-Type': 'application/json' },
    //             body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] })
    //         });
            
    //         const data = await response.json();
    //         const reply = data.candidates?.[0]?.content?.parts?.[0]?.text || "I couldn't process that.";
            
    //         responseText.innerText = reply;
            
    //         // Speak the answer
    //         speak(reply);

    //     } catch (error) {
    //         console.error(error);
    //         responseText.innerText = "Error connecting to AI.";
    //     }
    // }
       const prompt = `
            You are an intelligent attendance admin assistant named "Upasthita AI".
            
            Here is the real-time Student Database in JSON format: 
            ${JSON.stringify(aiContext)}
            
            DATA DEFINITIONS:
            - 'roll': Unique ID of the student.
            - 'name': Full Name of the student.
            - 'address': Where the student lives.
            - 'pct': Their attendance percentage.
            - 'today': Status today ('Present', 'Late', or 'Absent').

            INSTRUCTIONS:
            1. Answer questions naturally based ONLY on the JSON data provided above.
            2. If asked about a specific student (e.g., "Who is Roll 101?"), provide their Name, Address, and Status.
            3. If asked "Who lives in Kolkata?", list the names of students with 'address' containing "Kolkata".
            4. If asked "Is Rahul present?", find the student named Rahul and check his 'today' status.
            5. Keep answers short, professional, and direct. Do not mention "JSON" or "Data" in your reply.

            USER QUESTION: "${query}"
        `;

       try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] })
        });
        
        const data = await response.json();
        const reply = data.candidates?.[0]?.content?.parts?.[0]?.text || "I didn't understand.";
        
        responseText.innerText = reply;
        speak(reply);

    } catch (error) {
        console.error(error);
        responseText.innerText = "Connection Error.";
        // Hide error after 3 seconds
        setTimeout(fullReset, 15000);
    }
}

// --- TEXT TO SPEECH ---
function speak(text) {
    window.speechSynthesis.cancel();
    const speech = new SpeechSynthesisUtterance(text);
    speech.text = text.replace(/[*#]/g, '');
    
    speech.onstart = function() {
        isSpeaking = true;
        micBtn.className = "speaking-mode"; // Wave animation
        micBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
    };

    speech.onend = function() {
        // STOP ANIMATION BUT KEEP TEXT VISIBLE FOR 5 SECONDS
        isSpeaking = false;
        micBtn.classList.remove('speaking-mode');
        micBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        
        // Auto-hide bubble after 5 seconds
        autoHideTimer = setTimeout(fullReset, 5000);
    };

    window.speechSynthesis.speak(speech);
}
</script>
</body>
</html>