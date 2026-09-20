<?php
$page_title = 'Holiday Calendar';
require_once __DIR__ . '/includes/header.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstWeekday = (int)date('w', strtotime($monthStart));
$holidays = [];

$result = mysqli_query($conn, "SELECT holiday_date, holiday_name FROM holidays WHERE YEAR(holiday_date) = $year ORDER BY holiday_date");
while ($holiday = mysqli_fetch_assoc($result)) {
    $holidays[$holiday['holiday_date']] = $holiday['holiday_name'];
}

$previous = strtotime('-1 month', strtotime($monthStart));
$next = strtotime('+1 month', strtotime($monthStart));
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-gray-900">Holiday Calendar</h1>
        <p class="text-gray-500 text-sm mt-1">View all available holidays and non-working dates</p>
    </div>
    <div class="flex items-center gap-2 mt-3 md:mt-0">
        <a href="?month=<?php echo date('n', $previous); ?>&year=<?php echo date('Y', $previous); ?>" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50" aria-label="Previous month">
            <i class="fas fa-chevron-left"></i>
        </a>
        <span class="px-4 py-2 font-bold text-gray-900"><?php echo date('F Y', strtotime($monthStart)); ?></span>
        <a href="?month=<?php echo date('n', $next); ?>&year=<?php echo date('Y', $next); ?>" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50" aria-label="Next month">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm p-4 md:p-6 mb-6">
    <div class="grid grid-cols-7 gap-1 md:gap-2 mb-2">
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName): ?>
        <div class="text-center text-xs font-bold text-gray-500 uppercase py-2"><?php echo $dayName; ?></div>
        <?php endforeach; ?>
    </div>
    <div class="grid grid-cols-7 gap-1 md:gap-2">
        <?php for ($blank = 0; $blank < $firstWeekday; $blank++): ?>
        <div class="min-h-20 md:min-h-24 rounded-lg bg-gray-50"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $isHoliday = isset($holidays[$date]);
            $isWeekend = in_array((int)date('w', strtotime($date)), [0, 6], true);
            $classes = $isHoliday ? 'bg-red-50 border-red-200 text-red-700' : ($isWeekend ? 'bg-gray-50 border-gray-100 text-gray-500' : 'bg-white border-gray-100 text-gray-900');
        ?>
        <div class="min-h-20 md:min-h-24 rounded-lg border p-2 <?php echo $classes; ?>">
            <div class="font-bold text-sm"><?php echo $day; ?></div>
            <?php if ($isHoliday): ?>
            <div class="mt-2 text-xs font-semibold leading-tight">
                <i class="fas fa-umbrella-beach mr-1"></i><?php echo htmlspecialchars($holidays[$date]); ?>
            </div>
            <?php elseif ($isWeekend): ?>
            <div class="mt-2 text-xs">Weekend</div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 border-b border-gray-200">
        <h2 class="font-bold text-gray-900">All Holidays in <?php echo $year; ?></h2>
    </div>
    <div class="divide-y divide-gray-100">
        <?php if (count($holidays) > 0): ?>
            <?php foreach ($holidays as $date => $name): ?>
            <div class="flex items-center gap-3 p-4">
                <div class="w-10 h-10 rounded-lg bg-red-100 text-red-600 flex items-center justify-center">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($name); ?></p>
                    <p class="text-sm text-gray-500"><?php echo date('l, F j, Y', strtotime($date)); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <p class="p-6 text-center text-gray-500">No holidays have been added for this year.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
