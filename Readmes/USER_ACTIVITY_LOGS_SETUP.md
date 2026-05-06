# User Activity Logs System - Setup Guide

This document provides comprehensive instructions for setting up the User Activity Logs system in your Laravel application with Microsoft SQL Server.

## Overview

The User Activity Logs system tracks and displays:
- User IP addresses
- User agents (browser information)
- Login and logout times
- Online status
- Device type (desktop, mobile, tablet)
- Browser and platform information
- Session duration
- Activity descriptions

## Features

### 🎯 Core Features
- **Real-time Activity Tracking**: Automatic logging of user login/logout events
- **Online Status Monitoring**: Track which users are currently online
- **Device Detection**: Identify device types, browsers, and platforms
- **Session Management**: Track session duration and activity
- **Comprehensive Dashboard**: Clean, user-friendly interface with statistics
- **Advanced Filtering**: Filter by user, status, device type, and date range
- **Data Export**: Export activity logs to CSV format
- **Bulk Operations**: Delete multiple logs at once
- **Automatic Cleanup**: Remove old logs based on retention policy

### 📊 Dashboard Features
- **Statistics Cards**: Total sessions, unique users, online users, average session duration
- **Online Users Panel**: Real-time display of currently online users
- **Interactive Data Table**: Sortable, searchable table with pagination
- **Activity Details Modal**: Detailed view of individual activity logs
- **Charts and Analytics**: Visual representation of user activity patterns

## Database Schema

### Table: `user_activity_logs`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT (PK) | Primary key |
| `user_id` | BIGINT (FK) | Foreign key to users table |
| `ip_address` | NVARCHAR(45) | User's IP address |
| `user_agent` | NVARCHAR(MAX) | Browser user agent string |
| `login_time` | DATETIME2 | When user logged in |
| `logout_time` | DATETIME2 | When user logged out |
| `is_online` | BIT | Current online status |
| `session_id` | NVARCHAR(255) | Laravel session ID |
| `device_type` | NVARCHAR(50) | Device type (desktop/mobile/tablet) |
| `browser` | NVARCHAR(100) | Browser name |
| `platform` | NVARCHAR(100) | Operating system |
| `location` | NVARCHAR(255) | Geographic location (optional) |
| `activity_type` | NVARCHAR(50) | Type of activity (login/logout/activity) |
| `activity_description` | NVARCHAR(MAX) | Description of the activity |
| `created_at` | DATETIME2 | Record creation timestamp |
| `updated_at` | DATETIME2 | Record update timestamp |

### Indexes
- `IX_user_activity_logs_user_id` - For user-specific queries
- `IX_user_activity_logs_is_online` - For online status queries
- `IX_user_activity_logs_login_time` - For time-based queries
- `IX_user_activity_logs_logout_time` - For session duration calculations
- `IX_user_activity_logs_created_at` - For date range filtering
- `IX_user_activity_logs_user_online` - Composite index for user online status
- `IX_user_activity_logs_user_created` - Composite index for user activity timeline

## Installation Steps

### Step 1: Database Setup

1. **Run the SQL Schema Script**:
   ```sql
   -- Execute the provided database_schema_user_activity_logs.sql file
   -- This creates the table, indexes, constraints, and helper objects
   ```

2. **Verify Database Connection**:
   Ensure your `.env` file has the correct SQL Server configuration:
   ```env
   DB_SQLSRV_HOST=your_sql_server_host
   DB_SQLSRV_PORT=1433
   DB_SQLSRV_DATABASE=your_database_name
   DB_SQLSRV_USERNAME=your_username
   DB_SQLSRV_PASSWORD=your_password
   ```

### Step 2: Laravel Migration

Run the Laravel migration to ensure the table is properly registered:
```bash
php artisan migrate
```

### Step 3: Register Event Listeners

Generate the event listeners:
```bash
php artisan event:generate
```

### Step 4: Register Middleware (Optional)

To automatically track user activity on every request, add the middleware to your `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \App\Http\Middleware\LogUserActivity::class,
    ],
];
```

Or apply it to specific routes:
```php
Route::group(['middleware' => ['auth', 'log.user.activity']], function () {
    // Your protected routes
});
```

### Step 5: Update Navigation Menu

Add the User Activity Logs link to your navigation menu. In your menu blade file, add:

```html
<a href="{{ route('user-activity-logs.index') }}" class="sidebar-item">
    <i class="fas fa-history sidebar-icon"></i>
    <span>User Activity Logs</span>
</a>
```

### Step 6: Set Permissions (Optional)

If you're using a permission system, create permissions for:
- `view user activity logs`
- `delete user activity logs`
- `export user activity logs`

## Usage

### Accessing the Dashboard

Navigate to `/user-activity-logs` in your application to access the User Activity Logs dashboard.

### API Endpoints

The system provides several API endpoints:

- `GET /user-activity-logs` - Main dashboard
- `GET /user-activity-logs/{id}` - View specific activity details
- `DELETE /user-activity-logs/{id}` - Delete specific activity log
- `GET /user-activity-logs/stats/data` - Get activity statistics
- `GET /user-activity-logs/online/users` - Get currently online users
- `GET /user-activity-logs/chart/data` - Get chart data for analytics
- `GET /user-activity-logs/export/csv` - Export logs to CSV
- `POST /user-activity-logs/bulk-delete` - Delete multiple logs
- `POST /user-activity-logs/clean-old` - Clean up old logs

### Programmatic Usage

#### Log Custom Activity
```php
use App\Models\UserActivityLog;

// Log a custom activity
UserActivityLog::logActivity(auth()->id(), 'custom_action', [
    'activity_description' => 'User performed a specific action'
]);
```

#### Get Online Users
```php
$onlineUsers = UserActivityLog::with('user')
    ->where('is_online', true)
    ->get();
```

#### Get Activity Statistics
```php
$stats = UserActivityLog::getActivityStats(30); // Last 30 days
```

## Customization

### Extending the Model

You can extend the `UserActivityLog` model to add custom functionality:

```php
// In your UserActivityLog model
public function getLocationAttribute()
{
    // Add IP geolocation logic here
    return $this->getLocationFromIP($this->ip_address);
}

private function getLocationFromIP($ip)
{
    // Implement IP geolocation service
    // e.g., using MaxMind GeoIP, ipapi.co, etc.
}
```

### Custom Activity Types

Define custom activity types by extending the model:

```php
const ACTIVITY_TYPES = [
    'login' => 'User Login',
    'logout' => 'User Logout',
    'password_change' => 'Password Changed',
    'profile_update' => 'Profile Updated',
    'document_access' => 'Document Accessed',
    // Add more as needed
];
```

### Adding Charts and Analytics

The system includes endpoints for chart data. You can extend the controller to provide additional analytics:

```php
public function getAdvancedAnalytics(Request $request)
{
    $days = $request->get('days', 30);
    
    // Peak hours analysis
    $peakHours = UserActivityLog::where('created_at', '>=', now()->subDays($days))
        ->selectRaw('HOUR(login_time) as hour, COUNT(*) as count')
        ->groupBy('hour')
        ->orderBy('count', 'desc')
        ->get();
    
    // Geographic distribution
    $locations = UserActivityLog::where('created_at', '>=', now()->subDays($days))
        ->selectRaw('location, COUNT(*) as count')
        ->whereNotNull('location')
        ->groupBy('location')
        ->orderBy('count', 'desc')
        ->get();
    
    return response()->json([
        'peak_hours' => $peakHours,
        'locations' => $locations
    ]);
}
```

## Maintenance

### Automatic Cleanup

The system includes a stored procedure for cleaning up old logs. You can set up a scheduled task to run this regularly:

```sql
-- Clean up logs older than 90 days
EXEC sp_cleanup_old_activity_logs @days_to_keep = 90;
```

### Laravel Scheduled Task

Add to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Clean up old activity logs daily
    $schedule->call(function () {
        UserActivityLog::where('created_at', '<', now()->subDays(90))->delete();
    })->daily();
}
```

### Performance Optimization

1. **Regular Index Maintenance**: Rebuild indexes periodically
2. **Partition Large Tables**: Consider partitioning by date for very large datasets
3. **Archive Old Data**: Move old data to archive tables
4. **Monitor Query Performance**: Use SQL Server's query execution plans

## Security Considerations

1. **Data Privacy**: Ensure compliance with data protection regulations
2. **Access Control**: Implement proper permissions for viewing activity logs
3. **Data Retention**: Establish clear data retention policies
4. **Audit Trail**: The activity logs themselves serve as an audit trail
5. **IP Address Handling**: Consider anonymizing IP addresses for privacy

## Troubleshooting

### Common Issues

1. **Migration Fails**:
   - Check database connection
   - Verify SQL Server permissions
   - Ensure the users table exists

2. **Events Not Firing**:
   - Run `php artisan event:generate`
   - Check EventServiceProvider registration
   - Verify middleware is applied

3. **Performance Issues**:
   - Check database indexes
   - Monitor query execution times
   - Consider data archiving

4. **Memory Issues with Large Datasets**:
   - Implement pagination
   - Use database-level filtering
   - Consider chunked processing

### Debug Mode

Enable debug logging in your `.env`:
```env
LOG_LEVEL=debug
```

Check logs in `storage/logs/laravel.log` for activity logging issues.

## Support and Maintenance

### Regular Tasks
- Monitor disk space usage
- Review and clean old logs
- Update indexes as needed
- Monitor performance metrics

### Backup Considerations
- Include activity logs in your backup strategy
- Consider separate backup schedules for activity data
- Test restore procedures regularly

## Conclusion

The User Activity Logs system provides comprehensive tracking and monitoring capabilities for your Laravel application. With proper setup and maintenance, it offers valuable insights into user behavior and system usage patterns while maintaining security and performance standards.

For additional customization or advanced features, refer to the Laravel documentation and SQL Server best practices guides.