<?php
// Connect to DB
$host = "127.0.0.1";
$port = "5432";
$dbname = "dinadrawing";
$username = "kai";
$password = "DND2025";

$conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Read event id from URL
$id = $_GET['id'] ?? null;

if (!$id) {
    die("Event ID missing.");
}

// Fetch event
$stmt = $conn->prepare("SELECT * FROM events WHERE id = :id");
$stmt->bindValue(":id", $id, PDO::PARAM_INT);
$stmt->execute();

$event = $stmt->fetch();

if (!$event) {
    die("Event not found.");
}

$event_name = htmlspecialchars($event['name'] ?? $event['title'] ?? 'Untitled Event', ENT_QUOTES, 'UTF-8');
$event_desc = htmlspecialchars($event['description'] ?? $event['desc'] ?? '', ENT_QUOTES, 'UTF-8');

// support either 'place' or 'location' column names
$event_place = htmlspecialchars($event['place'] ?? $event['location'] ?? $event['loc'] ?? '', ENT_QUOTES, 'UTF-8');

// Build a value suitable for <input type="datetime-local">
// Support: 'datetime' column OR separate 'date' + 'time' columns OR just 'date'
$event_date_val = '';
$rawDate = null;
if (!empty($event['datetime'])) {
    $rawDate = $event['datetime'];
} elseif (!empty($event['date']) && !empty($event['time'])) {
    $rawDate = $event['date'] . ' ' . $event['time'];
} elseif (!empty($event['date'])) {
    $rawDate = $event['date'];
} elseif (!empty($event['starts_at'])) {
    $rawDate = $event['starts_at'];
}

if ($rawDate) {
    $ts = strtotime($rawDate);
    if ($ts) {
        // format for datetime-local (no seconds)
        $event_date_val = date('Y-m-d\TH:i', $ts);
    }
}

$banner_type = $event['banner_type'] ?? null;
$banner_color = $event['banner_color'] ?? null;
$banner_from  = $event['banner_from'] ?? null;
$banner_to    = $event['banner_to'] ?? null;
$banner_image = $event['banner_image'] ?? null;

function banner_style($type,$color,$from,$to,$image){
  if ($type==='image' && $image){
    $path = '/dinadrawing/'.ltrim($image,'/');
    return "background-image:url('".htmlspecialchars($path,ENT_QUOTES,'UTF-8')."');background-size:cover;background-position:center;color:#fff;";
  }
  if ($type==='gradient' && $from && $to)
    return "background:linear-gradient(to right,".htmlspecialchars($from,ENT_QUOTES,'UTF-8').",".htmlspecialchars($to,ENT_QUOTES,'UTF-8').");color:#111;";
  if ($type==='color' && $color)
    return "background:".htmlspecialchars($color,ENT_QUOTES,'UTF-8').";color:#fff;";
  return "background:linear-gradient(to right,#3b82f6,#9333ea);color:#fff;";
}
$banner_inline = banner_style($banner_type,$banner_color,$banner_from,$banner_to,$banner_image);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ongoing Plans | DiNaDrawing</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">
  <link rel="stylesheet" href="Assets/styles/scrollbar.css">
<style>
      body { font-family: 'Poppins', sans-serif; background-color: #fffaf2; }
    #postInput:empty::before { content: attr(data-placeholder); color: #9ca3af; pointer-events: none; }
    #cropImage { max-width: 100%; display: block; }

    /* EDIT BANNER MODAL UI */
    .edit-modal-grid {
      --swatch-size: 36px; 
      display: grid;
      grid-template-columns: repeat(6, var(--swatch-size));
      gap: 10px;
      justify-content: center;
      align-items: center;
    }
    .gradient-circle, .color-picker-circle {
      width: var(--swatch-size); height: var(--swatch-size); border-radius: 9999px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.12);
      border: 2px solid rgba(255,255,255,0.75);
      display:flex; align-items:center; justify-content:center; cursor:pointer; overflow:hidden;
      transition: transform .12s ease;
    }
    .gradient-circle:hover, .color-picker-circle:hover { transform: scale(1.06); }
    .edit-modal { max-width: 520px; width: 92%; }
    #colorPicker {
      appearance: none; width: var(--swatch-size); height: var(--swatch-size); border-radius: 9999px;
      border: 2px solid rgba(255,255,255,0.75); padding: 0; cursor: pointer; background: transparent;
    }
    #colorPicker::-webkit-color-swatch { border: none; border-radius: 9999px; }
    #colorPicker::-moz-color-swatch { border: none; border-radius: 9999px; }
    .crop-aspect { aspect-ratio: 21/9; }

    /* SCROLLBAR STYLING + UTILITIES */
    html, body { scrollbar-gutter: stable; }
    * { scrollbar-width: thin; scrollbar-color: #f4b41a transparent; }
    *::-webkit-scrollbar { width: 8px; height: 8px; }
    *::-webkit-scrollbar-track { background: transparent; }
    *::-webkit-scrollbar-thumb { background-color: #f4b41a; border-radius: 9999px; border: 2px solid transparent; background-clip: padding-box; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .no-scrollbar::-webkit-scrollbar { display: none; }

    /* NEW COLOR PICKER POPOVER STYLES*/
    .swatch-popover {
      position: absolute;
      top: calc(var(--swatch-size) + 8px);
      left: 50%;
      transform: translateX(-50%);
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 8px;
      box-shadow: 0 10px 20px rgba(0,0,0,.12);
      z-index: 20;
    }
    .picker-donut {
      position: relative;
      background: conic-gradient(red, yellow, lime, cyan, blue, magenta, red);
    }
    .picker-donut::after {
      content: "";
      position: absolute;
      inset: 25%;
      background: white;
      border-radius: 9999px;
      box-shadow: inset 0 0 0 2px rgba(0,0,0,0.06);
    }

    /* TAB CONTENT SECTIONS */
    .tab-section { display: none; }
    .tab-section.active { display: block; }

    /* SETTINGS TAB STYLES */
    .tab-content {
      opacity: 0;
      transform: translateY(6px);
      transition: opacity 240ms ease, transform 240ms ease;
      will-change: opacity, transform;
    }
    .tab-content.show {
      opacity: 1;
      transform: translateY(0);
    }
    .soft-shadow {
      box-shadow: 0 6px 14px rgba(0,0,0,0.06);
    }
    .tab-btn {
      background: transparent;
      color: #6b7280; 
      font-weight: 600;
      padding: 6px 18px;
      border-radius: 6px;
      border: none;
      transition: color 160ms ease, background 160ms ease, transform 160ms ease;
    }
    .tab-btn:hover { color: #374151; }
    .tab-selected {
      background: #f4b41a !important; 
      color: #111 !important;
      box-shadow: 0 2px 6px rgba(0,0,0,0.06);
      padding: 4px 20px !important; 
      border-radius: 10px !important; 
      transform: translateY(-1px);
    }
    .thin-btn {
      padding: 4px 12px !important;
      border-radius: 8px !important;
      font-weight: 600;
      font-size: 0.95rem;
      height: 32px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .thin-danger { 
      padding: 4px 12px !important;
      border-radius: 8px !important;
      font-weight: 600;
      font-size: 0.95rem;
      height: 32px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .appearance-switch {
      position: relative;
      width: 140px;            
      height: 34px;
      background: #e5e7eb;     
      padding: 3px;
      border-radius: 9999px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .appearance-switch .option {
      width: 44px;
      height: 28px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 20px;
      cursor: pointer;
    }
    .appearance-switch .option.left svg { color: #9ca3af; }
    .appearance-switch .option.right svg { color: #9ca3af; }
    .appearance-switch .option.active {
      background: #ffffff;
      box-shadow: 0 3px 8px rgba(0,0,0,0.08);
      border: 1px solid rgba(0,0,0,0.04);
    }
    .appearance-switch .option.left.active svg { color: #f59e0b; }
    .appearance-switch .option.right.active svg { color: #374151; }
    .budget-step { display: none; }
    .budget-step:not(.hidden) { display: block; }
  </style>
</head>

<body class="flex bg-[#fffaf2] overflow-y-auto">

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
      <li><a href="myplans.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">My Plans</a></li>
      <li><a href="help.html" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Help</a></li>
      <li><a href="settings.html" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Settings</a></li>
    </ul>
  </nav>
</aside>

<!-- MAIN CONTENT -->
  <main class="ml-0 md:ml-64 flex-1 min-h-screen px-12 py-10 lg:mr-[23rem] lg:pr-4">
    <div
      class="fixed top-0 left-0 md:left-64 right-0 lg:right-[23rem] h-10 bg-[#fffaf2] z-30 pointer-events-none"
      aria-hidden="true"
    ></div>
    <div class="flex-1 w-full min-w-0">

      <!-- STICKY HEADER -->
      <div id="stickyHeader" class="sticky top-10 z-50 bg-[#fffaf2]">
        <!-- PLAN BANNER -->
        <div
          id="planBanner"
          class="group relative rounded-2xl px-8 font-bold text-3xl mb-3 shadow w-full overflow-hidden transition-colors duration-300 bg-cover bg-center py-20 lg:py-24 min-h-[12rem]"
          style="<?php echo $banner_inline; ?>"
        >
          <a href="myplans.php"
             class="absolute top-5 left-8 inline-flex items-center justify-center w-8 h-8 rounded-lg hover:bg-white/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f4b41a]"
             style="color: inherit;"
             aria-label="Back to My Plans">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                 class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M15 18l-6-6 6-6"/>
            </svg>
          </a>
          <span id="bannerText" class="absolute bottom-5 left-8"><?php echo $event_name; ?></span>
          <button
            id="editBannerBtn"
            class="absolute top-5 right-5 z-10 bg-white/90 text-[#222] px-3 py-1.5 rounded-lg text-sm font-semibold opacity-0 group-hover:opacity-100 transition pointer-events-auto"
          >
            Edit Banner
          </button>
          <div id="bannerMenu" class="absolute top-14 right-5 bg-white shadow-lg rounded-lg w-44 p-2 space-y-1 hidden z-50">
            <button id="uploadImageBtn" class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 text-sm font-medium">Upload Image</button>
          </div>
          <input type="file" id="bannerImageUpload" accept="image/*" class="hidden" />
        </div>

        <!-- PLAN TABS -->
        <div class="flex bg-white rounded-lg shadow p-0.5 mb-2 w-full">
          <button onclick="switchTab('feed')" id="tab-feed" class="flex-1 bg-[#f4b41a] text-[#222] font-semibold py-2 text-center rounded-lg">Feed</button>
          <button onclick="switchTab('budget')" id="tab-budget" class="flex-1 text-gray-600 font-medium py-2 text-center hover:text-[#222] hover:bg-gray-100 rounded-lg">Budget</button>
          <button onclick="switchTab('settings')" id="tab-settings" class="flex-1 text-gray-600 font-medium py-2 text-center hover:text-[#222] hover:bg-gray-100 rounded-lg">Settings</button>
        </div>
      </div>

      <!-- FEED SECTION -->
      <div id="feed-section" class="tab-section active">
      <!-- POST BOX -->
      <div id="postBox" class="bg-white p-4 rounded-lg shadow w-full transition-all duration-300">
        <div class="flex items-start gap-3">
          <img src="Assets/Profile Icon/profile.png" alt="User" class="w-10 h-10 rounded-full" />
          <div class="flex-1">
            <div class="border border-gray-300 rounded-lg focus-within:border-[#f4b41a] transition-all duration-300 overflow-hidden">
              <!-- POST INPUT -->
              <div
                id="postInput"
                contenteditable="true"
                data-placeholder="Announce something to group"
                class="w-full px-3 py-2 text-sm resize-none min-h-[60px] focus:outline-none"
              ></div>

              <!-- BOLD, ITALIC, & UNDERLINE -->
              <div id="toolbar" class="hidden flex items-center gap-2 p-2 border-t border-gray-200 text-gray-600 bg-gray-50">
                <button type="button" onmousedown="keepFocus(event)" onclick="formatText('bold')" class="hover:text-[#f4b41a] font-semibold">B</button>
                <button type="button" onmousedown="keepFocus(event)" onclick="formatText('italic')" class="hover:text-[#f4b41a] italic">I</button>
                <button type="button" onmousedown="keepFocus(event)" onclick="formatText('underline')" class="hover:text-[#f4b41a] underline">U</button>
              </div>
            </div>

            <!-- BUTTON ICONS -->
            <div id="postActions" class="hidden mt-3 flex justify-between items-center">
              <div class="flex gap-2">
                <!-- CREATE POLL -->
                <button onmousedown="keepFocus(event)" class="bg-gray-100 hover:bg-[#f4b41a]/30 rounded-full w-8 h-8 flex items-center justify-center overflow-hidden border" title="Create Poll" onclick="openPoll()">
                  <img src="Assets/polls.jpg" alt="Poll" class="w-full h-full object-cover" />
                </button>

                <!-- ASSIGN TASK -->
                <button onmousedown="keepFocus(event)" class="bg-gray-100 hover:bg-[#f4b41a]/30 rounded-full w-8 h-8 flex items-center justify-center overflow-hidden border" title="Assign Task" onclick="openTask()">
                  <img src="Assets/task.jpg" alt="Task" class="w-full h-full object-cover" />
                </button>
              </div>

              <div class="flex gap-2">
                <button type="button" class="px-4 py-2 bg-gray-200 rounded-lg text-sm hover:bg-gray-300 transition" onclick="cancelPost()">Cancel</button>
                <button type="button" class="px-4 py-2 bg-[#f4b41a] text-[#222] rounded-lg text-sm font-medium hover:bg-[#e3a918] transition" onclick="submitPost()">Post</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CREATE POLL MODAL -->
      <div id="pollModal" class="fixed inset-0 bg-black/40 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-lg w-96 p-6 relative">
          <h2 class="text-lg font-semibold mb-4">Create a Poll</h2>
          <button onclick="closePoll()" class="absolute top-3 right-3 text-gray-500 hover:text-black">✕</button>

          <div class="space-y-2" id="pollQuestionSection">
            <input type="text" placeholder="Enter your question here..." class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none" />
            <div id="pollOptionsContainer" class="space-y-2">
              <input type="text" placeholder="Add option 1" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none" />
            </div>
            <button id="pollAddOptionBtn" class="text-sm text-[#3b82f6] font-medium hover:underline">+ Add more option</button>
          </div>
          <p class="font-semibold mb-2">Poll Settings</p>
          <div class="flex items-center justify-between mb-2">
            <span>Allow multiple votes</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" class="sr-only peer" />
              <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-[#f4b41a] transition"></div>
              <span class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
            </label>
          </div>

          <div class="flex items-center justify-between mb-4">
            <span>Anonymous voting</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" class="sr-only peer" />
              <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-[#f4b41a] transition"></div>
              <span class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
            </label>
          </div>

          <button class="w-full bg-[#f4b41a] text-[#222] font-medium py-2 rounded-lg hover:bg-[#e3a918] transition">Create Poll</button>
        </div>
      </div>

      <!-- ASSIGN TASK MODAL -->
      <div id="taskModal" class="fixed inset-0 bg-black/40 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-lg w-96 p-6 relative">
          <h2 class="text-lg font-semibold mb-4">Assigned Tasks</h2>
          <button onclick="closeTask()" class="absolute top-3 right-3 text-gray-500 hover:text-black">✕</button>

          <div class="space-y-2" id="taskSection">
            <input type="text" placeholder="Enter the task title..." class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none" />
            <div id="taskOptionsContainer" class="space-y-2">
              <input type="text" placeholder="Add task 1" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none" />
            </div>
            <button id="taskAddOptionBtn" class="text-sm text-[#3b82f6] font-medium hover:underline">+ Add more task</button>
          </div>

          <div class="border-t pt-3 text-sm">
            <p class="font-semibold mb-2">Assigned Task Settings</p>
            <div class="flex items-center justify-between mb-2">
              <span>Allow members to mention co-members</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" class="sr-only peer" checked />
                <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-[#f4b41a] transition"></div>
                <span class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
              </label>
            </div>

            <div class="flex items-center justify-between mb-4">
              <span>Allow members to add tasks</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" class="sr-only peer" />
                <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-[#f4b41a] transition"></div>
                <span class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
              </label>
            </div>

            <label class="block mb-1 font-medium">Ends on</label>
            <select class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#f4b41a] mb-4">
              <option>No deadline</option>
              <option>Tomorrow</option>
              <option>Next week</option>
              <option>Custom date...</option>
            </select>

            <button class="w-full bg-[#f4b41a] text-[#222] font-medium py-2 rounded-lg hover:bg-[#e3a918] transition">Save</button>
          </div>
        </div>
      </div>
      </div>
      <!-- END FEED SECTION -->

      <!-- BUDGET SECTION -->
      <div id="budget-section" class="tab-section">
        <!-- NO BUDGET VIEW -->
        <div id="noBudgetView" class="flex items-center justify-center h-[60vh]">
          <div class="text-center">
            <button id="openBudgetModal" class="bg-[#f4b41a] text-[#222] px-5 py-2 rounded-lg font-semibold shadow hover:bg-[#e3a918] transition">+ Set the budget</button>
            <p class="text-gray-500 mt-2 text-sm">Ready to set your plan's budget?</p>
          </div>
        </div>

        <!-- BUDGET VIEW -->
        <div id="budgetView" class="hidden space-y-4">
          <!-- SUMMARY CARDS -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl p-5 shadow-sm border">
              <p class="text-sm text-gray-500">Estimated Budget</p>
              <p class="text-3xl font-bold text-gray-900" id="displayTotalBudget">₱0</p>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border">
              <p class="text-sm text-gray-500">Money Collected</p>
              <p class="text-3xl font-bold text-green-700" id="displayMoneyCollected">₱0</p>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border">
              <p class="text-sm text-gray-500">Not Collected</p>
              <p class="text-3xl font-bold text-orange-700" id="displayBalanceRemaining">₱0</p>
            </div>
          </div>

          <!-- PROGRESS -->
          <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex justify-between items-center mb-3">
              <p class="text-sm font-semibold text-gray-700">Collection Progress</p>
              <p class="text-sm font-bold text-[#f4b41a]" id="progressPercentage">0%</p>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
              <div id="progressBar" class="bg-gradient-to-r from-[#f4b41a] to-[#f59e0b] h-4 rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Click member payment status below to update</p>
          </div>

          <!-- EXPENSES -->
          <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex justify-between items-center mb-5">
              <h3 class="text-xl font-bold text-[#222]">Expense Breakdown</h3>
              <button id="editExpensesBtn" class="text-[#f4b41a] hover:text-[#e3a918] font-medium text-sm flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-yellow-50 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit
          </button>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b-2 border-gray-200">
                  <tr>
                    <th class="py-3 px-4 text-left font-semibold text-gray-700">Expense Item</th>
                    <th class="py-3 px-4 text-right font-semibold text-gray-700">Estimated</th>
                    <th class="py-3 px-4 text-right font-semibold text-gray-700">Actual Cost</th>
                    <th class="py-3 px-4 text-center font-semibold text-gray-700">Status</th>
                    <th class="py-3 px-4 w-10"></th>
                  </tr>
                </thead>
                <tbody id="expenseDisplayBody" class="divide-y divide-gray-100"></tbody>
                <tfoot class="border-t-2 bg-gradient-to-r from-gray-50 to-gray-100">
                  <tr>
                    <td class="py-4 px-4 font-bold text-gray-800">Total</td>
                    <td class="py-4 px-4 text-right font-bold text-gray-800" id="totalEstimated">₱0</td>
                    <td class="py-4 px-4 text-right font-bold text-gray-800" id="totalActual">₱0</td>
                    <td colspan="2"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- CONTRIBUTIONS -->
          <div class="bg-white rounded-xl p-5 shadow-sm border">
            <div class="flex justify-between items-center mb-5">
              <h3 class="text-xl font-bold text-[#222]">Member Contributions</h3>
              <button id="editContributionsBtn" class="text-[#f4b41a] hover:text-[#e3a918] font-medium text-sm flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-yellow-50 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit
          </button>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b-2 border-gray-200">
                  <tr>
                    <th class="py-3 px-4 text-left font-semibold text-gray-700">Member</th>
                    <th class="py-3 px-4 text-right font-semibold text-gray-700">Amount Due</th>
                    <th class="py-3 px-4 text-center font-semibold text-gray-700">Payment Status</th>
                  </tr>
                </thead>
                <tbody id="contributionDisplayBody" class="divide-y divide-gray-100"></tbody>
              </table>
            </div>
            <p class="text-xs text-gray-500 mt-3 text-center">Tip: Click on payment status to toggle paid/unpaid</p>
          </div>
        </div>
      </div>
      <!-- END BUDGET SECTION -->

      <!-- SETTINGS SECTION -->
      <div id="settings-section" class="tab-section">
        <!-- SETTINGS PANEL -->
        <section id="settingsPanel" class="bg-white p-6 rounded-2xl shadow">

          <!-- EVENT DETAILS -->
          <h3 class="text-xl font-semibold mb-4">Event Details</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium">Event name</label>
              <input id="eventName" type="text" value="<?php echo $event_name; ?>" class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200">
            </div>

            <!-- EVENT DESCRIPTION -->
            <div>
              <label class="block text-sm font-medium">Event description</label>
              <textarea id="eventDesc" rows="3" placeholder="Type your event description..." maxlength="270" class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200"><?php echo $event_desc; ?></textarea>
            </div>

            <!-- EVENT DATE & TIME -->
            <div>
              <label class="block text-sm font-medium">Event date & time</label>
              <input id="eventDate" type="datetime-local" value="<?php echo $event_date_val; ?>" class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200">
            </div>

            <!-- EVENT PLACE -->
            <div>
              <label class="block text-sm font-medium">Event place</label>
              <input id="eventPlace" type="text" placeholder="Saan ba kasi?" value="<?php echo $event_place; ?>" class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200">
            </div>
          </div>

          <hr class="my-6">

          <!-- EVENT VISIBILITY & PRIVACY -->
          <h3 class="text-xl font-semibold mb-3">Event Visibility & Privacy</h3>
          <div class="space-y-4">
            <label class="flex items-center space-x-2">
              <input id="allowInvites" type="checkbox" class="w-5 h-5 text-blue-600 rounded focus:ring-blue-400">
              <span>Allow members to invite others</span>
            </label>

            <div>
              <label class="block text-sm font-medium mb-1">Event visibility</label>
              <select id="visibility" class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200">
                <option value="public">Public (anyone can view)</option>
                <option value="private">Private (members only)</option>
                <option value="invite-only">Invite-only (only invited can access)</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Who can edit event details?</label>
              <select id="editPermission" class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200">
                <option value="owner">Only event creator</option>
                <option value="admins">Admins only</option>
                <option value="members">All members</option>
              </select>
            </div>
          </div>
          <button id="saveSettingsBtn" class="mt-6 bg-[#F4B63F] px-6 py-2 rounded-lg font-semibold hover:bg-yellow-400">
            Save Changes
          </button>
          <div class="mt-8 border border-red-200 bg-red-50 rounded-xl p-4">
            <h4 class="font-semibold text-red-700 mb-3">Danger zone</h4>
            <div class="flex flex-col sm:flex-row gap-3">
              <button id="leaveEventBtn" class="px-4 py-2 rounded-lg border border-red-300 text-red-700 hover:bg-red-100 transition">
                Leave Event
              </button>
              <button id="deleteEventBtn" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 transition sm:ml-auto">
                Delete Event
              </button>
            </div>
            <p class="text-xs text-red-600 mt-2">Deleting removes all data. This action cannot be undone.</p>
          </div>
        </section>
      </div>
      <!-- END SETTINGS SECTION -->

    </div>

    <!-- RIGHT SIDEBAR -->
    <aside class="hidden lg:flex fixed top-10 right-12 bottom-4 w-80 flex-col space-y-4 z-40 overflow-y-auto no-scrollbar">
      <!-- MEMBERS -->
      <div id="membersCard" class="bg-white p-4 rounded-lg shadow">
        <h3 class="font-semibold mb-2">Members (1)</h3>
        <div id="membersList" class="grid grid-cols-5 gap-2 mb-3">
          <img src="Assets/Profile Icon/profile.png" class="w-8 h-8 rounded-full border" alt="Creator">
          <!-- Other members will be loaded from backend -->
        </div>
        <button onclick="openInvite()" class="w-full bg-[#f4b41a] text-[#222] py-2 rounded-lg font-medium hover:bg-[#e3a918] transition">
          Invite People
        </button>
      </div>

      <!-- PINNED -->
      <div class="bg-white p-4 rounded-lg shadow">
        <h3 class="font-semibold mb-2 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-black" fill="currentColor" viewBox="0 0 24 24">
            <path d="M14 2l-1 1-5 5-4 1 6 6-5 5 1 1 5-5 6 6 1-4 5-5 1-1-10-10z" />
          </svg>
          Pinned
        </h3>
        <p class="text-sm text-gray-500">No pinned items yet.</p>
      </div>
    </aside>
  </main>

  <!-- MOVED: INVITE PEOPLE MODAL -->
  <div id="inviteModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[60]">
    <div class="bg-white w-96 rounded-xl shadow-lg p-6 relative">
      <h2 class="text-lg font-semibold mb-3">Invite People</h2>
      <button onclick="closeInvite()" class="absolute top-3 right-3 text-gray-500 hover:text-black">✕</button>
      <p class="text-sm text-gray-600 mb-3">Share this link with your friends to invite them to this plan:</p>

      <!-- GENERATE LINK -->
      <div class="flex items-center justify-between bg-gray-100 rounded-lg px-3 py-2 mb-3 border border-gray-300">
        <input id="inviteLink" type="text" readonly class="bg-transparent text-sm w-full focus:outline-none" value="" />
        <button onclick="copyLink()" class="ml-2 text-[#f4b41a] font-medium hover:underline">Copy</button>
      </div>

      <button onclick="generateLink()" class="w-full bg-[#f4b41a] text-[#222] py-2 rounded-lg font-medium hover:bg-[#e3a918] transition">Generate New Link</button>
    </div>
  </div>

  <!-- ADD: EDIT BANNER MODAL -->
  <div id="editBannerModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[60]">
    <div class="edit-modal bg-white rounded-2xl shadow-xl p-5 relative">
      <button id="closeEditModal" class="absolute top-3 right-3 text-gray-500 hover:text-black">✕</button>

      <h3 class="text-lg font-semibold">Customize Banner</h3>
      <p class="text-sm text-gray-500 mb-4">Choose a color or upload an image for your banner</p>

      <p class="text-sm font-medium mb-2">Colors</p>
      <div id="colorSwatches" class="edit-modal-grid mb-4 relative"></div>

      <p class="text-sm font-medium mb-2">Image</p>
      <button id="uploadIconBtn" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm mb-4">Upload image</button>

      <p class="text-sm font-medium mb-2">Preview</p>
      <div id="bannerPreview" class="crop-aspect w-full rounded-lg overflow-hidden border border-gray-200 bg-gray-100 mb-4 flex items-end">
        <span class="m-3 text-white font-semibold drop-shadow">Banner preview</span>
      </div>

      <div class="flex justify-end gap-2">
        <button id="applyBannerBtn" class="px-4 py-2 rounded-lg bg-[#f4b41a] text-[#222] text-sm font-medium hover:bg-[#e3a918]">Apply</button>
      </div>
    </div>
  </div>

  <!-- ADD: CROP MODAL -->
  <div id="cropModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[70]">
    <div class="bg-white rounded-2xl shadow-xl p-5 w-[min(90vw,820px)]">
      <h3 class="text-lg font-semibold mb-3">Crop Banner</h3>
      <div class="crop-aspect bg-gray-100 rounded-lg overflow-hidden">
        <img id="cropImage" alt="To crop" />
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button onclick="closeCropModal()" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">Cancel</button>
        <button id="applyCropBtn" class="px-4 py-2 rounded-lg bg-[#f4b41a] text-[#222] text-sm font-medium hover:bg-[#e3a918]">Done</button>
      </div>
    </div>
  </div>

<!-- SCRIPTS -->
  <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>

      <script>
    // BANNER
    const banner = document.getElementById('planBanner');
    const bannerText = document.getElementById('bannerText');
    const editBannerBtn = document.getElementById('editBannerBtn');
    const bannerMenu = document.getElementById('bannerMenu');
    const bannerImageUpload = document.getElementById('bannerImageUpload');
    const resetBannerBtn = document.getElementById('resetBannerBtn');

    // MODAL ELEMENTS
    const editBannerModal = document.getElementById('editBannerModal');
    const closeEditModal = document.getElementById('closeEditModal');
    const colorSwatches = document.getElementById('colorSwatches');
    const uploadIconBtn = document.getElementById('uploadIconBtn');
    const bannerPreview = document.getElementById('bannerPreview');
    const applyBannerBtn = document.getElementById('applyBannerBtn');

    // MODAL HELPERS
    function initBannerPreviewFromCurrent() {
      if (!banner || !bannerPreview) return;
      const cs = getComputedStyle(banner);
      const bgImg = cs.backgroundImage;
      const bgCol = cs.backgroundColor;
      if (bgImg && bgImg !== 'none') {
        pending.type = 'image';
        pending.image = bgImg;
        pending.hex = null;
        pending.gradient = null;
        bannerPreview.style.backgroundImage = bgImg;
        bannerPreview.style.background = '';
      } else {
        pending.type = 'color';
        pending.hex = bgCol && bgCol !== 'rgba(0, 0, 0, 0)' ? bgCol : DEFAULT_SOLID;
        pending.image = null;
        pending.gradient = null;
        bannerPreview.style.backgroundImage = '';
        bannerPreview.style.background = pending.hex;
      }
      bannerPreview.style.backgroundSize = 'cover';
      bannerPreview.style.backgroundPosition = 'center';
    }
    function openEditModal() {
      if (!editBannerModal) return;
      editBannerModal.classList.remove('hidden');
      editBannerModal.classList.add('flex');
      initBannerPreviewFromCurrent();
    }
    function closeEditModalIfOpen() {
      if (!editBannerModal) return;
      editBannerModal.classList.add('hidden');
      editBannerModal.classList.remove('flex');
    }
    function closeCropModal() {
      if (!cropModal) return;
      cropModal.classList.add('hidden');
      cropModal.classList.remove('flex');
      if (cropper) { cropper.destroy(); cropper = null; }
    }

    // PENDING SELECTION STATE FOR MODAL
    // Make pending mutable and include imageData & gradient parts
    let pending = { type: 'color', hex: '#3b82f6', image: null, imageData: null, gradient: null, from: null, to: null };

   // CROPPER MODAL
    let cropper;
    const cropModal = document.getElementById('cropModal');
    const cropImage = document.getElementById('cropImage');
    const applyCropBtn = document.getElementById('applyCropBtn');

    // DEFAULT ONLY IF NOTHING SET INLINE
    const DEFAULT_SOLID = '#3b82f6';
    (function ensureBannerHasBackground() {
      const cs0 = getComputedStyle(banner);
      const noImg = !cs0.backgroundImage || cs0.backgroundImage === 'none';
      const noCol = !cs0.backgroundColor || cs0.backgroundColor === 'rgba(0, 0, 0, 0)';
      if (noImg && noCol) {
        banner.style.background = DEFAULT_SOLID;
        banner.style.backgroundSize = 'cover';
        banner.style.backgroundPosition = 'center';
        banner.style.color = '#fff';
      }
    })();

    // HELPERS
    function hexToRgb(hex) {
      const h = hex.replace('#',''); const n = parseInt(h,16);
      if (h.length === 6) return { r:(n>>16)&255, g:(n>>8)&255, b:n&255 };
      if (h.length === 3) return { r:parseInt(h[0]+h[0],16), g:parseInt(h[1]+h[1],16), b:parseInt(h[2]+h[2],16) };
      return { r:0,g:0,b:0 };
    }
    function getBrightnessFromRgb({r,g,b}) { return (r*299+g*587+b*114)/1000; }
    function setTextColorForHex(hex) { banner.style.color = getBrightnessFromRgb(hexToRgb(hex)) < 140 ? '#fff' : '#222'; }
    function setTextColorForGradient(fromHex,toHex){
      const a=hexToRgb(fromHex), b=hexToRgb(toHex);
      const avg={r:((a.r+b.r)/2)|0, g:((a.g+b.g)/2)|0, b:((a.b+b.b)/2)|0};
      banner.style.color = getBrightnessFromRgb(avg) < 140 ? '#fff' : '#222';
    }

    // CLOSE SMALL MENU ON OUTSIDE CLICK
    document.addEventListener('click', (e) => { if (banner && !banner.contains(e.target)) bannerMenu.classList.add('hidden'); });
    // OPEN MODAL FROM EDIT BUTTON
    editBannerBtn?.addEventListener('click', (e) => { e.stopPropagation(); openEditModal(); });

    // PRESETS
    const gradients = [
      { name: "Ocean Breeze", from: "#00c6ff", to: "#0072ff" },
      { name: "Sunset Glow", from: "#ff7e5f", to: "#feb47b" },
      { name: "Royal Purple", from: "#8360c3", to: "#2ebf91" },
      { name: "Pink Dream", from: "#ff9a9e", to: "#fad0c4" },
      { name: "Mango Fiesta", from: "#f9d423", to: "#ff4e50" },
      { name: "Aqua Chill", from: "#13547a", to: "#80d0c7" },
      { name: "Midnight Sky", from: "#2c3e50", to: "#4ca1af" },
      { name: "Lush Meadow", from: "#56ab2f", to: "#a8e063" },
      { name: "Berry Punch", from: "#ff416c", to: "#ff4b2b" },
      { name: "Cosmic Violet", from: "#8e2de2", to: "#4a00e0" },
      { name: "Aurora Mist", from: "#f7971e", to: "#ffd200" },
      { name: "Minty Sky", from: "#83a4d4", to: "#b6fbff" }
    ];
    const solidColors = [
      '#222222','#000000','#ffffff','#f4b41a','#ef4444',
      '#22c55e','#06b6d4','#3b82f6','#8b5cf6','#94a3b8',
      '#10b981'
    ];

    // UNIFIED GRID
    if (colorSwatches) {
      const wrap = document.createElement('div');
      wrap.style.width = 'var(--swatch-size)';
      wrap.style.height = 'var(--swatch-size)';
      wrap.style.position = 'relative';

      const pickerBtn = document.createElement('button');
      pickerBtn.className = 'color-picker-circle picker-donut';
      pickerBtn.title = 'Custom color';
      wrap.appendChild(pickerBtn);

      const pop = document.createElement('div');
      pop.className = 'swatch-popover hidden';
      pop.innerHTML = `<input id="inlinePicker" type="color" value="#3b82f6" style="width: 160px; height: 36px; border: 0; background: transparent; padding: 0;"/>`;
      wrap.appendChild(pop);
      colorSwatches.appendChild(wrap);

      const inlinePicker = pop.querySelector('#inlinePicker');

      function hidePop() { pop.classList.add('hidden'); }
      function showPop() { pop.classList.remove('hidden'); inlinePicker?.focus(); }

      pickerBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        pop.classList.toggle('hidden');
      });
      document.addEventListener('click', (e) => {
        if (!pop.contains(e.target) && e.target !== pickerBtn) hidePop();
      });

      inlinePicker?.addEventListener('input', () => {
        const hex = inlinePicker.value;
        pending.type = 'color';
        pending.hex = hex;
        pending.image = null;
        pending.gradient = null;
        if (bannerPreview) {
          bannerPreview.style.backgroundImage = '';
          bannerPreview.style.background = hex;
          bannerPreview.style.backgroundSize = 'cover';
          bannerPreview.style.backgroundPosition = 'center';
        }
      });
      inlinePicker?.addEventListener('change', hidePop);

      solidColors.slice(0,12).forEach((hex) => {
        const sw = document.createElement('button');
        sw.className = 'color-picker-circle';
        sw.title = hex;
        sw.style.background = hex;
        sw.addEventListener('click', () => {
          pending.type = 'color';
          pending.hex = hex;
          pending.image = null;
          pending.gradient = null;
          if (bannerPreview) {
            bannerPreview.style.backgroundImage = '';
            bannerPreview.style.background = hex;
            bannerPreview.style.backgroundSize = 'cover';
            bannerPreview.style.backgroundPosition = 'center';
          }
          hidePop();
        });
        colorSwatches.appendChild(sw);
      });

      gradients.slice(0,12).forEach(g => {
        const btn = document.createElement('button');
        btn.className = 'gradient-circle';
        btn.title = g.name;
        btn.style.background = `linear-gradient(90deg, ${g.from}, ${g.to})`;
        btn.addEventListener('click', () => {
          pending.type = 'gradient';
          pending.gradient = `linear-gradient(to right, ${g.from}, ${g.to})`;
          pending.hex = null; pending.image = null; pending.from = g.from; pending.to = g.to;
          if (bannerPreview) {
            bannerPreview.style.backgroundImage = 'none';
            bannerPreview.style.background = pending.gradient;
            bannerPreview.style.backgroundSize = 'cover';
            bannerPreview.style.backgroundPosition = 'center';
          }
          hidePop();
        });
        colorSwatches.appendChild(btn);
      });
    }

    // CLOSE SMALL MENU ON OUTSIDE CLICK
    document.addEventListener('click', (e) => { if (banner && !banner.contains(e.target)) bannerMenu.classList.add('hidden'); });
    // OPEN MODAL FROM EDIT BUTTON
    editBannerBtn?.addEventListener('click', (e) => { e.stopPropagation(); openEditModal(); });

    // UPLOAD, CROP, PREVIEW
    uploadIconBtn?.addEventListener('click', () => { bannerImageUpload?.click(); });

    bannerImageUpload?.addEventListener('change', (e) => {
      const file = e.target.files?.[0]; if (!file || !cropImage) return;
      const reader = new FileReader();
      reader.onload = (ev) => {
        cropImage.src = ev.target.result;
        cropModal.classList.remove('hidden'); cropModal.classList.add('flex');
        if (cropper) cropper.destroy();
        cropper = new Cropper(cropImage, { aspectRatio: 21/9, viewMode: 1, autoCropArea: 1, responsive: true, background: false, dragMode: 'move' });
      };
      reader.readAsDataURL(file);
    });

    // When cropping finishes, keep the raw data URL for saving to server
    applyCropBtn?.addEventListener('click', () => {
      if (!cropper) return;
      const canvas = cropper.getCroppedCanvas({ width: 2000, height: 800 });
      const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
      // Mutate instead of reassign (const reassignment was breaking things)
      pending.type = 'image';
      pending.imageData = dataUrl;
      pending.image = `url("${dataUrl}")`;
      pending.hex = null; pending.gradient = null;
      bannerPreview.style.backgroundImage = `url("${dataUrl}")`;
      bannerPreview.style.background = 'none';
      // Close crop modal & destroy cropper
      if (cropper) { cropper.destroy(); cropper = null; }
      cropModal.classList.add('hidden');
      cropModal.classList.remove('flex');
    });
    // WIRE MODAL CLOSE BEHAVIORS
   closeEditModal?.addEventListener('click', closeEditModalIfOpen);
   editBannerModal?.addEventListener('click', (e)=>{ if (e.target === editBannerModal) closeEditModalIfOpen(); });
   document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeEditModalIfOpen(); });

    // APPLY, WRITE PREVIEW TO THE BANNER; DONE, JUST CLOSE
   applyBannerBtn?.addEventListener('click', async () => {
       if (!pending?.type) return;
       const payload = { id: <?php echo (int)$id; ?>, type: pending.type };
       if (pending.type === 'image' && pending.imageData) payload.imageData = pending.imageData;
       if (pending.type === 'color') payload.color = pending.hex;
       if (pending.type === 'gradient') { payload.from = pending.from; payload.to = pending.to; }
       console.log('Banner payload:', payload);
       try {
        // Use consistent path casing (adjust if your server expects uppercase)
        const resp = await fetch('/DINADRAWING/Backend/api/event/save_banner.php', {
           method:'POST',
           headers:{'Content-Type':'application/json'},
           body: JSON.stringify(payload)
         });
         const res = await resp.json();
         console.log('Banner response:', res);
         if (!resp.ok || !res.success) {
           alert('Save failed: '+(res.detail||res.error||resp.status));
           return;
         }
         if (res.banner?.type === 'image' && res.banner.imageUrl) {
           const el = document.getElementById('planBanner');
           if (el){
             el.style.backgroundImage = `url("${res.banner.imageUrl}")`;
             el.style.backgroundSize = 'cover';
             el.style.backgroundPosition = 'center';
             el.style.backgroundColor = 'transparent';
             el.style.color = '#fff';
           }
         } else if (res.banner?.type === 'color' && payload.color) {
          banner.style.backgroundImage = 'none';
          banner.style.background = payload.color;
          setTextColorForHex(payload.color);
         } else if (res.banner?.type === 'gradient' && payload.from && payload.to) {
          banner.style.backgroundImage = 'none';
          banner.style.background = `linear-gradient(to right, ${payload.from}, ${payload.to})`;
          setTextColorForGradient(payload.from, payload.to);
         }
        // Close edit modal after successful apply
        closeEditModalIfOpen();
       } catch(e){
         console.error(e);
         alert('Network error saving banner');
       }
     });
  </script>

  <script>
    // POST BOX 
    const postInput = document.getElementById('postInput');
    const toolbar = document.getElementById('toolbar');
    const postActions = document.getElementById('postActions');

    function expandPostBox() {
      postInput?.classList.add('min-h-[120px]');
      toolbar?.classList.remove('hidden');
      postActions?.classList.remove('hidden');
    }
    function collapsePostBox() {
      postInput?.classList.remove('min-h-[120px]');
      toolbar?.classList.add('hidden');
      postActions?.classList.add('hidden');
    }
    postInput?.addEventListener('focus', expandPostBox);
    document.addEventListener('click', (e) => {
      const postBox = document.getElementById('postBox');s
      if (postBox && !postBox.contains(e.target) && (postInput?.innerText.trim() === '')) {
        collapsePostBox();
      }
    });
    function keepFocus(e){ e.preventDefault(); }
    function formatText(cmd){
      postInput?.focus();
      document.execCommand(cmd === 'underline' ? 'underline' : cmd, false, null);
    }
    function cancelPost(){ if (postInput) postInput.innerHTML=''; collapsePostBox(); }
    function submitPost(){ collapsePostBox(); }

    // POLL MODAL   
    function openPoll(){ document.getElementById('pollModal')?.classList.remove('hidden'); }
    function closePoll(){ document.getElementById('pollModal')?.classList.add('hidden'); }
    const pollAddOptionBtn = document.getElementById('pollAddOptionBtn');
    const pollOptionsContainer = document.getElementById('pollOptionsContainer');
    let pollOptionCount = (pollOptionsContainer?.children.length || 1);
    pollAddOptionBtn?.addEventListener('click', () => {
      pollOptionCount++;
      const input = document.createElement('input');
      input.type = 'text';
      input.placeholder = `Add option ${pollOptionCount}`;
      input.className = 'w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none';
      pollOptionsContainer?.appendChild(input);
    });

    // POLL POST MENU + ADD OPTION
    const pollMenuBtn = document.getElementById('pollOptionsBtn');
    const pollMenu = document.getElementById('pollOptionsMenu');
    pollMenuBtn?.addEventListener('click', (e)=>{ e.stopPropagation(); pollMenu?.classList.toggle('hidden'); });
    document.addEventListener('click', (e)=>{
      if (!pollMenu || !pollMenuBtn) return;
      if (!pollMenu.classList.contains('hidden') && !pollMenu.contains(e.target) && e.target !== pollMenuBtn){
        pollMenu.classList.add('hidden');
      }
    });
    const addOptionBtn = document.getElementById('addOptionBtn');
    const pollOptionsList = document.getElementById('pollOptions');
    addOptionBtn?.addEventListener('click', ()=>{
      const wrapper = document.createElement('div');
      wrapper.className = 'relative w-full bg-gray-200 rounded-full h-6 overflow-hidden flex items-center';
      wrapper.innerHTML = '<div class="absolute top-0 left-0 bg-[#f4b41a]/40 h-full w-[0%] rounded-full"></div><span class="relative z-10 text-xs text-black pl-3 font-medium" contenteditable="true">New option</span>';
      pollOptionsList?.appendChild(wrapper);
    });

    // ASSIGNED TASK POST
    const taskMenuBtn = document.getElementById('taskOptionsBtn');
    const taskMenu = document.getElementById('taskOptionsMenu');
    const addTaskBtn = document.getElementById('addTaskBtn');
    const taskList = document.getElementById('taskList');
    taskMenuBtn?.addEventListener('click', (e)=>{ e.stopPropagation(); taskMenu?.classList.toggle('hidden'); });
    document.addEventListener('click', (e)=>{
      if (!taskMenu || !taskMenuBtn) return;
      if (!taskMenu.classList.contains('hidden') && !taskMenu.contains(e.target) && e.target !== taskMenuBtn){
        taskMenu.classList.add('hidden');
      }
    });
    addTaskBtn?.addEventListener('click', ()=>{
      const row = document.createElement('div');
      row.className = 'flex items-center justify-between bg-white rounded-full border px-4 py-1.5';
      row.innerHTML = `
        <span contenteditable="true" class="text-sm font-medium text-gray-700 outline-none">New Task</span>
        <span contenteditable="true" class="text-xs text-gray-500 outline-none">@mention</span>
      `;
      taskList?.appendChild(row);
    });

    // TASK MODAL 
    function openTask(){ document.getElementById('taskModal')?.classList.remove('hidden'); }
    function closeTask(){ document.getElementById('taskModal')?.classList.add('hidden'); }
    const taskAddOptionBtn = document.getElementById('taskAddOptionBtn');
    const taskOptionsContainer = document.getElementById('taskOptionsContainer');
    let taskCount = (taskOptionsContainer?.children.length || 1);
    taskAddOptionBtn?.addEventListener('click', ()=>{
      taskCount++;
      const input = document.createElement('input');
      input.type = 'text';
      input.placeholder = `Add task ${taskCount}`;
      input.className = 'w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none';
      taskOptionsContainer?.appendChild(input);
    });

</script>

  <script>
    // INVITE PEOPLE
    const inviteModal = document.getElementById('inviteModal');
    const inviteLinkInput = document.getElementById('inviteLink');

    function openInvite() {
      inviteModal?.classList.remove('hidden');
      inviteModal?.classList.add('flex');
      generateLink();
    }

    function closeInvite() {
      inviteModal?.classList.add('hidden');
      inviteModal?.classList.remove('flex');
    }

    function generateLink() {
      const code = Math.random().toString(36).slice(2, 10);
      const inviteLink = `https://dinadrawing.com/invite?event=<?php echo (int)$id; ?>&code=${code}`;
      if (inviteLinkInput) inviteLinkInput.value = inviteLink;
    }

    function copyLink() {
      const text = inviteLinkInput?.value || '';
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text).then(() => alert('Copied to clipboard!'));
      } else if (inviteLinkInput) {
        inviteLinkInput.select();
        document.execCommand('copy');
        alert('Copied to clipboard!');
      }
    }
    
  </script>

  <!-- BUDGET SETUP MODAL -->
  <div id="budgetModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[65] hidden items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-auto relative">
      <div id="budgetHeader" class="flex justify-between items-center mb-4">
        <div class="flex flex-col">
          <p id="budgetStepLabel" class="text-sm text-gray-600">Step 1 of 3</p>
          <h2 id="budgetStepTitle" class="text-xl items-center font-semibold text-[#222]">Add Expenses</h2>
        </div>
        <button id="closeBudgetModal" class="text-gray-500 hover:text-black text-xl font-bold">&times;</button>
      </div>

      <!-- STEP 1 -->
      <div id="budgetStep1" class="budget-step">
        <p class="text-gray-500 text-sm mb-3">List down your expected expenses below.</p>
        <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden mb-3">
          <thead class="bg-gray-100">
            <tr><th class="py-2 px-3 text-left">Expense</th><th class="py-2 px-3 text-right">Estimated Cost (₱)</th></tr>
          </thead>
          <tbody id="expenseTableBody">
            <tr><td><input type="text" placeholder="e.g. Venue rental" class="w-full border-none p-2 outline-none"></td><td><input type="number" placeholder="0" class="w-full border-none p-2 text-right outline-none"></td></tr>
            <tr><td><input type="text" placeholder="e.g. Decorations" class="w-full border-none p-2 outline-none"></td><td><input type="number" placeholder="0" class="w-full border-none p-2 text-right outline-none"></td></tr>
            <tr><td><input type="text" placeholder="e.g. Entertainment" class="w-full border-none p-2 outline-none"></td><td><input type="number" placeholder="0" class="w-full border-none p-2 text-right outline-none"></td></tr>
            <tr><td><input type="text" placeholder="e.g. Food & drinks" class="w-full border-none p-2 outline-none"></td><td><input type="number" placeholder="0" class="w-full border-none p-2 text-right outline-none"></td></tr>
          </tbody>
        </table>
        <button id="addExpenseRow" class="mt-2 mb-4 w-full py-2.5 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:border-[#f4b41a] hover:text-[#f4b41a] hover:bg-yellow-50 transition font-medium flex items-center justify-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add New Expense
        </button>
        <div class="flex justify-between items-center mt-2">
          <span class="font-medium text-gray-700">Total Estimated:</span>
          <span id="totalCost" class="font-semibold text-gray-800">₱0</span>
        </div>
        <div class="mt-6 flex justify-end">
          <button id="toStep2" class="bg-yellow-500 hover:bg-yellow-600 text-[#222] px-4 py-2 rounded-lg font-medium">Next</button>
        </div>
      </div>

      <!-- STEP 2 -->
      <div id="budgetStep2" class="budget-step hidden">
        <p class="text-sm text-gray-600 mb-4">Let's divide ₱<span id="totalEstimatedStep2">0</span> among your members.</p>
        <div class="space-y-2 mb-3">
          <label class="flex items-center gap-2"><input type="radio" name="division" value="equal" checked/><span>Split Equally</span></label>
          <label class="flex items-center gap-2"><input type="radio" name="division" value="custom"/><span>Custom Allocation</span></label>
        </div>
        <div class="overflow-x-auto border rounded-lg mb-3">
          <table class="w-full text-sm" id="memberTable">
            <thead class="bg-gray-100 text-left"><tr><th class="py-2 px-3">Member</th><th class="py-2 px-3 w-32">Amount (₱)</th><th class="py-2 px-3 w-10"></th></tr></thead>
            <tbody id="memberTableBody"></tbody>
            <tfoot class="border-t bg-gray-50"><tr><td class="py-2 px-3 font-semibold">Total</td><td class="py-2 px-3 font-semibold text-right" id="divisionTotal">₱0</td><td></td></tr></tfoot>
          </table>
        </div>
        <button id="addMemberBtn" class="mt-2 mb-4 w-full py-2.5 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:border-[#f4b41a] hover:text-[#f4b41a] hover:bg-yellow-50 transition font-medium flex items-center justify-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add New Member
        </button>
        <div class="flex justify-between mt-4">
          <button id="backFromStep2" class="text-gray-600 hover:text-black px-4 py-2 rounded-lg font-medium">← Back</button>
          <button id="toStep3" class="bg-[#f4b41a] text-[#222] px-5 py-2 rounded-lg font-semibold hover:bg-[#e3a918] transition">Save & Continue →</button>
        </div>
      </div>

      <!-- STEP 3 -->
      <div id="budgetStep3" class="budget-step hidden">
        <p class="text-gray-500 text-sm mb-4">Here's a summary before saving your budget plan.</p>
        <div class="border border-gray-200 rounded-lg p-4 space-y-2 text-sm text-gray-700">
          <p><strong>Total Budget:</strong> <span id="summaryTotal">₱0</span></p>
          <p><strong>Division:</strong> <span id="summaryDivision">Split equally</span></p>
          <p><strong>Members:</strong> <span id="summaryMembers">0</span></p>
        </div>
        <div class="mt-6 flex justify-between">
          <button id="backToStep2" class="text-gray-600 hover:text-black px-4 py-2 rounded-lg font-medium">Back</button>
          <button id="saveBudgetPlan" class="bg-yellow-500 hover:bg-yellow-600 text-[#222] px-4 py-2 rounded-lg font-medium">Confirm & Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- CHANGE PROFILE MODAL -->
  <div id="changeProfileModal" class="fixed inset-0 flex items-center justify-center bg-black/40 backdrop-blur-sm z-50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl relative">
      <button id="cancelBtn" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
      <h2 class="text-2xl font-bold mb-1">Change Profile Photo</h2>
      <p class="text-sm text-gray-500 mb-4">Choose or upload a new profile photo</p>
      <div class="flex justify-center mb-4">
        <img id="profilePreview" src="Assets/Profile Icon/profile.png" alt="Profile Preview" class="w-32 h-32 rounded-full object-cover border shadow-md" style="border-color: #090404;" />
      </div>
      <div class="flex justify-center gap-4 mb-4">
        <div id="uploadBtn" class="w-8 h-8 rounded-full border flex items-center justify-center cursor-pointer hover:bg-gray-100 transition" style="border-color: #090404;">
          <img src="Assets/upload.png" alt="Upload" class="w-6 h-6 opacity-70">
        </div>
        <img src="Assets/Profile Icon/Profile8.png" class="w-12 h-12 rounded-full cursor-pointer border hover:border-yellow-500" />
      </div>
      <div class="flex justify-end gap-3">
        <button class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition" id="cancelBtnFooter">Cancel</button>
        <button class="bg-[#f4b41a] px-4 py-2 rounded-lg text-black hover:bg-[#e0a419] transition" id="saveChangesBtn">Save Changes</button>
      </div>
    </div>
  </div>

  <script>
    // TAB SWITCHING
    function switchTab(tabName) {
      // Hide all sections
      document.getElementById('feed-section').classList.remove('active');
      document.getElementById('budget-section').classList.remove('active');
      document.getElementById('settings-section').classList.remove('active');
      
      // Show selected section
      document.getElementById(tabName + '-section').classList.add('active');
      
      // Update tab button styles
      ['feed', 'budget', 'settings'].forEach(tab => {
        const btn = document.getElementById('tab-' + tab);
        if ( tab === tabName) {
          btn.className = 'flex-1 bg-[#f4b41a] text-[#222] font-semibold py-2 text-center rounded-lg cursor-pointer';
        } else {
          btn.className = 'flex-1 text-gray-600 font-medium py-2 text-center hover:text-[#222] hover:bg-gray-100 rounded-lg cursor-pointer';
        }
      });
      
      // Keep right sidebar always (no hide in settings)
      // If you ever need conditional layout changes, adjust main container width instead.
    }
  </script>

  <script>
    // EVENT DETAILS SAVE BUTTON
    document.getElementById("saveSettingsBtn")?.addEventListener("click", () => {
      const payload = {
        id: <?php echo (int)$id; ?>,
        name: document.getElementById("eventName").value,
        description: document.getElementById("eventDesc").value,
        date: document.getElementById("eventDate").value || null,
        place: document.getElementById("eventPlace").value || null
      };
      fetch('update_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(r => r.json()).then(res => {
        if (res.success) {
          alert('Event saved');
          // Optionally refresh the page or update UI
        } else {
          alert('Save failed: ' + (res.error || 'unknown'));
        }
      }).catch(()=> alert('Save failed'));
    });
    
    // Delete event (call server)
    document.getElementById('deleteEventBtn')?.addEventListener('click', () => {
      if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) return;
      fetch('delete_event.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ id: <?php echo (int)$id; ?> })
      }).then(r=>r.json()).then(res=>{
        if (res.success) window.location.href='myplans.php';
        else alert('Delete failed: '+(res.error||'unknown'));
      }).catch(()=>alert('Delete failed'));
    });
  </script>

  <script>
    // BUDGET WIZARD
    document.addEventListener("DOMContentLoaded", () => {
      const modal = document.getElementById("budgetModal");
      const openBtn = document.getElementById("openBudgetModal");
      const closeBtn = document.getElementById("closeBudgetModal");
      const steps = [
        { id: "budgetStep1", title: "Add Expenses" },
        { id: "budgetStep2", title: "Set Division" },
        { id: "budgetStep3", title: "Confirm Plan" }
      ];
      let currentStep = 0;
      const stepLabel = document.getElementById("budgetStepLabel");
      const stepTitle = document.getElementById("budgetStepTitle");

      function showStep(i){
        steps.forEach((s,idx)=>document.getElementById(s.id).classList.toggle("hidden", idx!==i));
        stepLabel.textContent = `Step ${i+1} of 3`;
        stepTitle.textContent = steps[i].title;
      }

      openBtn?.addEventListener('click', ()=>{ modal.classList.remove('hidden'); modal.classList.add('flex'); currentStep=0; showStep(currentStep); });
      closeBtn?.addEventListener('click', ()=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); currentStep=0; showStep(currentStep); });
      modal?.addEventListener('click', (e)=>{ if (e.target===modal){ closeBtn.click(); }});

      const eventMembers = [
        { name: "Juan Dela Cruz", username: "@juan_dc", avatar: "Assets/Profile Icon/profile.png" },
        { name: "Maria Santos", username: "@maria_s", avatar: "Assets/Profile Icon/profile2.png" },
        { name: "Pedro Reyes", username: "@pedro_r", avatar: "Assets/Profile Icon/profile3.png" },
        { name: "Ana Garcia", username: "@ana_g", avatar: "Assets/Profile Icon/profile4.png" }
      ];
      let memberCounter = eventMembers.length;
      let budgetData = null;

      function initMemberList() {
        const tbody = document.getElementById("memberTableBody");
        const divisionType = document.querySelector('input[name="division"]:checked').value;
        const total = parseFloat((document.getElementById("totalCost").textContent || '0').replace(/[₱,]/g,'')) || 0;
        tbody.innerHTML = "";
        memberCounter = eventMembers.length;
        eventMembers.forEach(m=>{
          const amount = divisionType==="equal" ? (total/eventMembers.length).toFixed(2) : "0.00";
          addMemberRow(m, amount, divisionType);
        });
        updateDivisionTotal();
      }

      function addMemberRow(member, amount="0.00", divisionType=null){
        const tbody = document.getElementById("memberTableBody");
        const currentDivision = divisionType || document.querySelector('input[name="division"]:checked').value;
        const isReadOnly = currentDivision === "equal";
        const row = document.createElement("tr");
        row.className = "border-t";
        row.innerHTML = `
          <td class="py-2 px-3">
            <div class="flex items-center gap-2">
              <img src="${member.avatar||'Assets/Profile Icon/profile.png'}" class="w-6 h-6 rounded-full border" alt="">
              <div class="flex-1">
                <input type="text" value="${member.name}" class="text-sm font-medium w-full border-none outline-none p-0 member-name" placeholder="Member name">
                ${member.username?`<div class="text-xs text-gray-500">${member.username}</div>`:''}
              </div>
            </div>
          </td>
          <td class="py-2 px-3">
            <input type="number" value="${amount}" class="w-full border rounded px-2 py-1 text-right member-amount ${isReadOnly?'bg-gray-100 cursor-not-allowed':''}" placeholder="0.00" step="0.01" min="0" ${isReadOnly?'readonly':''}>
          </td>
          <td class="py-2 px-3 text-center">
            <button class="text-red-500 hover:text-red-700 delete-member" title="Remove member">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </td>`;

        tbody.appendChild(row);
        if (!isReadOnly) row.querySelector('.member-amount').addEventListener('input', updateDivisionTotal);
        row.querySelector('.delete-member').addEventListener('click', ()=>{
          row.remove();
          if ((document.querySelector('input[name="division"]:checked')?.value) === "equal") recalcEqualSplit();
          updateDivisionTotal();
        });
      }

      function recalcEqualSplit(){
        const total = parseFloat((document.getElementById("totalCost").textContent||'0').replace(/[₱,]/g,''))||0;
        const inputs = document.querySelectorAll('.member-amount');
        const n = inputs.length || 1;
        const per = (total/n).toFixed(2);
        inputs.forEach(inp=>inp.value = per);
      }

      function updateDivisionTotal(){
        let sum = 0;
        document.querySelectorAll('.member-amount').forEach(i=>sum += parseFloat(i.value)||0);
        const out = document.getElementById("divisionTotal");
        if (out) out.textContent = `₱${sum.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`;
      }

      document.querySelectorAll('input[name="division"]').forEach(r=>{
        r.addEventListener('change', ()=>{
          const type = r.value;
          const inputs = document.querySelectorAll('.member-amount');
          if (type==="equal"){
            recalcEqualSplit();
            inputs.forEach(i=>{ i.readOnly=true; i.classList.add('bg-gray-100','cursor-not-allowed'); });
          } else {
            inputs.forEach(i=>{ i.readOnly=false; i.classList.remove('bg-gray-100','cursor-not-allowed'); });
          }
          updateDivisionTotal();
        });
      });

      document.getElementById("addMemberBtn").addEventListener('click', ()=>{
        memberCounter++;
        const type = document.querySelector('input[name="division"]:checked').value;
        const total = parseFloat((document.getElementById("totalCost").textContent||'0').replace(/[₱,]/g,''))||0;
        const newMember = { name:`Member ${memberCounter}`, username: "", avatar: "Assets/Profile Icon/profile.png" };
        let amount = "0.00";
        if (type==="equal"){
          const current = document.querySelectorAll('.member-amount').length;
          amount = (total/(current+1)).toFixed(2);
        }
        addMemberRow(newMember, amount, type);
        if (type==="equal") recalcEqualSplit();
        updateDivisionTotal();
      });

      document.getElementById("toStep2").addEventListener('click', ()=>{
        currentStep = 1;
        showStep(currentStep);
        const numeric = parseFloat((document.getElementById("totalCost").textContent||'0').replace(/[₱,]/g,''))||0;
        document.getElementById("totalEstimatedStep2").innerText = numeric.toLocaleString();
        initMemberList();
      });
      
      document.getElementById("backFromStep2").addEventListener('click', ()=>{ currentStep = 0; showStep(currentStep); });
      
      document.getElementById("toStep3").addEventListener('click', ()=>{
        document.getElementById("summaryTotal").textContent = document.getElementById("totalCost").textContent;
        const divType = document.querySelector("input[name='division']:checked").value;
        document.getElementById("summaryDivision").textContent = divType==="equal" ? "Split equally" : "Custom amounts";
        document.getElementById("summaryMembers").textContent = document.querySelectorAll('.member-amount').length;
        currentStep = 2;
        showStep(currentStep);
      });
      
      document.getElementById("backToStep2").addEventListener('click', ()=>{ currentStep = 1; showStep(currentStep); });

      document.getElementById("saveBudgetPlan").addEventListener('click', ()=>{
        const expenses = [];
        document.querySelectorAll('#expenseTableBody tr').forEach(row=>{
          const name = row.querySelector('input[type="text"]')?.value?.trim() || "Untitled Expense";
          const est = parseFloat(row.querySelector('input[type="number"]')?.value || "0") || 0;
          if (name || est) expenses.push({ name, estimated: est, actual: 0, paid: false });
        });
        
        const contributions = [];
        document.querySelectorAll('#memberTableBody tr').forEach(row=>{
          const name = row.querySelector('.member-name')?.value || 'Member';
          const amt = parseFloat(row.querySelector('.member-amount')?.value || "0") || 0;
          const avatar = row.querySelector('img')?.src || "Assets/Profile Icon/profile.png";
          contributions.push({ name, amount: amt, paid: false, avatar });
        });
        
        budgetData = { expenses, contributions, totalBudget: expenses.reduce((s,e)=>s+e.estimated,0) };
        renderBudget();
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentStep=0;
        showStep(currentStep);
      });

      const expenseTable = document.getElementById("expenseTableBody");
      document.getElementById("addExpenseRow").addEventListener("click", ()=>{
        const tr = document.createElement("tr");
        tr.innerHTML = `<td><input type="text" placeholder="e.g. New expense" class="w-full border-none p-2 outline-none"></td>
                        <td><input type="number" placeholder="0" class="w-full border-none p-2 text-right outline-none"></td>`;
        expenseTable.appendChild(tr);
      });
      
      expenseTable.addEventListener("input", ()=>{
        let total = 0;
        expenseTable.querySelectorAll("input[type='number']").forEach(i=>{ total += parseFloat(i.value)||0; });
        document.getElementById("totalCost").textContent = `₱${total.toLocaleString()}`;
      });

      function renderBudget(){
        if (!budgetData) return;
        document.getElementById('noBudgetView').classList.add('hidden');
        document.getElementById('budgetView').classList.remove('hidden');
        updateSummaryCards();
        renderExpenseTable();
        renderContributionTable();
      }
      
      function updateSummaryCards(){
        const total = budgetData.totalBudget;
        const collected = budgetData.contributions.filter(c=>c.paid).reduce((s,c)=>s+c.amount,0);
        const remain = Math.max(0, total - collected);
        const pct = total>0 ? Math.round((collected/total)*100) : 0;
        document.getElementById('displayTotalBudget').textContent = `₱${total.toLocaleString()}`;
        document.getElementById('displayMoneyCollected').textContent = `₱${collected.toLocaleString()}`;
        document.getElementById('displayBalanceRemaining').textContent = `₱${remain.toLocaleString()}`;
        document.getElementById('progressPercentage').textContent = `${pct}%`;
        document.getElementById('progressBar').style.width = `${pct}%`;
      }

      function renderExpenseTable(){
        const tbody = document.getElementById('expenseDisplayBody');
        tbody.innerHTML = '';
        budgetData.expenses.forEach((e,idx)=>{
          const tr = document.createElement('tr');
          tr.className='hover:bg-gray-50';
          tr.innerHTML = `
            <td class="py-3 px-4 font-medium text-gray-700">${e.name}</td>
            <td class="py-3 px-4 text-right text-gray-600">₱${e.estimated.toLocaleString()}</td>
            <td class="py-3 px-4 text-right">
              <input type="number" value="${e.actual||0}" class="w-20 border rounded px-2 py-1 text-right actual-cost-input text-sm" data-index="${idx}" step="0.01" min="0">
            </td>
            <td class="py-3 px-4 text-center">
              <span class="status-badge inline-block px-3 py-1.5 rounded-full text-xs font-semibold ${e.paid?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700'} expense-status-badge" data-index="${idx}">
                ${e.paid?'✓ Paid':'Pending'}
              </span>
            </td>
            <td class="py-3 px-4 text-center">
              <button class="text-red-400 hover:text-red-600 delete-expense-btn" data-index="${idx}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </td>`;
          tbody.appendChild(tr);
        });

        document.querySelectorAll('.actual-cost-input').forEach(inp=>{
          inp.addEventListener('change', e=>{
            const i = +e.target.dataset.index;
            budgetData.expenses[i].actual = parseFloat(e.target.value)||0;
            updateExpenseTotals();
          });
        });
        
        document.querySelectorAll('.expense-status-badge').forEach(b=>{
          b.addEventListener('click', e=>{
            const i = +e.target.dataset.index;
            const v = budgetData.expenses[i];
            v.paid = !v.paid;
            e.target.className = `status-badge inline-block px-3 py-1.5 rounded-full text-xs font-semibold ${v.paid?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700'} expense-status-badge`;
            e.target.textContent = v.paid ? '✓ Paid' : 'Pending';
          });
        });
        
        document.querySelectorAll('.delete-expense-btn').forEach(btn=>{
          btn.addEventListener('click', e=>{
            const i = +(e.currentTarget.dataset.index);
            budgetData.expenses.splice(i,1);
            renderExpenseTable();
            updateExpenseTotals();
          });
        });
        updateExpenseTotals();
      }
      
      function updateExpenseTotals(){
        const totalEst = budgetData.expenses.reduce((s,e)=>s+e.estimated,0);
        const totalAct = budgetData.expenses.reduce((s,e)=>s+(e.actual||0),0);
        document.getElementById('totalEstimated').textContent = `₱${totalEst.toLocaleString()}`;
        document.getElementById('totalActual').textContent = `₱${totalAct.toLocaleString()}`;
      }

      function renderContributionTable(){
        const tbody = document.getElementById('contributionDisplayBody');
        tbody.innerHTML = '';
        budgetData.contributions.forEach((m,idx)=>{
          const tr = document.createElement('tr');
          tr.className='hover:bg-gray-50';
          tr.innerHTML = `
            <td class="py-3 px-4"><div class="flex items-center gap-3">
              <img src="${m.avatar}" class="w-10 h-10 rounded-full border-2 border-gray-200" alt=""><span class="font-medium text-gray-700">${m.name}</span></div>
            </td>
            <td class="py-3 px-4 text-right font-semibold text-gray-800">₱${m.amount.toLocaleString()}</td>
            <td class="py-3 px-4 text-center">
              <span class="status-badge inline-block px-4 py-2 rounded-full text-xs font-bold ${m.paid?'bg-green-100 text-green-700':'bg-orange-100 text-orange-700'} member-payment-badge" data-index="${idx}">
                ${m.paid?'✓ Paid' : 'Unpaid'}
              </span>
            </td>`;
          tbody.appendChild(tr);
        });
        
        document.querySelectorAll('.member-payment-badge').forEach(b=>{
          b.addEventListener('click', e=>{
            const i = +e.target.dataset.index;
            const v = budgetData.contributions[i];
            v.paid = !v.paid;
            e.target.className = `status-badge inline-block px-4 py-2 rounded-full text-xs font-bold ${v.paid?'bg-green-100 text-green-700':'bg-orange-100 text-orange-700'} member-payment-badge`;
            e.target.textContent = v.paid ? '✓ Paid' : 'Unpaid';
            updateSummaryCards();
          });
        });
      }

      let editOnlyMode = false;

      function toggleWizardControls(visible) {
        ['toStep2','backFromStep2','toStep3','backToStep2','saveBudgetPlan'].forEach(id=>{
          const el = document.getElementById(id);
          if (!el) return;
          el.style.display = visible ? '' : 'none';
        });
      }

      function ensureApplyButton() {
        let btn = document.getElementById('applyEditsBtn');
        if (btn) return btn;
        btn = document.createElement('button');
        btn.id = 'applyEditsBtn';
        btn.className = 'mt-4 bg-[#f4b41a] text-[#222] px-4 py-2 rounded-lg font-medium';
        btn.textContent = 'Apply Changes';
        btn.addEventListener('click', applyEditsAndClose);
        const container = document.querySelector('#budgetModal > div');
        if (container) container.appendChild(btn);
        return btn;
      }
      
      function enterEditModal(step = 0) {
        if (!budgetData) return;
        editOnlyMode = true;
        populateExpensesForEdit();
        populateContributionsForEdit();
        currentStep = step;
        showStep(currentStep);
        toggleWizardControls(false);
        ensureApplyButton().style.display = '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
      }

      function exitEditMode() {
        editOnlyMode = false;
        toggleWizardControls(true);
        const b = document.getElementById('applyEditsBtn');
        if (b) b.style.display = 'none';
      }
      
      function applyEditsAndClose() {
        const expenses = [];
        document.querySelectorAll('#expenseTableBody tr').forEach(row=>{
          const name = row.querySelector('input[type="text"]')?.value?.trim() || "Untitled Expense";
          const est = parseFloat(row.querySelector('input[type="number"]')?.value || "0") || 0;
          expenses.push({ name, estimated: est, actual: 0, paid: false });
        });
        
        const contributions = [];
        document.querySelectorAll('#memberTableBody tr').forEach(row=>{
          const name = row.querySelector('.member-name')?.value || 'Member';
          const amt = parseFloat(row.querySelector('.member-amount')?.value || "0") || 0;
          const avatar = row.querySelector('img')?.src || "Assets/Profile Icon/profile.png";
          contributions.push({ name, amount: amt, paid: false, avatar });
        });
        
        budgetData = { expenses, contributions, totalBudget: expenses.reduce((s,e)=>s+e.estimated,0) };
        renderBudget();
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentStep = 0;
        showStep(currentStep);
        exitEditMode();
      }
      
      function populateExpensesForEdit() {
        const tbody = document.getElementById('expenseTableBody');
        if (!tbody || !budgetData) return;
        tbody.innerHTML = '';
        budgetData.expenses.forEach(expense => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td><input type="text" value="${expense.name}" class="w-full border-none p-2 outline-none"></td>
            <td><input type="number" value="${expense.estimated}" class="w-full border-none p-2 text-right outline-none"></td>
          `;
          tbody.appendChild(row);
        });
        tbody.dispatchEvent(new Event('input'));
      }
      
      function populateContributionsForEdit() {
        const tbody = document.getElementById('memberTableBody');
        if (!tbody || !budgetData) return;
        tbody.innerHTML = '';
        budgetData.contributions.forEach(member => {
          const memberData = { name: member.name, username: '', avatar: member.avatar };
          addMemberRow(memberData, (member.amount||0).toFixed(2), 'custom');
        });
        updateDivisionTotal();
      }

      document.getElementById('editExpensesBtn')?.addEventListener('click', () => { enterEditModal(0); });
      document.getElementById('editContributionsBtn')?.addEventListener('click', () => {
        document.getElementById("totalEstimatedStep2").innerText = (budgetData?.totalBudget||0).toLocaleString();
        enterEditModal(1);
      });

      const _btn = document.getElementById('applyEditsBtn');
      if (_btn) _btn.style.display = 'none';
    });
  </script>

  <script>

async function createEventAndOpenPlan() {
  const payload = {
    name: document.getElementById('createName').value,
    description: document.getElementById('createDescription').value,
    date: document.getElementById('createDate').value || null,
    time: document.getElementById('createTime').value || null,
    location: document.getElementById('createLocation').value || null
  };

  const res = await fetch('/DINADRAWING/Backend/api/event/create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  const data = await res.json();
  if (res.ok && data.success && data.id) {
    // Redirect to the plan page for the new event
    window.location.href = `/DINADRAWING/plan.php?id=${data.id}`;
  } else {
    alert(`Create failed: ${data.message || 'unknown error'}`);
  }
}

// Wire to your modal’s final “Save & Continue” button
document.getElementById('createEventSubmitBtn')?.addEventListener('click', (e) => {
  e.preventDefault();
  createEventAndOpenPlan();
});
  </script>
</body>
</html>
