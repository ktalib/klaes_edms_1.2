<?php
/**
 * Step 2 of recovery: Preview deleted file_indexings rows in the browser.
 * Reads storage/app/recovery/deleted_file_indexings.json
 */

$jsonPath = __DIR__ . '/storage/app/recovery/deleted_file_indexings.json';

if (!file_exists($jsonPath)) {
    http_response_code(404);
    echo "<h1>No data file found.</h1>";
    echo "<p>Run <code>php _recover_fi_export.php</code> first.</p>";
    echo "<p>Expected at: <code>" . htmlspecialchars($jsonPath) . "</code></p>";
    exit;
}

$payload = json_decode(file_get_contents($jsonPath), true);
$rows    = $payload['rows'] ?? [];

$filterType = $_GET['type'] ?? '';
$search     = trim($_GET['q'] ?? '');

$filtered = array_filter($rows, function ($r) use ($filterType, $search) {
    if ($filterType !== '' && (string)($r['file_type'] ?? '') !== $filterType) return false;
    if ($search !== '') {
        $hay = strtolower(implode(' ', [
            $r['file_number'] ?? '', $r['FileName'] ?? '', $r['tracking_id'] ?? '',
            $r['location'] ?? '', $r['lga'] ?? '', $r['district'] ?? '', $r['tp_no'] ?? '',
            $r['plot_no'] ?? '',
        ]));
        if (strpos($hay, strtolower($search)) === false) return false;
    }
    return true;
});

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

$typeColours = [
    'Lands (MLS)' => 'bg-blue-100 text-blue-800',
    'New KANGIS'  => 'bg-green-100 text-green-800',
    'KANGIS'      => 'bg-emerald-100 text-emerald-800',
    'ST'          => 'bg-purple-100 text-purple-800',
    'GKN'         => 'bg-amber-100 text-amber-800',
    'DCIV'        => 'bg-rose-100 text-rose-800',
    '—'           => 'bg-gray-100 text-gray-700',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>File Indexings Recovery Preview</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: 'Segoe UI', sans-serif; }
  table { border-collapse: collapse; width: 100%; }
  th, td { padding: 6px 10px; border: 1px solid #e5e7eb; font-size: 12px; vertical-align: top; }
  th { background: #f3f4f6; position: sticky; top: 0; z-index: 1; white-space: nowrap; }
  tr:nth-child(even) td { background: #fafafa; }
  .pill { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
</style>
</head>
<body class="bg-gray-50 p-6">

<div class="max-w-full">
  <h1 class="text-2xl font-bold mb-1">File Indexings Recovery — Preview</h1>
  <p class="text-sm text-gray-600 mb-4">
    Source: <code><?= h($payload['database']) ?></code> @ <code><?= h($payload['host']) ?></code>
    · Generated: <code><?= h($payload['generated_at']) ?></code>
    · Date filter: <code><?= h($payload['date_filter'] ?: 'none') ?></code>
    · Scope: <code><?= ($payload['include_non_lands'] ?? false) ? 'all file types' : 'lands files only' ?></code>
  </p>

  <!-- Summary cards -->
  <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
    <div class="bg-white rounded shadow p-3">
      <div class="text-xs text-gray-500">Total deleted</div>
      <div class="text-2xl font-bold"><?= (int)$payload['total_rows'] ?></div>
    </div>
    <?php foreach ($payload['by_type'] ?? [] as $t => $cnt): ?>
      <div class="bg-white rounded shadow p-3">
        <div class="text-xs text-gray-500"><?= h($t) ?></div>
        <div class="text-2xl font-bold"><?= h($cnt) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Filters -->
  <form method="get" class="bg-white p-3 rounded shadow mb-3 flex flex-wrap gap-3 items-end">
    <div>
      <label class="block text-xs text-gray-600">File type</label>
      <select name="type" class="border rounded px-2 py-1 text-sm">
        <option value="">All</option>
        <?php foreach ($payload['by_type'] ?? [] as $t => $cnt): ?>
          <option value="<?= h($t) ?>" <?= $filterType === (string)$t ? 'selected' : '' ?>>
            <?= h($t) ?> (<?= h($cnt) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex-1 min-w-[260px]">
      <label class="block text-xs text-gray-600">Search (file no / title / location / tracking id)</label>
      <input type="text" name="q" value="<?= h($search) ?>" class="border rounded px-2 py-1 text-sm w-full">
    </div>
    <div>
      <button class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm">Filter</button>
      <a href="?" class="ml-2 text-sm text-gray-600 underline">reset</a>
    </div>
    <div class="ml-auto text-sm text-gray-700">
      Showing <strong><?= count($filtered) ?></strong> of <?= count($rows) ?>
    </div>
  </form>

  <div class="bg-white rounded shadow overflow-auto" style="max-height: 78vh;">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Tracking ID</th>
          <th>File Number</th>
          <th>Type</th>
          <th>File Title</th>
          <th>Location</th>
          <th>LGA</th>
          <th>District</th>
          <th>State</th>
          <th>TP No</th>
          <th>Plot No</th>
          <th>Phone</th>
          <th>Address</th>
          <th>Temp File No</th>
          <th>Created By</th>
          <th>Updated By</th>
          <th>Created At</th>
          <th>Updated At</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; foreach ($filtered as $r): $i++; ?>
          <?php
            $type = (string)($r['file_type'] ?? '—');
            $cls  = $typeColours[$type] ?? 'bg-gray-100 text-gray-700';
          ?>
          <tr>
            <td class="text-gray-500"><?= $i ?></td>
            <td class="font-mono whitespace-nowrap"><?= h($r['tracking_id'] ?? '') ?></td>
            <td class="font-mono font-semibold whitespace-nowrap"><?= h($r['file_number'] ?? '') ?></td>
            <td><span class="pill <?= h($cls) ?>"><?= h($type) ?></span></td>
            <td><?= h($r['FileName'] ?? '') ?></td>
            <td><?= h($r['location'] ?? '') ?></td>
            <td><?= h($r['lga'] ?? '') ?></td>
            <td><?= h($r['district'] ?? '') ?></td>
            <td><?= h($r['state'] ?? '') ?></td>
            <td><?= h($r['tp_no'] ?? '') ?></td>
            <td><?= h($r['plot_no'] ?? '') ?></td>
            <td><?= h($r['phone_no'] ?? '') ?></td>
            <td><?= h($r['address'] ?? '') ?></td>
            <td><?= h(($r['temp_file_no'] ?? '') ?: ($r['temp_fileno'] ?? '')) ?></td>
            <td><?= h($r['created_by'] ?? '') ?></td>
            <td><?= h($r['updated_by'] ?? '') ?></td>
            <td class="whitespace-nowrap"><?= h($r['created_at'] ?? '') ?></td>
            <td class="whitespace-nowrap"><?= h($r['updated_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($filtered)): ?>
          <tr><td colspan="18" class="text-center text-gray-500 py-6">No rows match the current filters.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="text-xs text-gray-500 mt-3">
    JSON source: <code><?= h($jsonPath) ?></code>
  </p>
</div>

</body>
</html>
