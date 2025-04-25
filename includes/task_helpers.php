<?php
// Helper functions related to tasks

/**
 * Calculate a due date by adding working days to a start date.
 * Weekends (Saturday and Sunday) are skipped.
 *
 * @param string $startDate Date string in 'Y-m-d' format
 * @param int $workingDays Number of working days to add
 * @return string Due date in 'Y-m-d' format
 */
function addWorkingDays(string $startDate, int $workingDays): string {
    $date = new DateTime($startDate);
    while ($workingDays > 0) {
        $date->modify('+1 day');
        $dayOfWeek = $date->format('N'); // 1 (Mon) to 7 (Sun)
        if ($dayOfWeek < 6) { // Mon-Fri are working days
            $workingDays--;
        }
    }
    return $date->format('Y-m-d');
}

/**
 * Get working days count based on priority.
 *
 * @param string $priority Priority string ('low', 'medium', 'high', 'urgent')
 * @return int Number of working days
 */
function getWorkingDaysByPriority(string $priority): int {
    return match($priority) {
        'low' => 3,
        'medium' => 5,
        'high' => 7,
        'urgent' => 3,
        default => 5,
    };
}

/**
 * Check if a task is delayed.
 *
 * @param string|null $dueDate Due date string in 'Y-m-d' format or null
 * @param string $currentDate Current date string in 'Y-m-d' format
 * @return bool True if delayed, false otherwise
 */
function isTaskDelayed(?string $dueDate, string $currentDate): bool {
    if (!$dueDate) {
        return false; // No due date means cannot determine delay
    }
    return strtotime($currentDate) > strtotime($dueDate);
}

/**
 * Get number of days delayed.
 *
 * @param string|null $dueDate Due date string in 'Y-m-d' format or null
 * @param string $currentDate Current date string in 'Y-m-d' format
 * @return int Number of days delayed, 0 if not delayed or no due date
 */
function getDaysDelayed(?string $dueDate, string $currentDate): int {
    if (!$dueDate) {
        return 0;
    }
    $due = strtotime($dueDate);
    $current = strtotime($currentDate);
    if ($current <= $due) {
        return 0;
    }
    $diffSeconds = $current - $due;
    return (int)floor($diffSeconds / (60 * 60 * 24));
}
?>
