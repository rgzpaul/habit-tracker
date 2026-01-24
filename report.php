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
    $data = json_decode($content, true) ?? ['columns' => [], 'days' => [], 'startDate' => date('Y-m-d'), 'endDate' => date('Y-m-d')];

    // Migrate old format (simple string array) to new format (objects with name and frequency)
    if (!empty($data['columns']) && isset($data['columns'][0]) && is_string($data['columns'][0])) {
        $data['columns'] = array_map(function($name) {
            return ['name' => $name, 'frequency' => 7];
        }, $data['columns']);
    }

    return $data;
}

function getHabitName(mixed $habit): string {
    return is_array($habit) ? $habit['name'] : $habit;
}

function getHabitFrequency(mixed $habit): int {
    return is_array($habit) ? ($habit['frequency'] ?? 7) : 7;
}

function calculateExpectedCompletions(int $trackingDays, int $frequencyPerWeek): int {
    // Calculate expected completions based on weekly frequency
    // For tracking period, expected = (tracking_days / 7) * frequency
    $weeks = $trackingDays / 7;
    return (int) round($weeks * $frequencyPerWeek);
}

function calculateWeeklyDots(array $data, string $habitName, int $frequency, DateTime $startDate, DateTime $endDate, DateTime $currentDate): array {
    $greenDots = 0;
    $redDots = 0;
    $grayDots = 0;

    // Iterate through each week
    $weekStart = clone $startDate;
    while ($weekStart <= $endDate) {
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');
        if ($weekEnd > $endDate) {
            $weekEnd = clone $endDate;
        }

        // Count completions for this week
        $weekCompletions = 0;
        $checkDate = clone $weekStart;
        while ($checkDate <= $weekEnd) {
            $dateStr = $checkDate->format('Y-m-d');
            if (isset($data['days'][$dateStr][$habitName]) && $data['days'][$dateStr][$habitName]) {
                $weekCompletions++;
            }
            $checkDate->modify('+1 day');
        }

        // Determine if this week is elapsed, current, or future
        if ($weekEnd < $currentDate) {
            // Fully elapsed week - cap at frequency, rest is red
            $greenDots += min($weekCompletions, $frequency);
            $redDots += max(0, $frequency - $weekCompletions);
        } elseif ($weekStart > $currentDate) {
            // Future week - all gray
            $grayDots += $frequency;
        } else {
            // Current week - calculate what's achievable vs impossible
            $daysRemaining = $currentDate->diff($weekEnd)->days; // days after today
            $maxPossible = $weekCompletions + $daysRemaining;

            $greenDots += min($weekCompletions, $frequency);
            $impossibleToRecover = max(0, $frequency - $maxPossible);
            $stillAchievable = max(0, min($daysRemaining, $frequency - $weekCompletions));

            $redDots += $impossibleToRecover;
            $grayDots += $stillAchievable;
        }

        $weekStart->modify('+7 days');
    }

    return [
        'green' => $greenDots,
        'red' => $redDots,
        'gray' => $grayDots,
    ];
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

    // Calculate elapsed days (from start to today, capped at end date)
    if ($currentDate < $startDate) {
        $elapsedDays = 0;
    } elseif ($currentDate > $endDate) {
        $elapsedDays = $trackingDays;
    } else {
        $elapsedDays = $startDate->diff($currentDate)->days + 1;
    }

    // Build habit name to frequency map
    $habitFrequencies = [];
    $habitNames = [];
    foreach ($data['columns'] as $habit) {
        $name = getHabitName($habit);
        $habitNames[] = $name;
        $habitFrequencies[$name] = getHabitFrequency($habit);
    }

    // Initialize habit stats
    $habitStats = array_fill_keys($habitNames, 0);
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

    // Calculate expected completions based on frequency (total and elapsed)
    $habitExpected = [];
    $habitElapsedExpected = [];
    $totalPossible = 0;
    $totalElapsedPossible = 0;
    foreach ($habitNames as $name) {
        $freq = $habitFrequencies[$name];
        $expected = calculateExpectedCompletions($trackingDays, $freq);
        $elapsedExpected = calculateExpectedCompletions($elapsedDays, $freq);
        $habitExpected[$name] = $expected;
        $habitElapsedExpected[$name] = $elapsedExpected;
        $totalPossible += $expected;
        $totalElapsedPossible += $elapsedExpected;
    }

    $progressPercent = $totalPossible > 0 ? round(($totalChecks / $totalPossible) * 100) : 0;

    return [
        'startDate'            => $startDate,
        'endDate'              => $endDate,
        'trackingDays'         => $trackingDays,
        'elapsedDays'          => $elapsedDays,
        'daysRemaining'        => $daysRemaining,
        'habitStats'           => $habitStats,
        'habitExpected'        => $habitExpected,
        'habitElapsedExpected' => $habitElapsedExpected,
        'habitFrequencies'     => $habitFrequencies,
        'totalChecks'          => $totalChecks,
        'totalPossible'        => $totalPossible,
        'totalElapsedPossible' => $totalElapsedPossible,
        'progressPercent'      => $progressPercent,
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
    <meta name="theme-color" content="#334155">
    <link rel="manifest" href="manifest.php">
    <title>Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <main class="max-w-3xl mx-auto px-4 py-8">

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
                    $habitName = getHabitName($habit);
                    $frequency = getHabitFrequency($habit);
                    $completed = $stats['habitStats'][$habitName];
                    $expected = $stats['habitExpected'][$habitName];

                    // Calculate dots per-week (no banking allowed)
                    $currentDate = new DateTime('now');
                    $currentDate->setTime(0, 0, 0);
                    $dots = calculateWeeklyDots($data, $habitName, $frequency, $stats['startDate'], $stats['endDate'], $currentDate);
                    $greenDots = $dots['green'];
                    $redDots = $dots['red'];
                    $grayDots = $dots['gray'];

                    // Percentage based on green vs total expected (green + red + gray)
                    $totalDots = $greenDots + $redDots + $grayDots;
                    $percent = $totalDots > 0 ? round(($greenDots / $totalDots) * 100) : 0;
                ?>
                    <div class="px-5 py-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-slate-700"><?= htmlspecialchars($habitName) ?></span>
                                <span class="text-xs text-slate-400"><?= $frequency ?>x/wk</span>
                            </div>
                            <span class="text-xs text-slate-400 tabular-nums"><?= $completed ?>/<?= $expected ?></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 flex gap-0.5 flex-wrap">
                                <?php for ($i = 0; $i < $greenDots; $i++): ?>
                                    <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <?php endfor; ?>
                                <?php for ($i = 0; $i < $redDots; $i++): ?>
                                    <div class="w-2 h-2 rounded-full bg-red-400"></div>
                                <?php endfor; ?>
                                <?php for ($i = 0; $i < $grayDots; $i++): ?>
                                    <div class="w-2 h-2 rounded-full bg-slate-200"></div>
                                <?php endfor; ?>
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

    </main>

    <script>
        if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');
        lucide.createIcons();
    </script>
</body>
</html>
