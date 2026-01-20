# Habit Tracker

A simple PHP-based web application for tracking daily habits over a customizable time period.

## Features

- **Daily Habit Tracking**: Check off habits day by day with a clean table interface
- **Admin Panel**: Manage habits, set tracking periods, import/export data
- **Report Page**: View statistics and progress for each habit
- **Auto-scroll**: Automatically scrolls to today's row on page load
- **Mobile Responsive**: Works on desktop and mobile devices

## Requirements

- PHP 7.4 or higher
- Web server with PHP support (Apache, Nginx, etc.)
- FTP access for deployment (if using GitHub Actions)

## Local Development

1. Clone the repository
2. Serve the directory with any PHP-capable server:
   ```bash
   php -S localhost:8000
   ```
3. Open `http://localhost:8000` in your browser

## Deployment

This project uses GitHub Actions to automatically deploy to an FTP server when changes are pushed to the `main` branch.

### Setting Up GitHub Secrets

Go to your repository **Settings > Secrets and variables > Actions** and add the following secrets:

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `FTP_SERVER` | FTP server hostname | `ftp.yourdomain.com` |
| `FTP_USERNAME` | FTP login username | `user@yourdomain.com` |
| `FTP_PASSWORD` | FTP login password | `your-password` |
| `FTP_PATH` | Remote directory path (must end with `/`) | `/public_html/habit-tracker/` |

### Deployment Workflow

The deployment is configured in `.github/workflows/deploy.yml`:

- **Trigger**: Pushes to `main` branch
- **Action**: Uses [SamKirkland/FTP-Deploy-Action](https://github.com/SamKirkland/FTP-Deploy-Action)
- **Protocol**: FTP on port 21
- **Excluded files**: `data.json`, `README.md`

### Manual Deployment

If you prefer manual deployment:

1. Upload all `.php` files to your web server
2. Ensure the directory is writable for `data.json` to be created
3. Set appropriate file permissions (644 for files, 755 for directories)

## File Structure

```
habit-tracker/
├── index.php          # Main tracking interface
├── admin.php          # Admin panel for management
├── report.php         # Statistics and progress report
├── data.json          # Data storage (auto-generated, not in repo)
├── README.md          # This file
└── .github/
    └── workflows/
        └── deploy.yml # GitHub Actions deployment workflow
```

## Data Storage

All data is stored in `data.json` with the following structure:

```json
{
  "columns": ["Habit 1", "Habit 2"],
  "days": {
    "2026-01-20": {
      "Habit 1": true,
      "Habit 2": true
    }
  },
  "startDate": "2026-01-01",
  "endDate": "2026-03-01"
}
```

## Pages

- **/** - Main tracker with checkboxes for each day/habit
- **/admin.php** - Add/remove habits, set tracking period, import/export data
- **/report.php** - View completion statistics and progress bars

## License

MIT
