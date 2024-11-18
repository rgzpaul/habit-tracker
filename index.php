<?php
// Initial Setup
$startDate = new DateTime('2024-11-17');
$startDate->setTime(0, 0, 0); // Reset to midnight
$numberOfDays = 99;

$currentDate = new DateTime('now');
$currentDate->setTime(0, 0, 0); // Reset to midnight
$endDate = clone $startDate;
$endDate->modify("+" . ($numberOfDays - 1) . " days");

// Update data.json
$data = json_decode(file_get_contents('data.json'), true);
$data['startDate'] = $startDate->format('Y-m-d');
$data['endDate'] = $endDate->format('Y-m-d');
file_put_contents('data.json', json_encode($data, JSON_PRETTY_PRINT));

// Calculate remaining days inclusively
$interval = $endDate->diff($currentDate);
$daysRemaining = max(0, $interval->days + 1); // Include today

// Adjust remaining days and progress if the end date has passed
if ($currentDate > $endDate) {
    $daysRemaining = 0;
    $progressPercent = 100; // Set progress to 100% if the end date is past
} else {
    // Calculate progress if the end date has not passed
    $progressPercent = min(100, round((($numberOfDays - $daysRemaining) / $numberOfDays) * 100));
}

$dayNames = [
    1 => 'LUN', 2 => 'MAR', 3 => 'MER', 
    4 => 'GIO', 5 => 'VEN', 6 => 'SAB', 0 => 'DOM'
];

// Load data
$data = json_decode(file_get_contents('data.json'), true);
$columns = $data['columns'];

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day = $_POST['day'];
    $column = $_POST['column'];
    $checked = $_POST['checked'] === 'true';
    
    if ($checked) {
        $data['days'][$day][$column] = true;
    } else {
        unset($data['days'][$day][$column]);
        if (empty($data['days'][$day])) {
            unset($data['days'][$day]);
        }
    }
    
    file_put_contents('data.json', json_encode($data, JSON_PRETTY_PRINT));
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $numberOfDays; ?> Days Tracker</title>
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
            content: '✓';
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
    <script>
        $(document).ready(function() {
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
</head>
<body class="bg-gray-100 p-4 md:p-8 flex justify-center">
    <div class="max-w-4xl w-full">
        <div class="flex justify-between items-center mb-4 md:mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800"><?php echo $numberOfDays; ?> Days Tracker</h1>
            <div class="w-64">
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span><?= $daysRemaining ?> days remaining</span>
                    <span><?= $progressPercent ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $progressPercent ?>%"></div>
                </div>
            </div>
        </div>
        <div class="table-wrapper bg-white rounded-lg shadow">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-3 md:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Day</th>
                        <?php foreach ($columns as $column): ?>
                            <th class="px-3 md:px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                                <?= htmlspecialchars($column) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    for ($i = 0; $i < $numberOfDays; $i++):
                        $currentTableDate = clone $startDate;
                        $currentTableDate->modify("+$i days");
                        $dateKey = $currentTableDate->format('Y-m-d');
                        $dayNum = $currentTableDate->format('w');
                    ?>
                        <tr>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap text-xs md:text-sm font-medium text-gray-900 date-column text-center">
                                <?= $dayNames[$dayNum] . ' ' . $currentTableDate->format('d/m/y') ?>
                            </td>
                            <?php foreach ($columns as $column): ?>
                                <td class="px-3 md:px-6 py-4 whitespace-nowrap text-xs md:text-sm text-gray-500 text-center checkbox-cell hover:bg-gray-100 transition-colors">
                                    <input type="checkbox" 
                                           name="<?= $dateKey ?>_<?= $column ?>"
                                           <?= isset($data['days'][$dateKey][$column]) ? 'checked' : '' ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
