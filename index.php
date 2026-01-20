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
    return json_decode($content, true) ?? ['columns' => [], 'days' => [], 'startDate' => date('Y-m-d'), 'endDate' => date('Y-m-d')];
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
    return DAY_NAMES[$dayNum] . ' ' . $date->format('d/m/y');
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
    <title><?= $info['trackingDays'] ?> Days Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        .date-column {
            font-family: 'JetBrains Mono', monospace;
        }

        .checkbox-cell {
            cursor: pointer;
        }

        .checkbox-cell input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 24px;
            height: 24px;
            border: none;
            outline: none;
            cursor: pointer;
            position: relative;
            background: transparent;
        }

        .checkbox-cell input[type="checkbox"]:checked::before {
            content: '\2713';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #2563eb;
            font-size: 20px;
            font-weight: 900;
        }

        @media (max-width: 768px) {
            .table-wrapper {
                max-width: 100vw;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table-wrapper table {
                min-width: 800px;
            }
        }
    </style>
</head>

<body class="bg-gray-100 p-4 md:p-8 flex justify-center">
    <div class="max-w-4xl w-full">

        <!-- Header -->
        <header class="flex justify-between items-center mb-4 md:mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                <?= $info['trackingDays'] ?> Days Tracker
            </h1>
            <div class="w-64">
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span><?= $info['daysRemaining'] ?> days remaining</span>
                    <span><?= $info['progressPercent'] ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $info['progressPercent'] ?>%"></div>
                </div>
            </div>
        </header>

        <!-- Tracking Table -->
        <div class="table-wrapper bg-white rounded-lg shadow">
            <table class="w-full">
                <thead class="sticky top-0">
                    <tr class="bg-gray-50">
                        <th class="px-3 md:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                            Day
                        </th>
                        <?php foreach ($columns as $column): ?>
                            <th class="px-3 md:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                                <?= htmlspecialchars($column) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php for ($i = 0; $i < $info['trackingDays']; $i++):
                        $rowDate = clone $info['startDate'];
                        $rowDate->modify("+$i days");
                        $dateKey = $rowDate->format('Y-m-d');
                        $isToday = $rowDate == $info['currentDate'];
                    ?>
                        <tr<?= $isToday ? ' id="today"' : '' ?>>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap text-xs md:text-sm font-medium text-gray-900 date-column text-center">
                                <?= formatDateWithDay($rowDate) ?>
                            </td>
                            <?php foreach ($columns as $column): ?>
                                <td class="px-3 md:px-6 py-4 whitespace-nowrap text-xs md:text-sm text-gray-500 text-center checkbox-cell hover:bg-gray-100 transition-colors">
                                    <input type="checkbox"
                                        name="<?= $dateKey ?>_<?= htmlspecialchars($column) ?>"
                                        <?= isset($data['days'][$dateKey][$column]) ? 'checked' : '' ?>>
                                </td>
                            <?php endforeach; ?>
                            </tr>
                        <?php endfor; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        $(document).ready(function() {
            // Scroll to current day
            const todayRow = document.getElementById('today');
            if (todayRow) {
                todayRow.scrollIntoView({
                    block: 'center'
                });
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