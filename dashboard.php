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

// 3. DATABASE CONNECTION & FETCHING
$upcomingEvents = [];
$pastEvents = [];
$calendarEventsJS = []; 

try {
    // Database Connection
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
    $today = date('Y-m-d');

    // QUERY: Get Active Plans (Not Archived, Not Deleted)
    $sql = "
      SELECT DISTINCT
        e.id, e.date, e.time, e.name, e.description, e.location, e.banner_color
      FROM events e
      LEFT JOIN event_members em ON e.id = em.event_id
      WHERE (e.owner_id = :uid OR em.user_id = :uid)
      AND e.archived IS NOT TRUE  
      AND e.deleted_at IS NULL 
      ORDER BY e.date ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $allEvents = $stmt->fetchAll();

    foreach ($allEvents as $ev) {
        // Prepare Data for Calendar
        $calendarEventsJS[] = [
            'id' => $ev['id'],
            'title' => htmlspecialchars($ev['name']),
            'date' => $ev['date'], 
            'color' => $ev['banner_color'] ?? '#f4b41a',
            'desc' => htmlspecialchars($ev['description'] ?? '')
        ];

        // Sort into Lists (Past vs Upcoming)
        if (!empty($ev['date']) && $ev['date'] < $today) {
            array_unshift($pastEvents, $ev); // Recent
        } else {
            $upcomingEvents[] = $ev; // Upcoming
        }
    }
    
    $pastEvents = array_slice($pastEvents, 0, 5); // Limit recent to 5

} catch (Exception $e) {
    // Silent fail
}

// Helpers
function formatDay($d) { return $d ? date('d', strtotime($d)) : '-'; }
function formatMonth($d) { return $d ? date('M', strtotime($d)) : '-'; }
function formatRecentDate($d) { return $d ? date('M d, Y', strtotime($d)) : 'No Date'; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home | DiNaDrawing</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://apis.google.com/js/api.js"></script>
  
  <style>
    body { font-family: 'Poppins', sans-serif; background-color: #fffaf2; color: #222; }
    
    /* --- GOOGLE CALENDAR SPECIFIC STYLES --- */
    .gcal-container {
        font-family: 'Roboto', sans-serif;
        background: white;
        border: 1px solid #dadce0;
        border-radius: 8px;
        overflow: hidden;
    }
    .gcal-header-row {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        border-bottom: 1px solid #dadce0;
    }
    .gcal-day-name {
        font-size: 11px;
        font-weight: 500;
        color: #70757a;
        text-transform: uppercase;
        text-align: center;
        padding: 8px 0;
    }
    .gcal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background-color: #dadce0; /* Grid lines color */
        gap: 1px;
        border-bottom: 1px solid #dadce0;
    }
    .gcal-cell {
        background-color: white;
        min-height: 110px;
        padding: 8px;
        display: flex;
        flex-direction: column;
        transition: background 0.2s;
    }
    .gcal-cell:hover { background-color: #f1f3f4; }
    .gcal-date-number {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 12px;
        font-weight: 500;
        margin-bottom: 4px;
    }
    .gcal-date-number.today {
        background-color: #1a73e8;
        color: white;
    }
    .gcal-event-chip {
        background-color: #039BE5;
        color: white;
        font-size: 11px;
        padding: 2px 6px;
        border-radius: 4px;
        margin-bottom: 2px;
        font-weight: 500;
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .google-btn {
        background-color: #1a73e8;
        color: white;
        font-weight: 500;
        padding: 6px 16px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
    }
    .google-btn:hover { background-color: #1557b0; }

    /* Keep existing styles */
    body.dark-mode { background-color: #1a1a1a !important; color: #e0e0e0 !important; }
    body.dark-mode .bg-white { background-color: #2a2a2a !important; }
    body.dark-mode .bg-\[\#fffaf2\] { background-color: #1a1a1a !important; }
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3,
    body.dark-mode p, body.dark-mode span, body.dark-mode div { color: #e0e0e0 !important; }
    body.dark-mode .text-gray-600, body.dark-mode .text-gray-700, body.dark-mode .text-gray-500 { color: #a0a0a0 !important; }
    body.dark-mode .border-gray-200 { border-color: #404040 !important; }
    body.dark-mode .border-gray-100 { border-color: #353535 !important; }
    body.dark-mode .shadow, body.dark-mode .shadow-md, body.dark-mode .shadow-lg { box-shadow: 0 4px 6px rgba(0,0,0,0.5) !important; }
    body.dark-mode .border-gray-300 { border-color: #454545 !important; }
    body.dark-mode .text-\[\#222\] { color: #e0e0e0 !important; }
    body.dark-mode input, body.dark-mode textarea, body.dark-mode select {
      background-color: #2a2a2a !important; color: #e0e0e0 !important; border-color: #454545 !important;
    }
    body.dark-mode input::placeholder, body.dark-mode textarea::placeholder { color: #707070 !important; }
    body.dark-mode .custom-calendar { background: #2a2a2a !important; border-color: #f4b41a !important; }
    body.dark-mode .calendar-day { background: #333333 !important; border-color: #f4b41a50 !important; }
    body.dark-mode .calendar-day-number { color: #e0e0e0 !important; }
    body.dark-mode .calendar-day:hover { background: #404040 !important; }
    body.dark-mode #createEventModal .bg-white,
    body.dark-mode #joinEventModal .bg-white,
    body.dark-mode #aboutUsModal .bg-white { background-color: #2a2a2a !important; }
    body.dark-mode #aboutUsModal .bg-\[\#fffaf2\] { background-color: #333333 !important; }
    body.dark-mode .text-gray-400 { color: #909090 !important; }
    body.dark-mode label { color: #e0e0e0 !important; }

    .custom-calendar {
      background: #fffaf2; border: 2px solid #f4b41a; border-radius: 20px; padding: 20px; width: 100%;
    }
    .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; }
    
    .calendar-day {
        min-height: 100px;
        background: white; border-radius: 12px; padding: 6px;
        box-shadow: 0 3px 6px rgba(0,0,0,0.05); border: 1px solid #f4b41a50; 
        transition: 0.2s;
        display: flex; flex-direction: column; align-items: flex-start;
        overflow: hidden;
    }
    .calendar-day:hover { background: #f4b41a20; }
    .calendar-day-number { font-weight: 600; color: #222; margin-bottom: 4px; }
    .event-tag { 
        background: #f4b41a; color: white; 
        padding: 2px 6px; border-radius: 4px; 
        font-size: 10px; margin-top: 2px; 
        width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        cursor: pointer;
    }

    /* Hamburger Menu */
    .hamburger { display: flex; flex-direction: column; gap: 4px; cursor: pointer; padding: 8px; border-radius: 8px; transition: background 0.2s; }
    .hamburger:hover { background: rgba(244, 180, 26, 0.1); }
    .hamburger span { width: 24px; height: 3px; background: #222; border-radius: 2px; transition: all 0.3s; }
    body.dark-mode .hamburger span { background: #e0e0e0; }

    /* Sidebar overlay */
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 45; }
    .sidebar-overlay.active { display: block; }

    /* Sidebar toggle */
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

  <script>
    // Load dark mode preference early
    (function() {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme === 'dark') { document.documentElement.classList.add('dark-mode'); }
    })();
  </script>
</head>
<body class="flex bg-[#fffaf2]">

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside id="sidebar"
class="fixed top-4 left-0 h-[calc(100vh-1rem)] w-64
       bg-[#f4b41a] rounded-tr-3xl
       p-6 shadow
       hidden md:flex md:flex-col md:gap-6">

  <div class="flex items-center gap-2">
    <img src="Assets/dinadrawing-logo.png" alt="Logo" class="w-14">
    <h2 class="text-xl font-bold text-[#222]">DiNaDrawing</h2>
  </div>

  <nav>
    <ul class="space-y-5">
      <li><a href="dashboard.html" class="block px-4 py-2 rounded-lg font-medium bg-[#222] text-white hover:bg-[#111] transition">Home</a></li>
      <li><a href="myplans.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">My Plans</a></li>
      <li><a href="help.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Help</a></li>
      <li><a href="settings.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Settings</a></li>
    </ul>
  </nav>
</aside>

<main id="mainContent" class="flex-1 min-h-screen px-12 py-10 pt-28">

<div class="page-header flex justify-between items-center border-b-2 border-gray-200 pb-4 mb-6 fixed top-0 left-0 w-full bg-[#fffaf2] z-40 px-12 py-10">
  <div class="flex items-center gap-4">
    <button id="hamburgerBtn" class="hamburger">
      <span></span><span></span><span></span>
    </button>

    <div class="flex flex-col">
      <h1 class="text-3xl font-bold">Welcome, <span id="userDisplayName">...</span>!</h1>
      <span class="text-gray-600 text-sm">Your personal event planner assistant.</span>
    </div>
  </div>

  <div class="flex items-center gap-2 md:gap-3">
    <button id="openCreateEvent" class="border border-[#222] bg-white px-4 py-2 rounded-2xl font-medium hover:bg-[#222] hover:text-white transition">
      <span class="hidden md:inline">+ Create Plan</span>
      <span class="md:hidden">+</span>
    </button>

    <button id="openJoinEvent" class="border border-[#222] bg-white px-4 py-2 rounded-2xl font-medium hover:bg-[#222] hover:text-white transition">
      <span class="hidden md:inline">Join Plan</span>
      <span class="md:hidden">Join</span>
    </button>

    <div class="flex items-center gap-4 relative">
      <div>
        <button id="notificationBtn"
          class="relative w-9 h-9 flex items-center justify-center rounded-full bg-white border border-[#222] hover:bg-[#222] hover:text-white transition">
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
            <a href="help.php" class="block px-4 py-2 text-sm hover:bg-gray-50">Help</a>
            <button id="aboutUsBtn" type="button" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50">About Us</button>
            <a href="settings.php" class="block px-4 py-2 text-sm hover:bg-gray-50">Settings</a>
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

<div class="mt-4 relative overflow-hidden rounded-2xl shadow-md" style="height: 16rem;">
  <div id="bannerCarousel" class="flex transition-transform duration-1000 ease-in-out h-full" style="width: 300%;">
    <img src="Assets/1.png" alt="Banner 1" class="w-1/3 h-full object-cover flex-shrink-0">
    <img src="Assets/2.png" alt="Banner 2" class="w-1/3 h-full object-cover flex-shrink-0">
    <img src="Assets/3.png" alt="Banner 3" class="w-1/3 h-full object-cover flex-shrink-0">
  </div>
</div>

<div class="mt-8">
  <section class="col-span-2 bg-white rounded-2xl shadow p-6 flex flex-col">
    <div class="flex justify-between items-center mb-4">
      <h3 class="font-semibold text-lg">Your Upcoming Plans</h3>
      <div class="flex gap-2">
        <button id="listViewBtn" class="bg-[#f4b41a] text-[#222] text-sm px-4 py-1.5 rounded-lg font-medium">List</button>
        <button id="calendarViewBtn" class="text-gray-600 hover:text-[#222] text-sm px-4 py-1.5 rounded-lg font-medium">Calendar</button>
      </div>
    </div>

    <div id="listView" class="space-y-4">
      
      <?php if (!empty($upcomingEvents)): ?>
        <?php foreach ($upcomingEvents as $ev): ?>
          <div class="flex flex-col sm:flex-row items-stretch rounded-2xl overflow-hidden shadow border border-gray-300">
            <div class="bg-white flex flex-row sm:flex-col items-center justify-between sm:justify-center w-full sm:w-24 px-4 sm:px-0 py-3 sm:py-4 border-b sm:border-b-0 sm:border-r border-gray-200">
              <p class="text-gray-500 text-sm sm:text-lg"><?php echo formatMonth($ev['date']); ?></p>
              <p class="text-2xl sm:text-4xl font-bold text-[#222] leading-none"><?php echo formatDay($ev['date']); ?></p>
            </div>
            <div class="flex-1 p-4 flex flex-row sm:flex-col md:flex-row justify-between items-center sm:items-start md:items-center gap-2 text-[#222]" 
                 style="background-color: <?php echo htmlspecialchars($ev['banner_color'] ?? '#f4b41a'); ?>;">
              <div>
                <p class="text-lg sm:text-xl font-bold leading-tight"><?php echo htmlspecialchars($ev['name']); ?></p>
                <p class="text-sm opacity-90"><?php echo htmlspecialchars($ev['location'] ?? 'No Location'); ?></p>
              </div>
              <div class="flex justify-end">
                <button onclick="window.location.href='plan.php?id=<?php echo $ev['id']; ?>'" 
                        class="bg-[#222] text-white text-sm py-1.5 px-6 rounded-full hover:bg-[#444] transition whitespace-nowrap">
                    View
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="p-8 text-center text-gray-500 bg-gray-50 rounded-2xl border border-dashed border-gray-300">
            <p>No upcoming plans. Create one now!</p>
        </div>
      <?php endif; ?>

      <div class="mt-6 bg-white border border-gray-200 rounded-2xl p-4">
        <h3 class="font-semibold mb-2 text-lg">Recent Plans</h3>
        <ul class="space-y-2 text-sm">
          <?php if (!empty($pastEvents)): ?>
            <?php foreach ($pastEvents as $ev): ?>
              <li class="flex justify-between items-center border-b border-gray-100 py-2">
                <span class="font-medium"><?php echo htmlspecialchars($ev['name']); ?></span>
                <span class="text-gray-400 text-xs">last <?php echo formatRecentDate($ev['date']); ?></span>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li class="text-gray-400 text-xs italic">No recent history.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div id="calendarView" class="hidden">
      <select id="monthSelect" class="hidden"></select>
      <select id="yearSelect" class="hidden"></select>

      <div id="customCalendar" class="gcal-container w-full">
        <div class="gcal-header-row px-4 py-4 flex items-center justify-between border-b border-gray-300">
          <div class="flex items-center gap-4">
             <h2 id="calendarTitle" class="text-xl font-normal text-gray-700">Calendar</h2>
             <div class="flex gap-1">
                <button id="prevMonth" class="px-2 py-1 border rounded hover:bg-gray-100">&lt;</button>
                <button id="nextMonth" class="px-2 py-1 border rounded hover:bg-gray-100">&gt;</button>
                <button id="todayBtn" class="px-2 py-1 border rounded hover:bg-gray-100 text-sm">Today</button>
             </div>
          </div>
          <button id="authorize_button" class="hidden google-btn shadow">
             <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="currentColor" d="M21.35 11.1h-9.17v2.73h6.51c-.33 3.81-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27c3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10c5.35 0 9.25-3.67 9.25-9.09c0-1.15-.15-1.81-.15-1.81z"/></svg>
             Sync Calendar
          </button>
        </div>

        <div class="gcal-header-row bg-gray-50 border-b border-gray-200">
          <div class="gcal-day-name">Sun</div><div class="gcal-day-name">Mon</div><div class="gcal-day-name">Tue</div><div class="gcal-day-name">Wed</div><div class="gcal-day-name">Thu</div><div class="gcal-day-name">Fri</div><div class="gcal-day-name">Sat</div>
        </div>

        <div id="calendarDays" class="gcal-grid">
             <div class="col-span-7 text-center py-10 text-gray-500">Initializing Calendar...</div>
        </div>
      </div>
    </div>
    </section>
</div>

<div id="eventDetailModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
  <div class="bg-white rounded-lg shadow-xl relative w-full max-w-md p-0 overflow-hidden font-roboto">
    <div class="bg-gray-100 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-gray-600 font-medium">Event Details</h3>
        <button id="closeEventDetail" class="text-gray-500 hover:text-gray-800 text-xl">&times;</button>
    </div>
    <div class="p-6">
      <h2 id="eventDetailTitle" class="text-2xl font-normal text-[#3c4043] mb-2">Event Title</h2>
      <p id="eventDetailDate" class="text-sm font-medium text-[#1a73e8] mb-4">Date</p>
      
      <div class="text-sm text-[#3c4043] bg-gray-50 p-3 rounded border border-gray-100">
         <p id="eventDetailDescription">No description</p>
      </div>
    </div>
  </div>
</div>

<div id="createEventModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
  <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-md max-h-[90vh] overflow-y-auto p-6">
    <button id="closeCreateEvent" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
    
    <h2 class="text-2xl font-bold mb-1">Create Plan</h2>
    <p class="text-sm text-gray-500 mb-4">Fill in the details to start planning.</p>
    
    <label class="block text-sm font-medium mb-1">Plan Name <span class="text-gray-400 text-xs">(required)</span> </label>
    <input type="text" id="eventName" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4" placeholder="Enter plan name">
    
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
    
    <p class="text-gray-500 text-xs mb-4 text-justify">Only Plan Name is required. You can leave Description, Date, Time, and Location blank and create a poll later.</p>
    
    <div class="flex justify-end gap-3 mb-2">
      <button id="cancelCreateEvent" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Cancel</button>
      <button id="saveCreateEvent" class="bg-[#f4b41a] px-4 py-2 rounded-lg font-medium hover:bg-[#e0a419] transition">Save & Continue</button>
    </div>
  </div>
</div>

<div id="joinEventModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center hidden z-50">
  <div class="bg-white rounded-2xl p-6 w-[500px] shadow-xl relative">
    <button id="closeJoinEvent" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
    
    <h2 class="text-2xl font-bold mb-1">Join Plan</h2>
    <p class="text-sm text-gray-500 mb-4">Enter the plan code or link to join.</p>
    
    <label class="block text-sm font-medium mb-1">Plan Code / Link</label>
    <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-6" placeholder="Paste code or link here">
    
    <div class="flex justify-end gap-3">
      <button id="cancelJoinEvent" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Cancel</button>
      <button id="joinJoinEvent" class="bg-[#f4b41a] px-4 py-2 rounded-lg font-medium hover:bg-[#e0a419] transition">Join</button>
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

<div id="logoutModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden z-[70] p-4 flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-md p-6"
       role="dialog" aria-modal="true" aria-labelledby="logoutTitle" onclick="event.stopPropagation()">
    <h2 id="logoutTitle" class="text-xl font-bold text-[#222] mb-2">Log out?</h2>
    <p class="text-sm text-gray-600 mb-6">Are you sure you want to log out?</p>
    <div class="flex justify-end gap-3">
      <button id="cancelLogoutBtn" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Cancel</button>
      <button id="confirmLogoutBtn" class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition">Yes</button>
    </div>
  </div>
</div>

<script>
// ==========================================
// 1. GLOBAL VARIABLES & HELPERS
// ==========================================
const localEvents = <?php echo json_encode($calendarEventsJS); ?>;
const currentUser = <?php echo json_encode($userData); ?>;
const GM_API_KEY = 'AIzaSyAGsgQDC6nVu9GQ9CYHQ2TTkbcX6qiF3Qc'; 
const CLIENT_ID = '140429427589-4lsoupn515qcb6073lpq8voqo3te3mah.apps.googleusercontent.com'; 
const CAL_API_KEY = 'AIzaSyD19U4Pz5MgS7hBLRCy1CRAPpDXIlenq4M';     
const DISCOVERY_DOCS = ["https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest"];
const SCOPES = "https://www.googleapis.com/auth/calendar.events";

// Modal Helper (Essential for Create/Join)
function toggleModal(id, show) {
    const el = document.getElementById(id);
    if(show) el.classList.remove('hidden'); else el.classList.add('hidden');
}

// ==========================================
// 2. UI INITIALIZATION (Sidebar, Dark Mode, User Info)
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    // Dark Mode
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        document.documentElement.classList.add('dark-mode');
    }

    // User Info Injection
    if (currentUser) {
        ['userDisplayName', 'navProfileName', 'dropdownProfileName'].forEach(id => { 
            const el = document.getElementById(id); 
            if(el) el.textContent = currentUser.name || currentUser.username; 
        });
        const navImg = document.getElementById('navProfileImg');
        const ddImg  = document.getElementById('dropdownProfileImg');
        if (navImg && currentUser.photo) navImg.src = currentUser.photo;
        if (ddImg && currentUser.photo) ddImg.src  = currentUser.photo;
    }

    // Sidebar Logic
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');
    const pageHeader = document.querySelector('.page-header');
    const isMobile = window.innerWidth < 769;

    // Restore Sidebar State
    if (localStorage.getItem('sidebarOpen') === 'true' || (localStorage.getItem('sidebarOpen') === null && !isMobile)) {
        if(!isMobile) { 
            sidebar.classList.add('active'); 
            mainContent.classList.add('sidebar-open'); 
            pageHeader.classList.add('sidebar-open'); 
        }
    }

    // Sidebar Toggles
    if (hamburgerBtn) {
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
    }

    // Banner Carousel
    const bannerCarousel = document.getElementById('bannerCarousel');
    let currentIndex = 0; const totalBanners = 3;
    setInterval(() => {
        currentIndex = (currentIndex + 1) % totalBanners;
        bannerCarousel.style.transform = `translateX(-${currentIndex * (100 / totalBanners)}%)`;
    }, 5000);
});

// ==========================================
// 3. CREATE PLAN LOGIC (DB Save + Map)
// ==========================================

// Open/Close Modals
document.getElementById("openCreateEvent").addEventListener("click", () => {
    toggleModal("createEventModal", true);
    // Load map only when modal opens
    loadGoogleMaps().then(() => { 
        if(!map) initMapOnce(); 
        setTimeout(() => { 
            if(map) { google.maps.event.trigger(map, 'resize'); map.setCenter(marker.getPosition()); }
        }, 200);
    });
});
document.getElementById("closeCreateEvent").addEventListener("click", () => toggleModal("createEventModal", false));
document.getElementById("cancelCreateEvent").addEventListener("click", () => toggleModal("createEventModal", false));

// Save Action
// ==========================================
// CREATE PLAN LOGIC (DB + AUTO GOOGLE SYNC)
// ==========================================

document.getElementById("saveCreateEvent").addEventListener("click", async () => {
    const name = document.getElementById("eventName").value.trim();
    if (!name) { alert("Plan Name required"); return; }
    
    // 1. Prepare Data
    const eventData = {
        name: name,
        description: document.getElementById("eventDescription").value.trim(),
        date: document.getElementById("eventDate").value,
        time: document.getElementById("eventTime").value,
        location: document.getElementById("eventLocation").value.trim()
    };

    // 2. Save to YOUR Database (DiNaDrawing)
    try {
        const res = await fetch('/DINADRAWING/Backend/events/create.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(eventData)
        });
        const result = await res.json();

        if (result.success) {
            // ✅ SUCCESS SA DB!
            
            // 3. TRY AUTO-SYNC TO GOOGLE (Silent)
            // Che-check kung may permission tayo kay Google. Kung meron, save agad.
            if (gapiInited && gapi.client.getToken() && eventData.date && eventData.time) {
                try {
                    console.log("Syncing to Google Calendar...");
                    const startDateTime = new Date(`${eventData.date}T${eventData.time}:00`);
                    const endDateTime = new Date(startDateTime.getTime() + (60 * 60 * 1000)); // Default 1 hour duration

                    await gapi.client.calendar.events.insert({
                        'calendarId': 'primary',
                        'resource': {
                            'summary': eventData.name,
                            'location': eventData.location,
                            'description': eventData.description,
                            'start': { 'dateTime': startDateTime.toISOString() },
                            'end': { 'dateTime': endDateTime.toISOString() }
                        }
                    });
                    alert("Plan created & synced to Google Calendar!");
                } catch (googleErr) {
                    console.error("Google Sync Error:", googleErr);
                    alert("Plan saved to App, but Google Sync failed (check console).");
                }
            } else {
                // Kung hindi naka-login sa Google, save lang sa App. No error.
                alert("Plan created successfully!");
            }

            // Redirect to the new plan
            window.location.href = `plan.php?id=${result.id}`;

        } else {
            alert("Failed to save: " + (result.error || "Unknown Error"));
        }
    } catch (e) { 
        alert("Network Error"); console.error(e); 
    }
});

// Description Character Count
const desc = document.getElementById("eventDescription");
const charCount = document.getElementById("charCount");
desc?.addEventListener("input", function () { charCount.textContent = desc.value.length; });

// Google Maps Loader
let map, marker, autocomplete;
function loadGoogleMaps() {
    if(window.google && google.maps) return Promise.resolve();
    return new Promise((resolve) => {
        const s = document.createElement('script');
        s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(GM_API_KEY)}&libraries=places`;
        s.async = true; s.defer = true; s.onload = resolve;
        document.head.appendChild(s);
    });
}
function initMapOnce() {
    const el = document.getElementById("map"); if (!el) return;
    map = new google.maps.Map(el, { center: { lat: 14.5995, lng: 120.9842 }, zoom: 12 });
    marker = new google.maps.Marker({ map, position: { lat: 14.5995, lng: 120.9842 }, draggable: true });
    
    const input = document.getElementById("eventLocation");
    if (input && google.maps.places) {
        autocomplete = new google.maps.places.Autocomplete(input); 
        autocomplete.bindTo("bounds", map);
        autocomplete.addListener("place_changed", () => {
            const p = autocomplete.getPlace(); 
            if(p.geometry) { map.setCenter(p.geometry.location); map.setZoom(15); marker.setPosition(p.geometry.location); }
        });
    }
    marker.addListener("dragend", () => {
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: marker.getPosition() }, (res, status) => { 
            if (status === "OK" && res[0]) input.value = res[0].formatted_address; 
        });
    });
}

// ==========================================
// 4. JOIN PLAN LOGIC
// ==========================================
document.getElementById("openJoinEvent").addEventListener("click", () => {
    const input = document.querySelector("#joinEventModal input");
    if(input) input.value = ''; // Clear input
    toggleModal("joinEventModal", true);
});
document.getElementById("closeJoinEvent").addEventListener("click", () => toggleModal("joinEventModal", false));
document.getElementById("cancelJoinEvent").addEventListener("click", () => toggleModal("joinEventModal", false));

document.getElementById("joinJoinEvent").addEventListener("click", async () => {
    const code = document.querySelector("#joinEventModal input").value.trim();
    if (!code) return alert("Enter code");
    try {
        const res = await fetch('/DINADRAWING/Backend/events/join_event.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ code: code })
        });
        const data = await res.json();
        if (data.success) window.location.href = `plan.php?id=${data.event_id}`;
        else alert("Failed to join. Invalid code?");
    } catch (e) { alert("Network Error"); }
});

// ==========================================
// 5. CALENDAR VIEW LOGIC (With Auto-Save Session)
// ==========================================
const calendarDays = document.getElementById('calendarDays');
const monthSelect = document.getElementById('monthSelect'); 
const yearSelect = document.getElementById('yearSelect');   
const calendarTitle = document.getElementById('calendarTitle');
let gapiInited = false, gisInited = false, tokenClient;

// Init Dropdowns
const currentYear = new Date().getFullYear();
for (let y = currentYear - 5; y <= currentYear + 5; y++) {
    const option = document.createElement('option'); option.value = y; option.innerText = y;
    if (y === currentYear) option.selected = true; yearSelect.appendChild(option);
}
for(let m=0; m<12; m++){ const opt = document.createElement('option'); opt.value = m; monthSelect.appendChild(opt); }
monthSelect.value = new Date().getMonth();

// GAPI Loaders
function gapiLoaded() { gapi.load('client', initializeGapiClient); }
async function initializeGapiClient() {
    await gapi.client.init({ apiKey: CAL_API_KEY, discoveryDocs: DISCOVERY_DOCS });
    gapiInited = true; maybeEnableButtons();
}
function gisLoaded() {
    tokenClient = google.accounts.oauth2.initTokenClient({ client_id: CLIENT_ID, scope: SCOPES, callback: '' });
    gisInited = true; maybeEnableButtons();
}

// --- FIX: SAVES LOGIN SO IT DOESN'T DISAPPEAR ON REFRESH ---
function maybeEnableButtons() {
    const authBtn = document.getElementById('authorize_button');
    if (gapiInited && gisInited) {
        
        // 1. Check if we have a saved token in browser storage
        const storedTokenStr = localStorage.getItem('gcal_token');
        if (storedTokenStr) {
            const storedToken = JSON.parse(storedTokenStr);
            // Check if token is still valid (not expired)
            if (Date.now() < storedToken.expiration_time) {
                gapi.client.setToken(storedToken); // Restore session
                authBtn.classList.add('hidden');   // Hide button
                updateCalendar();                  // Load events
                return;
            }
        }

        // 2. If no valid token found, show the Sync button
        if (gapi.client.getToken() === null) {
            authBtn.classList.remove('hidden'); 
            authBtn.onclick = handleAuthClick;
            updateCalendar(); // Show local events only
        } else {
            authBtn.classList.add('hidden'); 
            updateCalendar();
        }
    }
}

// --- FIX: SAVES TOKEN WHEN YOU CLICK SYNC ---
function handleAuthClick() {
    tokenClient.callback = async (resp) => {
        if (resp.error !== undefined) throw (resp);
        
        // Save the token to LocalStorage with Expiration
        const token = gapi.client.getToken();
        const expirationTime = Date.now() + (token.expires_in * 1000);
        localStorage.setItem('gcal_token', JSON.stringify({ ...token, expiration_time: expirationTime }));

        document.getElementById('authorize_button').classList.add('hidden'); 
        await updateCalendar();
    };
    tokenClient.requestAccessToken({prompt: 'consent'});
}

async function updateCalendar() {
    const m = parseInt(monthSelect.value); 
    const y = parseInt(yearSelect.value);
    const dateObj = new Date(y, m, 1);
    calendarTitle.innerText = dateObj.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    
    let googleEvents = [];
    if (gapiInited && gapi.client.getToken()) {
        try {
            const start = new Date(y, m, 1); const end = new Date(y, m+1, 0);
            const res = await gapi.client.calendar.events.list({
                'calendarId': 'primary', 'timeMin': start.toISOString(), 'timeMax': end.toISOString(),
                'showDeleted': false, 'singleEvents': true, 'maxResults': 100, 'orderBy': 'startTime'
            });
            googleEvents = res.result.items;
        } catch(err) { 
            console.error("Token might be expired", err);
            // If error, clear invalid token so user can login again next time
            localStorage.removeItem('gcal_token');
            document.getElementById('authorize_button').classList.remove('hidden');
        }
    }
    renderGrid(m, y, googleEvents);
}

function renderGrid(month, year, googleEvents) {
    calendarDays.innerHTML = ''; 
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();

    for (let i = 0; i < firstDay; i++) {
        const div = document.createElement('div'); div.className = "gcal-cell bg-gray-50"; calendarDays.appendChild(div);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const cell = document.createElement('div'); cell.className = "gcal-cell";
        const isToday = (d === today.getDate() && month === today.getMonth() && year === today.getFullYear());
        cell.innerHTML = `<div class="gcal-date-number ${isToday ? 'today' : ''}">${d}</div>`;

        // Local Events
        localEvents.filter(e => {
            if(!e.date) return false;
            const ed = new Date(e.date);
            return ed.getDate() === d && ed.getMonth() === month && ed.getFullYear() === year;
        }).forEach(evt => {
            const tag = document.createElement('div'); 
            tag.className = 'gcal-event-chip local-plan'; 
            tag.innerText = evt.title;
            tag.style.backgroundColor = evt.color;
            tag.onclick = (e) => { e.stopPropagation(); window.location.href=`plan.php?id=${evt.id}`; };
            cell.appendChild(tag);
        });

        // Google Events
        if(googleEvents) {
            googleEvents.filter(e => {
                const start = e.start.dateTime || e.start.date;
                const ed = new Date(start);
                return ed.getDate() === d && ed.getMonth() === month && ed.getFullYear() === year;
            }).forEach(evt => {
                const tag = document.createElement('div'); tag.className = 'gcal-event-chip'; tag.innerText = evt.summary;
                tag.onclick = (e) => { e.stopPropagation(); openGoogleEventModal(evt); };
                cell.appendChild(tag);
            });
        }
        calendarDays.appendChild(cell);
    }
}

// Calendar Navigation
document.getElementById('prevMonth').addEventListener('click', () => {
    let m = parseInt(monthSelect.value); m--; if(m < 0) { m = 11; yearSelect.value--; } monthSelect.value = m; updateCalendar();
});
document.getElementById('nextMonth').addEventListener('click', () => {
    let m = parseInt(monthSelect.value); m++; if(m > 11) { m = 0; yearSelect.value++; } monthSelect.value = m; updateCalendar();
});
document.getElementById('todayBtn').addEventListener('click', () => {
    monthSelect.value = new Date().getMonth(); yearSelect.value = new Date().getFullYear(); updateCalendar();
});

// Google Event Modal
function openGoogleEventModal(event) {
    const modal = document.getElementById('eventDetailModal');
    document.getElementById('eventDetailTitle').innerText = event.summary;
    const start = new Date(event.start.dateTime || event.start.date);
    document.getElementById('eventDetailDate').innerText = start.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', hour: '2-digit', minute:'2-digit' });
    document.getElementById('eventDetailDescription').innerText = event.description || "No description provided.";
    modal.classList.remove('hidden');
}
document.getElementById('closeEventDetail').addEventListener('click', () => {
    document.getElementById('eventDetailModal').classList.add('hidden');
});


// ==========================================
// 6. OTHER UI (Notifications, Views, Logout)
// ==========================================

// List/Calendar Switch
const listViewBtn = document.getElementById("listViewBtn");
const calendarViewBtn = document.getElementById("calendarViewBtn");
const listView = document.getElementById("listView");
const calendarView = document.getElementById("calendarView");

listViewBtn.addEventListener("click", () => {
    listView.classList.remove("hidden"); calendarView.classList.add("hidden");
    listViewBtn.classList.add("bg-[#f4b41a]", "text-[#222]"); calendarViewBtn.classList.remove("bg-[#f4b41a]", "text-[#222]"); calendarViewBtn.classList.add("text-gray-600");
});
calendarViewBtn.addEventListener("click", () => {
    listView.classList.add("hidden"); calendarView.classList.remove("hidden");
    calendarViewBtn.classList.add("bg-[#f4b41a]", "text-[#222]"); listViewBtn.classList.remove("bg-[#f4b41a]", "text-[#222]"); listViewBtn.classList.add("text-gray-600");
    updateCalendar(); // Refresh grid
});

// Notifications
const notificationBtn = document.getElementById("notificationBtn");
const notificationPanel = document.getElementById("notificationPanel");
const notificationDot = document.getElementById("notificationDot");
const profileBtn = document.getElementById('profileBtn');
const profileDropdown = document.getElementById('profileDropdown');

notificationBtn?.addEventListener("click", (e) => { 
    e.stopPropagation(); document.getElementById('profileDropdown')?.classList.add('hidden'); 
    notificationPanel?.classList.toggle("hidden"); 
    if (!notificationPanel?.classList.contains("hidden")) { loadNotifications(); } 
});
profileBtn?.addEventListener('click', (e) => { 
    e.stopPropagation(); notificationPanel?.classList.add('hidden'); 
    profileDropdown?.classList.toggle('hidden'); 
});
document.addEventListener('click', (e) => {
    if(!profileBtn?.contains(e.target) && !profileDropdown?.contains(e.target)) profileDropdown?.classList.add('hidden');
    if(!notificationBtn?.contains(e.target) && !notificationPanel?.contains(e.target)) notificationPanel?.classList.add('hidden');
});

function loadNotifications() {
    fetch('/DINADRAWING/Backend/events/get_notifications.php')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('notificationList');
            if(data.success && data.notifications.length > 0) {
                list.innerHTML = data.notifications.map(n => `
                <li class="p-3 border-b border-gray-50 text-sm hover:bg-gray-50">
                    <span class="font-bold">${n.actor_name}</span> ${n.action_text} <span class="font-bold">${n.event_name}</span>
                    <div class="text-xs text-gray-400 mt-1">${n.time_ago}</div>
                </li>`).join('');
                if(data.notifications.some(n => !n.is_read)) document.getElementById('notificationDot')?.classList.remove('hidden');
            } else {
                list.innerHTML = '<li class="p-4 text-center text-gray-400 text-sm">No new notifications.</li>';
                document.getElementById('notificationDot')?.classList.add('hidden');
            }
        }).catch(e => console.error(e));
}
window.markAllRead = function() { fetch('/DINADRAWING/Backend/events/mark_read.php').then(() => { loadNotifications(); document.getElementById('notificationDot')?.classList.add('hidden'); }); };

// About Us & Logout
document.getElementById("aboutUsBtn")?.addEventListener("click", (e) => { e.preventDefault(); toggleModal("aboutUsModal", true); document.getElementById('profileDropdown').classList.add('hidden'); });
document.getElementById("closeAboutUsModal")?.addEventListener("click", () => toggleModal("aboutUsModal", false));
document.getElementById('logoutProfile')?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); toggleModal('logoutModal', true); profileDropdown.classList.add('hidden'); });
document.getElementById('cancelLogoutBtn')?.addEventListener('click', () => toggleModal('logoutModal', false));
document.getElementById('confirmLogoutBtn')?.addEventListener('click', () => { localStorage.removeItem('currentUser'); window.location.href = '/DINADRAWING/Backend/auth/logout.php'; });
</script>

<script async defer src="https://apis.google.com/js/api.js" onload="gapiLoaded()"></script>
<script async defer src="https://accounts.google.com/gsi/client" onload="gisLoaded()"></script>

</body>
</html>