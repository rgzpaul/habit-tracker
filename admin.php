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
// Action Handlers
// ============================================================================

function handleAddHabit(array &$data, string $habitName): array {
    $habitName = trim($habitName);

    if (empty($habitName)) {
        return ['error', 'Habit name cannot be empty.'];
    }

    if (in_array($habitName, $data['columns'])) {
        return ['error', "Habit '$habitName' already exists."];
    }

    $data['columns'][] = $habitName;
    saveData($data);
    return ['success', "Habit '$habitName' added successfully."];
}

function handleRemoveHabit(array &$data, string $habitName): array {
    if (!in_array($habitName, $data['columns'])) {
        return ['error', "Habit '$habitName' not found."];
    }

    $data['columns'] = array_values(array_filter(
        $data['columns'],
        fn($h) => $h !== $habitName
    ));

    foreach ($data['days'] as $date => &$dayData) {
        unset($dayData[$habitName]);
        if (empty($dayData)) {
            unset($data['days'][$date]);
        }
    }

    saveData($data);
    return ['success', "Habit '$habitName' removed successfully."];
}

function handleUpdateSettings(array &$data, string $startDateStr, int $numberOfDays): array {
    if (empty($startDateStr) || $numberOfDays < 1) {
        return ['error', 'Invalid start date or number of days.'];
    }

    $start = new DateTime($startDateStr);
    $end = clone $start;
    $end->modify('+' . ($numberOfDays - 1) . ' days');

    $data['startDate'] = $start->format('Y-m-d');
    $data['endDate'] = $end->format('Y-m-d');
    saveData($data);

    return ['success', "Tracking period updated: {$data['startDate']} to {$data['endDate']} ($numberOfDays days)."];
}

function handleResetData(array &$data): array {
    $data['days'] = [];
    saveData($data);
    return ['success', 'All tracking data has been reset.'];
}

function handleExportData(array $data): void {
    $filename = 'habit-tracker-backup-' . date('Y-m-d') . '.json';
    header('Content-Type: application/json');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function handleImportData(array &$data, array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error', 'File upload failed.'];
    }

    $imported = json_decode(file_get_contents($file['tmp_name']), true);

    if (!$imported || !isset($imported['columns']) || !isset($imported['days'])) {
        return ['error', 'Invalid backup file format.'];
    }

    saveData($imported);
    $data = $imported;
    return ['success', 'Data imported successfully.'];
}

// ============================================================================
// Request Processing
// ============================================================================

$data = loadData();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $result = null;

    switch ($action) {
        case 'add_habit':
            $result = handleAddHabit($data, $_POST['habit_name'] ?? '');
            break;

        case 'remove_habit':
            $result = handleRemoveHabit($data, $_POST['habit_to_remove'] ?? '');
            break;

        case 'update_settings':
            $result = handleUpdateSettings(
                $data,
                $_POST['start_date'] ?? '',
                intval($_POST['number_of_days'] ?? 0)
            );
            break;

        case 'reset_data':
            $result = handleResetData($data);
            break;

        case 'export_data':
            handleExportData($data);
            break;

        case 'import_data':
            $result = handleImportData($data, $_FILES['import_file'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
            break;
    }

    if ($result) {
        [$messageType, $message] = $result;
    }

    $data = loadData();
}

$stats = calculateStats($data);

// ============================================================================
// HTML Rendering Helpers
// ============================================================================

function renderStatCard(string $label, $value): string {
    return <<<HTML
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500 uppercase tracking-wider">$label</div>
        <div class="text-2xl font-bold text-gray-800">$value</div>
    </div>
    HTML;
}

function renderHabitItem(string $habit): string {
    $escaped = htmlspecialchars($habit);
    return <<<HTML
    <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
        <span class="text-gray-700">$escaped</span>
        <form method="POST" class="inline" onsubmit="return confirm('Remove habit \\'$escaped\\'? This will delete all related data.');">
            <input type="hidden" name="action" value="remove_habit">
            <input type="hidden" name="habit_to_remove" value="$escaped">
            <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Remove</button>
        </form>
    </div>
    HTML;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= $stats['trackingDays'] ?> Days Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        <!-- Message Alert -->
        <?php if ($message): ?>
            <?php $alertClass = $messageType === 'success'
                ? 'bg-green-100 text-green-800 border-green-200'
                : 'bg-red-100 text-red-800 border-red-200'; ?>
            <div class="mb-6 p-4 rounded-lg border <?= $alertClass ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <section class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <?= renderStatCard('Habits', $stats['habitsCount']) ?>
            <?= renderStatCard('Tracking Days', $stats['trackingDays']) ?>
            <?= renderStatCard('Days with Data', $stats['daysWithData']) ?>
            <?= renderStatCard('Total Checks', $stats['totalChecks']) ?>
        </section>

        <!-- Main Content Grid -->
        <div class="grid md:grid-cols-2 gap-6">

            <!-- Manage Habits -->
            <section class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Manage Habits</h2>

                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add_habit">
                    <div class="flex gap-2">
                        <input type="text"
                               name="habit_name"
                               placeholder="New habit name"
                               required
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            Add
                        </button>
                    </div>
                </form>

                <div class="space-y-2">
                    <?php foreach ($data['columns'] as $habit): ?>
                        <?= renderHabitItem($habit) ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Tracking Period Settings -->
            <section class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Tracking Period</h2>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_settings">

                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Start Date</label>
                        <input type="date"
                               name="start_date"
                               value="<?= $stats['startDate'] ?>"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Number of Days</label>
                        <input type="number"
                               name="number_of_days"
                               value="<?= $stats['trackingDays'] ?>"
                               min="1"
                               max="365"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <p class="text-sm text-gray-500">
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
                    <form method="POST">
                        <input type="hidden" name="action" value="export_data">
                        <button type="submit" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                            Export Data (JSON)
                        </button>
                    </form>

                    <form method="POST" enctype="multipart/form-data" class="flex gap-2">
                        <input type="hidden" name="action" value="import_data">
                        <input type="file"
                               name="import_file"
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

                <form method="POST" onsubmit="return confirm('Are you sure you want to reset ALL tracking data? This cannot be undone.');">
                    <input type="hidden" name="action" value="reset_data">
                    <button type="submit" class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Reset All Tracking Data
                    </button>
                    <p class="text-sm text-gray-500 mt-2">
                        This will delete all check marks but keep your habits and settings.
                    </p>
                </form>
            </section>

        </div>

        <!-- Footer -->
        <footer class="mt-6 text-center text-sm text-gray-500">
            Last updated: <?= date('Y-m-d H:i:s') ?>
        </footer>

    </div>
</body>
</html>
