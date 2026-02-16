<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/calendar-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

// Get current month/year or from params
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Handle iCal export before any output
if (isset($_GET['export']) && $_GET['export'] === 'ical') {
    $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    $ical = exportCalendarToICal($pdo, $user_id, $start_date, $end_date);
    
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="tasks_' . $year . '_' . $month . '.ics"');
    
    echo $ical;
    exit;
}

// Get calendar data
$start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$end_date = date('Y-m-t', strtotime($start_date));

$tasks = getUserTaskCalendar($pdo, $user_id, $start_date, $end_date);

// Get statistics
$stats = getCalendarStatistics($pdo, $user_id, $month, $year);

// Group tasks by date
$tasks_by_date = [];
foreach ($tasks as $task) {
    $date = $task['scheduled_date'];
    if (!isset($tasks_by_date[$date])) {
        $tasks_by_date[$date] = [];
    }
    $tasks_by_date[$date][] = $task;
}

// Calculate calendar days
$first_day = date('w', strtotime($start_date));
$days_in_month = date('t', strtotime($start_date));

$current_page = 'task-calendar';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Calendar - ReviewFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .calendar-day {
            min-height: 100px;
            border: 1px solid #ddd;
            padding: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .calendar-day:hover {
            background-color: #f8f9fa;
        }
        .calendar-day.today {
            background-color: #e3f2fd;
        }
        .task-badge {
            font-size: 0.7rem;
            display: block;
            margin-bottom: 2px;
        }
        .calendar-header {
            background: #2c3e50;
            color: white;
            padding: 10px;
            text-align: center;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container my-5">
    <h2 class="mb-4"><i class="bi bi-calendar3"></i> Task Calendar</h2>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5>Total Scheduled</h5>
                    <h3><?php echo $stats['total_scheduled'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5>Completed</h5>
                    <h3 class="text-success"><?php echo $stats['completed'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5>Pending</h5>
                    <h3 class="text-warning"><?php echo $stats['pending'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Month Navigation -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <a href="?month=<?php echo ($month == 1) ? 12 : $month - 1; ?>&year=<?php echo ($month == 1) ? $year - 1 : $year; ?>" 
                       class="btn btn-outline-primary">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                </div>
                <div class="col-md-4 text-center">
                    <h4><?php echo date('F Y', strtotime($start_date)); ?></h4>
                </div>
                <div class="col-md-4 text-end">
                    <a href="?month=<?php echo ($month == 12) ? 1 : $month + 1; ?>&year=<?php echo ($month == 12) ? $year + 1 : $year; ?>" 
                       class="btn btn-outline-primary">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr class="calendar-header">
                            <th>Sun</th>
                            <th>Mon</th>
                            <th>Tue</th>
                            <th>Wed</th>
                            <th>Thu</th>
                            <th>Fri</th>
                            <th>Sat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $day = 1;
                        for ($week = 0; $week < 6; $week++) {
                            echo "<tr>";
                            for ($dow = 0; $dow < 7; $dow++) {
                                if (($week == 0 && $dow < $first_day) || $day > $days_in_month) {
                                    echo "<td class='calendar-day'></td>";
                                } else {
                                    $current_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                                    $is_today = $current_date == date('Y-m-d') ? 'today' : '';
                                    $day_tasks = $tasks_by_date[$current_date] ?? [];
                                    
                                    echo "<td class='calendar-day $is_today'>";
                                    echo "<div class='fw-bold'>$day</div>";
                                    
                                    foreach ($day_tasks as $task) {
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'completed' => 'success',
                                            'assigned' => 'info',
                                            'rejected' => 'danger'
                                        ];
                                        $color = $status_colors[$task['status']] ?? 'secondary';
                                        echo "<span class='badge bg-$color task-badge' title='" . htmlspecialchars($task['title']) . "'>";
                                        echo htmlspecialchars(substr($task['title'], 0, 15));
                                        echo "</span>";
                                    }
                                    
                                    echo "</td>";
                                    $day++;
                                }
                            }
                            echo "</tr>";
                            if ($day > $days_in_month) break;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Export Options -->
    <div class="mt-3">
        <a href="?export=ical&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download"></i> Export to iCal
        </a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
