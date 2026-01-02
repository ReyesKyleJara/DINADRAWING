<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

// 2. PREPARE USER DATA
$userData = [
    'name' => $_SESSION['name'] ?? 'User',
    'username' => $_SESSION['username'] ?? 'User',
    'email' => $_SESSION['email'] ?? '',
    'photo' => $_SESSION['profile_picture'] ?? 'Assets/Profile Icon/profile.png' 
];

// 3. DATABASE CONNECTION & LOGIC
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = new PDO(
      "pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing",
      "kai",
      "DND2025",
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]
    );

    $userId = $_SESSION['user_id'];
    $showArchived = !empty($_GET['archived']);
    
    // Archive Filter
    $archiveClause = $showArchived ? "e.archived IS TRUE" : "e.archived IS NOT TRUE";

    // --- QUERY 1: ACTIVE PLANS ---
    $sql = "
      SELECT DISTINCT
        e.id, e.date, e.time, e.name, e.description,
        CASE
          WHEN e.date IS NOT NULL AND e.time IS NOT NULL THEN e.date::text || ' ' || e.time::text
          WHEN e.date IS NOT NULL THEN e.date::text
          WHEN e.time IS NOT NULL THEN e.time::text
          ELSE NULL
        END AS dt,
        e.location AS loc,
        e.banner_type, e.banner_color, e.banner_from, e.banner_to, e.banner_image,
        e.invite_code, 
        e.archived, e.owner_id
      FROM events e
      LEFT JOIN event_members em ON e.id = em.event_id
      WHERE (e.owner_id = :uid OR em.user_id = :uid)
      AND $archiveClause
      AND e.deleted_at IS NULL 
      ORDER BY e.date NULLS LAST, e.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $events = $stmt->fetchAll();

    // --- SEPARATE PLANS ---
    $myPlans = [];
    $joinedPlans = [];

    if (!empty($events)) {
        foreach ($events as $ev) {
            if ($ev['owner_id'] == $userId) {
                $myPlans[] = $ev;
            } else {
                $joinedPlans[] = $ev;
            }
        }
    }

    // --- QUERY 2: TRASHED PLANS ---
    $trashSql = "SELECT id, name, deleted_at FROM events WHERE owner_id = :uid AND deleted_at IS NOT NULL ORDER BY deleted_at DESC";
    $trashStmt = $pdo->prepare($trashSql);
    $trashStmt->execute([':uid' => $userId]);
    $trashItems = $trashStmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function h($s){ return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8'); }
function formatMonth($dt){ $ts=$dt?strtotime($dt):false; return $ts?date('M',$ts):'-'; }
function formatDay($dt){ $ts=$dt?strtotime($dt):false; return $ts?date('d',$ts):'-'; }
function formatDate($dt){ $ts=$dt?strtotime($dt):false; return $ts?date('M d, Y',$ts):'-'; }

function card_banner_style($ev){
  $t = $ev['banner_type'] ?? null;
  if ($t === 'image' && !empty($ev['banner_image'])){
    $img = $ev['banner_image'];
    if (strpos($img, 'DINADRAWING/') === false) { $img = '/DINADRAWING/' . ltrim($img, '/'); } 
    else { $img = '/' . ltrim($img, '/'); }
    return "background-image:url('".h($img)."');background-size:cover;background-position:center;color:#fff;";
  }
  if ($t === 'gradient' && !empty($ev['banner_from']) && !empty($ev['banner_to'])){
    return "background:linear-gradient(to right,".h($ev['banner_from']).",".h($ev['banner_to']).");color:#111;";
  }
  if ($t === 'color' && !empty($ev['banner_color'])){
    return "background:".h($ev['banner_color']).";color:#fff;";
  }
  return "background:#f4b41a;color:#222;"; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Plans | DiNaDrawing</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* STYLES COPIED EXACTLY FROM DASHBOARD */
    body { font-family: 'Poppins', sans-serif; background-color: #fffaf2; color: #222; }
    
    /* Dark Mode Styles */
    body.dark-mode { background-color: #1a1a1a !important; color: #e0e0e0 !important; }
    body.dark-mode .bg-white { background-color: #2a2a2a !important; }
    body.dark-mode .bg-\[\#fffaf2\] { background-color: #1a1a1a !important; }
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode p, body.dark-mode span, body.dark-mode div { color: #e0e0e0 !important; }
    body.dark-mode .text-gray-600, body.dark-mode .text-gray-700, body.dark-mode .text-gray-500 { color: #a0a0a0 !important; }
    body.dark-mode .border-gray-200 { border-color: #404040 !important; }
    body.dark-mode .border-gray-100 { border-color: #353535 !important; }
    body.dark-mode .border-gray-300 { border-color: #454545 !important; }
    body.dark-mode .shadow, body.dark-mode .shadow-md, body.dark-mode .shadow-lg { box-shadow: 0 4px 6px rgba(0,0,0,0.5) !important; }
    body.dark-mode input, body.dark-mode textarea, body.dark-mode select { background-color: #2a2a2a !important; color: #e0e0e0 !important; border-color: #454545 !important; }
    body.dark-mode input::placeholder, body.dark-mode textarea::placeholder { color: #707070 !important; }
    body.dark-mode .text-\[\#222\] { color: #e0e0e0 !important; }
    
    /* Hamburger Menu */
    .hamburger { display: flex; flex-direction: column; gap: 4px; cursor: pointer; padding: 8px; border-radius: 8px; transition: background 0.2s; }
    .hamburger:hover { background: rgba(244, 180, 26, 0.1); }
    .hamburger span { width: 24px; height: 3px; background: #222; border-radius: 2px; transition: all 0.3s; }
    body.dark-mode .hamburger span { background: #e0e0e0; }

    /* Sidebar overlay */
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 45; }
    .sidebar-overlay.active { display: block; }

    /* Sidebar toggle logic - Exact from Dashboard */
    #sidebar { transition: transform 0.3s ease; z-index: 50; transform: translateX(-100%); }
    #sidebar.active { transform: translateX(0); }
    @media (min-width: 769px) {
      #sidebar { transform: translateX(0); }
      #sidebar:not(.active) { transform: translateX(-100%); }
    }

    /* Main/content shift */
    main { transition: margin-left 0.3s ease; }
    @media (min-width: 769px) {
      main.sidebar-open { margin-left: 16rem; }
      main:not(.sidebar-open) { margin-left: 0; }
    }

    /* Header shift */
    .page-header { transition: left 0.3s ease; }
    @media (min-width: 769px) {
      .page-header.sidebar-open { left: 16rem; width: calc(100% - 16rem); }
      .page-header:not(.sidebar-open) { left: 0; width: 100%; }
    }
  </style>
</head>
<body class="flex bg-[#fffaf2]">

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside id="sidebar" class="fixed top-4 left-0 h-[calc(100vh-1rem)] w-64 bg-[#f4b41a] rounded-tr-3xl p-6 shadow hidden md:flex md:flex-col md:gap-6">
  <div class="flex items-center gap-2">
    <img src="Assets/dinadrawing-logo.png" alt="Logo" class="w-14">
    <h2 class="text-xl font-bold text-[#222]">DiNaDrawing</h2>
  </div>
  <nav>
    <ul class="space-y-5">
      <li><a href="dashboard.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Home</a></li>
      <li><a href="myplans.php" class="block px-4 py-2 rounded-lg font-medium bg-[#222] text-white hover:bg-[#111] transition">My Plans</a></li>
      <li><a href="help.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Help</a></li>
      <li><a href="settings.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Settings</a></li>
    </ul>
  </nav>
</aside>

<main id="mainContent" class="flex-1 min-h-screen px-12 py-10 pt-28">

<div class="page-header flex justify-between items-center border-b-2 border-gray-200 pb-4 mb-6 fixed top-0 left-0 w-full bg-[#fffaf2] z-40 px-12 py-10">
  <div class="flex items-center gap-4">
    <button id="hamburgerBtn" class="hamburger"><span></span><span></span><span></span></button>
    <div class="flex flex-col">
      <h1 class="text-3xl font-bold">My Plans</h1>
      <span class="text-gray-600 text-sm">Manage, view, and edit your plans easily.</span>
    </div>
  </div>

  <div class="flex items-center gap-3">
    <button id="openCreateEvent" class="border border-[#222] bg-white px-4 py-2 rounded-2xl font-medium hover:bg-[#222] hover:text-white transition">
      <span class="hidden md:inline">+ Create Event</span><span class="md:hidden">Create</span>
    </button>
    <button id="openJoinEvent" class="border border-[#222] bg-white px-4 py-2 rounded-2xl font-medium hover:bg-[#222] hover:text-white transition">
      <span class="hidden md:inline">Join Event</span><span class="md:hidden">Join</span>
    </button>

    <div class="flex items-center gap-4 relative">
      <div>
        <button id="notificationBtn" class="relative w-9 h-9 flex items-center justify-center rounded-full bg-white border border-[#222] hover:bg-[#222] hover:text-white transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3c0 .386-.146.735-.395 1.002L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
          <span id="notificationDot" class="absolute top-1 right-1 w-2.5 h-2.5 bg-[#f4b41a] rounded-full"></span>
        </button>
        <div id="notificationPanel" class="absolute top-full right-0 mt-2 w-[90vw] md:w-60 lg:w-80 max-w-[28rem] bg-white shadow-lg rounded-2xl border border-gray-200 hidden z-50">
          <div class="p-4 border-b border-gray-200 flex justify-between items-center"><h4 class="font-semibold">Notifications</h4><a href="#" id="clearNotificationsTop" class="text-sm text-[#2563eb] hover:underline">Mark all as read</a></div>
          <ul id="notificationList" class="p-4 space-y-3 max-h-64 overflow-y-auto">
            <li class="flex items-start gap-3 bg-white hover:bg-gray-50 p-2 rounded-lg cursor-pointer">
              <p class="text-sm"><strong>System</strong> Welcome to DiNaDrawing!</p>
            </li>
          </ul>
        </div>
      </div>

      <div class="relative">
        <button id="profileBtn" class="flex items-center gap-2 bg-[#f4b41a]/30 border border-[#222] rounded-full px-3 py-1.5 hover:bg-[#f4b41a]/50 transition">
          <img id="navProfileImg" src="Assets/Profile Icon/profile.png" alt="Profile" class="w-8 h-8 rounded-full border-2 border-[#f4b41a]">
          <span id="navProfileName" class="font-medium text-[#222] hidden md:inline">User</span>
        </button>
        <div id="profileDropdown" class="absolute right-0 mt-2 w-60 bg-white shadow-lg rounded-2xl border border-gray-200 hidden z-50">
          <div class="p-4 border-b border-gray-200 text-center bg-[#fffaf2] rounded-t-2xl">
            <img id="dropdownProfileImg" src="Assets/Profile Icon/profile.png" alt="Profile" class="w-12 h-12 mx-auto rounded-full border-2 border-[#f4b41a] mb-2 shadow">
            <h2 id="dropdownProfileName" class="text-sm font-semibold text-[#222]">User</h2>
          </div>
          <div class="py-2">
            <a href="help.php" class="block px-4 py-2 text-sm hover:bg-gray-50">Help</a>
            <button id="aboutUsBtn" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50">About Us</button>
            <a href="settings.php" class="block px-4 py-2 text-sm hover:bg-gray-50">Settings</a>
            <button id="logoutProfile" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Log out</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="bg-white rounded-2xl shadow border border-gray-200 p-6 mt-6">
  <div class="flex items-center justify-between mb-8">
      <h2 class="text-xl font-bold text-gray-800">
        <?php echo $showArchived ? 'Archived Plans' : 'Active Plans'; ?>
      </h2>
      
      <div class="flex gap-2">
<button id="galleryViewBtn" title="Gallery view" class="p-2 rounded-md bg-[#f4b41a] text-[#222] transition shadow-sm">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
        <rect x="4" y="4" width="5" height="5" rx="1"></rect>
        <rect x="11" y="4" width="5" height="5" rx="1"></rect>
        <rect x="11" y="11" width="5" height="5" rx="1"></rect>
        <rect x="4" y="11" width="5" height="5" rx="1"></rect>
    </svg>
</button>
        <button id="listViewBtn" title="List view" class="p-2 rounded-md text-gray-600 hover:bg-gray-100 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z" clip-rule="evenodd" /></svg>
        </button>

        <div class="relative ml-2 border-l pl-4 border-gray-300">
            <button id="headerMoreBtn" title="More actions" class="p-2 rounded-md text-gray-600 hover:bg-gray-100 transition">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
            </button>
            <div id="headerMoreMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
              <button onclick="window.location.href='<?php echo $showArchived ? 'myplans.php' : 'myplans.php?archived=1'; ?>'" 
                      class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 text-sm transition">
                <?php if($showArchived): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.707-10.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L9.414 11H13a1 1 0 100-2H9.414l1.293-1.293z" clip-rule="evenodd" /></svg>
                    <span>Active Plans</span>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a1 1 0 00-1 1v2a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H4zm0 8v4a2 2 0 002 2h8a2 2 0 002-2v-4H4z"/></svg>
                    <span>Archived Plans</span>
                <?php endif; ?>
              </button>
              <button id="headerMenuTrash" class="w-full flex items-center gap-3 px-4 py-3 hover:bg-red-50 text-sm text-red-600 transition border-t border-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                <span>Trash Bin</span>
              </button>
            </div>
        </div>
      </div>
    </div>

    <div id="galleryView">
      <section class="mb-12">
        <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2 text-lg">
          <span class="w-1.5 h-6 bg-[#f4b41a] rounded-full"></span>
          Plans by Me
        </h3>
        <div class="flex flex-wrap gap-6">
          <?php if (!empty($myPlans)): ?>
            <?php foreach ($myPlans as $ev): ?>
              <div class="relative w-64 bg-white border border-gray-300 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col h-full" 
                   data-id="<?php echo $ev['id']; ?>" 
                   data-owner="1"
                   data-date="<?php echo $ev['date']; ?>"> <div class="font-bold p-4 text-lg flex justify-between items-start h-24" style="<?php echo card_banner_style($ev); ?>">
                  <div class="relative z-10 flex flex-col gap-1">
                     <?php if(!empty($ev['invite_code'])): ?>
                      <div class="inline-flex items-center gap-1 bg-black/40 backdrop-blur-md text-white text-[10px] px-2 py-0.5 rounded-full cursor-pointer hover:bg-black/60 transition w-fit"
                           onclick="copyToClipboard('<?php echo h($ev['invite_code']); ?>', this)" title="Click to copy code">
                          <span class="uppercase tracking-wider font-bold"><?php echo h($ev['invite_code']); ?></span>
                      </div>
                     <?php endif; ?>
                  </div>
                  <div class="relative z-10">
                    <button onclick="showPlanActions(event,this)" class="text-white hover:text-gray-200 px-1.5 py-0.5 rounded hover:bg-black/20 transition text-xl leading-none">⋮</button>
                  </div>
                </div>
                <div class="p-4 flex-1">
                    <h3 class="font-bold text-[#222] text-lg leading-tight mb-1 line-clamp-2"><?php echo h($ev['name'] ?? 'Untitled'); ?></h3>
                    <p class="text-sm text-gray-500 flex items-center gap-1 mt-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                      <?php echo h($ev['loc'] ?? 'No location'); ?>
                    </p>
                </div>
                <div class="px-4 pb-4 flex items-center justify-between mt-auto border-t border-gray-50 pt-3">
                  <div class="text-center leading-none">
                    <div class="text-[10px] text-gray-400 uppercase font-bold tracking-wider"><?php echo formatMonth($ev['dt']); ?></div>
                    <div class="text-lg font-bold text-[#222]"><?php echo formatDay($ev['dt']); ?></div>
                  </div>
                  <button class="bg-[#222] text-white text-xs font-medium px-4 py-2 rounded-lg hover:bg-[#444] transition"
                          onclick="window.location.href='plan.php?id=<?php echo (int)$ev['id']; ?>'">Open</button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="w-full text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-300">
              <p class="text-gray-500 text-sm mb-2">You haven't created any plans yet.</p>
              <button onclick="document.getElementById('createEventModal').classList.remove('hidden')" class="text-[#f4b41a] font-bold text-sm hover:underline">+ Create New Plan</button>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section>
        <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2 text-lg">
          <span class="w-1.5 h-6 bg-blue-500 rounded-full"></span>
          Plans with Me
        </h3>
        <div class="flex flex-wrap gap-6">
          <?php if (!empty($joinedPlans)): ?>
            <?php foreach ($joinedPlans as $ev): ?>
              <div class="relative w-64 bg-white border border-gray-300 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col h-full" 
                   data-id="<?php echo $ev['id']; ?>" data-owner="0"> 
                <div class="font-bold p-4 text-lg flex justify-between items-start h-24" style="<?php echo card_banner_style($ev); ?>">
                  <div class="relative z-10"></div>
                  <div class="relative z-10">
                    <button onclick="showPlanActions(event,this)" class="text-white hover:text-gray-200 px-1.5 py-0.5 rounded hover:bg-black/20 transition text-xl leading-none">⋮</button>
                  </div>
                </div>
                <div class="p-4 flex-1">
                    <h3 class="font-bold text-[#222] text-lg leading-tight mb-1 line-clamp-2"><?php echo h($ev['name'] ?? 'Untitled'); ?></h3>
                    <p class="text-sm text-gray-500 flex items-center gap-1 mt-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                      <?php echo h($ev['loc'] ?? 'No location'); ?>
                    </p>
                </div>
                <div class="px-4 pb-4 flex items-center justify-between mt-auto border-t border-gray-50 pt-3">
                  <div class="text-center leading-none">
                    <div class="text-[10px] text-gray-400 uppercase font-bold tracking-wider"><?php echo formatMonth($ev['dt']); ?></div>
                    <div class="text-lg font-bold text-[#222]"><?php echo formatDay($ev['dt']); ?></div>
                  </div>
                  <button class="bg-gray-100 text-gray-700 border border-gray-200 text-xs font-medium px-4 py-2 rounded-lg hover:bg-gray-200 transition"
                          onclick="window.location.href='plan.php?id=<?php echo (int)$ev['id']; ?>'">View</button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="w-full text-center py-6">
              <p class="text-gray-400 text-sm italic">You haven't joined any plans yet.</p>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <div id="listView" class="hidden">
      <h3 class="font-bold text-gray-700 mb-3 mt-2">Plans by Me</h3>
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
        <table class="w-full">
          <tbody class="divide-y divide-gray-100">
          <?php if (!empty($myPlans)): foreach ($myPlans as $ev): ?>
            <tr class="hover:bg-gray-50 transition" data-id="<?php echo $ev['id']; ?>" data-owner="1" data-date="<?php echo $ev['date']; ?>">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-[#f4b41a] rounded-lg flex items-center justify-center text-[#222] font-bold text-xs shadow-sm">
                    <?php echo formatDay($ev['dt']); ?>
                  </div>
                  <span class="font-semibold text-gray-800"><?php echo h($ev['name']); ?></span>
                </div>
              </td>
              <td class="px-6 py-4 text-sm text-gray-500"><?php echo h($ev['loc']); ?></td>
              <td class="px-6 py-4 text-right">
                <div class="flex justify-end gap-2">
                  <button class="text-xs bg-[#222] text-white px-3 py-1.5 rounded hover:bg-[#444]" onclick="window.location.href='plan.php?id=<?php echo $ev['id']; ?>'">Open</button>
                  <button onclick="showPlanActions(event,this)" class="text-gray-400 hover:text-gray-600 px-2">⋮</button>
                </div>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3" class="px-6 py-4 text-center text-gray-400 text-sm">No plans created.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <h3 class="font-bold text-gray-700 mb-3">Plans with Me</h3>
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
          <tbody class="divide-y divide-gray-100">
          <?php if (!empty($joinedPlans)): foreach ($joinedPlans as $ev): ?>
            <tr class="hover:bg-gray-50 transition" data-id="<?php echo $ev['id']; ?>" data-owner="0" data-date="<?php echo $ev['date']; ?>">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center font-bold text-xs shadow-sm">
                    <?php echo formatDay($ev['dt']); ?>
                  </div>
                  <span class="font-semibold text-gray-800"><?php echo h($ev['name']); ?></span>
                </div>
              </td>
              <td class="px-6 py-4 text-sm text-gray-500"><?php echo h($ev['loc']); ?></td>
              <td class="px-6 py-4 text-right">
                <div class="flex justify-end gap-2">
                  <button class="text-xs bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded hover:bg-gray-50" onclick="window.location.href='plan.php?id=<?php echo $ev['id']; ?>'">View</button>
                  <button onclick="showPlanActions(event,this)" class="text-gray-400 hover:text-gray-600 px-2">⋮</button>
                </div>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3" class="px-6 py-4 text-center text-gray-400 text-sm">No joined plans.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<div id="createEventModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
  <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-md max-h-[90vh] overflow-y-auto p-6">
    <button id="closeCreateEvent" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
    <h2 class="text-2xl font-bold mb-1">Create Event</h2>
    <p class="text-sm text-gray-500 mb-4">Fill in the details to start planning your event.</p>
    <label class="block text-sm font-medium mb-1">Event Name <span class="text-gray-400 text-xs">(required)</span> </label>
    <input type="text" id="eventName" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4" placeholder="Enter event name">
    <label class="block text-sm font-medium mb-1">Description </label>
    <textarea id="eventDescription" maxlength="200" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-1 resize-none h-20" placeholder="Write a short description..."></textarea>
    <div class="text-right text-xs text-gray-500 mb-4"><span id="charCount">0</span>/200</div>
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div><label class="block text-sm font-medium mb-1">Date</label><input id="eventDate" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
      <div><label class="block text-sm font-medium mb-1">Time</label><input id="eventTime" type="time" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
    </div>
    <label class="block text-sm font-medium mb-1">Location</label>
    <input id="eventLocation" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-2" placeholder="Search for a location">
    <div id="map" class="w-full h-60 rounded-lg border border-gray-300 mb-4"></div>
    <p class="text-gray-500 text-xs mb-4 text-justify">Only Event Name is required. You can leave Description, Date, Time, and Location blank and create a poll for your attendees to vote on these details.</p>
    <div class="flex justify-end gap-3 mb-2">
      <button id="cancelCreateEvent" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Cancel</button>
      <button id="saveCreateEvent" class="bg-[#f4b41a] px-4 py-2 rounded-lg font-medium hover:bg-[#e0a419] transition">Save & Continue</button>
    </div>
  </div>
</div>

<div id="joinEventModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center hidden z-50">
  <div class="bg-white rounded-2xl p-6 w-[500px] shadow-xl relative">
    <button id="closeJoinEvent" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
    <h2 class="text-2xl font-bold mb-1">Join Event</h2>
    <p class="text-sm text-gray-500 mb-4">Enter the event code or link to join.</p>
    <label class="block text-sm font-medium mb-1">Event Code / Link</label>
    <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-6" placeholder="Paste code or link here">
    <div class="flex justify-end gap-3">
      <button id="cancelJoinEvent" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Cancel</button>
      <button id="joinJoinEvent" class="bg-[#f4b41a] px-4 py-2 rounded-lg font-medium hover:bg-[#e0a419] transition">Join</button>
    </div>
  </div>
</div>

<div id="logoutModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden z-[70] p-4 flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-md p-6" role="dialog" aria-modal="true" aria-labelledby="logoutTitle" onclick="event.stopPropagation()">
    <h2 id="logoutTitle" class="text-xl font-bold text-[#222] mb-2">Log out?</h2>
    <p class="text-sm text-gray-600 mb-6">Are you sure you want to log out?</p>
    <div class="flex justify-end gap-3">
      <button id="cancelLogoutBtn" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Cancel</button>
      <button id="confirmLogoutBtn" class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition">Yes</button>
    </div>
  </div>
</div>

      <div id="aboutUsModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden z-[60] p-4">
        <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-2xl max-h-[90vh] overflow-y-auto p-8 mx-auto my-10"
             role="dialog" aria-modal="true" aria-labelledby="aboutUsTitle" onclick="event.stopPropagation()">
          <button id="closeAboutUsModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-2xl" aria-label="Close">&times;</button>

          <h1 id="aboutUsTitle" class="text-3xl font-bold text-[#222] mb-4">About Us</h1>
          <p class="text-gray-700 mb-6 leading-relaxed">
            <span class="font-semibold text-[#f4b41a]">DiNaDrawing</span> is a collaborative planning platform designed to make organizing events and group activities easy, fun, and efficient.
            Whether you're preparing a trip, party, or project, DiNaDrawing helps you stay connected, assign tasks, and track your plans in one place.
          </p>

          <div class="grid md:grid-cols-2 gap-6 mt-8">
            <div class="bg-[#fffaf2] p-6 rounded-xl border border-gray-200 shadow-sm">
              <h2 class="text-xl font-semibold text-[#222] mb-3">Our Mission</h2>
              <p class="text-gray-700 leading-relaxed text-sm">
                To simplify teamwork and planning by creating a space where collaboration feels effortless, ensuring every plan—big or small—gets drawn out perfectly.
              </p>
            </div>
            <div class="bg-[#fffaf2] p-6 rounded-xl border border-gray-200 shadow-sm">
              <h2 class="text-xl font-semibold text-[#222] mb-3">Our Vision</h2>
              <p class="text-gray-700 leading-relaxed text-sm">
                To become the go-to digital planner for creative teams and friend groups around the world, empowering people to turn their ideas into reality.
              </p>
            </div>
          </div>

          <div class="mt-8">
            <h2 class="text-2xl font-bold text-[#222] mb-4">Meet the Team</h2>
            <div class="grid grid-cols-5 gap-4">
              <div class="flex flex-col items-center bg-[#fffaf2] p-4 rounded-xl border border-gray-200 shadow-sm">
                <img src="Assets/Profile Icon/Ken.jpg" alt="Ken" class="w-16 h-16 rounded-full border-2 border-[#f4b41a] shadow mb-2">
                <h3 class="font-semibold text-[#222] text-sm">Ken Mendoza</h3>
                <p class="text-xs text-gray-600">Front-End Dev</p>
              </div>
              <div class="flex flex-col items-center bg-[#fffaf2] p-4 rounded-xl border border-gray-200 shadow-sm">
                <img src="Assets/Profile Icon/Andrea.jpg" alt="Andrea" class="w-16 h-16 rounded-full border-2 border-[#f4b41a] shadow mb-2">
                <h3 class="font-semibold text-[#222] text-sm">Andrea Calilap</h3>
                <p class="text-xs text-gray-600">Front-End Dev</p>
              </div>
              <div class="flex flex-col items-center bg-[#fffaf2] p-4 rounded-xl border border-gray-200 shadow-sm">
                <img src="Assets/Profile Icon/Angel.jpg" alt="Venice" class="w-16 h-16 rounded-full border-2 border-[#f4b41a] shadow mb-2">
                <h3 class="font-semibold text-[#222] text-sm">Angel Retabale</h3>
                <p class="text-xs text-gray-600">Project Manager</p>
              </div>
              <div class="flex flex-col items-center bg-[#fffaf2] p-4 rounded-xl border border-gray-200 shadow-sm">
                <img src="Assets/Profile Icon/Jara.jpg" alt="Kyle" class="w-16 h-16 rounded-full border-2 border-[#f4b41a] shadow mb-2">
                <h3 class="font-semibold text-[#222] text-sm">Kyle Reyes</h3>
                <p class="text-xs text-gray-600">Back-End Dev</p>
              </div>
              <div class="flex flex-col items-center bg-[#fffaf2] p-4 rounded-xl border border-gray-200 shadow-sm">
                <img src="Assets/Profile Icon/Jovan.jpg" alt="Ralf" class="w-16 h-16 rounded-full border-2 border-[#f4b41a] shadow mb-2">
                <h3 class="font-semibold text-[#222] text-sm">Ralf Sanchez</h3>
                <p class="text-xs text-gray-600">Back-End Dev</p>
              </div>
            </div>
          </div>

          <footer class="mt-8 text-center text-gray-500 text-xs border-t border-gray-200 pt-4">
            © 2025 DiNaDrawing. All rights reserved.
          </footer>
        </div>
      </div>

<div id="planActionModal" class="hidden fixed z-50">
  <div id="planActionInner" class="bg-white border border-gray-200 rounded-lg shadow-lg text-sm w-44 py-2">
    <button id="actionArchiveBtn" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-gray-100 transition owner-only">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a1 1 0 00-1 1v2a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H4zm0 8v4a2 2 0 002 2h8a2 2 0 002-2v-4H4z" /></svg>
      <span>Archive Plan</span>
    </button>
    <button id="actionDownloadBtn" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-gray-100 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor"><path d="M9 3a1 1 0 012 0v8.586l1.293-1.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.414L9 11.586V3z" /><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" /></svg>
      <span>Download PDF</span>
    </button>
    <button id="actionDeleteBtn" class="flex items-center gap-2 w-full text-left px-4 py-2 text-red-600 hover:bg-red-50 transition owner-only">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
      <span>Delete Plan</span>
    </button>
    <button id="actionLeaveBtn" class="flex items-center gap-2 w-full text-left px-4 py-2 text-red-600 hover:bg-red-50 transition member-only hidden">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
      <span>Leave Plan</span>
    </button>
  </div>
</div>

<div id="pastEventModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-[60] flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all scale-100">
    <div class="bg-[#f4b41a] p-4 text-center">
      <h3 class="text-[#222] font-bold text-lg">Event Check-in</h3>
      <p class="text-[#222]/80 text-xs">This plan's date has passed.</p>
    </div>
    <div class="p-6">
      <h4 id="pastEventTitle" class="text-xl font-bold text-gray-800 text-center mb-6">Untitled Plan</h4>
      <div id="pastStep1">
        <p class="text-gray-600 text-sm text-center mb-6">Did this event happen?</p>
        <div class="flex gap-3">
          <button onclick="handlePastAnswer('no')" class="flex-1 py-2.5 border border-gray-300 text-gray-600 rounded-xl font-medium hover:bg-gray-50 transition">No</button>
          <button onclick="handlePastAnswer('yes')" class="flex-1 py-2.5 bg-[#222] text-white rounded-xl font-medium hover:bg-black transition">Yes</button>
        </div>
      </div>
      <div id="pastStep2" class="hidden text-center">
        <div class="mb-4"><span class="text-3xl">🎉</span></div>
        <p class="text-gray-600 text-sm mb-2">Great! Hope it went well.</p>
        <p class="text-gray-500 text-xs mb-6">Would you like to move this to your <b>Archived Plans</b> to keep your dashboard clean?</p>
        <div class="flex gap-3">
          <button onclick="closePastModal()" class="flex-1 py-2.5 border border-gray-300 text-gray-600 rounded-xl font-medium hover:bg-gray-50">Keep Here</button>
          <button onclick="confirmArchivePast()" class="flex-1 py-2.5 bg-[#f4b41a] text-[#222] rounded-xl font-bold hover:bg-[#e0a419]">Archive It</button>
        </div>
      </div>
      <div id="pastStep3" class="hidden">
        <p class="text-gray-600 text-sm text-center mb-4">Oh no! What would you like to do?</p>
        <div class="space-y-2">
            <button onclick="showRescheduleInput()" class="w-full text-left px-4 py-3 rounded-xl border border-gray-200 hover:border-[#f4b41a] hover:bg-[#fffaf2] transition flex items-center justify-between group">
                <span class="text-sm font-medium text-gray-700 group-hover:text-[#222]">Reschedule</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 group-hover:text-[#f4b41a]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            </button>
            <button onclick="confirmArchivePast()" class="w-full text-left px-4 py-3 rounded-xl border border-gray-200 hover:border-gray-400 hover:bg-gray-50 transition flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">Archive it anyway</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
            </button>
            <button onclick="closePastModal()" class="w-full text-center py-2 text-xs text-gray-400 hover:text-gray-600 mt-2">Just keep it in active plans</button>
        </div>
        <div id="rescheduleContainer" class="hidden mt-4 bg-gray-50 p-3 rounded-xl border border-gray-200">
            <label class="block text-xs font-bold text-gray-500 mb-1">Pick a new date:</label>
            <input type="date" id="rescheduleDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2">
            <button onclick="confirmReschedule()" class="w-full bg-[#222] text-white text-sm font-bold py-2 rounded-lg hover:bg-black">Save New Date</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="archiveListPanel" class="hidden fixed z-50">
  <div class="bg-white shadow-lg rounded-2xl border border-gray-200 w-80 p-3">
    <div class="flex items-center justify-between pb-2 border-b border-gray-100 mb-2">
      <h4 class="font-semibold">Archived Plans</h4>
      <button id="closeArchivePanel" class="text-sm text-gray-500 hover:text-gray-700">Close</button>
    </div>
    <ul id="archiveList" class="space-y-2 max-h-48 overflow-y-auto text-sm text-gray-700">
      <li class="text-gray-500">No archived plans</li>
    </ul>
  </div>
</div>

<div id="trashListPanel" class="hidden fixed z-50">
  <div class="bg-white shadow-lg rounded-2xl border border-gray-200 w-96 p-4">
    <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
      <h4 class="font-bold text-red-600 flex items-center gap-2">Deleted Plans</h4>
      <button id="closeTrashPanel" class="text-gray-400 hover:text-gray-700">✕</button>
    </div>
    <div class="bg-red-50 text-red-600 text-[11px] p-2 rounded mb-3 border border-red-100 leading-tight">Items in the trash will be permanently deleted after 30 days.</div>
    <ul id="trashList" class="space-y-2 max-h-60 overflow-y-auto pr-1">
      <?php if (!empty($trashItems)): ?>
        <?php foreach ($trashItems as $trash): ?>
          <li class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-100">
             <div class="flex flex-col overflow-hidden mr-2">
                <span class="truncate font-semibold text-sm text-gray-800"><?php echo h($trash['name']); ?></span>
                <span class="text-[10px] text-gray-400">Deleted: <?php echo date('M d', strtotime($trash['deleted_at'])); ?></span>
             </div>
             <div class="flex items-center gap-1 shrink-0">
                <button onclick="restorePlan(<?php echo $trash['id']; ?>)" title="Restore" class="p-1.5 rounded-full text-green-600 hover:bg-green-100"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg></button>
                <button onclick="hardDeletePlan(<?php echo $trash['id']; ?>)" title="Delete Permanently" class="p-1.5 rounded-full text-red-600 hover:bg-red-100"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
             </div>
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <li class="text-gray-400 italic text-center py-6 text-sm">Trash is empty.</li>
      <?php endif; ?>
    </ul>
  </div>
</div>

<div id="actionConfirmModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden z-[70] flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-sm p-6 text-center">
    
    <h3 id="actionConfirmTitle" class="text-lg font-bold text-gray-900 mb-2">Confirm Action</h3>
    <p id="actionConfirmMessage" class="text-sm text-gray-500 mb-6">Are you sure?</p>

    <div id="actionConfirmInputWrap" class="mb-4 text-left bg-gray-50 p-3 rounded-lg hidden">
      <label class="flex items-start gap-2 cursor-pointer">
          <input id="actionConfirmCheckbox" type="checkbox" class="w-4 h-4 mt-0.5" />
          <span class="text-xs text-gray-600">Permanently delete (cannot be undone)</span>
      </label>
    </div>

    <div class="flex gap-3 justify-center">
      <button id="actionConfirmCancel" class="flex-1 px-4 py-2 bg-white border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-medium">Cancel</button>
      <button id="actionConfirmOk" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700">Confirm</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
  // ==========================================
  // 1. GLOBAL VARIABLES & SETUP
  // ==========================================
  const API_KEY = 'AIzaSyAGsgQDC6nVu9GQ9CYHQ2TTkbcX6qiF3Qc'; 
  const currentUser = <?php echo json_encode($userData); ?>;
  window.__planActionContext = {}; 

  document.addEventListener('DOMContentLoaded', function() {
    // 1. Theme Check
    if (localStorage.getItem('theme') === 'dark') {
      document.documentElement.classList.add('dark-mode');
      document.body.classList.add('dark-mode');
    }

    // 2. Set UI User Data
    if (currentUser) {
      const ids = ['userDisplayName', 'navProfileName', 'dropdownProfileName'];
      ids.forEach(id => { const el = document.getElementById(id); if(el) el.textContent = currentUser.name || currentUser.username; });
      const navImg = document.getElementById('navProfileImg');
      const ddImg  = document.getElementById('dropdownProfileImg');
      if (navImg && currentUser.photo) navImg.src = currentUser.photo;
      if (ddImg && currentUser.photo) ddImg.src  = currentUser.photo;
    }

    // 3. SIDEBAR LOGIC (Preserves State)
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');
    const pageHeader = document.querySelector('.page-header');

    // Check Saved State
    const sidebarState = localStorage.getItem('sidebarOpen');
    const isMobile = window.innerWidth < 769;

    if (sidebarState === null) {
      // Default: Open on Desktop
      if (!isMobile) {
        sidebar.classList.add('active');
        mainContent.classList.add('sidebar-open');
        pageHeader.classList.add('sidebar-open');
      }
    } else if (sidebarState === 'true') {
      sidebar.classList.add('active');
      if (!isMobile) {
        mainContent.classList.add('sidebar-open');
        pageHeader.classList.add('sidebar-open');
      }
    }

    // Toggle Listener
    if (hamburgerBtn && sidebar) {
      hamburgerBtn.addEventListener('click', () => {
        const isActive = sidebar.classList.toggle('active');
        if (window.innerWidth >= 769) {
            mainContent?.classList.toggle('sidebar-open');
            pageHeader?.classList.toggle('sidebar-open');
        } else {
            sidebarOverlay?.classList.toggle('active');
        }
        localStorage.setItem('sidebarOpen', isActive);
      });
      sidebarOverlay?.addEventListener('click', () => {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        localStorage.setItem('sidebarOpen', 'false');
      });
    }
    
    if (typeof checkPastEvents === 'function') checkPastEvents();
  });

  // ==========================================
  // 2. HEADER MENU & VIEW TOGGLES
  // ==========================================
  const galleryViewBtn = document.getElementById("galleryViewBtn");
  const listViewBtn = document.getElementById("listViewBtn");
  if (galleryViewBtn && listViewBtn) {
      galleryViewBtn.addEventListener("click", () => {
        document.getElementById("galleryView").classList.remove("hidden");
        document.getElementById("listView").classList.add("hidden");
        galleryViewBtn.classList.add("bg-[#f4b41a]", "text-[#222]");
        listViewBtn.classList.remove("bg-[#f4b41a]", "text-[#222]");
      });
      listViewBtn.addEventListener("click", () => {
        document.getElementById("galleryView").classList.add("hidden");
        document.getElementById("listView").classList.remove("hidden");
        listViewBtn.classList.add("bg-[#f4b41a]", "text-[#222]");
        galleryViewBtn.classList.remove("bg-[#f4b41a]", "text-[#222]");
      });
  }

  const headerMoreBtn = document.getElementById('headerMoreBtn');
  const headerMoreMenu = document.getElementById('headerMoreMenu');
  const trashPanel = document.getElementById('trashListPanel');
  const btnMenuTrash = document.getElementById('headerMenuTrash');

  headerMoreBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    trashPanel?.classList.add('hidden');
    headerMoreMenu?.classList.toggle('hidden');
  });

  btnMenuTrash?.addEventListener('click', (e) => {
    e.stopPropagation();
    headerMoreMenu.classList.add('hidden');
    const rect = headerMoreBtn.getBoundingClientRect();
    trashPanel.style.top = (rect.bottom + 10) + 'px';
    trashPanel.style.right = '40px'; 
    trashPanel.classList.remove('hidden');
  });

  document.getElementById('closeTrashPanel')?.addEventListener('click', () => trashPanel.classList.add('hidden'));

  // ==========================================
  // 3. DROPDOWNS & MODALS
  // ==========================================
  const notificationBtn = document.getElementById("notificationBtn");
  const notificationPanel = document.getElementById("notificationPanel");
  const profileBtn = document.getElementById('profileBtn');
  const profileDropdown = document.getElementById('profileDropdown');

  notificationBtn?.addEventListener("click", (e) => {
    e.stopPropagation();
    profileDropdown?.classList.add('hidden');
    notificationPanel?.classList.toggle("hidden");
  });

  profileBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    notificationPanel?.classList.add('hidden');
    profileDropdown?.classList.toggle('hidden');
  });

  // Logout & About Us logic
  const logoutProfile = document.getElementById('logoutProfile');
  const logoutModal = document.getElementById('logoutModal');
  if(logoutProfile) logoutProfile.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); logoutModal.classList.remove('hidden'); profileDropdown?.classList.add('hidden'); });
  document.getElementById('cancelLogoutBtn')?.addEventListener('click', () => logoutModal.classList.add('hidden'));
  document.getElementById('confirmLogoutBtn')?.addEventListener('click', () => { localStorage.removeItem('currentUser'); window.location.href = '/DINADRAWING/Backend/auth/logout.php'; });

  const aboutUsBtn = document.getElementById('aboutUsBtn');
  const aboutUsModal = document.getElementById('aboutUsModal');
  if(aboutUsBtn) aboutUsBtn.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); aboutUsModal.classList.remove('hidden'); profileDropdown?.classList.add('hidden'); });
  document.getElementById('closeAboutUsModal')?.addEventListener('click', () => aboutUsModal.classList.add('hidden'));

  document.addEventListener('click', (e) => {
    if (headerMoreMenu && !headerMoreMenu.contains(e.target) && e.target !== headerMoreBtn) headerMoreMenu.classList.add('hidden');
    if (profileDropdown && !profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) profileDropdown.classList.add('hidden');
    if (notificationPanel && !notificationPanel.contains(e.target) && !notificationBtn.contains(e.target)) notificationPanel.classList.add('hidden');
    if (document.getElementById('planActionModal') && !document.getElementById('planActionModal').contains(e.target)) document.getElementById('planActionModal').classList.add('hidden');
  });

  // ==========================================
  // 4. PLAN ACTIONS (Smart Title, PDF, Modals)
  // ==========================================
  
  // Show Action Menu
  window.showPlanActions = function(e, button) {
    e.stopPropagation();
    document.getElementById('headerMoreMenu')?.classList.add('hidden');
    
    const modal = document.getElementById('planActionModal');
    modal.classList.add('hidden'); 

    const card = button.closest('[data-id]');
    if (!card) return;

    const id = card.getAttribute('data-id');
    const isOwner = card.getAttribute('data-owner') === '1';
    
    // SMART TITLE GRAB (Avoids "CODE: ...")
    let titleEl = card.querySelector('h3'); 
    if (!titleEl) titleEl = card.querySelector('span.font-semibold');
    const title = titleEl ? titleEl.textContent.trim() : 'Plan';

    // Data for PDF
    const locEl = card.querySelector('p.text-sm'); 
    const location = locEl ? locEl.innerText.trim() : 'No location';
    const date = card.getAttribute('data-date') || 'No date';

    window.__planActionContext = { id, title, location, date };

    // Toggle Buttons
    const ownerBtns = document.querySelectorAll('.owner-only');
    const memberBtns = document.querySelectorAll('.member-only');
    if (isOwner) {
        ownerBtns.forEach(el => el.classList.remove('hidden'));
        memberBtns.forEach(el => el.classList.add('hidden'));
    } else {
        ownerBtns.forEach(el => el.classList.add('hidden'));
        memberBtns.forEach(el => el.classList.remove('hidden'));
    }

    const rect = button.getBoundingClientRect();
    modal.style.top = (rect.bottom + 5) + 'px';
    modal.style.left = (rect.left - 130) + 'px'; 
    modal.classList.remove('hidden');
  };

  // --- DELETE PLAN (Opens Centered Modal) ---
  document.getElementById('actionDeleteBtn')?.addEventListener('click', () => {
      document.getElementById('planActionModal').classList.add('hidden');
      
      const modal = document.getElementById('actionConfirmModal');
      const title = document.getElementById('actionConfirmTitle');
      const msg = document.getElementById('actionConfirmMessage');
      const chk = document.getElementById('actionConfirmInputWrap');
      const okBtn = document.getElementById('actionConfirmOk');
      
      if(title) title.textContent = "Delete Plan";
      msg.textContent = `Move "${window.__planActionContext.title}" to Trash?`;
      if(chk) chk.classList.add('hidden'); // Hide checkbox for simple trash move
      
      okBtn.textContent = "Delete";
      okBtn.disabled = false;
      okBtn.onclick = executeSoftDelete;

      modal.classList.remove('hidden');
  });

  // --- LEAVE PLAN (Opens Centered Modal) ---
  document.getElementById('actionLeaveBtn')?.addEventListener('click', () => {
      document.getElementById('planActionModal').classList.add('hidden');

      const modal = document.getElementById('actionConfirmModal');
      const title = document.getElementById('actionConfirmTitle');
      const msg = document.getElementById('actionConfirmMessage');
      const chk = document.getElementById('actionConfirmInputWrap');
      const okBtn = document.getElementById('actionConfirmOk');

      if(title) title.textContent = "Leave Plan";
      msg.textContent = `Are you sure you want to leave "${window.__planActionContext.title}"?`;
      if(chk) chk.classList.add('hidden'); 
      
      okBtn.textContent = "Leave"; 
      okBtn.disabled = false;
      okBtn.onclick = executeLeave;

      modal.classList.remove('hidden');
  });

  // --- ARCHIVE PLAN ---
  document.getElementById('actionArchiveBtn')?.addEventListener('click', async () => {
      const { id, title } = window.__planActionContext;
      if(!confirm(`Archive "${title}"?`)) return;
      try {
          const res = await fetch('/DINADRAWING/Backend/events/archive.php', {
              method: 'POST', headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({ id: id })
          });
          if((await res.json()).success) location.reload();
      } catch(e) { alert("Network Error"); }
  });

  // --- DOWNLOAD PDF ---
  document.getElementById('actionDownloadBtn')?.addEventListener('click', () => {
      document.getElementById('planActionModal').classList.add('hidden');
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();
      const { title, location, date } = window.__planActionContext;

      // Header
      doc.setFillColor(244, 180, 26); doc.rect(0, 0, 210, 25, 'F');
      doc.setTextColor(34, 34, 34); doc.setFontSize(18); doc.setFont("helvetica", "bold");
      doc.text("DiNaDrawing Plan Summary", 10, 17);

      // Details
      doc.setTextColor(0, 0, 0); doc.setFontSize(22); doc.text(title, 10, 45);
      doc.setFontSize(12); doc.setFont("helvetica", "bold"); doc.text("Date:", 10, 60);
      doc.setFont("helvetica", "normal"); doc.text(date, 35, 60);
      doc.setFont("helvetica", "bold"); doc.text("Location:", 10, 70);
      doc.setFont("helvetica", "normal"); doc.text(location, 35, 70);

      // Footer
      doc.setDrawColor(200); doc.line(10, 280, 200, 280);
      doc.setFontSize(10); doc.setTextColor(150); doc.text("Generated by DiNaDrawing", 10, 287);

      doc.save(`${title.replace(/[^a-z0-9]/gi, '_')}.pdf`);
  });

  // Close Confirm Modal
  document.getElementById('actionConfirmCancel')?.addEventListener('click', () => {
      document.getElementById('actionConfirmModal').classList.add('hidden');
  });

  // Helper: Execute Delete
  async function executeSoftDelete() {
      const btn = document.getElementById('actionConfirmOk');
      btn.disabled = true; btn.textContent = "Deleting...";
      try {
          const res = await fetch('/DINADRAWING/Backend/events/delete.php', {
              method: 'POST', headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({ id: window.__planActionContext.id })
          });
          const data = await res.json();
          if (data.success) location.reload();
          else { alert(data.error || "Failed"); btn.disabled = false; btn.textContent = "Delete"; }
      } catch(e) { alert("Network Error"); btn.disabled = false; btn.textContent = "Delete"; }
  }

  // Helper: Execute Leave
  async function executeLeave() {
      const btn = document.getElementById('actionConfirmOk');
      btn.disabled = true; btn.textContent = "Leaving...";
      try {
          const res = await fetch('/DINADRAWING/Backend/events/leave.php', {
              method: 'POST', headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({ id: window.__planActionContext.id })
          });
          const data = await res.json();
          if (data.success) location.reload();
          else { alert(data.error || "Failed"); btn.disabled = false; btn.textContent = "Leave"; }
      } catch(e) { alert("Network Error"); btn.disabled = false; btn.textContent = "Leave"; }
  }

  // Helper: Copy Code
  window.copyToClipboard = function(text, el) {
      if(event) { event.stopPropagation(); event.preventDefault(); }
      navigator.clipboard.writeText(text).then(() => {
          const original = el.innerHTML;
          el.innerHTML = '<span class="text-green-400 font-bold text-[10px]">COPIED!</span>';
          setTimeout(() => { el.innerHTML = original; }, 1500);
      });
  };

  // --- TRASH RESTORE/DELETE ---
  window.restorePlan = async function(id) {
    if(!confirm("Restore plan?")) return;
    try {
        const res = await fetch('/DINADRAWING/Backend/events/restore.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id: id })
        });
        if((await res.json()).success) location.reload();
    } catch(e) { console.error(e); }
  };

  window.hardDeletePlan = async function(id) {
    if(!confirm("⚠️ Permanently delete?")) return;
    try {
        const res = await fetch('/DINADRAWING/Backend/events/hard_delete.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id: id })
        });
        if((await res.json()).success) location.reload();
    } catch(e) { console.error(e); }
  };

  // ==========================================
  // 5. CREATE & JOIN & MAPS
  // ==========================================
  function toggleModal(id, show) {
      const el = document.getElementById(id);
      if(show) { el.classList.remove('hidden'); document.body.classList.add('modal-open'); }
      else { el.classList.add('hidden'); document.body.classList.remove('modal-open'); }
  }

  document.getElementById("openCreateEvent")?.addEventListener("click", () => toggleModal('createEventModal', true));
  document.getElementById("closeCreateEvent")?.addEventListener("click", () => toggleModal('createEventModal', false));
  document.getElementById("cancelCreateEvent")?.addEventListener("click", () => toggleModal('createEventModal', false));

  document.getElementById("openJoinEvent")?.addEventListener("click", () => {
      document.getElementById("joinEventModal").querySelector('input').value = ''; 
      toggleModal('joinEventModal', true);
  });
  document.getElementById("closeJoinEvent")?.addEventListener("click", () => toggleModal('joinEventModal', false));
  document.getElementById("cancelJoinEvent")?.addEventListener("click", () => toggleModal('joinEventModal', false));

  // Save Event
  document.getElementById("saveCreateEvent")?.addEventListener("click", async () => {
    const name = document.getElementById("eventName").value.trim();
    if (!name) { alert("Event Name required"); return; }
    try {
      const res = await fetch('/DINADRAWING/Backend/events/create.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: name,
          description: document.getElementById("eventDescription").value.trim(),
          date: document.getElementById("eventDate").value,
          time: document.getElementById("eventTime").value,
          location: document.getElementById("eventLocation").value.trim()
        })
      });
      const result = await res.json();
      if (result.success) window.location.href = `plan.php?id=${result.id}`;
      else alert("Failed");
    } catch (e) { alert("Network Error"); }
  });

  // Join Event
  document.getElementById("joinJoinEvent")?.addEventListener("click", async () => {
      const code = document.querySelector("#joinEventModal input").value.trim();
      if (!code) return alert("Enter code");
      try {
          const res = await fetch('/DINADRAWING/Backend/events/join_event.php', {
              method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ code: code })
          });
          const data = await res.json();
          if (data.success) window.location.href = `plan.php?id=${data.event_id}`;
          else alert("Failed");
      } catch (e) { alert("Network Error"); }
  });

  // Maps
  let map;
  function initMapOnce() {
    const mapEl = document.getElementById("map");
    if (!mapEl) return;
    map = new google.maps.Map(mapEl, { center: { lat: 14.5995, lng: 120.9842 }, zoom: 12 });
    const marker = new google.maps.Marker({ map, position: { lat: 14.5995, lng: 120.9842 }, draggable: true });
    
    // Autocomplete
    const input = document.getElementById("eventLocation");
    if(input && google.maps.places) {
        const ac = new google.maps.places.Autocomplete(input);
        ac.bindTo("bounds", map);
        ac.addListener("place_changed", () => {
            const place = ac.getPlace();
            if(place.geometry) {
                map.setCenter(place.geometry.location);
                map.setZoom(15);
                marker.setPosition(place.geometry.location);
            }
        });
    }
    marker.addListener("dragend", () => {
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: marker.getPosition() }, (res, status) => {
            if (status === "OK" && res[0]) document.getElementById("eventLocation").value = res[0].formatted_address;
        });
    });
  }
  document.getElementById("openCreateEvent")?.addEventListener("click", () => {
    if (!window.google) {
        const s = document.createElement('script');
        s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(API_KEY)}&libraries=places`;
        s.async = true; s.defer = true;
        s.onload = () => { initMapOnce(); };
        document.head.appendChild(s);
    } else if(!map) { initMapOnce(); }
  });

  // ==========================================
  // 6. PAST EVENT CHECKER
  // ==========================================
  let currentPastEventId = null;

  function checkPastEvents() {
      const today = new Date().toISOString().split('T')[0]; 
      const cards = document.querySelectorAll('#galleryView [data-owner="1"]'); 

      for (const card of cards) {
          const date = card.getAttribute('data-date');
          if (date && date < today) {
              currentPastEventId = card.getAttribute('data-id');
              const title = card.querySelector('h3').innerText;
              triggerPastEventModal(title);
              break; 
          }
      }
  }

  function triggerPastEventModal(title) {
      document.getElementById('pastEventTitle').innerText = title;
      document.getElementById('pastEventModal').classList.remove('hidden');
      document.getElementById('pastStep1').classList.remove('hidden');
      document.getElementById('pastStep2').classList.add('hidden');
      document.getElementById('pastStep3').classList.add('hidden');
      document.getElementById('rescheduleContainer').classList.add('hidden');
  }

  window.handlePastAnswer = function(answer) {
      document.getElementById('pastStep1').classList.add('hidden');
      if (answer === 'yes') {
          document.getElementById('pastStep2').classList.remove('hidden');
      } else {
          document.getElementById('pastStep3').classList.remove('hidden');
      }
  };

  window.closePastModal = function() {
      document.getElementById('pastEventModal').classList.add('hidden');
  };

  window.confirmArchivePast = async function() {
      if(!currentPastEventId) return;
      try {
          const res = await fetch('/DINADRAWING/Backend/events/archive.php', {
              method: 'POST', headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({ id: currentPastEventId })
          });
          if((await res.json()).success) location.reload();
      } catch(e) { alert("Network Error"); }
  };

  window.showRescheduleInput = function() {
      document.getElementById('rescheduleContainer').classList.remove('hidden');
  };

  window.confirmReschedule = async function() {
      const date = document.getElementById('rescheduleDate').value;
      if(!date) return alert("Please select a date");
      try {
          const res = await fetch('/DINADRAWING/Backend/events/reschedule.php', {
              method: 'POST', headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({ id: currentPastEventId, date: date })
          });
          if((await res.json()).success) location.reload();
      } catch(e) { alert("Network Error"); }
  };
</script>
</body>
</html>