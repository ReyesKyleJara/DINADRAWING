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
  <title>My Plans | DiNaDrawing</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <!-- Early theme application (dark mode persistence) -->
  <script>
    (function() {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark-mode');
        document.body.classList.add('dark-mode');
      }
      window.addEventListener('storage', (e) => {
        if (e.key === 'theme') {
          if (e.newValue === 'dark') {
            document.documentElement.classList.add('dark-mode');
            document.body.classList.add('dark-mode');
          } else {
            document.documentElement.classList.remove('dark-mode');
            document.body.classList.remove('dark-mode');
          }
        }
      });
    })();
  </script>
  <style>
    body{font-family:'Poppins',sans-serif;background-color:#fffaf2;}
    /* Dark mode palette (frontend only) */
    body.dark-mode { background-color: #1a1a1a !important; color: #e0e0e0 !important; }
    body.dark-mode .bg-white { background-color: #2a2a2a !important; }
    body.dark-mode .bg-\[\#fffaf2\] { background-color: #1a1a1a !important; }
    body.dark-mode .bg-gray-50 { background-color: #2a2a2a !important; }
    body.dark-mode .bg-gray-300 { background-color: #404040 !important; }
    body.dark-mode .text-gray-600, body.dark-mode .text-gray-700, body.dark-mode .text-gray-500 { color: #a0a0a0 !important; }
    body.dark-mode .border-gray-200 { border-color: #404040 !important; }
    body.dark-mode .border-gray-100 { border-color: #353535 !important; }
    body.dark-mode .border-gray-300 { border-color: #454545 !important; }

    /* Hamburger + sidebar animation */
    .hamburger { display: flex; flex-direction: column; gap: 4px; cursor: pointer; padding: 8px; border-radius: 8px; transition: background 0.2s; }
    .hamburger:hover { background: rgba(244,180,26,0.1); }
    .hamburger span { width: 24px; height: 3px; background: #222; border-radius: 2px; transition: all .3s; }
    body.dark-mode .hamburger span { background: #e0e0e0; }

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

    /* Blur page under modal */
    body.modal-open #sidebar,
    body.modal-open main,
    body.modal-open .page-header {
      filter: blur(2px) brightness(0.92);
      pointer-events: none;
    }
  </style>
</head>
<body class="flex bg-[#fffaf2]">

<!-- SIDEBAR OVERLAY -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- SIDEBAR -->
<aside id="sidebar"
class="fixed top-4 left-0 h-[calc(100vh-1rem)] w-64
       bg-[#f4b41a] rounded-tr-3xl
       p-6 shadow
       flex flex-col gap-6">

  <!-- HEADER -->
  <div class="flex items-center gap-2">
    <img src="Assets/dinadrawing-logo.png" alt="Logo" class="w-14">
    <h2 class="text-xl font-bold text-[#222]">DiNaDrawing</h2>
  </div>

  <!-- NAVIGATIONS -->
  <nav>
    <ul class="space-y-5">
      <li><a href="dashboard.html" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Home</a></li>
      <li><a href="myplans.php" class="block px-4 py-2 rounded-lg font-medium bg-[#222] text-white hover:bg-[#111] transition">My Plans</a></li>
      <li><a href="help.html" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Help</a></li>
      <li><a href="settings.html" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Settings</a></li>
    </ul>
  </nav>
</aside>

<!-- MAIN CONTENT -->
<main id="mainContent" class="flex-1 min-h-screen px-12 py-10 pt-28">
  <!-- PAGE HEADER -->
  <div class="page-header flex justify-between items-center border-b-2 border-gray-200 pb-4 mb-6 fixed top-0 left-0 w-full bg-[#fffaf2] z-40 px-12 py-10">
    <div class="flex items-center gap-4">
      <button id="hamburgerBtn" class="hamburger"><span></span><span></span><span></span></button>
      <div class="flex flex-col">
        <h1 class="text-3xl font-bold">My Plans</h1>
        <span class="text-gray-600 text-sm">Manage, view, and edit your plans easily.</span>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <!-- CREATE EVENT -->
      <button id="openCreateEvent" class="border border-[#222] bg-white px-4 py-2 rounded-2xl font-medium hover:bg-[#222] hover:text-white transition">
        <span class="hidden md:inline">+ Create Event</span>
        <span class="md:hidden">Create</span>
      </button>

      <!-- JOIN EVENT -->
      <button id="openJoinEvent" class="border border-[#222] bg-white px-4 py-2 rounded-2xl font-medium hover:bg-[#222] hover:text-white transition">
        <span class="hidden md:inline">Join Event</span>
        <span class="md:hidden">Join</span>
      </button>

      <!-- RIGHT: notifications + profile -->
      <div class="flex items-center gap-4 relative">
        <!-- NOTIFICATIONS -->
        <div>
          <button id="notificationBtn" class="relative w-9 h-9 flex items-center justify-center rounded-full bg-white border border-[#222] hover:bg-[#222] hover:text-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3c0 .386-.146.735-.395 1.002L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span id="notificationDot" class="absolute top-1 right-1 w-2.5 h-2.5 bg-[#f4b41a] rounded-full"></span>
          </button>
        </div>

        <!-- PROFILE -->
        <div class="relative">
          <button id="profileBtn" class="flex items-center gap-2 bg-[#f4b41a]/30 border border-[#222] rounded-full px-3 py-1.5 hover:bg-[#f4b41a]/50 transition">
            <img id="navProfileImg" src="Assets/Profile Icon/profile.png" alt="Profile" class="w-8 h-8 rounded-full border-2 border-[#f4b41a]">
            <span id="navProfileName" class="font-medium text-[#222] hidden md:inline">User</span>
          </button>

          <!-- PROFILE DROPDOWN -->
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

        <!-- NOTIFICATION DROPDOWN -->
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
            <li class="flex items-start gap-3 bg-white hover:bg-gray-50 p-2 rounded-lg cursor-pointer">
              <img src="Assets/Profile Icon/profile4.png" alt="User" class="w-10 h-10 rounded-full border border-gray-200">
              <p class="text-sm"><strong>Ken</strong> voted for <strong>Oct 16</strong> as the final date for <strong>Birthday Trip.</strong></p>
            </li>
            <li class="flex items-start gap-3 bg-white hover:bg-gray-50 p-2 rounded-lg cursor-pointer">
              <img src="Assets/Profile Icon/profile3.png" alt="User" class="w-10 h-10 rounded-full border border-gray-200">
              <p class="text-sm"><strong>Andrea</strong> added 'Japan' to Poll in <strong>Birthday Trip.</strong></p>
            </li>
          </ul>
          <div class="border-t border-gray-200 p-3 text-center">
            <a href="#" id="clearNotificationsBottom" class="text-sm text-[#2563eb] hover:underline">Clear All Notifications</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- VIEW TOGGLE -->
  <div class="flex justify-end items-center mt-28 mb-6">
    <div class="flex gap-2">
      <button id="galleryViewBtn" class="bg-[#f4b41a] text-[#222] text-sm px-4 py-1.5 rounded-lg font-medium">Gallery</button>
      <button id="listViewBtn" class="text-gray-600 hover:text-[#222] text-sm px-4 py-1.5 rounded-lg font-medium">List</button>
    </div>
  </div>

  <!-- YOUR UPCOMING PLANS -->
  <section class="mb-10">
    <h2 class="text-lg font-semibold mb-4">Your Upcoming Plans &gt;</h2>

    <!-- GALLERY VIEW -->
    <div id="galleryView" class="flex flex-wrap gap-6">
      <?php if (!empty($events)): ?>
        <?php foreach ($events as $ev): ?>
          <div class="relative w-64 bg-white border border-gray-300 rounded-2xl overflow-hidden shadow group">
            <div class="font-bold p-4 text-lg flex justify-between items-start" style="<?php echo card_banner_style($ev); ?>">
              <div>
                <?php echo h($ev['name'] ?? 'Untitled'); ?>
                <div class="text-sm font-normal text-[#222]/80"><?php echo h($ev['loc'] ?? '-'); ?></div>
              </div>
              <div class="relative">
                <button onclick="toggleMenu(this)" class="text-[#222] hover:text-gray-700 px-2 py-1 rounded-lg hover:bg-[#f5c94a]/30 transition">⋮</button>
                <div class="hidden absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg text-sm z-10">
                  <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 transition" onclick="deletePlan()">Delete Plan</button>
                  <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 transition" onclick="downloadPDF()">Download PDF</button>
                </div>
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

    <!-- LIST VIEW -->
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
                      <button onclick="toggleMenu(this)" class="text-gray-600 hover:text-[#222] px-2 py-1 rounded-lg hover:bg-gray-100 transition">⋮</button>
                      <div class="hidden absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg text-sm z-10">
                        <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 transition" onclick="deletePlan()">Delete Plan</button>
                        <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 transition" onclick="downloadPDF()">Download PDF</button>
                      </div>
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

  <!-- YOUR ONGOING PLANS -->
  <section>
    <h2 class="text-lg font-semibold mb-4">Your Ongoing Plans &gt;</h2>

    <!-- GALLERY VIEW -->
    <div id="galleryView2" class="flex flex-wrap gap-6">
      <div class="relative w-64 bg-white border border-gray-300 rounded-2xl overflow-hidden shadow group">
        <div class="bg-blue-500 text-[#222] font-bold p-4 text-lg flex justify-between items-start">
          <div>
            Dagat naaa!
            <div class="text-sm font-normal text-[#222]/80">-</div>
          </div>
          <div class="relative">
            <button onclick="toggleMenu(this)" class="text-[#222] hover:text-gray-700 px-2 py-1 rounded-lg hover:bg-blue-400/30 transition">⋮</button>
            <div class="hidden absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg text-sm z-10">
              <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 transition" onclick="deletePlan()">Delete Plan</button>
              <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 transition" onclick="downloadPDF()">Download PDF</button>
            </div>
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

    <!-- LIST VIEW -->
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
                    <button onclick="toggleMenu(this)" class="text-gray-600 hover:text-[#222] px-2 py-1 rounded-lg hover:bg-gray-100 transition">⋮</button>
                    <div class="hidden absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg text-sm z-10">
                      <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 transition" onclick="deletePlan()">Delete Plan</button>
                      <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 transition" onclick="downloadPDF()">Download PDF</button>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>

<!-- LOGOUT CONFIRM MODAL -->
<div id="logoutModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden z-[70] p-4 flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-md p-6"
       role="dialog" aria-modal="true" aria-labelledby="logoutTitle" onclick="event.stopPropagation()">
    <h2 id="logoutTitle" class="text-xl font-bold text-[#222] mb-2">Log out?</h2>
    <p class="text-sm text-gray-600 mb-6">Are you sure you want to log out?</p>
    <div class="flex justify-end gap-3">
      <button id="cancelLogoutBtn" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">
        Continue planning
      </button>
      <button id="confirmLogoutBtn" class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition">
        Yes
      </button>
    </div>
  </div>
</div>

<!-- SCRIPTS -->
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

  // VIEW TOGGLE
  const galleryViewBtn = document.getElementById("galleryViewBtn");
  const listViewBtn = document.getElementById("listViewBtn");
  const galleryView = document.getElementById("galleryView");
  const listView = document.getElementById("listView");
  const galleryView2 = document.getElementById("galleryView2");
  const listView2 = document.getElementById("listView2");

  galleryViewBtn.addEventListener("click", () => {
    galleryView.classList.remove("hidden"); listView.classList.add("hidden");
    galleryView2.classList.remove("hidden"); listView2.classList.add("hidden");
    galleryViewBtn.classList.add("bg-[#f4b41a]","text-[#222]");
    galleryViewBtn.classList.remove("text-gray-600");
    listViewBtn.classList.remove("bg-[#f4b41a]","text-[#222]");
    listViewBtn.classList.add("text-gray-600");
  });

  listViewBtn.addEventListener("click", () => {
    galleryView.classList.add("hidden"); listView.classList.remove("hidden");
    galleryView2.classList.add("hidden"); listView2.classList.remove("hidden");
    listViewBtn.classList.add("bg-[#f4b41a]","text-[#222]");
    listViewBtn.classList.remove("text-gray-600");
    galleryViewBtn.classList.remove("bg-[#f4b41a]","text-[#222]");
    galleryViewBtn.classList.add("text-gray-600");
  });

  function toggleMenu(button) {
    const menu = button.nextElementSibling;
    document.querySelectorAll('.group .absolute.mt-2, table .absolute.mt-2').forEach(m => {
      if (m !== menu) m.classList.add('hidden');
    });
    menu.classList.toggle('hidden');
  }

  function deletePlan() {
    alert("Plan deleted successfully!");
  }

  async function downloadPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFillColor(244, 180, 26); doc.rect(0, 0, 210, 25, 'F');
    doc.setFontSize(18); doc.text("DiNaDrawing Plan Receipt", 20, 17);
    doc.setFontSize(14); doc.text("Plan Title: Dagat naaa!", 20, 45);
    doc.text("Location: Batangas", 20, 55);
    doc.text("Date: October 16, 2025", 20, 65);
    doc.text("Status: Ongoing", 20, 75);
    doc.setFontSize(10); doc.setTextColor(100);
    doc.text("Thank you for using DiNaDrawing!", 20, 95);
    const pdfBlob = doc.output('blob'); const pdfURL = URL.createObjectURL(pdfBlob);
    window.open(pdfURL, '_blank');
  }

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
  const closeAboutUsModal = document.getElementById('closeAboutUsModal');
  if (closeAboutUsModal) {
    closeAboutUsModal.addEventListener('click', () => {
      document.getElementById('aboutUsModal').classList.add('hidden');
      document.body.classList.remove('modal-open');
    });
  }
  const aboutUsModal = document.getElementById('aboutUsModal');
  if (aboutUsModal) {
    aboutUsModal.addEventListener('click', (e) => {
      if (e.target === aboutUsModal) {
        aboutUsModal.classList.add('hidden');
        document.body.classList.remove('modal-open');
      }
    });
  }

  // Header quick-action menu (three dots) – optional panels could be added similarly
</script>

<!-- Google Maps integration (frontend only) -->
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

<!-- Backend/auth: preserved -->
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
