<?php
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/config/db.php';

// Signed-in users see the members' calendar; visitors get public services and programs only.
$isSignedIn = !empty($_SESSION['user_id']);
$calendarProcedure = $isSignedIn ? 'getMonthCalendar' : 'getPublicMonthCalendar';

$today = new DateTimeImmutable('today');
$month = filter_var($_GET['month'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?: (int)$today->format('n');
$year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => 2200]]) ?: (int)$today->format('Y');
$inputError = '';
if ((isset($_GET['month']) && (string)$_GET['month'] !== (string)$month) || (isset($_GET['year']) && (string)$_GET['year'] !== (string)$year)) {
    $inputError = 'The selected month or year was not valid. Showing ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ' instead.';
}

$monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$prev = $monthStart->modify('-1 month');
$next = $monthStart->modify('+1 month');

$weeks = [];
$entryCount = 0;
$loadError = '';
try {
    $stmt = $db->prepare("CALL {$calendarProcedure}(?, ?, 1)");
    $stmt->bind_param('ii', $month, $year);
    $stmt->execute();
    $weeks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    while ($stmt->more_results() && $stmt->next_result()) {
        if ($extra = $stmt->get_result()) {
            $extra->free();
        }
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('calendar.php: ' . $e->getMessage());
    $loadError = 'The calendar could not be loaded right now. Please try again later.';
}

$dayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
$entryClasses = ['service' => 'entry-service', 'program' => 'entry-program', 'birthday' => 'entry-birthday', 'anniversary' => 'entry-anniversary', 'reminder' => 'entry-reminder'];

// Converts "Type: Title (13:30-15:00 ...)" lines from getMonthCalendar into display entries with 12-hour times.
function parseCalendarEntries(string $entries): array
{
    $parsed = [];
    foreach (preg_split('/\R/', $entries) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $type = '';
        $text = $line;
        if (preg_match('/^(Service|Program|Reminder|Birthday|Anniversary):\s*(.*)$/s', $line, $m)) {
            $type = $m[1];
            $text = $m[2];
        }
        $text = preg_replace_callback('/\b([01]\d|2[0-3]):([0-5]\d)\b/', static function ($t) {
            $h = (int)$t[1];
            return sprintf('%d:%s %s', ($h % 12) ?: 12, $t[2], $h < 12 ? 'AM' : 'PM');
        }, $text);
        $parsed[] = ['type' => $type, 'text' => $text];
    }
    return $parsed;
}

$monthNames = [];
for ($m = 1; $m <= 12; $m++) {
    $monthNames[$m] = date('F', mktime(0, 0, 0, $m, 1, 2000));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendar - <?= htmlspecialchars($monthStart->format('F Y')) ?></title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .calendar-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 1rem;
    }
    .calendar-toolbar form {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.5rem;
      width: auto;
      max-width: none;
      margin: 0;
      padding: 0;
      background: none;
      box-shadow: none;
    }
    .calendar-toolbar select, .calendar-toolbar input { width: auto; margin: 0; }
    .calendar-toolbar input[type="number"] { width: 6rem; }
    .calendar-nav { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .calendar-nav a { text-decoration: none; }
    .calendar-title { margin: 0 0 0.75rem; color: #28089a; text-align: center; }
    .calendar-scroll { overflow-x: auto; }
    .calendar-grid { width: 100%; min-width: 760px; border-collapse: collapse; table-layout: fixed; }
    .calendar-grid th {
      background: #28089a;
      color: #fff;
      padding: 0.5rem;
      font-size: 0.9rem;
      text-align: center;
    }
    .calendar-grid td {
      border: 1px solid #e2e8f0;
      vertical-align: top;
      height: 7rem;
      padding: 0.35rem;
      font-size: 0.8rem;
    }
    .calendar-grid td.outside { background: #f8fafc; }
    .calendar-grid td.today { background: #fefce8; box-shadow: inset 0 0 0 2px #f59e0b; }
    .day-number { font-weight: 700; color: #334155; margin-bottom: 0.25rem; }
    .calendar-entry {
      display: block;
      border-left: 3px solid #94a3b8;
      background: #f1f5f9;
      border-radius: 3px;
      padding: 0.15rem 0.3rem;
      margin-bottom: 0.2rem;
      line-height: 1.25;
      word-wrap: break-word;
    }
    .entry-service { border-left-color: #2563eb; background: #eff6ff; }
    .entry-program { border-left-color: #0d9488; background: #f0fdfa; }
    .entry-birthday { border-left-color: #db2777; background: #fdf2f8; }
    .entry-anniversary { border-left-color: #9333ea; background: #faf5ff; }
    .entry-reminder { border-left-color: #d97706; background: #fffbeb; }
    .calendar-legend { display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; margin-top: 0.75rem; font-size: 0.85rem; }
    .calendar-legend .calendar-entry { display: inline-block; margin: 0; }
    .calendar-notice { margin-bottom: 1rem; }
    .calendar-notice.error { color: #991b1b; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); border: 0; }
  </style>
</head>
<body>
<?php include __DIR__ . '/include/header.php'; ?>
<main class="dashboard-container">
  <h2>Calendar</h2>

  <div class="card">
    <div class="calendar-toolbar">
      <form method="get" action="calendar.php">
        <label for="month" class="sr-only">Month</label>
        <select id="month" name="month">
          <?php foreach ($monthNames as $num => $name): ?>
            <option value="<?= $num ?>" <?= $num === $month ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="year" class="sr-only">Year</label>
        <input id="year" name="year" type="number" min="1900" max="2200" value="<?= $year ?>" required>
        <button type="submit" class="btn btn-primary">Show</button>
      </form>
      <div class="calendar-nav">
        <a class="btn btn-secondary" href="calendar.php?month=<?= (int)$prev->format('n') ?>&amp;year=<?= (int)$prev->format('Y') ?>">&#9664; <?= htmlspecialchars($prev->format('M Y')) ?></a>
        <a class="btn btn-secondary" href="calendar.php?month=<?= (int)$today->format('n') ?>&amp;year=<?= (int)$today->format('Y') ?>">Today</a>
        <a class="btn btn-secondary" href="calendar.php?month=<?= (int)$next->format('n') ?>&amp;year=<?= (int)$next->format('Y') ?>"><?= htmlspecialchars($next->format('M Y')) ?> &#9654;</a>
      </div>
    </div>

    <?php if ($inputError): ?>
      <p class="calendar-notice error"><?= htmlspecialchars($inputError) ?></p>
    <?php endif; ?>

    <h3 class="calendar-title"><?= htmlspecialchars($monthStart->format('F Y')) ?></h3>

    <?php if ($loadError): ?>
      <p class="calendar-notice error"><?= htmlspecialchars($loadError) ?></p>
    <?php else: ?>
      <?php ob_start(); ?>
      <div class="calendar-scroll">
        <table class="calendar-grid">
          <thead>
            <tr><th>Sunday</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th><th>Thursday</th><th>Friday</th><th>Saturday</th></tr>
          </thead>
          <tbody>
          <?php foreach ($weeks as $week): ?>
            <?php
              // The procedure always returns six week rows; skip trailing rows that contain no days of this month.
              $hasDay = false;
              foreach ($dayKeys as $k) {
                  if (($week[$k . '_day'] ?? '') !== '') { $hasDay = true; break; }
              }
              if (!$hasDay) { continue; }
            ?>
            <tr>
              <?php foreach ($dayKeys as $k): ?>
                <?php
                  $dayNum = (string)($week[$k . '_day'] ?? '');
                  $entries = $dayNum === '' ? [] : parseCalendarEntries((string)($week[$k . '_entries'] ?? ''));
                  $entryCount += count($entries);
                  $isToday = $dayNum !== '' && $monthStart->setDate($year, $month, (int)$dayNum)->format('Y-m-d') === $today->format('Y-m-d');
                  $cellClass = $dayNum === '' ? 'outside' : ($isToday ? 'today' : '');
                ?>
                <td class="<?= $cellClass ?>">
                  <?php if ($dayNum !== ''): ?>
                    <div class="day-number"><?= htmlspecialchars($dayNum) ?></div>
                    <?php foreach ($entries as $entry): ?>
                      <span class="calendar-entry <?= $entryClasses[strtolower($entry['type'])] ?? '' ?>"<?= $entry['type'] !== '' ? ' title="' . htmlspecialchars($entry['type']) . '"' : '' ?>>
                        <?php if ($entry['type'] !== ''): ?><span class="sr-only"><?= htmlspecialchars($entry['type']) ?>: </span><?php endif; ?>
                        <?= htmlspecialchars($entry['text']) ?>
                      </span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php $gridHtml = ob_get_clean(); ?>
      <?php if ($entryCount === 0): ?>
        <p class="calendar-notice">No <?= $isSignedIn ? 'services, programs, reminders, birthdays, or anniversaries are' : 'services, programs, or reminders are' ?> scheduled for <?= htmlspecialchars($monthStart->format('F Y')) ?>.</p>
      <?php endif; ?>
      <?= $gridHtml ?>
      <div class="calendar-legend">
        <span class="calendar-entry entry-service">Service</span>
        <span class="calendar-entry entry-program">Program / Event</span>
        <span class="calendar-entry entry-reminder">Reminder</span>
        <?php if ($isSignedIn): ?>
          <span class="calendar-entry entry-birthday">Birthday</span>
          <span class="calendar-entry entry-anniversary">Anniversary</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
