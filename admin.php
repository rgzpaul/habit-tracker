<?php
// Load data
$dataFile = 'data.json';
$data = json_decode(file_get_contents($dataFile), true);

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_habit':
            $newHabit = trim($_POST['habit_name'] ?? '');
            if ($newHabit && !in_array($newHabit, $data['columns'])) {
                $data['columns'][] = $newHabit;
                file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
                $message = "Habit '$newHabit' added successfully.";
                $messageType = 'success';
            } else if (in_array($newHabit, $data['columns'])) {
                $message = "Habit '$newHabit' already exists.";
                $messageType = 'error';
            }
            break;

        case 'remove_habit':
            $habitToRemove = $_POST['habit_to_remove'] ?? '';
            if ($habitToRemove && in_array($habitToRemove, $data['columns'])) {
                $data['columns'] = array_values(array_filter($data['columns'], fn($h) => $h !== $habitToRemove));
                // Remove habit data from all days
                foreach ($data['days'] as $date => &$dayData) {
                    unset($dayData[$habitToRemove]);
                    if (empty($dayData)) {
                        unset($data['days'][$date]);
                    }
                }
                file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
                $message = "Habit '$habitToRemove' removed successfully.";
                $messageType = 'success';
            }
            break;

        case 'update_settings':
            $startDate = $_POST['start_date'] ?? '';
            $numberOfDays = intval($_POST['number_of_days'] ?? 99);

            if ($startDate && $numberOfDays > 0) {
                $start = new DateTime($startDate);
                $end = clone $start;
                $end->modify("+" . ($numberOfDays - 1) . " days");

                $data['startDate'] = $start->format('Y-m-d');
                $data['endDate'] = $end->format('Y-m-d');
                file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
                $message = "Settings updated. Tracking period: $startDate to " . $end->format('Y-m-d') . " ($numberOfDays days).";
                $messageType = 'success';
            }
            break;

        case 'reset_data':
            $data['days'] = [];
            file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
            $message = "All tracking data has been reset.";
            $messageType = 'success';
            break;

        case 'export_data':
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="habit-tracker-backup-' . date('Y-m-d') . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT);
            exit;

        case 'import_data':
            if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                $importedData = json_decode(file_get_contents($_FILES['import_file']['tmp_name']), true);
                if ($importedData && isset($importedData['columns']) && isset($importedData['days'])) {
                    file_put_contents($dataFile, json_encode($importedData, JSON_PRETTY_PRINT));
                    $data = $importedData;
                    $message = "Data imported successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Invalid backup file format.";
                    $messageType = 'error';
                }
            }
            break;
    }

    // Reload data after changes
    $data = json_decode(file_get_contents($dataFile), true);
}

// Calculate statistics
$totalDays = count($data['days']);
$totalChecks = 0;
foreach ($data['days'] as $dayData) {
    $totalChecks += count($dayData);
}

$startDate = new DateTime($data['startDate']);
$endDate = new DateTime($data['endDate']);
$trackingDays = $startDate->diff($endDate)->days + 1;
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Habit Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 p-4 md:p-8 flex justify-center">
    <div class="max-w-4xl w-full">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Admin Panel</h1>
            <div class="flex gap-2">
                <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">Tracker</a>
                <a href="report.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors text-sm">Report</a>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500 uppercase tracking-wider">Habits</div>
                <div class="text-2xl font-bold text-gray-800"><?= count($data['columns']) ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500 uppercase tracking-wider">Tracking Days</div>
                <div class="text-2xl font-bold text-gray-800"><?= $trackingDays ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500 uppercase tracking-wider">Days with Data</div>
                <div class="text-2xl font-bold text-gray-800"><?= $totalDays ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500 uppercase tracking-wider">Total Checks</div>
                <div class="text-2xl font-bold text-gray-800"><?= $totalChecks ?></div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <!-- Manage Habits -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Manage Habits</h2>

                <!-- Add Habit -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add_habit">
                    <div class="flex gap-2">
                        <input type="text" name="habit_name" placeholder="New habit name" required
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">Add</button>
                    </div>
                </form>

                <!-- Current Habits -->
                <div class="space-y-2">
                    <?php foreach ($data['columns'] as $habit): ?>
                    <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                        <span class="text-gray-700"><?= htmlspecialchars($habit) ?></span>
                        <form method="POST" class="inline" onsubmit="return confirm('Remove habit \'<?= htmlspecialchars($habit) ?>\'? This will delete all related data.');">
                            <input type="hidden" name="action" value="remove_habit">
                            <input type="hidden" name="habit_to_remove" value="<?= htmlspecialchars($habit) ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Remove</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tracking Settings -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Tracking Period</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Start Date</label>
                            <input type="date" name="start_date" value="<?= $data['startDate'] ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Number of Days</label>
                            <input type="number" name="number_of_days" value="<?= $trackingDays ?>" min="1" max="365" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="text-sm text-gray-500">
                            Current period: <?= $data['startDate'] ?> to <?= $data['endDate'] ?>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            Update Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Data Management -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Data Management</h2>

                <div class="space-y-3">
                    <!-- Export -->
                    <form method="POST">
                        <input type="hidden" name="action" value="export_data">
                        <button type="submit" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                            Export Data (JSON)
                        </button>
                    </form>

                    <!-- Import -->
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_data">
                        <div class="flex gap-2">
                            <input type="file" name="import_file" accept=".json" required
                                   class="flex-1 text-sm text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                            <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                                Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="bg-white rounded-lg shadow p-6 border-2 border-red-200">
                <h2 class="text-lg font-bold text-red-600 mb-4">Danger Zone</h2>

                <form method="POST" onsubmit="return confirm('Are you sure you want to reset ALL tracking data? This cannot be undone.');">
                    <input type="hidden" name="action" value="reset_data">
                    <button type="submit" class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Reset All Tracking Data
                    </button>
                    <p class="text-sm text-gray-500 mt-2">This will delete all check marks but keep your habits and settings.</p>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center text-sm text-gray-500">
            Last updated: <?= date('Y-m-d H:i:s') ?>
        </div>
    </div>
</body>
</html>
