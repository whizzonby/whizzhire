<?php
/**
 * Whizz Hire — Admin Dashboard
 * 
 * View, filter, and export waitlist subscribers.
 * Protected by simple password authentication.
 */

session_start();

// ─── Configuration ───────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'whizzhire');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Change this to a strong password!
define('ADMIN_PASSWORD', 'whizzhire2026');

// ─── Authentication ──────────────────────────────────────────
$loginError = '';

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_authenticated'] = true;
    } else {
        $loginError = 'Incorrect password. Please try again.';
    }
}

// Show login if not authenticated
if (empty($_SESSION['admin_authenticated'])) {
    showLoginPage($loginError);
    exit;
}

// ─── Database Connection ─────────────────────────────────────
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed. Check your credentials.");
}

// ─── Handle CSV Export ───────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportCSV($pdo);
    exit;
}

// ─── Filters ─────────────────────────────────────────────────
$filterType = $_GET['type'] ?? 'all';
$search     = trim($_GET['search'] ?? '');
$page       = max(1, intval($_GET['page'] ?? 1));
$perPage    = 25;
$offset     = ($page - 1) * $perPage;

// Build query
$where  = [];
$params = [];

if ($filterType === 'candidate' || $filterType === 'business') {
    $where[]  = "type = :type";
    $params['type'] = $filterType;
}
if ($search !== '') {
    $where[]  = "email LIKE :search";
    $params['search'] = "%{$search}%";
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM waitlist {$whereSQL}");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Get subscribers
$stmt = $pdo->prepare("
    SELECT id, email, type, ip_address, created_at 
    FROM waitlist {$whereSQL}
    ORDER BY created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$subscribers = $stmt->fetchAll();

// Get stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN type = 'candidate' THEN 1 ELSE 0 END) as candidates,
        SUM(CASE WHEN type = 'business' THEN 1 ELSE 0 END) as businesses,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM waitlist
")->fetch();

// ─── Render Dashboard ────────────────────────────────────────
showDashboard($subscribers, $stats, $filterType, $search, $page, $totalPages, $totalRows);

// ═════════════════════════════════════════════════════════════
// FUNCTIONS
// ═════════════════════════════════════════════════════════════

function exportCSV($pdo) {
    $type = $_GET['type'] ?? 'all';
    
    $where = '';
    $params = [];
    if ($type === 'candidate' || $type === 'business') {
        $where = 'WHERE type = :type';
        $params['type'] = $type;
    }

    $stmt = $pdo->prepare("SELECT email, type, ip_address, created_at FROM waitlist {$where} ORDER BY created_at DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $filename = "whizzhire_waitlist_{$type}_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Type', 'IP Address', 'Signed Up']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['email'], $row['type'], $row['ip_address'], $row['created_at']]);
    }
    fclose($out);
}

function showLoginPage($error) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Whizz Hire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background: #fafafa; }
        .logo-w {
            display: inline-flex; align-items: center; justify-content: center;
            width: 44px; height: 44px; background: linear-gradient(135deg, #7c3aed, #a855f7);
            border-radius: 10px; color: white; font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800; font-size: 24px; margin-right: 8px;
            box-shadow: 0 4px 14px rgba(124, 58, 237, 0.3);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="inline-flex items-center mb-4">
                <div class="logo-w">W</div>
                <span class="font-bold text-2xl text-gray-900" style="font-family:'Plus Jakarta Sans',sans-serif; font-weight:800;">Whizz Hire</span>
            </div>
            <p class="text-gray-500 text-sm">Admin Dashboard</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-1">Sign in</h2>
            <p class="text-gray-400 text-sm mb-6">Enter the admin password to continue.</p>
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input
                    type="password"
                    name="password"
                    placeholder="Password"
                    autofocus
                    required
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100 mb-4"
                >
                <button type="submit" class="w-full py-3 bg-gradient-to-r from-purple-600 to-purple-500 text-white rounded-xl font-semibold text-sm hover:shadow-lg hover:shadow-purple-200 transition-all">
                    Sign in
                </button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
}

function showDashboard($subscribers, $stats, $filterType, $search, $page, $totalPages, $totalRows) {
    $qs = function($overrides = []) use ($filterType, $search, $page) {
        $params = array_merge(['type' => $filterType, 'search' => $search, 'page' => $page], $overrides);
        if ($params['type'] === 'all') unset($params['type']);
        if ($params['search'] === '') unset($params['search']);
        if ($params['page'] <= 1) unset($params['page']);
        return $params ? '?' . http_build_query($params) : '';
    };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Whizz Hire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background: #f8f8fa; }
        .logo-w {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; background: linear-gradient(135deg, #7c3aed, #a855f7);
            border-radius: 8px; color: white; font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800; font-size: 19px; margin-right: 8px;
        }
        .stat-card {
            background: white; border: 1px solid #e5e7eb; border-radius: 14px;
            padding: 20px 24px; transition: box-shadow 0.2s;
        }
        .stat-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.04); }
        .badge {
            display: inline-block; padding: 3px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600; text-transform: capitalize;
        }
        .badge-candidate { background: #ede9fe; color: #6d28d9; }
        .badge-business { background: #dbeafe; color: #1d4ed8; }
        table { border-collapse: separate; border-spacing: 0; }
        th { position: sticky; top: 0; z-index: 10; }
        .tab-active {
            background: white; color: #111827; box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
        }
        .tab-inactive {
            background: transparent; color: #6b7280; border: 1px solid transparent;
        }
        .tab-active, .tab-inactive {
            padding: 7px 16px; border-radius: 8px; font-size: 13px;
            font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.15s;
        }
        .tab-inactive:hover { color: #374151; background: rgba(255,255,255,0.6); }
    </style>
</head>
<body class="min-h-screen">

    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-20">
        <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between">
            <div class="flex items-center">
                <div class="logo-w">W</div>
                <span class="font-bold text-lg text-gray-900 mr-3" style="font-family:'Plus Jakarta Sans',sans-serif; font-weight:700;">Whizz Hire</span>
                <span class="text-gray-300 text-sm hidden sm:inline">|</span>
                <span class="text-gray-400 text-sm ml-3 hidden sm:inline">Admin Dashboard</span>
            </div>
            <div class="flex items-center gap-3">
                <!-- Export dropdown -->
                <div class="relative" id="exportDropdown">
                    <button onclick="toggleExport()" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-all">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export CSV
                    </button>
                    <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl border border-gray-200 shadow-lg py-1 z-30">
                        <a href="admin.php?export=csv&type=all" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">All subscribers</a>
                        <a href="admin.php?export=csv&type=candidate" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Candidates only</a>
                        <a href="admin.php?export=csv&type=business" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Businesses only</a>
                    </div>
                </div>
                <form method="POST" class="inline">
                    <button name="logout" value="1" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition">Logout</button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-5 py-8">

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="stat-card">
                <p class="text-gray-400 text-xs font-semibold uppercase tracking-wide mb-1">Total Signups</p>
                <p class="text-3xl font-bold text-gray-900"><?= number_format($stats['total'] ?? 0) ?></p>
            </div>
            <div class="stat-card">
                <p class="text-gray-400 text-xs font-semibold uppercase tracking-wide mb-1">Candidates</p>
                <p class="text-3xl font-bold text-purple-600"><?= number_format($stats['candidates'] ?? 0) ?></p>
            </div>
            <div class="stat-card">
                <p class="text-gray-400 text-xs font-semibold uppercase tracking-wide mb-1">Businesses</p>
                <p class="text-3xl font-bold text-blue-600"><?= number_format($stats['businesses'] ?? 0) ?></p>
            </div>
            <div class="stat-card">
                <p class="text-gray-400 text-xs font-semibold uppercase tracking-wide mb-1">Today</p>
                <p class="text-3xl font-bold text-emerald-600"><?= number_format($stats['today'] ?? 0) ?></p>
            </div>
        </div>

        <!-- Filters bar -->
        <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <!-- Tabs -->
                <div class="flex items-center gap-2 bg-gray-100/70 rounded-lg p-1">
                    <a href="admin.php<?= $qs(['type' => 'all', 'page' => 1]) ?>" class="<?= $filterType === 'all' ? 'tab-active' : 'tab-inactive' ?>">
                        All
                    </a>
                    <a href="admin.php<?= $qs(['type' => 'candidate', 'page' => 1]) ?>" class="<?= $filterType === 'candidate' ? 'tab-active' : 'tab-inactive' ?>">
                        Candidates
                    </a>
                    <a href="admin.php<?= $qs(['type' => 'business', 'page' => 1]) ?>" class="<?= $filterType === 'business' ? 'tab-active' : 'tab-inactive' ?>">
                        Businesses
                    </a>
                </div>
                <!-- Search -->
                <form method="GET" class="flex items-center gap-2">
                    <?php if ($filterType !== 'all'): ?>
                        <input type="hidden" name="type" value="<?= htmlspecialchars($filterType) ?>">
                    <?php endif; ?>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input
                            type="text"
                            name="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search by email..."
                            class="pl-10 pr-4 py-2 w-64 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-100"
                        >
                    </div>
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition">Search</button>
                    <?php if ($search): ?>
                        <a href="admin.php<?= $qs(['search' => '', 'page' => 1]) ?>" class="text-sm text-gray-400 hover:text-gray-600 ml-1">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden mb-6">
            <?php if (empty($subscribers)): ?>
                <div class="text-center py-16">
                    <div class="text-gray-300 mb-3">
                        <svg class="mx-auto" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <p class="text-gray-500 font-medium">No subscribers found</p>
                    <p class="text-gray-400 text-sm mt-1">
                        <?= $search ? 'Try a different search term.' : 'Signups will appear here once people join the waitlist.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50/80">
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">IP Address</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Signed Up</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($subscribers as $i => $sub): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-400"><?= $sub['id'] ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($sub['email']) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="badge badge-<?= $sub['type'] ?>"><?= $sub['type'] ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-400 hidden md:table-cell"><?= htmlspecialchars($sub['ip_address'] ?? '—') ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('M j, Y · g:ia', strtotime($sub['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">
                Showing <?= ($page - 1) * 25 + 1 ?>–<?= min($page * 25, $totalRows) ?> of <?= number_format($totalRows) ?>
            </p>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="admin.php<?= $qs(['page' => $page - 1]) ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">← Prev</a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                    <a href="admin.php<?= $qs(['page' => $p]) ?>"
                       class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $p === $page ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="admin.php<?= $qs(['page' => $page + 1]) ?>" class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Next →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <script>
        function toggleExport() {
            document.getElementById('exportMenu').classList.toggle('hidden');
        }
        // Close dropdown on outside click
        document.addEventListener('click', (e) => {
            const dd = document.getElementById('exportDropdown');
            if (!dd.contains(e.target)) {
                document.getElementById('exportMenu').classList.add('hidden');
            }
        });
    </script>
</body>
</html>
<?php } ?>
