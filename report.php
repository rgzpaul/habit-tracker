<?php
/**
 * Habit Tracker - Report & Analytics
 * Display habit completion statistics and progress
 */

const DATA_FILE = 'data.json';

// ============================================================================
// Data Functions
// ============================================================================

function loadData(): array {
    $content = file_get_contents(DATA_FILE);
    return json_decode($content, true) ?? ['columns' => [], 'days' => [], 'startDate' => date('Y-m-d'), 'endDate' => date('Y-m-d')];
}

function calculateReportStats(array $data): array {
    $startDate = new DateTime($data['startDate']);
    $endDate = new DateTime($data['endDate']);
    $currentDate = new DateTime('now');
    $currentDate->setTime(0, 0, 0);

    $trackingDays = $startDate->diff($endDate)->days + 1;
    $elapsedDays = max(0, $currentDate->diff($startDate)->days);
    $daysRemaining = max(0, $trackingDays - $elapsedDays);

    // Initialize habit stats
    $habitStats = array_fill_keys($data['columns'], 0);
    $totalChecks = 0;

    // Count completions within tracking period
    foreach ($data['days'] as $date => $habits) {
        $habitDate = new DateTime($date);
        if ($habitDate >= $startDate && $habitDate <= $endDate) {
            foreach ($habits as $habit => $completed) {
                if ($completed && isset($habitStats[$habit])) {
                    $habitStats[$habit]++;
                    $totalChecks++;
                }
            }
        }
    }

    $totalPossible = $trackingDays * count($data['columns']);
    $progressPercent = $totalPossible > 0 ? round(($totalChecks / $totalPossible) * 100) : 0;

    return [
        'startDate'       => $startDate,
        'endDate'         => $endDate,
        'trackingDays'    => $trackingDays,
        'daysRemaining'   => $daysRemaining,
        'habitStats'      => $habitStats,
        'totalChecks'     => $totalChecks,
        'totalPossible'   => $totalPossible,
        'progressPercent' => $progressPercent,
    ];
}

// ============================================================================
// Day Name Helpers
// ============================================================================

const DAY_NAMES = [
    0 => 'DOM', 1 => 'LUN', 2 => 'MAR', 3 => 'MER',
    4 => 'GIO', 5 => 'VEN', 6 => 'SAB'
];

function formatDateWithDay(DateTime $date): string {
    $dayNum = (int) $date->format('w');
    return DAY_NAMES[$dayNum] . ' ' . $date->format('d/m/y');
}

function calculateHabitProgress(int $completed, int $total): int {
    return $total > 0 ? round(($completed / $total) * 100) : 0;
}

// ============================================================================
// HTML Rendering Helpers
// ============================================================================

function renderSummaryCard(string $title, string $value, string $color = 'blue'): string {
    return <<<HTML
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">$title</h2>
        <div class="text-3xl font-bold text-{$color}-600">$value</div>
    </div>
    HTML;
}

function renderHabitRow(string $habit, int $completed, int $total): string {
    $escaped = htmlspecialchars($habit);
    $percent = calculateHabitProgress($completed, $total);

    return <<<HTML
    <tr>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
            $escaped
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
            $completed/$total
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
            <div class="flex items-center justify-center">
                <div class="w-full bg-gray-200 rounded-full h-2.5 max-w-[200px] mr-2">
                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: {$percent}%"></div>
                </div>
                {$percent}%
            </div>
        </td>
    </tr>
    HTML;
}

// ============================================================================
// Request Processing
// ============================================================================

$data = loadData();
$stats = calculateReportStats($data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $stats['trackingDays'] ?> Days Tracker - Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 p-4 md:p-8">
    <div class="max-w-4xl mx-auto">

        <!-- Header -->
        <header class="flex justify-between items-center mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                <?= $stats['trackingDays'] ?> Days Tracker - Report
            </h1>
            <nav class="flex gap-2">
                <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">Tracker</a>
                <a href="admin.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors text-sm">Admin</a>
            </nav>
        </header>

        <!-- Summary Cards -->
        <section class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
            <?= renderSummaryCard('Total Progress', $stats['totalChecks'] . '/' . $stats['totalPossible'], 'blue') ?>
            <?= renderSummaryCard('Days Remaining', (string) $stats['daysRemaining'], 'purple') ?>
        </section>

        <!-- Tracking Period -->
        <section class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4">Tracking Period</h2>
            <p class="text-sm text-gray-600">
                Start Date: <span class="font-bold"><?= formatDateWithDay($stats['startDate']) ?></span>
            </p>
            <p class="text-sm text-gray-600">
                End Date: <span class="font-bold"><?= formatDateWithDay($stats['endDate']) ?></span>
            </p>
        </section>

        <!-- Habit Statistics Table -->
        <section class="bg-white rounded-lg shadow overflow-hidden">
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
                        <?= renderHabitRow($habit, $stats['habitStats'][$habit], $stats['trackingDays']) ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

    </div>
</body>
</html>
