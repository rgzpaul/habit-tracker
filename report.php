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
    $startDate->setTime(0, 0, 0);
    $endDate = new DateTime($data['endDate']);
    $endDate->setTime(0, 0, 0);
    $currentDate = new DateTime('now');
    $currentDate->setTime(0, 0, 0);

    $trackingDays = $startDate->diff($endDate)->days + 1;

    if ($currentDate > $endDate) {
        $daysRemaining = 0;
    } else {
        $daysRemaining = max(0, $endDate->diff($currentDate)->days + 1);
    }

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

function calculateHabitProgress(int $completed, int $total): int {
    return $total > 0 ? round(($completed / $total) * 100) : 0;
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
    <title>Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="max-w-2xl mx-auto px-4 py-8">

        <!-- Header -->
        <header class="flex items-center justify-between mb-8">
            <h1 class="text-xl font-semibold text-slate-800">Report</h1>
            <nav class="flex items-center gap-1">
                <a href="index.php" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Tracker">
                    <i data-lucide="layout-grid" class="w-5 h-5"></i>
                </a>
                <a href="admin.php" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Settings">
                    <i data-lucide="settings" class="w-5 h-5"></i>
                </a>
            </nav>
        </header>

        <!-- Overview -->
        <section class="grid grid-cols-3 gap-3 mb-8">
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-2xl font-semibold text-slate-800"><?= $stats['progressPercent'] ?>%</div>
                <div class="text-xs text-slate-400 uppercase tracking-wide mt-1">Complete</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-2xl font-semibold text-slate-800"><?= $stats['totalChecks'] ?></div>
                <div class="text-xs text-slate-400 uppercase tracking-wide mt-1">Checks</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="text-2xl font-semibold text-slate-800"><?= $stats['daysRemaining'] ?></div>
                <div class="text-xs text-slate-400 uppercase tracking-wide mt-1">Days Left</div>
            </div>
        </section>

        <!-- Period -->
        <section class="bg-white rounded-xl border border-slate-200 p-5 mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-slate-100 rounded-lg">
                        <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                    </div>
                    <div>
                        <div class="text-sm text-slate-800"><?= $stats['startDate']->format('M j') ?> &rarr; <?= $stats['endDate']->format('M j, Y') ?></div>
                        <div class="text-xs text-slate-400"><?= $stats['trackingDays'] ?> days total</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-slate-800"><?= $stats['totalChecks'] ?>/<?= $stats['totalPossible'] ?></div>
                    <div class="text-xs text-slate-400">completed</div>
                </div>
            </div>
        </section>

        <!-- Habits -->
        <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-medium text-slate-800">Habits</h2>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($data['columns'] as $habit):
                    $completed = $stats['habitStats'][$habit];
                    $percent = calculateHabitProgress($completed, $stats['trackingDays']);
                ?>
                    <div class="px-5 py-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-slate-700"><?= htmlspecialchars($habit) ?></span>
                            <span class="text-xs text-slate-400 tabular-nums"><?= $completed ?>/<?= $stats['trackingDays'] ?></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-slate-700 rounded-full transition-all duration-300" style="width: <?= $percent ?>%"></div>
                            </div>
                            <span class="text-xs text-slate-500 tabular-nums w-8"><?= $percent ?>%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($data['columns'])): ?>
                    <div class="px-5 py-8 text-center text-sm text-slate-400">
                        No habits yet
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
