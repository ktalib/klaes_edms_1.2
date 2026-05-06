# PropID Master Sync Setup Guide

To ensure that the system always knows about existing Property IDs (preventing collisions during TOT/OP capture), you should set up a recurring task to run the backfill command.

## Option 1: Using Windows Task Scheduler (Recommended for WAMP)

Since the server is running on Windows, use the built-in Task Scheduler:

1.  **Open Task Scheduler**: Click Start and type "Task Scheduler".
2.  **Create Basic Task**:
    *   Name: `KLAES PropID Sync`
    *   Trigger: `Daily` (or `Weekly` depending on how often bulk imports happen).
    *   Action: `Start a Program`.
3.  **Configure Action**:
    *   **Program/script**: `C:\wamp64\bin\php\php8.1.x\php.exe` (Point this to your actual PHP path).
    *   **Add arguments**: `C:\wamp64\www\klaes\artisan propid:backfill`
    *   **Start in**: `C:\wamp64\www\klaes`
4.  **Finish**: Click Finish. The task will now run automatically.

---

## Option 2: Using Laravel Scheduler (Cleaner)

If you already have the Laravel Scheduler running on the server, you can simply add the command to your `app/Console/Kernel.php` file.

Edit `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run the backfill every night at midnight
    $schedule->command('propid:backfill')->dailyAt('00:00');
}
```

---

## Option 3: Manual Command

You can run this manually anytime after you perform a bulk data import from an external source:

```powershell
php artisan propid:backfill
```

### Why is this necessary?
The system uses the `PropID_Master` table to ensure every property has a unique ID. When you import old records from legacy systems, they might use IDs that the new system doesn't know about yet. This command "claims" those IDs in the master table so they are never accidentally reused for new transactions.
