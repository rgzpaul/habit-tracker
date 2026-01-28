<?php
header('Content-Type: application/manifest+json');
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
?>
{
  "name": "Habit Tracker",
  "short_name": "Habits",
  "start_url": "<?= $base ?>",
  "display": "standalone",
  "background_color": "#f8fafc",
  "theme_color": "#334155",
  "icons": [
    {
      "src": "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%23334155' width='100' height='100' rx='20'/><path d='M25 50l15 15 35-35' stroke='white' stroke-width='8' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>",
      "sizes": "any",
      "type": "image/svg+xml",
      "purpose": "any"
    }
  ]
}