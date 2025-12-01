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
  <style>body{font-family:'Poppins',sans-serif;background-color:#fffaf2;}</style>
</head>
<body class="flex bg-[#fffaf2]">

<!-- SIDEBAR -->
<aside id="sidebar"
class="fixed top-4 left-0 h-[calc(100vh-1rem)] w-64
       bg-[#f4b41a] rounded-tr-3xl
       p-6 shadow
       hidden md:flex md:flex-col md:gap-6">

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
  <main class="ml-0 md:ml-64 flex-1 min-h-screen px-12 py-10 pt-28">

<!-- PAGE TITLE (STICKY HEADER) -->
<div class="flex justify-between items-center border-b-2 border-gray-200 pb-4 mb-6 fixed top-0 md:left-64 left-0 w-[calc(100%-0rem)] md:w-[calc(100%-16rem)] bg-[#fffaf2] z-40 px-12 py-10">

  <div class="flex flex-col">
    <h1 class="text-3xl font-bold">My Plans</h1>
    <span class="text-gray-600 text-sm">Manage, view, and edit your plans easily. </span>
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

        <!-- TOPBAR (RIGHT) -->
        <div class="flex items-center gap-4 relative">

          <!-- CREATE EVENT MODAL -->
          <div id="createEventModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-2xl p-6 w-[500px] shadow-xl relative">
              <button id="closeCreateEvent" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
              <h2 class="text-2xl font-bold mb-1">Create Event</h2>
              <p class="text-sm text-gray-500 mb-4">Fill in the details to start planning your event.</p>

              <label class="block text-sm font-medium mb-1">Event Name <span class="text-gray-400 text-xs">(required)</span></label>
              <input id="eventName" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4" placeholder="Enter event name">

              <label class="block text-sm font-medium mb-1">Description</label>
              <textarea id="eventDescription" maxlength="200" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-1 resize-none h-20" placeholder="Write a short description..."></textarea>
              <div class="text-right text-xs text-gray-500 mb-4"><span id="charCount">0</span>/200</div>

              <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                  <label class="block text-sm font-medium mb-1">Date</label>
                  <input id="eventDate" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1">Time</label>
                  <input id="eventTime" type="time" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
              </div>

              <label class="block text-sm font-medium mb-1">Location</label>
              <input id="eventLocation" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-6" placeholder="Enter location">

              <p class="text-gray-500 text-xs mb-4">Only Event Name is required. Others can be blank.</p>

              <div class="flex justify-end gap-3">
                <button id="cancelCreateEvent" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Cancel</button>
                <button id="saveCreateEvent" class="bg-[#f4b41a] px-4 py-2 rounded-lg font-medium hover:bg-[#e0a419] transition">Save & Continue</button>
              </div>
            </div>
          </div>

          <!-- JOIN EVENT MODAL -->
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

          <!-- ABOUT US MODAL -->
          <div id="aboutUsModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-2xl max-h-[90vh] overflow-y-auto p-8">
              <button id="closeAboutUsModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
              <h1 class="text-3xl font-bold text-[#222] mb-4">About Us</h1>
              <p class="text-gray-700 mb-6 leading-relaxed">
                <span class="font-semibold text-[#f4b41a]">DiNaDrawing</span> is a collaborative planning platform designed to make organizing events and group activities easy, fun, and efficient. Whether you're preparing a trip, party, or project, DiNaDrawing helps you stay connected, assign tasks, and track your plans in one place.
              </p>
              <div class="grid md:grid-cols-2 gap-6 mt-8">
                <div class="bg-[#fffaf2] p-6 rounded-xl border border-gray-200 shadow-sm">
                  <h2 class="text-xl font-semibold text-[#222] mb-3">Our Mission</h2>
                  <p class="text-gray-700 leading-relaxed text-sm">To simplify teamwork and planning by creating a space where collaboration feels effortless, ensuring every plan—big or small—gets drawn out perfectly.</p>
                </div>
                <div class="bg-[#fffaf2] p-6 rounded-xl border border-gray-200 shadow-sm">
                  <h2 class="text-xl font-semibold text-[#222] mb-3">Our Vision</h2>
                  <p class="text-gray-700 leading-relaxed text-sm">To become the go-to digital planner for creative teams and friend groups around the world, empowering people to turn their ideas into reality.</p>
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
              <footer class="mt-8 text-center text-gray-500 text-xs border-t border-gray-200 pt-4">© 2025 DiNaDrawing. All rights reserved.</footer>
            </div>
          </div>

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
              <img src="Assets/Profile Icon/profile.png" alt="Profile" class="w-8 h-8 rounded-full border-2 border-[#f4b41a]">
              <span class="font-medium text-[#222] hidden md:inline">Venice</span>
            </button>
            <div id="profileDropdown" class="absolute right-0 mt-2 w-60 bg-white shadow-lg rounded-2xl border border-gray-200 hidden z-50">
              <div class="p-4 border-b border-gray-200 text-center bg-[#fffaf2] rounded-t-2xl">
                <img src="Assets/Profile Icon/profile.png" alt="Profile" class="w-12 h-12 mx-auto rounded-full border-2 border-[#f4b41a] mb-2 shadow">
                <h2 class="text-sm font-semibold text-[#222]">Venice</h2>
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
                <img src="Assets/Profile Icon/profile2.png" onerror="this.src='Assets/profile2.png'" alt="User" class="w-10 h-10 rounded-full border border-gray-200">
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

    <!-- VIEW TOGGLE (FOR ALL PLANS) -->
    <div class="flex justify-end items-center mb-6">
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
        <!-- Example static card (replace with ongoing filter later if needed) -->
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

<!-- SCRIPTS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
  // UNIFIED VIEW TOGGLE
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
  document.getElementById("openCreateEvent").addEventListener("click", () => {
    document.getElementById("createEventModal").classList.remove("hidden");
  });
  document.getElementById("closeCreateEvent").addEventListener("click", () => {
    document.getElementById("createEventModal").classList.add("hidden");
  });
  document.getElementById("cancelCreateEvent").addEventListener("click", () => {
    document.getElementById("createEventModal").classList.add("hidden");
  });
  document.getElementById("createEventModal").addEventListener("click", function (e) {
    if (e.target === this) this.classList.add("hidden");
  });
  const desc = document.getElementById("eventDescription");
  const charCount = document.getElementById("charCount");
  desc.addEventListener("input", () => { charCount.textContent = desc.value.length; });

  // JOIN EVENT MODAL
  document.getElementById("openJoinEvent").addEventListener("click", () => {
    document.getElementById("joinEventModal").classList.remove("hidden");
  });
  document.getElementById("closeJoinEvent").addEventListener("click", () => {
    document.getElementById("joinEventModal").classList.add("hidden");
  });
  document.getElementById("cancelJoinEvent").addEventListener("click", () => {
    document.getElementById("joinEventModal").classList.add("hidden");
  });
  document.getElementById("joinJoinEvent").addEventListener("click", () => {
    document.getElementById("joinEventModal").classList.add("hidden");
  });
  document.getElementById("joinEventModal").addEventListener("click", function (e) {
    if (e.target === this) this.classList.add("hidden");
  });

  // NOTIFICATIONS + PROFILE (copied behavior)
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
    if (!notificationPanel?.classList.contains("hidden")) notificationDot?.classList.add("hidden");
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
    e.stopPropagation(); notificationPanel?.classList.add('hidden');
    profileDropdown?.classList.toggle('hidden');
  });
  document.addEventListener('click', (e) => {
    if (!profileBtn?.contains(e.target) && !profileDropdown?.contains(e.target)) profileDropdown?.classList.add('hidden');
    if (!notificationBtn?.contains(e.target) && !notificationPanel?.contains(e.target)) notificationPanel?.classList.add('hidden');
  });
  document.addEventListener('keydown', (e) => {
    if(e.key==='Escape'){
      document.getElementById('profileDropdown')?.classList.add('hidden');
      document.getElementById('notificationPanel')?.classList.add('hidden');
    }
  });
  document.getElementById('logoutProfile')?.addEventListener('click', () => {
    document.getElementById('logoutBtn')?.click();
  });

  // ABOUT US MODAL
  const aboutUsBtn = document.getElementById('aboutUsBtn');
  if (aboutUsBtn) {
    aboutUsBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      document.getElementById('aboutUsModal').classList.remove('hidden');
      document.getElementById('profileDropdown').classList.add('hidden');
    });
  }
  const closeAboutUsModal = document.getElementById('closeAboutUsModal');
  if (closeAboutUsModal) {
    closeAboutUsModal.addEventListener('click', () => {
      document.getElementById('aboutUsModal').classList.add('hidden');
    });
  }
  const aboutUsModal = document.getElementById('aboutUsModal');
  if (aboutUsModal) {
    aboutUsModal.addEventListener('click', (e) => {
      if (e.target === aboutUsModal) {
        aboutUsModal.classList.add('hidden');
      }
    });
  }

  // SAVE & CONTINUE: create event via backend then redirect
  document.getElementById('saveCreateEvent')?.addEventListener('click', async (e) => {
    e.preventDefault();
    const name = document.getElementById('eventName')?.value.trim() || '';
    const description = document.getElementById('eventDescription')?.value.trim() || '';
    const date = document.getElementById('eventDate')?.value || '';
    const time = document.getElementById('eventTime')?.value || '';
    const location = document.getElementById('eventLocation')?.value.trim() || '';
    if (!name) { alert('Event name is required.'); return; }
    const payload = { name, description, date, time, location };
    try {
      const resp = await fetch('/DINADRAWING/Backend/api/event/create.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
      });
      const data = await resp.json();
      if (resp.ok && data.success && data.id) {
        window.location.href = `/DINADRAWING/plan.php?id=${data.id}`;
      } else {
        alert(data.message || 'Failed to create event');
      }
    } catch {
      alert('Network error');
    }
  });

  // ARCHIVE TOGGLE: archive or unarchive plan
  async function archivePlan(id, archived){
    try{
      const r = await fetch('/DINADRAWING/Backend/api/event/archive_toggle.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id, archived })
      });
      const j = await r.json();
      if(!r.ok || !j.success){ alert(j.error||'Failed'); return; }
      location.reload();
    }catch(e){ alert('Network error'); }
  }
</script>

<!-- LOGOUT FUNCTION -->
<script type="module">
  import { auth } from './firebase-config.js'; 
  import { signOut } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";
  document.addEventListener('DOMContentLoaded', function() {
    const logoutSidebar = document.getElementById('logoutBtn');
    const logoutProfile = document.getElementById('logoutProfile');
    function handleLogout() {
      signOut(auth).then(() => { window.location.href = 'index.html'; })
        .catch(() => { alert('Logout failed. Please try again.'); });
    }
    if (logoutSidebar) logoutSidebar.addEventListener('click', handleLogout);
    if (logoutProfile) logoutProfile.addEventListener('click', handleLogout);
  });
</script>

</body>
</html>
