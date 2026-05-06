<?php

return [
    'early_check_in_minutes' => 30,
    'late_tolerance_minutes' => 0,
    'max_consecutive_absences' => 3,
    'max_monthly_absences' => 5,
    'auto_logout' => [
        'enabled' => env('ATTENDANCE_AUTO_LOGOUT', true),
        'grace_minutes' => env('ATTENDANCE_AUTO_LOGOUT_GRACE', 10),
        'chunk_size' => env('ATTENDANCE_AUTO_LOGOUT_CHUNK', 100),
        'track_overtime' => env('ATTENDANCE_AUTO_LOGOUT_ALLOW_OVERTIME', true),
        'overtime_extension_minutes' => env('ATTENDANCE_AUTO_LOGOUT_OVERTIME_EXTENSION', 120),
    ],
    'shifts' => [
        'morning' => [
            'label' => 'Morning (9:00 AM - 1:00 PM)',
            'start' => '09:00',
            'end' => '13:00',
            'overnight' => false,
        ],
        'afternoon' => [
            'label' => 'Afternoon (1:00 PM - 5:00 PM)',
            'start' => '13:00',
            'end' => '17:00',
            'overnight' => false,
        ],
        'night' => [
            'label' => 'Night (5:00 PM - 9:00 PM)',
            'start' => '17:00',
            'end' => '21:00',
            'overnight' => false,
        ],
        'overnight' => [
            'label' => 'Overnight (9:00 PM - 4:00 AM)',
            'start' => '21:00',
            'end' => '04:00',
            'overnight' => true,
        ],
        'full_day' => [
            'label' => 'Full Day (9:00 AM - 5:00 PM)',
            'start' => '09:00',
            'end' => '17:00',
            'overnight' => false,
        ],
    ],
    'holidays' => [
        // Add YYYY-MM-DD entries here or plug into a dedicated holiday provider service.
    ],
];
