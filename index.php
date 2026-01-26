<?php

/**
 * Habit Tracker - Main tracking interface
 * Display and toggle daily habit checkboxes
 */

const DATA_FILE = 'data.json';

// ============================================================================
// Data Functions
// ============================================================================

function loadData(): array
{
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

function getHabitName(mixed $habit): string
{
    return is_array($habit) ? $habit['name'] : $habit;
}

function saveData(array $data): void
{
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function calculateTrackingInfo(array $data): array
{
    $startDate = new DateTime($data['startDate']);
    $startDate->setTime(0, 0, 0);

    $endDate = new DateTime($data['endDate']);
    $endDate->setTime(0, 0, 0);

    $currentDate = new DateTime('now');
    $currentDate->setTime(0, 0, 0);

    $trackingDays = $startDate->diff($endDate)->days + 1;

    if ($currentDate > $endDate) {
        $daysRemaining = 0;
        $progressPercent = 100;
    } else {
        $daysRemaining = max(0, $endDate->diff($currentDate)->days + 1);
        $progressPercent = min(100, round((($trackingDays - $daysRemaining) / $trackingDays) * 100));
    }

    return [
        'startDate'       => $startDate,
        'endDate'         => $endDate,
        'currentDate'     => $currentDate,
        'trackingDays'    => $trackingDays,
        'daysRemaining'   => $daysRemaining,
        'progressPercent' => $progressPercent,
    ];
}

// ============================================================================
// AJAX Handler
// ============================================================================

function handleCheckboxUpdate(array &$data): void
{
    $day = $_POST['day'] ?? '';
    $column = $_POST['column'] ?? '';
    $checked = ($_POST['checked'] ?? '') === 'true';

    if (empty($day) || empty($column)) {
        return;
    }

    if ($checked) {
        $data['days'][$day][$column] = true;
    } else {
        unset($data['days'][$day][$column]);
        if (empty($data['days'][$day])) {
            unset($data['days'][$day]);
        }
    }

    saveData($data);
}

// ============================================================================
// Day Name Helpers
// ============================================================================

const DAY_NAMES = [
    0 => 'DOM',
    1 => 'LUN',
    2 => 'MAR',
    3 => 'MER',
    4 => 'GIO',
    5 => 'VEN',
    6 => 'SAB'
];

function formatDateWithDay(DateTime $date): string
{
    $dayNum = (int) $date->format('w');
    return DAY_NAMES[$dayNum] . ' ' . $date->format('d/m');
}

// ============================================================================
// Request Processing
// ============================================================================

$data = loadData();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleCheckboxUpdate($data);
    exit;
}

$info = calculateTrackingInfo($data);
$columns = $data['columns'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#334155">
    <link rel="manifest" href="manifest.php">
    <title>Habit Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .checkbox-cell { cursor: pointer; }
        .checkbox-cell input[type="checkbox"] {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 1.5px solid #cbd5e1;
            border-radius: 4px;
            cursor: pointer;
            position: relative;
            background: white;
            transition: all 0.15s ease;
        }
        .checkbox-cell input[type="checkbox"]:hover {
            border-color: #94a3b8;
        }
        .checkbox-cell input[type="checkbox"]:checked {
            background: #334155;
            border-color: #334155;
        }
        .checkbox-cell input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        @media (max-width: 768px) {
            .table-wrapper {
                max-width: 100vw;
                -webkit-overflow-scrolling: touch;
            }
            .table-wrapper table { min-width: 600px; }
        }
    </style>
</head>

<body class="bg-slate-50 h-screen flex flex-col">
    <main class="max-w-3xl mx-auto px-4 py-8 flex flex-col flex-1 min-h-0 w-full">

        <!-- Header -->
        <header class="mb-8 shrink-0">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-xl font-semibold text-slate-800">
                    <?= $info['trackingDays'] ?> Days
                </h1>
                <nav class="flex items-center gap-1">
                    <a href="admin.php" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Settings">
                        <i data-lucide="settings" class="w-5 h-5"></i>
                    </a>
                    <a href="report.php" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Report">
                        <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                    </a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex-1 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                    <div class="h-full bg-slate-700 rounded-full transition-all duration-300" style="width: <?= $info['progressPercent'] ?>%"></div>
                </div>
                <span class="text-sm text-slate-500 tabular-nums"><?= $info['daysRemaining'] ?>d left</span>
            </div>
        </header>

        <!-- Tracking Table -->
        <div class="table-wrapper bg-white rounded-xl border border-slate-200 overflow-auto flex-1 min-h-0">
            <table class="w-full">
                <thead class="sticky top-0 bg-white z-10">
                    <tr class="border-b border-slate-100">
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wide">
                            Date
                        </th>
                        <?php foreach ($columns as $column):
                            $columnName = getHabitName($column);
                        ?>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase tracking-wide">
                                <?= htmlspecialchars($columnName) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php for ($i = 0; $i < $info['trackingDays']; $i++):
                        $rowDate = clone $info['startDate'];
                        $rowDate->modify("+$i days");
                        $dateKey = $rowDate->format('Y-m-d');
                        $isToday = $rowDate == $info['currentDate'];
                    ?>
                        <tr class="<?= $isToday ? 'bg-slate-50' : 'hover:bg-slate-25' ?>"<?= $isToday ? ' id="today"' : '' ?>>
                            <td class="px-4 py-3 text-sm text-slate-600 tabular-nums <?= $isToday ? 'font-medium text-slate-800' : '' ?>">
                                <?= formatDateWithDay($rowDate) ?>
                            </td>
                            <?php foreach ($columns as $column):
                                $columnName = getHabitName($column);
                            ?>
                                <td class="px-4 py-3 text-center checkbox-cell">
                                    <input type="checkbox"
                                        name="<?= $dateKey ?>_<?= htmlspecialchars($columnName) ?>"
                                        <?= isset($data['days'][$dateKey][$columnName]) ? 'checked' : '' ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script>
        if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');
        lucide.createIcons();

        $(document).ready(function() {
            const todayRow = document.getElementById('today');
            if (todayRow) {
                todayRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }

            $('.checkbox-cell').on('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    const checkbox = $(this).find('input[type="checkbox"]');
                    checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
                }
            });

            $('input[type="checkbox"]').change(function() {
                const checkbox = $(this);
                const [dateKey, column] = checkbox.attr('name').split('_');

                $.post('index.php', {
                    day: dateKey,
                    column: column,
                    checked: checkbox.prop('checked')
                });
            });
        });
    </script>
</body>

</html>