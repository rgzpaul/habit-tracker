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
    $data = json_decode($content, true) ?? ['columns' => [], 'days' => [], 'startDate' => date('Y-m-d'), 'endDate' => date('Y-m-d')];

    // Migrate old format (simple string array) to new format (objects with name and frequency)
    if (!empty($data['columns']) && isset($data['columns'][0]) && is_string($data['columns'][0])) {
        $data['columns'] = array_map(function($name) {
            return ['name' => $name, 'frequency' => 7]; // Default to daily (7x/week)
        }, $data['columns']);
    }

    return $data;
}

function getHabitNames(array $data): array {
    return array_map(function($habit) {
        return is_array($habit) ? $habit['name'] : $habit;
    }, $data['columns']);
}

function getHabitByName(array $data, string $name): ?array {
    foreach ($data['columns'] as $habit) {
        if ((is_array($habit) ? $habit['name'] : $habit) === $name) {
            return is_array($habit) ? $habit : ['name' => $habit, 'frequency' => 7];
        }
    }
    return null;
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
        'columns'       => $data['columns'],
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
            $frequency = intval($_POST['frequency'] ?? 7);
            $frequency = max(1, min(7, $frequency)); // Clamp between 1 and 7
            $habitNames = getHabitNames($data);
            if ($habitName && !in_array($habitName, $habitNames)) {
                $data['columns'][] = ['name' => $habitName, 'frequency' => $frequency];
                saveData($data);
            }
            break;

        case 'update_habit_frequency':
            $habitName = $_POST['habit_name'] ?? '';
            $frequency = intval($_POST['frequency'] ?? 7);
            $frequency = max(1, min(7, $frequency));
            foreach ($data['columns'] as &$habit) {
                if ((is_array($habit) ? $habit['name'] : $habit) === $habitName) {
                    $habit = ['name' => $habitName, 'frequency' => $frequency];
                    break;
                }
            }
            saveData($data);
            break;

        case 'remove_habit':
            $habitName = $_POST['habit_name'] ?? '';
            $habitNames = getHabitNames($data);
            if (in_array($habitName, $habitNames)) {
                $data['columns'] = array_values(array_filter($data['columns'], function($h) use ($habitName) {
                    return (is_array($h) ? $h['name'] : $h) !== $habitName;
                }));
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
    <meta name="theme-color" content="#334155">
    <link rel="manifest" href="manifest.php">
    <title>Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        input[type="text"], input[type="date"], input[type="number"] {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <main class="max-w-3xl mx-auto px-4 py-8">

        <!-- Header -->
        <header class="flex items-center justify-between mb-8">
            <h1 class="text-xl font-semibold text-slate-800">Settings</h1>
            <nav class="flex items-center gap-1">
                <a href="index.php" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Tracker">
                    <i data-lucide="layout-grid" class="w-5 h-5"></i>
                </a>
                <a href="report.php" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Report">
                    <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                </a>
            </nav>
        </header>

        <!-- Stats -->
        <section id="stats" class="grid grid-cols-2 gap-3 mb-8">
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-2xl font-semibold text-slate-800" id="stat-habits"><?= $stats['habitsCount'] ?></div>
                <div class="text-xs text-slate-400 uppercase tracking-wide mt-1">Habits</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-2xl font-semibold text-slate-800" id="stat-days"><?= $stats['trackingDays'] ?></div>
                <div class="text-xs text-slate-400 uppercase tracking-wide mt-1">Days</div>
            </div>
        </section>

        <!-- Habits -->
        <section class="bg-white rounded-xl border border-slate-200 p-5 mb-4">
            <h2 class="text-sm font-medium text-slate-800 mb-4">Habits</h2>

            <form id="add-habit-form" class="mb-4">
                <div class="flex gap-2">
                    <input type="text"
                           id="habit-name"
                           placeholder="New habit"
                           required
                           class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-transparent">
                    <div class="flex items-center gap-1">
                        <input type="number"
                               id="habit-frequency"
                               value="7"
                               min="1"
                               max="7"
                               title="Times per week"
                               class="w-14 px-2 py-2 text-sm text-center border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-transparent">
                        <span class="text-xs text-slate-400">/wk</span>
                    </div>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Add
                    </button>
                </div>
            </form>

            <div id="habits-list" class="space-y-1">
                <?php foreach ($data['columns'] as $habit):
                    $habitName = is_array($habit) ? $habit['name'] : $habit;
                    $habitFreq = is_array($habit) ? $habit['frequency'] : 7;
                ?>
                    <div class="habit-item flex items-center justify-between py-2 px-3 rounded-lg hover:bg-slate-50 group" data-habit="<?= htmlspecialchars($habitName) ?>" data-frequency="<?= $habitFreq ?>">
                        <span class="text-sm text-slate-600"><?= htmlspecialchars($habitName) ?></span>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1 opacity-60 group-hover:opacity-100 transition-opacity">
                                <input type="number"
                                       class="habit-freq-input w-10 px-1 py-0.5 text-xs text-center border border-slate-200 rounded focus:outline-none focus:ring-1 focus:ring-slate-300"
                                       value="<?= $habitFreq ?>"
                                       min="1"
                                       max="7"
                                       title="Times per week">
                                <span class="text-xs text-slate-400">/wk</span>
                            </div>
                            <button class="remove-habit p-1 text-slate-300 hover:text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Tracking Period -->
        <section class="bg-white rounded-xl border border-slate-200 p-5 mb-4">
            <h2 class="text-sm font-medium text-slate-800 mb-4">Tracking Period</h2>

            <form id="settings-form" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Start Date</label>
                        <input type="date"
                               id="start-date"
                               value="<?= $stats['startDate'] ?>"
                               required
                               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Days</label>
                        <input type="number"
                               id="number-of-days"
                               value="<?= $stats['trackingDays'] ?>"
                               min="1"
                               max="365"
                               required
                               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-transparent">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <p id="current-period" class="text-xs text-slate-400">
                        <?= $stats['startDate'] ?> &rarr; <?= $stats['endDate'] ?>
                    </p>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save
                    </button>
                </div>
            </form>
        </section>

        <!-- Data -->
        <section class="bg-white rounded-xl border border-slate-200 p-5 mb-4">
            <h2 class="text-sm font-medium text-slate-800 mb-4">Data</h2>

            <div class="flex gap-2">
                <form method="POST" action="admin.php" class="flex-1">
                    <input type="hidden" name="action" value="export_data">
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Export
                    </button>
                </form>

                <form id="import-form" enctype="multipart/form-data" class="flex-1">
                    <label class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors cursor-pointer">
                        <i data-lucide="upload" class="w-4 h-4"></i>
                        Import
                        <input type="file" id="import-file" accept=".json" class="hidden">
                    </label>
                </form>
            </div>
        </section>

        <!-- Danger Zone -->
        <section class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-medium text-slate-800 mb-4">Danger Zone</h2>

            <button id="reset-btn" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 border border-slate-300 text-slate-500 text-sm font-medium rounded-lg hover:border-slate-400 hover:text-slate-600 transition-colors">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                Reset All Data
            </button>
            <p class="text-xs text-slate-400 mt-2 text-center">
                Clears all check marks. Habits and settings are kept.
            </p>
        </section>

    </main>

    <script>
        if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');
        lucide.createIcons();

        $(document).ready(function() {

            // Add Habit
            $('#add-habit-form').on('submit', function(e) {
                e.preventDefault();
                const habitName = $('#habit-name').val().trim();
                const frequency = $('#habit-frequency').val() || 7;
                if (!habitName) return;

                $.post('admin.php', { action: 'add_habit', habit_name: habitName, frequency: frequency }, function() {
                    const html = `
                        <div class="habit-item flex items-center justify-between py-2 px-3 rounded-lg hover:bg-slate-50 group" data-habit="${habitName}" data-frequency="${frequency}">
                            <span class="text-sm text-slate-600">${habitName}</span>
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-1 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <input type="number"
                                           class="habit-freq-input w-10 px-1 py-0.5 text-xs text-center border border-slate-200 rounded focus:outline-none focus:ring-1 focus:ring-slate-300"
                                           value="${frequency}"
                                           min="1"
                                           max="7"
                                           title="Times per week">
                                    <span class="text-xs text-slate-400">/wk</span>
                                </div>
                                <button class="remove-habit p-1 text-slate-300 hover:text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    $('#habits-list').append(html);
                    $('#habit-name').val('');
                    $('#habit-frequency').val(7);
                    lucide.createIcons();
                    updateHabitCount(1);
                });
            });

            // Update habit frequency
            $(document).on('change', '.habit-freq-input', function() {
                const item = $(this).closest('.habit-item');
                const habitName = item.data('habit');
                const frequency = $(this).val();

                $.post('admin.php', { action: 'update_habit_frequency', habit_name: habitName, frequency: frequency });
                item.data('frequency', frequency);
            });

            // Remove Habit
            $(document).on('click', '.remove-habit', function() {
                const item = $(this).closest('.habit-item');
                const habitName = item.data('habit');

                if (!confirm(`Remove "${habitName}"?`)) return;

                $.post('admin.php', { action: 'remove_habit', habit_name: habitName }, function() {
                    item.remove();
                    updateHabitCount(-1);
                });
            });

            // Update date range preview dynamically
            function updateDateRangePreview() {
                const startDate = $('#start-date').val();
                const numberOfDays = parseInt($('#number-of-days').val()) || 0;

                if (startDate && numberOfDays > 0) {
                    const start = new Date(startDate + 'T00:00:00');
                    const end = new Date(start);
                    end.setDate(end.getDate() + numberOfDays - 1);
                    const endStr = end.toISOString().split('T')[0];
                    $('#current-period').html(`${startDate} &rarr; ${endStr}`);
                }
            }

            // Listen for input changes on Start Date and Days
            $('#start-date, #number-of-days').on('input change', updateDateRangePreview);

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
                    $('#stat-days').text(numberOfDays);
                    updateDateRangePreview();
                });
            });

            // Import Data
            $('#import-file').on('change', function() {
                if (this.files.length === 0) return;

                const formData = new FormData();
                formData.append('action', 'import_data');
                formData.append('import_file', this.files[0]);

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
                if (!confirm('Reset all tracking data? This cannot be undone.')) return;

                $.post('admin.php', { action: 'reset_data' }, function() {
                    location.reload();
                });
            });

            function updateHabitCount(delta) {
                const current = parseInt($('#stat-habits').text());
                $('#stat-habits').text(current + delta);
            }

        });
    </script>
</body>
</html>
