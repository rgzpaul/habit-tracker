<?php
// Load data from JSON
$data = json_decode(file_get_contents('data.json'), true);

// Get start and end dates from JSON
$startDate = new DateTime($data['startDate']);
$endDate = new DateTime($data['endDate']);

$currentDate = new DateTime('now');
$currentDate->setTime(0, 0, 0); // Ensure midnight

// Calculate remaining days
$interval = $endDate->diff($currentDate);
$daysRemaining = max(0, $interval->days + 1); // Include today

// Calculate elapsed days (including today)
$elapsedDays = max(1, (int)$currentDate->diff($startDate)->format('%a') + 1);

// Count total completions
$totalChecks = 0;
$habitStats = array_fill_keys($data['columns'], 0); // Initialize habit stats

foreach ($data['days'] as $date => $habits) {
    $habitDate = new DateTime($date);
    if ($habitDate >= $startDate && $habitDate <= $currentDate) { // Only include dates on or after startDate
        foreach ($habits as $habit => $completed) {
            if ($completed) {
                $habitStats[$habit]++;
                $totalChecks++;
            }
        }
    }
}

// Calculate total progress percentage
$totalPossible = $elapsedDays * count($data['columns']); // Total possible checks for elapsed days
$progressPercent = $totalPossible > 0 ? round(($totalChecks / $totalPossible) * 100) : 0;

// Day names array
$dayNames = [
    1 => 'LUN', 2 => 'MAR', 3 => 'MER', 
    4 => 'GIO', 5 => 'VEN', 6 => 'SAB', 0 => 'DOM'
];

?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit Tracking Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-4 md:p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6">Habit Tracking Report</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Total Progress</h2>
                <div class="text-3xl font-bold text-blue-600"><?= $totalChecks ?>/<?= $totalPossible ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Days Remaining</h2>
                <div class="text-3xl font-bold text-purple-600"><?= $daysRemaining ?></div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4">Tracking Period</h2>
            <p class="text-sm text-gray-600">Start Date: <span class="font-bold">
                <?= $dayNames[$startDate->format('w')] . ' ' . $startDate->format('d/m/y') ?></span></p>
            <p class="text-sm text-gray-600">End Date: <span class="font-bold">
                <?= $dayNames[$endDate->format('w')] . ' ' . $endDate->format('d/m/y') ?></span></p>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Habit</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Completed</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Progress</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($data['columns'] as $habit): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($habit) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                <?= $habitStats[$habit] ?>/<?= $elapsedDays ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 max-w-[200px] mr-2">
                                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $elapsedDays > 0 ? round(($habitStats[$habit] / $elapsedDays) * 100) : 0 ?>%"></div>
                                    </div>
                                    <?= $elapsedDays > 0 ? round(($habitStats[$habit] / $elapsedDays) * 100) : 0 ?>%
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
