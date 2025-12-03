<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

$pdo = new PDO(
  "pgsql:host=127.0.0.1;port=5432;dbname=dinadrawing",
  "kai",
  "DND2025",
  [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]
);

$showArchived = !empty($_GET['archived']);
$where = $showArchived ? "archived IS TRUE" : "archived IS NOT TRUE";

$stmt = $pdo->query("
  SELECT
    id,
    name,
    description,
    CASE
      WHEN date IS NOT NULL AND time IS NOT NULL THEN date::text || ' ' || time::text
      WHEN date IS NOT NULL THEN date::text
      WHEN time IS NOT NULL THEN time::text
      ELSE NULL
    END AS dt,
    location AS loc,
    banner_type, banner_color, banner_from, banner_to, banner_image,
    archived
  FROM events
  WHERE $where
  ORDER BY date NULLS LAST, id DESC
");
$events = $stmt->fetchAll();

function h($s){ return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8'); }
function formatMonth($dt){ $ts=$dt?strtotime($dt):false; return $ts?date('M',$ts):'-'; }
function formatDay($dt){ $ts=$dt?strtotime($dt):false; return $ts?date('d',$ts):'-'; }
function formatDate($dt){ $ts=$dt?strtotime($dt):false; return $ts?date('M d, Y',$ts):'-'; }

function card_banner_style($ev){
  $t = $ev['banner_type'] ?? null;
  if ($t === 'image' && !empty($ev['banner_image'])){
    $rel = ltrim($ev['banner_image'],'/');
    return "background-image:url('/DINADRAWING/".h($rel)."');background-size:cover;background-position:center;color:#fff;";
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Plans | DiNaDrawing</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  
  <script>
    (function() {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark-mode');
      }
    })();
  </script>

  <style>
    body { font-family: 'Poppins', sans-serif; background-color: #fffaf2; color: #222; }

    /* --- DARK MODE STYLES (From Dashboard) --- */
    body.dark-mode { background-color: #1a1a1a !important; color: #e0e0e0 !important; }
    body.dark-mode .bg-white { background-color: #2a2a2a !important; }
    body.dark-mode .bg-\[\#fffaf2\] { background-color: #1a1a1a !important; }
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode h4,
    body.dark-mode p, body.dark-mode span, body.dark-mode div, body.dark-mode li { color: #e0e0e0 !important; }
    
    /* Text Colors */
    body.dark-mode .text-gray-600, body.dark-mode .text-gray-700, body.dark-mode .text-gray-500 { color: #a0a0a0 !important; }
    body.dark-mode .text-\[\#222\] { color: #e0e0e0 !important; }
    
    /* Borders & Shadows */
    body.dark-mode .border-gray-200 { border-color: #404040 !important; }
    body.dark-mode .border-gray-100 { border-color: #353535 !important; }
    body.dark-mode .border-gray-300 { border-color: #454545 !important; }
    body.dark-mode .shadow, body.dark-mode .shadow-md, body.dark-mode .shadow-lg { box-shadow: 0 4px 6px rgba(0,0,0,0.5) !important; }
    
    /* Inputs & Forms */
    body.dark-mode input, body.dark-mode textarea, body.dark-mode select {
      background-color: #2a2a2a !important; color: #e0e0e0 !important; border-color: #454545 !important;
    }
    body.dark-mode input::placeholder, body.dark-mode textarea::placeholder { color: #707070 !important; }
    body.dark-mode label { color: #e0e0e0 !important; }

    /* Specific to My Plans Page (Tables & Dropdowns) */
    body.dark-mode .bg-gray-50 { background-color: #2a2a2a !important; } /* Table Header */
    body.dark-mode tr.hover\:bg-gray-50:hover { background-color: #333 !important; } /* Table Row Hover */
    body.dark-mode .divide-gray-200 > :not([hidden]) ~ :not([hidden]) { border-color: #404040 !important; }
    
    /* Hamburger Menu */
    .hamburger { display: flex; flex-direction: column; gap: 4px; cursor: pointer; padding: 8px; border-radius: 8px; transition: background 0.2s; }
    .hamburger:hover { background: rgba(244,180,26,0.1); }
    .hamburger span { width: 24px; height: 3px; background: #222; border-radius: 2px; transition: all .3s; }
    body.dark-mode .hamburger span { background: #e0e0e0; }

    /* Layout/Sidebar Transitions */
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 45; }
    .sidebar-overlay.active { display: block; }
    #sidebar { transition: transform .3s ease; z-index: 50; transform: translateX(-100%); }
    #sidebar.active { transform: translateX(0); }
    @media (min-width: 769px) {
      #sidebar { transform: translateX(0); }
      #sidebar:not(.active) { transform: translateX(-100%); }
    }
    main { transition: margin-left .3s ease; }
    @media (min-width: 769px) {
      main.sidebar-open { margin-left: 16rem; }
      main:not(.sidebar-open) { margin-left: 0; }
    }
    .page-header { transition: left .3s ease, width .3s ease; }
    @media (min-width: 769px) {
      .page-header.sidebar-open { left: 16rem; width: calc(100% - 16rem); }
      .page-header:not(.sidebar-open) { left: 0; width: 100%; }
    }
    body.modal-open #sidebar, body.modal-open main, body.modal-open .page-header {
      filter: blur(2px) brightness(0.92); pointer-events: none;
    }
  </style>
</head>
<body class="flex bg-[#fffaf2]">

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside id="sidebar"
class="fixed top-4 left-0 h-[calc(100vh-1rem)] w-64
       bg-[#f4b41a] rounded-tr-3xl
       p-6 shadow
       flex flex-col gap-6">

  <div class="flex items-center gap-2">
    <img src="Assets/dinadrawing-logo.png" alt="Logo" class="w-14">
    <h2 class="text-xl font-bold text-[#222]">DiNaDrawing</h2>
  </div>

  <nav>
    <ul class="space-y-5">
      <li><a href="dashboard.html" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Home</a></li>
      <li><a href="myplans.php" class="block px-4 py-2 rounded-lg font-medium bg-[#222] text-white hover:bg-[#111] transition">My Plans</a></li>
      <li><a href="help.html" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Help</a></li>
      <li><a href="settings.html" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Settings</a></li>
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
        <span class="hidden md:inline">+ Create Event</span>
        <span class="md:hidden">Create</span>
      </button>

      <button id="openJoinEvent" class="border border-[#222] bg-white px-4 py-2 rounded-2xl font-medium hover:bg-[#222] hover:text-white transition">
        <span class="hidden md:inline">Join Event</span>
        <span class="md:hidden">Join</span>
      </button>

      <div class="flex items-center gap-4 relative">
        <div>
          <button id="notificationBtn" class="relative w-9 h-9 flex items-center justify-center rounded-full bg-white border border-[#222] hover:bg-[#222] hover:text-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3c0 .386-.146.735-.395 1.002L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span id="notificationDot" class="absolute top-1 right-1 w-2.5 h-2.5 bg-[#f4b41a] rounded-full"></span>
          </button>
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
              <a href="help.html" class="block px-4 py-2 text-sm hover:bg-gray-50">Help</a>
              <button id="aboutUsBtn" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50">About Us</button>
              <a href="settings.html" class="block px-4 py-2 text-sm hover:bg-gray-50">Settings</a>
              <button id="logoutProfile" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Log out</button>
            </div>
          </div>
        </div>

        <div id="notificationPanel"
             class="absolute top-full right-0 mt-2 w-[90vw] md:w-60 lg:w-80 max-w-[28rem]
                    bg-white shadow-lg rounded-2xl border border-gray-200 hidden z-50">
          <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h4 class="font-semibold">Notifications</h4>
            <a href="#" id="clearNotificationsTop" class="text-sm text-[#2563eb] hover:underline">Mark all as read</a>
          </div>
          <ul id="notificationList" class="p-4 space-y-3 max-h-64 overflow-y-auto">
            <li class="flex items-start gap-3 bg-white hover:bg-gray-50 p-2 rounded-lg cursor-pointer">
              <img src="Assets/Profile Icon/profile2.png" alt="User" class="w-10 h-10 rounded-full border border-gray-200">
              <p class="text-sm"><strong>Kyle</strong> added 'Rice' to Assigned Tasks in <strong>Family Reunion.</strong></p>
            </li>
            </ul>
          <div class="border-t border-gray-200 p-3 text-center">
            <a href="#" id="clearNotificationsBottom" class="text-sm text-[#2563eb] hover:underline">Clear All Notifications</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-2xl shadow border border-gray-200 p-6 mt-6">
    
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-lg font-semibold">Your Upcoming Plans &gt;</h2>
      </div>
      <div class="flex gap-2">
        <button id="galleryViewBtn" title="Gallery view" class="p-2 rounded-md bg-[#f4b41a] text-[#222] hover:bg-[#e0a419] transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
              <path d="M3 3h4v4H3V3zM3 9h4v4H3V9zM9 3h4v4H9V3zM9 9h4v4H9V9zM15 3h2v2h-2V3zM15 7h2v2h-2V7zM15 11h2v2h-2v-2z" />
            </svg>
        </button>

        <button id="listViewBtn" title="List view" class="p-2 rounded-md text-gray-600 hover:bg-gray-100 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z" clip-rule="evenodd" />
            </svg>
        </button>

        <div class="relative">
            <button id="headerMoreBtn" title="More actions" aria-label="More actions" class="p-2 rounded-md text-gray-600 hover:bg-gray-100 transition">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </button>

            <div id="headerMoreMenu" class="hidden fixed right-0 mt-2 w-44 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
              <button id="headerMenuArchive" class="w-full flex items-center gap-2 px-4 py-2 hover:bg-gray-50 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-700" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a1 1 0 00-1 1v2a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H4zm0 8v4a2 2 0 002 2h8a2 2 0 002-2v-4H4z"/></svg>
                <span>Archived Plans</span>
              </button>
              <button id="headerMenuTrash" class="w-full flex items-center gap-2 px-4 py-2 hover:bg-gray-50 text-sm text-red-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H3a1 1 0 100 2h1v9a2 2 0 002 2h6a2 2 0 002-2V6h1a1 1 0 100-2h-2V3a1 1 0 00-1-1H6zm2 4a1 1 0 011 1v7a1 1 0 11-2 0V7a1 1 0 011-1zm4 0a1 1 0 011 1v7a1 1 0 11-2 0V7a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                <span>Deleted Plans</span>
              </button>
            </div>
        </div>
      </div>
    </div>

    <section class="mb-10">
      
      <div id="galleryView" class="flex flex-wrap gap-6">
        <?php if (!empty($events)): ?>
          <?php foreach ($events as $ev): ?>
            <div class="relative w-64 bg-white border border-gray-300 rounded-2xl overflow-hidden shadow group">
              <div class="font-bold p-4 text-lg flex justify-between items-start" style="<?php echo card_banner_style($ev); ?>">
                <div>
                  <span class="font-medium"><?php echo h($ev['name'] ?? 'Untitled'); ?></span>
                  <div class="text-sm font-normal text-[#222]/80"><?php echo h($ev['loc'] ?? '-'); ?></div>
                </div>
                <div class="relative">
                  <button onclick="showPlanActions(event,this)" class="text-[#222] hover:text-gray-700 px-2 py-1 rounded-lg hover:bg-[#f5c94a]/30 transition">⋮</button>
                </div>
              </div>
              <div class="flex items-center justify-between p-4">
                <div class="text-center">
                  <div class="text-xs text-gray-700"><?php echo formatMonth($ev['dt']); ?></div>
                  <div class="text-2xl font-bold text-[#222]"><?php echo formatDay($ev['dt']); ?></div>
                </div>
                <button class="bg-[#222] text-white text-sm px-4 py-1.5 rounded-lg hover:bg-[#444] transition"
                        onclick="window.location.href='plan.php?id=<?php echo (int)$ev['id']; ?>'">View</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-gray-600">No plans yet. Create one!</div>
        <?php endif; ?>
      </div>

      <div id="listView" class="hidden">
        <div class="bg-white rounded-2xl shadow border border-gray-200 overflow-hidden">
          <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="text-left px-6 py-3 text-sm font-medium text-gray-700">Plan Name</th>
                <th class="text-left px-6 py-3 text-sm font-medium text-gray-700">Location</th>
                <th class="text-left px-6 py-3 text-sm font-medium text-gray-700">Date</th>
                <th class="text-left px-6 py-3 text-sm font-medium text-gray-700"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (!empty($events)): ?>
              <?php foreach ($events as $ev): ?>
                <tr class="hover:bg-gray-50 transition">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 bg-[#f4b41a] rounded-lg flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#222]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                      </div>
                      <span class="font-medium text-[#222]"><?php echo h($ev['name'] ?? 'Untitled'); ?></span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-gray-600"><?php echo h($ev['loc'] ?? '-'); ?></td>
                  <td class="px-6 py-4 text-gray-600"><?php echo formatDate($ev['dt']); ?></td>
                  <td class="px-6 py-4">
                    <div class="flex gap-2">
                      <button class="bg-[#222] text-white text-sm px-4 py-1.5 rounded-lg hover:bg-[#444] transition"
                              onclick="window.location.href='plan.php?id=<?php echo (int)$ev['id']; ?>'">View</button>
                      <div class="relative">
                        <button onclick="showPlanActions(event,this)" class="text-gray-600 hover:text-[#222] px-2 py-1 rounded-lg hover:bg-gray-100 transition">⋮</button>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="4" class="px-6 py-4 text-gray-600">No plans yet.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section>
      <h2 class="text-lg font-semibold mb-4">Your Ongoing Plans &gt;</h2>
      <div id="galleryView2" class="flex flex-wrap gap-6">
        <div class="relative w-64 bg-white border border-gray-300 rounded-2xl overflow-hidden shadow group">
          <div class="bg-blue-500 text-[#222] font-bold p-4 text-lg flex justify-between items-start">
            <div>
              <span class="font-medium">Dagat naaa!</span>
              <div class="text-sm font-normal text-[#222]/80">-</div>
            </div>
            <div class="relative">
              <button onclick="showPlanActions(event,this)" class="text-[#222] hover:text-gray-700 px-2 py-1 rounded-lg hover:bg-blue-400/30 transition">⋮</button>
            </div>
          </div>
          <div class="flex items-center justify-between p-4">
            <div class="text-center">
              <div class="text-xs text-gray-700">-</div>
              <div class="text-2xl font-bold text-[#222]">-</div>
            </div>
            <button onclick="window.location.href='plan.php?id=<?php echo !empty($events) ? (int)$events[0]['id'] : 0; ?>'"
              class="bg-[#222] text-white text-sm px-4 py-1.5 rounded-lg hover:bg-[#444] transition">View</button>
          </div>
        </div>
      </div>
      <div id="listView2" class="hidden">
        <div class="bg-white rounded-2xl shadow border border-gray-200 overflow-hidden">
          <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="text-left px-6 py-3 text-sm font-medium text-gray-700">Plan Name</th>
                <th class="text-left px-6 py-3 text-sm font-medium text-gray-700">Location</th>
                <th class="text-left px-6 py-3 text-sm font-medium text-gray-700">Date</th>
                <th class="text-left px-6 py-3 text-sm font-medium text-gray-700"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                    </div>
                    <span class="font-medium text-[#222]">Dagat naaa!</span>
                  </div>
                </td>
                <td class="px-6 py-4 text-gray-600">-</td>
                <td class="px-6 py-4 text-gray-600">-</td>
                <td class="px-6 py-4">
                  <div class="flex gap-2">
                    <button onclick="window.location.href='plan.php?id=<?php echo !empty($events) ? (int)$events[0]['id'] : 0; ?>'" class="bg-[#222] text-white text-sm px-4 py-1.5 rounded-lg hover:bg-[#444] transition">View</button>
                    <div class="relative">
                      <button onclick="showPlanActions(event,this)" class="text-gray-600 hover:text-[#222] px-2 py-1 rounded-lg hover:bg-gray-100 transition">⋮</button>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</main>

<div id="createEventModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
  <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-md max-h-[90vh] overflow-y-auto p-6">
    <button id="closeCreateEvent" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
    <h2 class="text-2xl font-bold mb-1">Create Event</h2>
    <p class="text-sm text-gray-500 mb-4">Fill in the details to start planning your event.</p>
    <label class="block text-sm font-medium mb-1">Event Name <span class="text-gray-400 text-xs">(required)</span> </label>
    <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4" placeholder="Enter event name">
    <label class="block text-sm font-medium mb-1">Description </label>
    <textarea id="eventDescription" maxlength="200" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-1 resize-none h-20" placeholder="Write a short description..."></textarea>
    <div class="text-right text-xs text-gray-500 mb-4"><span id="charCount">0</span>/200</div>
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div><label class="block text-sm font-medium mb-1">Date</label><input type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
      <div><label class="block text-sm font-medium mb-1">Time</label><input type="time" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
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
  <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-md p-6"
       role="dialog" aria-modal="true" aria-labelledby="logoutTitle" onclick="event.stopPropagation()">
    <h2 id="logoutTitle" class="text-xl font-bold text-[#222] mb-2">Log out?</h2>
    <p class="text-sm text-gray-600 mb-6">Are you sure you want to log out?</p>
    <div class="flex justify-end gap-3">
      <button id="cancelLogoutBtn" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Continue planning</button>
      <button id="confirmLogoutBtn" class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition">Yes</button>
    </div>
  </div>
</div>

<div id="planActionModal" class="hidden fixed z-50">
  <div id="planActionInner" class="bg-white border border-gray-200 rounded-lg shadow-lg text-sm w-44 py-2">
    <button id="actionArchiveBtn" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-gray-100 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
        <path d="M4 3a1 1 0 00-1 1v2a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H4zm0 8v4a2 2 0 002 2h8a2 2 0 002-2v-4H4z" />
      </svg>
      <span>Archive Plan</span>
    </button>
    <button id="actionDownloadBtn" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-gray-100 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path d="M9 3a1 1 0 012 0v8.586l1.293-1.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.414L9 11.586V3z" />
        <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" />
      </svg>
      <span>Download PDF</span>
    </button>
    <button id="actionDeleteBtn" class="flex items-center gap-2 w-full text-left px-4 py-2 text-red-600 hover:bg-red-50 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H3a1 1 0 100 2h1v9a2 2 0 002 2h6a2 2 0 002-2V6h1a1 1 0 100-2h-2V3a1 1 0 00-1-1H6zm2 4a1 1 0 011 1v7a1 1 0 11-2 0V7a1 1 0 011-1zm4 0a1 1 0 011 1v7a1 1 0 11-2 0V7a1 1 0 011-1z" clip-rule="evenodd" />
      </svg>
      <span>Delete Plan</span>
    </button>
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
  <div class="bg-white shadow-lg rounded-2xl border border-gray-200 w-80 p-3">
    <div class="flex items-center justify-between pb-2 border-b border-gray-100 mb-2">
      <h4 class="font-semibold">Deleted Plans</h4>
      <button id="closeTrashPanel" class="text-sm text-gray-500 hover:text-gray-700">Close</button>
    </div>
    <ul id="trashList" class="space-y-2 max-h-48 overflow-y-auto text-sm text-gray-700">
      <li class="text-gray-500">No deleted plans</li>
    </ul>
  </div>
</div>

<div id="actionConfirmModal" class="hidden fixed z-50">
  <div class="bg-white border border-gray-200 rounded-lg shadow-lg text-sm w-64 p-4">
    <p id="actionConfirmMessage" class="text-sm text-gray-700 mb-3">Are you sure?</p>

    <div id="actionConfirmInputWrap" class="mb-3 hidden">
      <label class="flex items-start gap-2">
          <input id="actionConfirmCheckbox" type="checkbox" class="w-4 h-4 mt-0.5" />
          <span id="actionConfirmCheckboxLabel" class="text-sm text-gray-600 leading-4">I understand deleting this plan is permanent</span>
      </label>
    </div>

    <div class="flex justify-end gap-2">
      <button id="actionConfirmCancel" class="px-3 py-1 rounded-lg border border-gray-300 hover:bg-gray-100">Cancel</button>
      <button id="actionConfirmOk" disabled class="px-3 py-1 rounded-lg bg-[#f4b41a] hover:bg-[#e0a419] disabled:opacity-60">Confirm</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
  // Sidebar / layout toggle (frontend-only; persists in localStorage)
  document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');
    const pageHeader = document.querySelector('.page-header');

    const sidebarState = localStorage.getItem('sidebarOpen');
    const isMobile = window.innerWidth < 769;

    if (sidebarState === null) {
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

    function applySidebarLayout() {
      if (window.innerWidth >= 769) {
        if (sidebar.classList.contains('active')) {
          mainContent.classList.add('sidebar-open');
          pageHeader.classList.add('sidebar-open');
        } else {
          mainContent.classList.remove('sidebar-open');
          pageHeader.classList.remove('sidebar-open');
        }
      } else {
        mainContent.classList.remove('sidebar-open');
        pageHeader.classList.remove('sidebar-open');
      }
    }

    if (hamburgerBtn && sidebar && sidebarOverlay) {
      hamburgerBtn.addEventListener('click', () => {
        const isActive = sidebar.classList.toggle('active');
        if (window.innerWidth >= 769) {
          mainContent.classList.toggle('sidebar-open');
          pageHeader.classList.toggle('sidebar-open');
        } else {
          sidebarOverlay.classList.toggle('active');
        }
        localStorage.setItem('sidebarOpen', isActive);
      });

      sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        localStorage.setItem('sidebarOpen', 'false');
      });

      const navLinks = sidebar.querySelectorAll('nav a');
      navLinks.forEach(link => {
        link.addEventListener('click', () => {
          if (window.innerWidth < 769) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            localStorage.setItem('sidebarOpen', 'false');
          }
        });
      });

      window.addEventListener('resize', () => {
        const isNowMobile = window.innerWidth < 769;
        if (!isNowMobile) {
          sidebarOverlay.classList.remove('active');
        }
        applySidebarLayout();
      });
    }

    applySidebarLayout();
  });

  // UNIFIED VIEW TOGGLE (CONTROLS BOTH SECTIONS)
  const galleryViewBtn = document.getElementById("galleryViewBtn");
  const listViewBtn = document.getElementById("listViewBtn");
  const galleryView = document.getElementById("galleryView");
  const listView = document.getElementById("listView");
  const galleryView2 = document.getElementById("galleryView2");
  const listView2 = document.getElementById("listView2");

  galleryViewBtn.addEventListener("click", () => {
    // Show gallery view for both sections
    galleryView.classList.remove("hidden");
    listView.classList.add("hidden");
    galleryView2.classList.remove("hidden");
    listView2.classList.add("hidden");
    
    // Update button styles
    galleryViewBtn.classList.add("bg-[#f4b41a]", "text-[#222]");
    galleryViewBtn.classList.remove("text-gray-600");
    listViewBtn.classList.remove("bg-[#f4b41a]", "text-[#222]");
    listViewBtn.classList.add("text-gray-600");
  });

  listViewBtn.addEventListener("click", () => {
    // Show list view for both sections
    galleryView.classList.add("hidden");
    listView.classList.remove("hidden");
    galleryView2.classList.add("hidden");
    listView2.classList.remove("hidden");
    
    // Update button styles
    listViewBtn.classList.add("bg-[#f4b41a]", "text-[#222]");
    listViewBtn.classList.remove("text-gray-600");
    galleryViewBtn.classList.remove("bg-[#f4b41a]", "text-[#222]");
    galleryViewBtn.classList.add("text-gray-600");
  });

  // Header quick-action (three dots) and menu
  const headerMoreBtn = document.getElementById('headerMoreBtn');
  const headerMoreMenu = document.getElementById('headerMoreMenu');
  const headerMenuArchive = document.getElementById('headerMenuArchive');
  const headerMenuTrash = document.getElementById('headerMenuTrash');
  const archivePanel = document.getElementById('archiveListPanel');
  const trashPanel = document.getElementById('trashListPanel');
  const closeArchive = document.getElementById('closeArchivePanel');
  const closeTrash = document.getElementById('closeTrashPanel');

  function openPanelNearButton(panel, btn) {
    if (!panel || !btn) return;
    // deactivate the header more button first
    if (headerMoreBtn) {
      headerMoreBtn.classList.remove('bg-[#f4b41a]', 'text-[#222]');
      const s = headerMoreBtn.querySelector('svg');
      if (s) {
        s.classList.remove('text-[#222]', 'text-red-600');
        s.classList.add('text-gray-600');
      }
    }

    // hide the other panel
    if (panel === archivePanel) trashPanel?.classList.add('hidden');
    if (panel === trashPanel) archivePanel?.classList.add('hidden');

    // Temporarily make visible to measure size
    panel.style.visibility = 'hidden';
    panel.classList.remove('hidden');
    const mRect = panel.getBoundingClientRect();
    const btnRect = btn.getBoundingClientRect();

    // Prefer below the button, clamp to viewport
    const gap = 8;
    let left = btnRect.left + window.scrollX;
    if (left + mRect.width + 8 > window.innerWidth + window.scrollX) {
      left = Math.max(8 + window.scrollX, window.innerWidth + window.scrollX - mRect.width - 8);
    }
    let top = btnRect.bottom + window.scrollY + gap;
    if (top + mRect.height + 8 > window.scrollY + window.innerHeight) {
      top = Math.max(8 + window.scrollY, btnRect.top + window.scrollY - mRect.height - gap);
    }

    panel.style.left = left + 'px';
    panel.style.top = top + 'px';
    panel.style.visibility = 'visible';

    // Mark the clicked header button active (yellow)
    btn.classList.add('bg-[#f4b41a]', 'text-[#222]');
    const svg = btn.querySelector('svg');
    if (svg) {
      svg.classList.remove('text-gray-600', 'text-red-600');
      svg.classList.add('text-[#222]');
    }
  }

  // header more button toggles the small menu
  headerMoreBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    // close panels
    archivePanel?.classList.add('hidden');
    trashPanel?.classList.add('hidden');
    // toggle menu
    if (!headerMoreMenu) return;
    if (headerMoreMenu.classList.contains('hidden')) {
      // position below the button
      headerMoreMenu.classList.remove('hidden');
      headerMoreMenu.style.visibility = 'hidden';
      const mRect = headerMoreMenu.getBoundingClientRect();
      const btnRect = headerMoreBtn.getBoundingClientRect();
      let left = btnRect.right - mRect.width + window.scrollX;
      left = Math.max(8 + window.scrollX, Math.min(left, window.innerWidth + window.scrollX - mRect.width - 8));
      let top = btnRect.bottom + window.scrollY + 6;
      headerMoreMenu.style.left = left + 'px';
      headerMoreMenu.style.top = top + 'px';
      headerMoreMenu.style.visibility = 'visible';
      // mark active
      headerMoreBtn.classList.add('bg-[#f4b41a]', 'text-[#222]');
      headerMoreBtn.querySelector('svg')?.classList.remove('text-gray-600');
      headerMoreBtn.querySelector('svg')?.classList.add('text-[#222]');
    } else {
      headerMoreMenu.classList.add('hidden');
      headerMoreBtn.classList.remove('bg-[#f4b41a]','text-[#222]');
      headerMoreBtn.querySelector('svg')?.classList.remove('text-[#222]');
      headerMoreBtn.querySelector('svg')?.classList.add('text-gray-600');
    }
  });

  // menu items open the panels anchored to the three-dots button
  headerMenuArchive?.addEventListener('click', (e) => {
    e.stopPropagation();
    headerMoreMenu?.classList.add('hidden');
    openPanelNearButton(archivePanel, headerMoreBtn);
  });
  headerMenuTrash?.addEventListener('click', (e) => {
    e.stopPropagation();
    headerMoreMenu?.classList.add('hidden');
    openPanelNearButton(trashPanel, headerMoreBtn);
  });

  closeArchive?.addEventListener('click', (e) => { e.stopPropagation(); archivePanel.classList.add('hidden'); headerMoreBtn?.classList.remove('bg-[#f4b41a]','text-[#222]'); headerMoreBtn?.querySelector('svg')?.classList.remove('text-[#222]'); headerMoreBtn?.querySelector('svg')?.classList.add('text-gray-600'); });
  closeTrash?.addEventListener('click', (e) => { e.stopPropagation(); trashPanel.classList.add('hidden'); headerMoreBtn?.classList.remove('bg-[#f4b41a]','text-[#222]'); headerMoreBtn?.querySelector('svg')?.classList.remove('text-[#222]'); headerMoreBtn?.querySelector('svg')?.classList.add('text-gray-600'); });

  // Close panels when clicking outside
  document.addEventListener('click', (e) => {
    // hide header menu if clicked outside
    if (headerMoreMenu && !headerMoreMenu.contains(e.target) && e.target !== headerMoreBtn) {
      headerMoreMenu.classList.add('hidden');
      headerMoreBtn?.classList.remove('bg-[#f4b41a]','text-[#222]');
      headerMoreBtn?.querySelector('svg')?.classList.remove('text-[#222]');
      headerMoreBtn?.querySelector('svg')?.classList.add('text-gray-600');
    }
    if (archivePanel && !archivePanel.contains(e.target) && e.target !== headerMoreBtn) {
      archivePanel.classList.add('hidden');
      headerMoreBtn?.classList.remove('bg-[#f4b41a]','text-[#222]');
      headerMoreBtn?.querySelector('svg')?.classList.remove('text-[#222]');
      headerMoreBtn?.querySelector('svg')?.classList.add('text-gray-600');
    }
    if (trashPanel && !trashPanel.contains(e.target) && e.target !== headerMoreBtn) {
      trashPanel.classList.add('hidden');
      headerMoreBtn?.classList.remove('bg-[#f4b41a]','text-[#222]');
      headerMoreBtn?.querySelector('svg')?.classList.remove('text-[#222]');
      headerMoreBtn?.querySelector('svg')?.classList.add('text-gray-600');
    }
  });

  // Global context for the plan actions modal
  window.__planActionContext = {};

  function showPlanActions(e, button) {
    e.stopPropagation();
    hidePlanActionModal();
    const modal = document.getElementById('planActionModal');
    const card = button.closest('.group') || button.closest('tr') || button.closest('div');
    if (!card) return;

    // Try to infer a title from the card
    let title = 'Plan';
    const titleEl = card.querySelector('span.font-medium, .font-medium, .text-lg, .font-bold');
    if (titleEl) title = titleEl.textContent.trim();

    window.__planActionContext = { card, title };

    // Anchor to the clicked button so the modal appears closer to the card
    const btnRect = button.getBoundingClientRect();

    // Temporarily show the modal invisibly to measure its size
    modal.style.visibility = 'hidden';
    modal.classList.remove('hidden');
    const mRect = modal.getBoundingClientRect();

    // Calculate preferred left: try to place to the right of the button, but clamp to viewport
    const gap = 6;
    let left = btnRect.right + gap + window.scrollX;
    if (left + mRect.width + 8 > window.innerWidth + window.scrollX) {
      // not enough space on the right; place it slightly overlapping the card (closer)
      left = Math.max(8 + window.scrollX, btnRect.left - mRect.width - gap + window.scrollX);
    }

    // Vertically center the modal near the button
    let top = btnRect.top + window.scrollY + (btnRect.height / 2) - (mRect.height / 2);
    // clamp top to viewport
    top = Math.max(8 + window.scrollY, Math.min(top, window.scrollY + window.innerHeight - mRect.height - 8));

    modal.style.left = left + 'px';
    modal.style.top = top + 'px';
    modal.style.visibility = 'visible';
    // modal is already visible (class removed)
  }

  function hidePlanActionModal() {
    document.getElementById('planActionModal').classList.add('hidden');
    document.getElementById('actionConfirmModal').classList.add('hidden');
  }

  function openActionConfirm(message, onConfirm, options = {}) {
    const confirmModal = document.getElementById('actionConfirmModal');
    const msg = document.getElementById('actionConfirmMessage');
    const inputWrap = document.getElementById('actionConfirmInputWrap');
    const checkbox = document.getElementById('actionConfirmCheckbox');
    const checkboxLabel = document.getElementById('actionConfirmCheckboxLabel');
    const okBtn = document.getElementById('actionConfirmOk');
    const cancelBtn = document.getElementById('actionConfirmCancel');

    msg.textContent = message;

    // Position the confirm modal near the action modal
    const actionModal = document.getElementById('planActionModal');
    const rect = actionModal.getBoundingClientRect();
    confirmModal.style.top = (rect.top + window.scrollY + 46) + 'px';
    confirmModal.style.left = (rect.left + window.scrollX) + 'px';

    // Options handling (no typing required)
    // - { type: 'delete' } -> require checking an "I understand" checkbox (strong wording)
    // - { type: 'archive' } -> require checking an "I understand" checkbox
    let requireCheckbox = false;
    if (options.type === 'delete') {
      requireCheckbox = true;
      inputWrap.classList.remove('hidden');
      checkbox.checked = false;
      checkboxLabel.textContent = `I understand deleting this plan is permanent`;
      okBtn.disabled = true;
      checkbox.focus();
    } else if (options.type === 'archive') {
      requireCheckbox = true;
      inputWrap.classList.remove('hidden');
      checkbox.checked = false;
      checkboxLabel.textContent = `I understand this plan will be archived`;
      okBtn.disabled = true;
      checkbox.focus();
    } else {
      inputWrap.classList.add('hidden');
      okBtn.disabled = false;
    }

    confirmModal.classList.remove('hidden');

    function cleanup() {
      okBtn.removeEventListener('click', okHandler);
      cancelBtn.removeEventListener('click', cancelHandler);
      checkbox.removeEventListener('change', checkboxHandler);
      // keep the confirm modal until explicitly closed by handlers
    }

    function okHandler() {
      cleanup();
      onConfirm && onConfirm();
      confirmModal.classList.add('hidden');
      hidePlanActionModal();
    }

    function cancelHandler() {
      cleanup();
      confirmModal.classList.add('hidden');
      // do NOT re-open action modal automatically
    }

    function checkboxHandler() {
      okBtn.disabled = !checkbox.checked;
    }

    okBtn.addEventListener('click', okHandler);
    cancelBtn.addEventListener('click', cancelHandler);
    if (requireCheckbox) checkbox.addEventListener('change', checkboxHandler);
  }

  function archivePlan() {
    const title = window.__planActionContext.title || 'this plan';
    openActionConfirm('Are you sure?', () => {
      // Backend Logic for Archiving should go here
      alert(`Archived "${title}"`);
    }, { type: 'archive' });
  }

  function deletePlan() {
    const title = window.__planActionContext.title || 'this plan';
    openActionConfirm('Are you sure?', () => {
      // Backend Logic for Deleting should go here
      alert(`Deleted "${title}"`);
    }, { type: 'delete' });
  }

  // DOWNLOAD PDF (accepts optional title)
  async function downloadPDF(titleOverride) {
    const title = titleOverride || window.__planActionContext.title || 'Plan';
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: 'a4' });

    const pageWidth = doc.internal.pageSize.getWidth();
    const margin = 40;
    const headerHeight = 72;

    function loadImageDataURL(src) {
      return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
          try {
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            resolve(canvas.toDataURL('image/png'));
          } catch (e) {
            resolve(null);
          }
        };
        img.onerror = function () {
          resolve(null);
        };
        img.src = src;
      });
    }

    // Helper to sanitize text (remove stray ampersands and excessive whitespace)
    function sanitizeText(s) {
      if (!s && s !== '') return '-';
      try {
        return String(s).replace(/&/g, '').replace(/\s+/g, ' ').trim() || '-';
      } catch (e) {
        return '-';
      }
    }

    // Header background
    doc.setFillColor(244, 180, 26);
    doc.rect(0, 0, pageWidth, headerHeight, 'F');

    // Try to load the logo from the Assets folder and place it on the header
    const logoData = await loadImageDataURL('Assets/dinadrawing-logo.png');
    const logoSize = 48;
    if (logoData) {
      doc.addImage(logoData, 'PNG', margin, 12, logoSize, logoSize);
    }

    // Title centered in the header
    doc.setFontSize(20);
    doc.setTextColor(0, 0, 0);
    doc.setFont('helvetica', 'bold');
    const titleX = margin + (logoData ? logoSize + 12 : 0);
    doc.text('DiNaDrawing Plan Receipt', titleX, 42);

    // Divider under header
    doc.setDrawColor(220);
    doc.setLineWidth(0.8);
    doc.line(margin, headerHeight + 8, pageWidth - margin, headerHeight + 8);

    // Body content
    let y = headerHeight + 36;
    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text('Plan Title:', margin, y);
    doc.setFont('helvetica', 'normal');
    doc.text(sanitizeText(title), margin + 90, y);

    y += 22;
    doc.setFont('helvetica', 'bold');
    doc.text('Location:', margin, y);
    doc.setFont('helvetica', 'normal');
    doc.text('-', margin + 90, y);

    y += 20;
    doc.setFont('helvetica', 'bold');
    doc.text('Date:', margin, y);
    doc.setFont('helvetica', 'normal');
    doc.text('-', margin + 90, y);

    y += 20;
    doc.setFont('helvetica', 'bold');
    doc.text('Status:', margin, y);
    doc.setFont('helvetica', 'normal');
    doc.text('-', margin + 90, y);

    y += 36;
    doc.setFontSize(10);
    doc.setTextColor(120);
    doc.text('Thank you for using DiNaDrawing!', margin, y);

    const pdfBlob = doc.output('blob');
    const pdfURL = URL.createObjectURL(pdfBlob);
    window.open(pdfURL, '_blank');
    hidePlanActionModal();
  }

  // Wire up the buttons inside the global action modal
  document.getElementById('actionArchiveBtn').addEventListener('click', () => archivePlan());
  document.getElementById('actionDownloadBtn').addEventListener('click', () => downloadPDF());
  document.getElementById('actionDeleteBtn').addEventListener('click', () => deletePlan());

  // Close action modal when clicking outside, but DO NOT auto-close the confirm modal
  document.addEventListener('click', (e) => {
    const actionModal = document.getElementById('planActionModal');
    if (!actionModal.contains(e.target)) {
      actionModal.classList.add('hidden');
    }
    // Do not close the confirmation modal on outside clicks; require explicit Cancel/Confirm
  });

  // Close dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.relative')) {
      document.querySelectorAll('.group .absolute.mt-2').forEach(m => m.classList.add('hidden'));
    }
  });


  // CREATE EVENT MODAL
  const createEventModal = document.getElementById("createEventModal");
  document.getElementById("openCreateEvent").addEventListener("click", () => {
    document.body.classList.add('modal-open');
    createEventModal.classList.remove("hidden");
  });
  document.getElementById("closeCreateEvent").addEventListener("click", () => {
    createEventModal.classList.add("hidden");
    document.body.classList.remove('modal-open');
  });
  document.getElementById("cancelCreateEvent").addEventListener("click", () => {
    createEventModal.classList.add("hidden");
    document.body.classList.remove('modal-open');
  });
  createEventModal.addEventListener("click", function (e) {
    if (e.target === this) { this.classList.add("hidden"); document.body.classList.remove('modal-open'); }
  });
  // DESCRIPTION CHAR COUNT
  const desc = document.getElementById("eventDescription");
  const charCount = document.getElementById("charCount");
  desc?.addEventListener("input", function () {
    charCount.textContent = desc.value.length;
  });

  // JOIN EVENT MODAL
  const joinEventModal = document.getElementById("joinEventModal");
  document.getElementById("openJoinEvent").addEventListener("click", () => {
    document.body.classList.add('modal-open');
    joinEventModal.classList.remove("hidden");
  });
  document.getElementById("closeJoinEvent").addEventListener("click", () => {
    joinEventModal.classList.add("hidden");
    document.body.classList.remove('modal-open');
  });
  document.getElementById("cancelJoinEvent").addEventListener("click", () => {
    joinEventModal.classList.add("hidden");
    document.body.classList.remove('modal-open');
  });
  document.getElementById("joinJoinEvent").addEventListener("click", () => {
    joinEventModal.classList.add("hidden");
    document.body.classList.remove('modal-open');
  });
  joinEventModal.addEventListener("click", function (e) {
    if (e.target === this) { this.classList.add("hidden"); document.body.classList.remove('modal-open'); }
  });

  // NOTIFICATIONS + PROFILE
  const notificationBtn = document.getElementById("notificationBtn");
  const notificationPanel = document.getElementById("notificationPanel");
  const notificationDot = document.getElementById("notificationDot");
  const clearTop = document.getElementById("clearNotificationsTop");
  const clearBottom = document.getElementById("clearNotificationsBottom");
  const notifList = document.getElementById("notificationList");

  notificationBtn?.addEventListener("click", (e) => {
    e.stopPropagation();
    document.getElementById('profileDropdown')?.classList.add('hidden');
    notificationPanel?.classList.toggle("hidden");
    if (!notificationPanel?.classList.contains("hidden")) {
      notificationDot?.classList.add("hidden");
    }
  });
  function clearNotifications(e){
    e?.preventDefault();
    if (notifList) notifList.innerHTML = '<li class="text-center text-gray-500 text-sm py-4">No notifications</li>';
    notificationDot?.classList.add("hidden");
  }
  clearTop?.addEventListener("click", clearNotifications);
  clearBottom?.addEventListener("click", clearNotifications);

  const profileBtn = document.getElementById('profileBtn');
  const profileDropdown = document.getElementById('profileDropdown');
  profileBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    notificationPanel?.classList.add('hidden');
    profileDropdown?.classList.toggle('hidden');
  });
  document.addEventListener('click', (e) => {
    if (!profileBtn?.contains(e.target) && !profileDropdown?.contains(e.target)) {
      profileDropdown?.classList.add('hidden');
    }
    if (!notificationBtn?.contains(e.target) && !notificationPanel?.contains(e.target)) {
      notificationPanel?.classList.add('hidden');
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      profileDropdown?.classList.add('hidden');
      notificationPanel?.classList.add('hidden');
    }
  });

  // ABOUT US MODAL
  const aboutUsBtn = document.getElementById('aboutUsBtn');
  if (aboutUsBtn) {
    aboutUsBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      document.body.classList.add('modal-open');
      document.getElementById('aboutUsModal').classList.remove('hidden');
      document.getElementById('profileDropdown').classList.add('hidden');
    });
  }
</script>

<script>
  const API_KEY = 'AIzaSyAGsgQDC6nVu9GQ9CYHQ2TTkbcX6qiF3Qc';
  window.gm_authFailure = function() {
    console.error('Google Maps authentication failure (gm_authFailure).');
    alert('Google Maps authentication failed. See console for details.');
  };
  let map, marker, autocomplete;

  function initMapOnce() {
    const defaultLocation = { lat: 14.5995, lng: 120.9842 };
    const mapEl = document.getElementById("map");
    if (!mapEl) return;

    map = new google.maps.Map(mapEl, { center: defaultLocation, zoom: 12 });
    marker = new google.maps.Marker({ map, position: defaultLocation, draggable: true });

    const input = document.getElementById("eventLocation");
    if (input && google.maps.places) {
      autocomplete = new google.maps.places.Autocomplete(input);
      autocomplete.bindTo("bounds", map);
      autocomplete.addListener("place_changed", () => {
        const place = autocomplete.getPlace();
        if (!place.geometry) return;
        map.setCenter(place.geometry.location);
        map.setZoom(15);
        marker.setPosition(place.geometry.location);
      });
    }

    marker.addListener("dragend", () => {
      const geocoder = new google.maps.Geocoder();
      geocoder.geocode({ location: marker.getPosition() }, (results, status) => {
        if (status === "OK" && results[0]) {
          const inputEl = document.getElementById("eventLocation");
          if (inputEl) inputEl.value = results[0].formatted_address;
        }
      });
    });
  }

  function loadGoogleMaps(apiKey) {
    if (window.google && google.maps) return Promise.resolve();
    return new Promise((resolve, reject) => {
      const existing = document.querySelector('script[data-gm-loader]');
      if (existing) existing.remove();

      const s = document.createElement('script');
      s.setAttribute('data-gm-loader', '1');
      s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=places`;
      s.async = true;
      s.defer = true;
      s.onload = () => {
        if (window.google && google.maps) resolve();
        else reject(new Error('Google Maps loaded but google.maps is not available.'));
      };
      s.onerror = (e) => reject(new Error('Failed to load Google Maps script: ' + e.message));
      document.head.appendChild(s);
    });
  }

  const openBtn = document.getElementById("openCreateEvent");
  openBtn?.addEventListener("click", () => {
    if (!API_KEY) {
      alert('Provide a valid Google Maps API key.');
      return;
    }
    loadGoogleMaps(API_KEY)
      .then(() => {
        if (!map) initMapOnce();
        setTimeout(() => {
          if (map) {
            google.maps.event.trigger(map, 'resize');
            map.setCenter(marker?.getPosition() || { lat: 14.5995, lng: 120.9842 });
          }
        }, 250);
      })
      .catch(err => {
        console.error('Maps load error:', err);
        alert('Google Maps failed to load. Check console for details.');
      });
  });
</script>

<script type="module">
import { auth } from './firebase-config.js'; 
import { signOut, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";

document.addEventListener('DOMContentLoaded', function() {
  
  onAuthStateChanged(auth, (user) => {
    if (user) {
      const displayName = user.displayName || user.email.split('@')[0];
      const defaultPhoto = "Assets/Profile Icon/profile.png"; 
      const userPhoto = user.photoURL ? user.photoURL : defaultPhoto;

      const navName = document.getElementById('navProfileName');
      const dropName = document.getElementById('dropdownProfileName');
      const navImg = document.getElementById('navProfileImg');
      const dropImg = document.getElementById('dropdownProfileImg');

      if (navName) navName.textContent = displayName;
      if (dropName) dropName.textContent = displayName;
      if (navImg) navImg.src = userPhoto;
      if (dropImg) dropImg.src = userPhoto;

    } else {
      console.log("No user logged in, redirecting...");
      window.location.href = 'index.html'; 
    }
  });

  const logoutSidebar = document.getElementById('logoutBtn');
  const logoutProfile = document.getElementById('logoutProfile');
  const logoutModal = document.getElementById('logoutModal');
  const confirmLogoutBtn = document.getElementById('confirmLogoutBtn');
  const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');

  function openLogoutModal() {
    if(logoutModal) logoutModal.classList.remove('hidden');
  }

  if (logoutSidebar) logoutSidebar.addEventListener('click', openLogoutModal);
  if (logoutProfile) logoutProfile.addEventListener('click', openLogoutModal);

  if (confirmLogoutBtn) {
    confirmLogoutBtn.addEventListener('click', () => {
        signOut(auth)
        .then(() => { window.location.href = 'index.html'; })
        .catch((error) => { console.error('Logout error:', error); });
    });
  }

  if (cancelLogoutBtn) {
    cancelLogoutBtn.addEventListener('click', () => {
        if(logoutModal) logoutModal.classList.add('hidden');
    });
  }

  if (logoutModal) {
    logoutModal.addEventListener('click', (e) => {
        if (e.target === logoutModal) logoutModal.classList.add('hidden');
    });
  }
});
</script>
</body>
</html>