<?php
/**
 * Admin Panel - Habit Tracker
 * Manage habits, tracking period, and data import/export
 */

const DATA_FILE = 'data.json';

// ============================================================================
// Data Functions
// ============================================================================

function loadData(): array {
    $content = file_get_contents(DATA_FILE);
    return json_decode($content, true) ?? ['columns' => [], 'days' => [], 'startDate' => date('Y-m-d'), 'endDate' => date('Y-m-d')];
}

function saveData(array $data): void {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function calculateStats(array $data): array {
    $startDate = new DateTime($data['startDate']);
    $endDate = new DateTime($data['endDate']);
    $trackingDays = $startDate->diff($endDate)->days + 1;

    $daysWithData = count($data['days']);
    $totalChecks = array_sum(array_map('count', $data['days']));

    return [
        'habitsCount'   => count($data['columns']),
        'trackingDays'  => $trackingDays,
        'daysWithData'  => $daysWithData,
        'totalChecks'   => $totalChecks,
        'startDate'     => $data['startDate'],
        'endDate'       => $data['endDate'],
    ];
}

// ============================================================================
// AJAX Request Handler
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = loadData();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_habit':
            $habitName = trim($_POST['habit_name'] ?? '');
            if ($habitName && !in_array($habitName, $data['columns'])) {
                $data['columns'][] = $habitName;
                saveData($data);
            }
            break;

        case 'remove_habit':
            $habitName = $_POST['habit_name'] ?? '';
            if (in_array($habitName, $data['columns'])) {
                $data['columns'] = array_values(array_filter($data['columns'], fn($h) => $h !== $habitName));
                foreach ($data['days'] as $date => &$dayData) {
                    unset($dayData[$habitName]);
                    if (empty($dayData)) {
                        unset($data['days'][$date]);
                    }
                }
                saveData($data);
            }
            break;

        case 'update_settings':
            $startDateStr = $_POST['start_date'] ?? '';
            $numberOfDays = intval($_POST['number_of_days'] ?? 0);
            if ($startDateStr && $numberOfDays > 0) {
                $start = new DateTime($startDateStr);
                $end = clone $start;
                $end->modify('+' . ($numberOfDays - 1) . ' days');
                $data['startDate'] = $start->format('Y-m-d');
                $data['endDate'] = $end->format('Y-m-d');
                saveData($data);
            }
            break;

        case 'reset_data':
            $data['days'] = [];
            saveData($data);
            break;

        case 'export_data':
            $filename = 'habit-tracker-backup-' . date('Y-m-d') . '.json';
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"$filename\"");
            echo json_encode($data, JSON_PRETTY_PRINT);
            exit;

        case 'import_data':
            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                $imported = json_decode(file_get_contents($_FILES['import_file']['tmp_name']), true);
                if ($imported && isset($imported['columns']) && isset($imported['days'])) {
                    saveData($imported);
                }
            }
            break;
    }

    exit;
}

// ============================================================================
// Page Load
// ============================================================================

$data = loadData();
$stats = calculateStats($data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= $stats['trackingDays'] ?> Days Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 p-4 md:p-8 flex justify-center">
    <div class="max-w-4xl w-full">

        <!-- Header -->
        <header class="flex justify-between items-center mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                <?= $stats['trackingDays'] ?> Days Tracker - Admin
            </h1>
            <nav class="flex gap-2">
                <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">Tracker</a>
                <a href="report.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors text-sm">Report</a>
            </nav>
        </header>

        <!-- Statistics Overview -->
        <section id="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500 uppercase tracking-wider">Habits</div>
                <div class="text-2xl font-bold text-gray-800" id="stat-habits"><?= $stats['habitsCount'] ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500 uppercase tracking-wider">Tracking Days</div>
                <div class="text-2xl font-bold text-gray-800" id="stat-days"><?= $stats['trackingDays'] ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500 uppercase tracking-wider">Days with Data</div>
                <div class="text-2xl font-bold text-gray-800" id="stat-data-days"><?= $stats['daysWithData'] ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500 uppercase tracking-wider">Total Checks</div>
                <div class="text-2xl font-bold text-gray-800" id="stat-checks"><?= $stats['totalChecks'] ?></div>
            </div>
        </section>

        <!-- Main Content Grid -->
        <div class="grid md:grid-cols-2 gap-6">

            <!-- Manage Habits -->
            <section class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Manage Habits</h2>

                <form id="add-habit-form" class="mb-4">
                    <div class="flex gap-2">
                        <input type="text"
                               id="habit-name"
                               placeholder="New habit name"
                               required
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            Add
                        </button>
                    </div>
                </form>

                <div id="habits-list" class="space-y-2">
                    <?php foreach ($data['columns'] as $habit): ?>
                        <div class="habit-item flex justify-between items-center p-2 bg-gray-50 rounded" data-habit="<?= htmlspecialchars($habit) ?>">
                            <span class="text-gray-700"><?= htmlspecialchars($habit) ?></span>
                            <button class="remove-habit text-red-600 hover:text-red-800 text-sm">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Tracking Period Settings -->
            <section class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Tracking Period</h2>

                <form id="settings-form" class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Start Date</label>
                        <input type="date"
                               id="start-date"
                               value="<?= $stats['startDate'] ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Number of Days</label>
                        <input type="number"
                               id="number-of-days"
                               value="<?= $stats['trackingDays'] ?>"
                               min="1"
                               max="365"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <p id="current-period" class="text-sm text-gray-500">
                        Current: <?= $stats['startDate'] ?> to <?= $stats['endDate'] ?>
                    </p>

                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Update Settings
                    </button>
                </form>
            </section>

            <!-- Data Management -->
            <section class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Data Management</h2>

                <div class="space-y-3">
                    <form method="POST" action="admin.php">
                        <input type="hidden" name="action" value="export_data">
                        <button type="submit" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                            Export Data (JSON)
                        </button>
                    </form>

                    <form id="import-form" enctype="multipart/form-data" class="flex gap-2">
                        <input type="file"
                               id="import-file"
                               accept=".json"
                               required
                               class="flex-1 text-sm text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                            Import
                        </button>
                    </form>
                </div>
            </section>

            <!-- Danger Zone -->
            <section class="bg-white rounded-lg shadow p-6 border-2 border-red-200">
                <h2 class="text-lg font-bold text-red-600 mb-4">Danger Zone</h2>

                <button id="reset-btn" class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                    Reset All Tracking Data
                </button>
                <p class="text-sm text-gray-500 mt-2">
                    This will delete all check marks but keep your habits and settings.
                </p>
            </section>

        </div>

        <!-- Footer -->
        <footer class="mt-6 text-center text-sm text-gray-500">
            Last updated: <?= date('Y-m-d H:i:s') ?>
        </footer>

    </div>

    <script>
        $(document).ready(function() {

            // Add Habit
            $('#add-habit-form').on('submit', function(e) {
                e.preventDefault();
                const habitName = $('#habit-name').val().trim();
                if (!habitName) return;

                $.post('admin.php', { action: 'add_habit', habit_name: habitName }, function() {
                    const html = `
                        <div class="habit-item flex justify-between items-center p-2 bg-gray-50 rounded" data-habit="${habitName}">
                            <span class="text-gray-700">${habitName}</span>
                            <button class="remove-habit text-red-600 hover:text-red-800 text-sm">Remove</button>
                        </div>
                    `;
                    $('#habits-list').append(html);
                    $('#habit-name').val('');
                    updateHabitCount(1);
                });
            });

            // Remove Habit
            $(document).on('click', '.remove-habit', function() {
                const item = $(this).closest('.habit-item');
                const habitName = item.data('habit');

                if (!confirm(`Remove habit '${habitName}'? This will delete all related data.`)) return;

                $.post('admin.php', { action: 'remove_habit', habit_name: habitName }, function() {
                    item.remove();
                    updateHabitCount(-1);
                });
            });

            // Update Settings
            $('#settings-form').on('submit', function(e) {
                e.preventDefault();
                const startDate = $('#start-date').val();
                const numberOfDays = $('#number-of-days').val();

                $.post('admin.php', {
                    action: 'update_settings',
                    start_date: startDate,
                    number_of_days: numberOfDays
                }, function() {
                    const start = new Date(startDate);
                    const end = new Date(start);
                    end.setDate(end.getDate() + parseInt(numberOfDays) - 1);
                    const endStr = end.toISOString().split('T')[0];

                    $('#stat-days').text(numberOfDays);
                    $('#current-period').text(`Current: ${startDate} to ${endStr}`);
                });
            });

            // Import Data
            $('#import-form').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData();
                formData.append('action', 'import_data');
                formData.append('import_file', $('#import-file')[0].files[0]);

                $.ajax({
                    url: 'admin.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function() {
                        location.reload();
                    }
                });
            });

            // Reset Data
            $('#reset-btn').on('click', function() {
                if (!confirm('Are you sure you want to reset ALL tracking data? This cannot be undone.')) return;

                $.post('admin.php', { action: 'reset_data' }, function() {
                    $('#stat-data-days').text('0');
                    $('#stat-checks').text('0');
                });
            });

            // Helper to update habit count
            function updateHabitCount(delta) {
                const current = parseInt($('#stat-habits').text());
                $('#stat-habits').text(current + delta);
            }

        });
    </script>
</body>
</html>
