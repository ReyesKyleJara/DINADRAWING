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

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$id = $_GET['id'] ?? null;

// 1. Basic Validation
if (!$id) die("Event ID missing.");
if (!$user_id) { header("Location: login.php"); exit; }

// 2. Fetch Event
$stmt = $conn->prepare("SELECT * FROM events WHERE id = :id");
$stmt->bindValue(":id", $id, PDO::PARAM_INT);
$stmt->execute();
$event = $stmt->fetch();

if (!$event) die("Event not found.");

// =========================================================
// 3. ACCESS CONTROL (THE IMPORTANT PART)
// =========================================================
// Fix: Use 'owner_id' as defined in your new SQL schema
$is_creator = ($event['owner_id'] == $user_id); 
$is_member = false;

// Check if user is in event_members table
$memStmt = $conn->prepare("SELECT role FROM event_members WHERE event_id = :eid AND user_id = :uid");
$memStmt->execute([':eid' => $id, ':uid' => $user_id]);
$membership = $memStmt->fetch();

if ($membership) {
    $is_member = true;
}

// If they are the creator but NOT in the members list, add them automatically
if ($is_creator && !$is_member) {
    $conn->prepare("INSERT INTO event_members (event_id, user_id, role) VALUES (?, ?, 'owner')")->execute([$id, $user_id]);
    $is_member = true;
}

// If neither creator nor member, BLOCK THEM
if (!$is_creator && !$is_member) {
    die("
        <div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
            <h1>Access Denied</h1>
            <p>You are not a member of this plan.</p>
            <a href='myplans.php' style='color:blue; text-decoration:underline;'>Go Back to My Plans</a>
        </div>
    ");
}
// =========================================================

// 4. Setup Variables for the Page
$event_name = htmlspecialchars($event['name'] ?? 'Untitled Event', ENT_QUOTES, 'UTF-8');
$event_desc = htmlspecialchars($event['description'] ?? '', ENT_QUOTES, 'UTF-8');
$event_place = htmlspecialchars($event['location'] ?? '', ENT_QUOTES, 'UTF-8');
$invite_code = htmlspecialchars($event['invite_code'] ?? 'No Code', ENT_QUOTES, 'UTF-8');

// Detect Link
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$invite_link = "$protocol://$_SERVER[HTTP_HOST]/DINADRAWING/join.php?code=" . $event['invite_code'];

// ... after security checks ...

// ============================================
// 5. FETCH MEMBERS LIST
// ============================================
$membersStmt = $conn->prepare("
    SELECT u.id, u.username, u.name, u.profile_picture, em.role 
    FROM event_members em
    JOIN users u ON em.user_id = u.id
    WHERE em.event_id = :eid
    ORDER BY em.role DESC, em.joined_at ASC
");
$membersStmt->execute([':eid' => $id]);
$members = $membersStmt->fetchAll();
$memberCount = count($members);

// Date Logic
$event_date_val = '';
$rawDate = $event['datetime'] ?? null;
if(!$rawDate && !empty($event['date'])) $rawDate = $event['date'] . ' ' . ($event['time'] ?? '');
if ($rawDate) $event_date_val = date('Y-m-d\TH:i', strtotime($rawDate));

// Banner Logic
$banner_type = $event['banner_type'] ?? null;
$banner_color = $event['banner_color'] ?? null;
$banner_image = $event['banner_image'] ?? null;
$banner_from = $event['banner_from'] ?? null;
$banner_to = $event['banner_to'] ?? null;

function banner_style($type,$color,$from,$to,$image){
  if ($type==='image' && $image){
    // Clean path logic
    $path = strpos($image, 'Assets') === 0 ? '/DINADRAWING/' . $image : $image;
    return "background-image:url('".htmlspecialchars($path,ENT_QUOTES,'UTF-8')."');background-size:cover;background-position:center;color:#fff;";
  }
  if ($type==='gradient' && $from && $to)
    return "background:linear-gradient(to right,".htmlspecialchars($from,ENT_QUOTES,'UTF-8').",".htmlspecialchars($to,ENT_QUOTES,'UTF-8').");color:#111;";
  if ($type==='color' && $color)
    return "background:".htmlspecialchars($color,ENT_QUOTES,'UTF-8').";color:#fff;";
  return "background:linear-gradient(to right,#3b82f6,#9333ea);color:#fff;";
}
$banner_inline = banner_style($banner_type,$banner_color,$banner_from,$banner_to,$banner_image);

// Current User Avatar & Name
$currentUserAvatar = 'Assets/Profile Icon/profile.png';
$currentUserName = 'User'; // Default fallback

if ($user_id) {
    // FIX: Added 'name' to the SELECT list
    $uStmt = $conn->prepare("SELECT name, profile_picture FROM users WHERE id = :uid");
    $uStmt->execute([':uid' => $user_id]);
    $userRow = $uStmt->fetch();
    
    if ($userRow) {
        $currentUserName = htmlspecialchars($userRow['name']); // Save the name for JS
        
        if (!empty($userRow['profile_picture'])) {
            $dbPic = $userRow['profile_picture'];
            if (strpos($dbPic, 'data:') === 0 || strpos($dbPic, 'http') === 0) {
                $currentUserAvatar = $dbPic;
            } else {
                $currentUserAvatar = str_replace('DINADRAWING/', '', ltrim($dbPic, '/'));
            }
        }
    }
}
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

    /* Chrome, Safari, Edge */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox */
input[type="number"] {
  -moz-appearance: textfield;
}



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
      <li><a href="dashboard.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Home</a></li>
      <li><a href="myplans.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">My Plans</a></li>
      <li><a href="help.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Help</a></li>
      <li><a href="settings.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Settings</a></li>
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

          <div class="absolute bottom-5 left-8 text-white drop-shadow-md select-none">
    
    <h1 id="bannerText" class="text-3xl font-bold leading-none mb-0.5">
        <?php echo $event_name; ?>
    </h1>

    <p class="text-sm font-medium opacity-90">
        <?php 
            $dateDisplay = !empty($event['date']) 
                ? date("M j, Y", strtotime($event['date'])) 
                : "Date TBD";
            
            echo $dateDisplay;

            if (!empty($event_place)) {
                echo " • " . $event_place;
            }
        ?>
    </p>
</div>

          <button
    id="editBannerBtn"
    class="absolute top-4 right-4 z-10 w-10 h-10 flex items-center justify-center rounded-full bg-black/10 hover:bg-black/20 text-white/80 hover:text-white backdrop-blur-sm transition-all duration-300 opacity-0 group-hover:opacity-100 pointer-events-auto border border-white/10"
    aria-label="Edit Banner"
  >
    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
    </svg>
  </button>
          <div id="bannerMenu" class="absolute top-14 right-5 bg-white shadow-lg rounded-lg w-44 p-2 space-y-1 hidden z-50">
            <button id="uploadImageBtn" class="w-full text-left px-3 py-2 rounded hover:bg-gray-100 text-sm font-medium">Upload Image</button>
          </div>
          <input type="file" id="bannerImageUpload" accept="image/*" class="hidden" />
        </div>

        <!-- PLAN TABS -->
        <div class="flex bg-white rounded-lg shadow p-0.5 mb-3 w-full">
  <button onclick="switchTab('feed')" id="tab-feed" class="flex-1 bg-[#f4b41a] text-[#222] font-semibold py-2 text-center rounded-lg">Feed</button>
  <button onclick="switchTab('budget')" id="tab-budget" class="flex-1 text-gray-600 font-medium py-2 text-center hover:text-[#222] hover:bg-gray-100 rounded-lg">Budget</button>
  
  <?php if ($is_creator || (isset($membership['role']) && $membership['role'] === 'admin')): ?>
    <button onclick="switchTab('settings')" id="tab-settings" class="flex-1 text-gray-600 font-medium py-2 text-center hover:text-[#222] hover:bg-gray-100 rounded-lg">Settings</button>
  <?php endif; ?>
</div>
      </div>

      <!-- FEED SECTION -->
      <div id="feed-section" class="tab-section active">
  
  <div id="postBox" class="bg-white p-4 rounded-lg shadow w-full transition-all duration-300 mb-6">
    <div class="flex items-start gap-3">
      <img src="<?php echo htmlspecialchars($currentUserAvatar); ?>" alt="User" class="w-10 h-10 rounded-full object-cover" />
      
      <div class="flex-1">
        <div class="border border-gray-300 rounded-lg focus-within:border-[#f4b41a] transition-all duration-300 overflow-hidden">
          <div id="postInput" contenteditable="true" data-placeholder="Announce something to group" class="w-full px-3 py-2 text-sm resize-none min-h-[60px] focus:outline-none max-h-[300px] overflow-y-auto"></div>

          <div id="postImagePreviewContainer" class="hidden p-2 relative">
              <img id="postImagePreview" src="" alt="Preview" class="max-h-48 rounded-lg border border-gray-200">
              <button onclick="removePostImage()" class="absolute top-3 right-3 bg-gray-800/70 hover:bg-gray-900 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs">✕</button>
          </div>
          <input type="file" id="postImageInput" accept="image/*" class="hidden">

          <div id="toolbar" class="flex items-center gap-1 p-2 border-t border-gray-200 text-gray-600 bg-gray-50">
            <button id="btnBold" type="button" onmousedown="keepFocus(event)" onclick="formatText('bold')" class="w-7 h-7 rounded hover:bg-gray-200 hover:text-[#f4b41a] font-bold transition flex items-center justify-center">B</button>
            <button id="btnItalic" type="button" onmousedown="keepFocus(event)" onclick="formatText('italic')" class="w-7 h-7 rounded hover:bg-gray-200 hover:text-[#f4b41a] italic transition flex items-center justify-center">I</button>
            <button id="btnUnderline" type="button" onmousedown="keepFocus(event)" onclick="formatText('underline')" class="w-7 h-7 rounded hover:bg-gray-200 hover:text-[#f4b41a] underline transition flex items-center justify-center">U</button>
          </div>
        </div>

        <div id="postActions" class="mt-3 flex justify-between items-center transition-all">
          
          <div class="flex gap-2">
            <button onmousedown="keepFocus(event)" onclick="triggerPostImageUpload()" class="bg-gray-100 hover:bg-[#f4b41a]/30 text-gray-600 hover:text-[#f4b41a] rounded-full w-8 h-8 flex items-center justify-center transition" title="Add Image">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            </button>

            <button onmousedown="keepFocus(event)" onclick="openPoll()" class="bg-gray-100 hover:bg-[#f4b41a]/30 text-gray-600 hover:text-[#f4b41a] rounded-full w-8 h-8 flex items-center justify-center transition" title="Create Poll">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </button>

            <button onmousedown="keepFocus(event)" onclick="openTask()" class="bg-gray-100 hover:bg-[#f4b41a]/30 text-gray-600 hover:text-[#f4b41a] rounded-full w-8 h-8 flex items-center justify-center transition" title="Assign Task">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
            </button>
          </div>

          <div id="submitButtons" class="flex gap-2 hidden transition-all">
            <button type="button" class="px-4 py-2 bg-gray-200 rounded-lg text-sm hover:bg-gray-300 transition" onclick="cancelPost()">Cancel</button>
            <button id="submitPostBtn" type="button" class="px-4 py-2 bg-[#f4b41a] text-[#222] rounded-lg text-sm font-medium hover:bg-[#e3a918] transition" onclick="submitPost()">Post</button>
          </div>

        </div>
      </div>
    </div>
  </div>
  <div id="feedContainer" class="mt-6 space-y-4"></div>

</div>

             

      <!-- CREATE POLL MODAL -->
      <div id="pollModal" class="fixed inset-0 bg-black/40 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-lg w-96 p-6 relative">
          <h2 class="text-lg font-semibold mb-4">Create a Poll</h2>
          <button onclick="closePoll()" class="absolute top-3 right-3 text-gray-500 hover:text-black">✕</button>

          <div class="space-y-2" id="pollQuestionSection">
            <input type="text" id="pollQuestionInput" placeholder="Enter your question here..." class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none" />
            
            <div id="pollOptionsContainer" class="space-y-2">
              <input type="text" placeholder="Add option 1" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none option-input" />
              <input type="text" placeholder="Add option 2" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none option-input" />
            </div>
            
            <button id="pollAddOptionBtn" class="text-sm text-[#3b82f6] font-medium hover:underline">+ Add more option</button>
          </div>

          <p class="font-semibold mb-2 mt-4">Poll Settings</p>
          
          <div class="flex items-center justify-between mb-2">
            <span>Allow multiple votes</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" id="pollAllowMultiple" class="sr-only peer" />
              <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-[#f4b41a] transition"></div>
              <span class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
            </label>
          </div>

          <div class="flex items-center justify-between mb-2">
            <span>Anonymous voting</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" id="pollIsAnonymous" class="sr-only peer" />
              <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-[#f4b41a] transition"></div>
              <span class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
            </label>
          </div>

          <div class="flex items-center justify-between mb-4">
            <span>Allow members to add options</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" id="pollAllowUserAdd" class="sr-only peer" />
              <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-[#f4b41a] transition"></div>
              <span class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
            </label>
          </div>

          <button id="btnCreatePoll" class="w-full bg-[#f4b41a] text-[#222] font-medium py-2 rounded-lg hover:bg-[#e3a918] transition">Create Poll</button>
        </div>
      </div>

      <!-- ASSIGN TASK MODAL -->
      <div id="taskModal" class="fixed inset-0 bg-black/40 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl shadow-lg w-96 p-6 relative">
          <h2 class="text-lg font-semibold mb-4">Assigned Tasks</h2>
          <button onclick="closeTask()" class="absolute top-3 right-3 text-gray-500 hover:text-black">✕</button>

          <div class="space-y-2" id="taskSection">
            <input type="text" placeholder="Enter the task title..." class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none" />
            <div id="taskOptionsContainer" class="space-y-2"></div>
            <button id="taskAddOptionBtn" class="text-sm text-[#3b82f6] font-medium hover:underline">+ Add more task</button>
          </div>

          <div class="border-t pt-3 text-sm">
            <p class="font-semibold mb-2">Assigned Task Settings</p>
          

            <div class="flex items-center justify-between mb-4">
  <span>Allow members to add tasks</span>
  <label class="relative inline-flex items-center cursor-pointer">
    <input type="checkbox" id="allowTaskUserAdd" class="sr-only peer" />
    <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-[#f4b41a] transition"></div>
    <span class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
  </label>
</div>

          <label class="block mb-1 font-medium">Ends on</label>
<div class="space-y-2 mb-4">
    <select id="taskDeadlineSelect" onchange="toggleCustomDate()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#f4b41a]">
      <option value="">No deadline</option>
      <option value="tomorrow">Tomorrow (24hrs)</option>
      <option value="next_week">Next week (7 days)</option>
      <option value="custom">Custom date...</option>
    </select>
    
    <input type="datetime-local" id="taskCustomDate" class="hidden w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#f4b41a]">
</div>

<button id="btnSaveTask" class="w-full bg-[#f4b41a] text-[#222] font-bold py-2.5 rounded-lg hover:bg-[#e3a918] transition shadow-sm text-sm">
        Create Task List
      </button>
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
              <p class="text-3xl font-bold text-gray-900" id="displayTotalBudget">₱0.00</p>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border">
              <p class="text-sm text-gray-500">Money Collected</p>
              <p class="text-3xl font-bold text-green-700" id="displayCollectedAmount">₱0.00</p>
            </div>
            <div class="bg-white rounded-xl p-5 shadow-sm border">
              <p class="text-sm text-gray-500">Not Collected</p>
              <p class="text-3xl font-bold text-orange-700" id="displayBalanceAmount">₱0.00</p>
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
       <?php if ($is_creator || (isset($membership['role']) && $membership['role'] === 'owner')): ?>
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
      <?php endif; ?>
      <!-- END SETTINGS SECTION -->

    </div>

<aside class="hidden lg:flex fixed top-10 right-12 bottom-4 w-80 flex-col space-y-4 z-40 overflow-y-auto no-scrollbar">
      
      <div id="membersCard" class="bg-white p-4 rounded-lg shadow">
        <h3 class="font-semibold mb-3">Members (<?php echo isset($memberCount) ? $memberCount : 0; ?>)</h3>
        
        <div id="membersList" class="flex flex-wrap gap-2 mb-4">
          <?php 
          // Check if we have members (requires the PHP Step 1 code at the top of file)
          if (isset($members) && count($members) > 0) {
              // Show max 10 members
              $displayMembers = array_slice($members, 0, 10);
              
              foreach ($displayMembers as $mem): 
                  // Fix Avatar Path Logic
                  $memPic = $mem['profile_picture'];
                  $memAvatar = 'Assets/Profile Icon/profile.png'; // Fallback
                  
                  if (!empty($memPic)) {
                      if (strpos($memPic, 'data:') === 0 || strpos($memPic, 'http') === 0) {
                          $memAvatar = $memPic;
                      } else {
                          // Clean path
                          $clean = str_replace('DINADRAWING/', '', ltrim($memPic, '/'));
                          $memAvatar = '/DINADRAWING/' . $clean;
                      }
                  }
                  $tooltipName = htmlspecialchars($mem['name'] ?: $mem['username']);
          ?>
              <div class="relative group">
                  <img src="<?php echo $memAvatar; ?>" 
                       alt="<?php echo $tooltipName; ?>" 
                       class="w-8 h-8 rounded-full border border-gray-200 object-cover cursor-pointer hover:border-[#f4b41a] transition">
                  
                  <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-1 hidden group-hover:block bg-gray-800 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap z-10">
                      <?php echo $tooltipName; ?>
                  </div>
              </div>
          <?php 
              endforeach; 
          } else {
              echo '<p class="text-xs text-gray-400 italic">No members yet.</p>';
          }
          ?>
          
          <?php if (isset($memberCount) && $memberCount > 10): ?>
             <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs text-gray-500 font-bold border border-gray-200">
                +<?php echo ($memberCount - 10); ?>
             </div>
          <?php endif; ?>
        </div>

        <button onclick="document.getElementById('inviteModal').classList.remove('hidden'); document.getElementById('inviteModal').classList.add('flex');" 
                class="w-full bg-[#f4b41a] text-[#222] py-2 rounded-lg font-medium hover:bg-[#e3a918] transition">
          Invite People
        </button>
      </div>

      <div class="bg-white p-4 rounded-lg shadow mb-4">
    <h3 class="font-bold text-[#222] mb-2 text-base">Plan Description</h3>
    <div class="text-sm text-gray-600 leading-relaxed">
        <?php if (!empty($event_desc)): ?>
            <p><?php echo nl2br($event_desc); ?></p>
        <?php else: ?>
            <?php if ($is_creator): ?>
                <span class="text-gray-400 cursor-pointer hover:text-gray-600" onclick="switchTab('settings')">
                    Add your plan description...
                </span>
            <?php else: ?>
                <span class="text-sm text-gray-400">Add your plan description...</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

      <div class="bg-white p-4 rounded-lg shadow">
        <h3 class="font-semibold mb-2 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-black" fill="currentColor" viewBox="0 0 24 24">
            <path d="M14 2l-1 1-5 5-4 1 6 6-5 5 1 1 5-5 6 6 1-4 5-5 1-1-10-10z" />
          </svg>
          Pinned
        </h3>
        <p class="text-sm text-gray-400">No pinned items yet.</p>
      </div>
    </aside>
  </main>

  <div id="inviteModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[60]">
    <div class="bg-white w-96 rounded-xl shadow-lg p-6 relative">
      <button onclick="document.getElementById('inviteModal').classList.add('hidden'); document.getElementById('inviteModal').classList.remove('flex');" class="absolute top-3 right-3 text-gray-500 hover:text-black">✕</button>
      
      <h2 class="text-lg font-semibold mb-1">Invite People</h2>
      <p class="text-sm text-gray-500 mb-5">Share the code or link to invite others.</p>

      <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Event Code</label>
      <div class="flex items-center justify-between bg-[#fffaf2] rounded-xl px-4 py-3 mb-5 border-2 border-dashed border-[#f4b41a]">
        <span class="text-2xl font-bold tracking-[0.2em] text-[#222]" id="displayInviteCode"><?php echo isset($invite_code) ? $invite_code : '---'; ?></span>
        <button onclick="navigator.clipboard.writeText(document.getElementById('displayInviteCode').innerText); alert('Code copied!');" class="bg-[#f4b41a] text-[#222] text-xs font-bold px-3 py-1.5 rounded hover:bg-[#e3a918] transition">
          COPY
        </button>
      </div>

      <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Or share link</label>
      <div class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
        <input id="inviteLinkInput" type="text" readonly class="bg-transparent text-sm w-full focus:outline-none text-gray-600 truncate mr-2" value="<?php echo isset($invite_link) ? $invite_link : ''; ?>" />
        <button onclick="navigator.clipboard.writeText(document.getElementById('inviteLinkInput').value); alert('Link copied!');" class="text-gray-500 hover:text-[#222] text-sm font-medium transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
        </button>
      </div>
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
          <thead class="bg-gray-100 text-gray-600 font-medium">
            <tr>
              <th class="py-2 px-3 text-left">Expense</th>
              <th class="py-2 px-3 text-right">Estimated Cost (₱)</th>
              <th class="py-2 px-2 w-10"></th> </tr>
          </thead>
          <tbody id="expenseTableBody">
            <tr class="border-b last:border-0 hover:bg-gray-50 transition">
              <td><input type="text" placeholder="e.g. Venue rental" class="w-full bg-transparent border-none p-2 outline-none"></td>
              <td><input type="number" placeholder="0.00" step="0.01" class="w-full bg-transparent border-none p-2 text-right outline-none expense-cost"></td>
              <td class="text-center align-middle">
                <button class="delete-expense text-gray-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50 transition" title="Delete">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
              </td>
            </tr>
            <tr class="border-b last:border-0 hover:bg-gray-50 transition">
              <td><input type="text" placeholder="e.g. Decorations" class="w-full bg-transparent border-none p-2 outline-none"></td>
              <td><input type="number" placeholder="0.00" step="0.01" class="w-full bg-transparent border-none p-2 text-right outline-none expense-cost"></td>
              <td class="text-center align-middle">
                <button class="delete-expense text-gray-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50 transition" title="Delete">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
              </td>
            </tr>
            <tr class="border-b last:border-0 hover:bg-gray-50 transition">
              <td><input type="text" placeholder="e.g. Food & drinks" class="w-full bg-transparent border-none p-2 outline-none"></td>
              <td><input type="number" placeholder="0.00" step="0.01" class="w-full bg-transparent border-none p-2 text-right outline-none expense-cost"></td>
              <td class="text-center align-middle">
                <button class="delete-expense text-gray-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50 transition" title="Delete">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
              </td>
            </tr>
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
          <span id="totalCost" class="font-semibold text-gray-800">₱0.00</span>
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


  <div id="confirmModal" class="fixed inset-0 bg-black/40 flex items-center justify-center hidden z-[100]">
  <div class="bg-white rounded-xl shadow-lg w-80 p-6 relative text-center">
    <div class="mx-auto w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#f4b41a]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
    </div>
    
    <h3 class="text-lg font-bold text-[#222] mb-2">Are you sure?</h3>
    <p class="text-gray-600 mb-6 text-sm" id="confirmMessage">Do you want to proceed?</p>
    
    <div class="flex justify-center gap-3">
      <button onclick="closeConfirm()" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium text-sm transition">Cancel</button>
      <button id="confirmYesBtn" class="px-4 py-2 rounded-lg bg-[#f4b41a] hover:bg-[#e3a918] text-[#222] font-bold text-sm transition">Yes, I'm sure</button>
    </div>
  </div>
</div>

<script>
// CONFIRMATION LOGIC
let pendingAction = null;

function showConfirm(msg, actionCallback) {
    document.getElementById('confirmMessage').textContent = msg;
    document.getElementById('confirmModal').classList.remove('hidden');
    document.getElementById('confirmModal').classList.add('flex');
    pendingAction = actionCallback;
}

function closeConfirm() {
    document.getElementById('confirmModal').classList.add('hidden');
    document.getElementById('confirmModal').classList.remove('flex');
    pendingAction = null;
}

// When "Yes" is clicked, run the saved action
document.getElementById('confirmYesBtn')?.addEventListener('click', () => {
    if (pendingAction) pendingAction();
    closeConfirm();
});
</script>

  
  <!-- SCRIPTS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
<script> 
    const EVENT_MEMBERS = <?php echo json_encode($members ?? []); ?>; 
    // ADD THIS NEW LINE:
const CURRENT_USER_NAME = "<?php echo $currentUserName; ?>";</script>

<script>
    // VARS
    const eventId = <?php echo (int)$id; ?>;
    const banner = document.getElementById('planBanner');
    const bannerText = document.getElementById('bannerText');
    const editBannerBtn = document.getElementById('editBannerBtn');
    const bannerMenu = document.getElementById('bannerMenu');
    const bannerImageUpload = document.getElementById('bannerImageUpload');
    
    // MODAL ELEMENTS
    const editBannerModal = document.getElementById('editBannerModal');
    const closeEditModal = document.getElementById('closeEditModal');
    const colorSwatches = document.getElementById('colorSwatches');
    const uploadIconBtn = document.getElementById('uploadIconBtn');
    const bannerPreview = document.getElementById('bannerPreview');
    const applyBannerBtn = document.getElementById('applyBannerBtn');

    // CROPPER VARS
    let cropper = null;
    const cropModal = document.getElementById('cropModal');
    const cropImage = document.getElementById('cropImage');
    const applyCropBtn = document.getElementById('applyCropBtn');

    // STATE
    let pending = { type: 'color', hex: '#3b82f6', image: null, imageData: null, gradient: null, from: null, to: null };
    const DEFAULT_SOLID = '#3b82f6';

    // --- HELPERS ---
    function hexToRgb(hex) {
      const h = hex.replace('#',''); const n = parseInt(h,16);
      if (h.length === 6) return { r:(n>>16)&255, g:(n>>8)&255, b:n&255 };
      if (h.length === 3) return { r:parseInt(h[0]+h[0],16), g:parseInt(h[1]+h[1],16), b:parseInt(h[2]+h[2],16) };
      return { r:0,g:0,b:0 };
    }
    function getBrightnessFromRgb({r,g,b}) { return (r*299+g*587+b*114)/1000; }
    function setTextColorForHex(hex) { if(banner) banner.style.color = getBrightnessFromRgb(hexToRgb(hex)) < 140 ? '#fff' : '#222'; }
    function setTextColorForGradient(fromHex,toHex){
      const a=hexToRgb(fromHex), b=hexToRgb(toHex);
      const avg={r:((a.r+b.r)/2)|0, g:((a.g+b.g)/2)|0, b:((a.b+b.b)/2)|0};
      if(banner) banner.style.color = getBrightnessFromRgb(avg) < 140 ? '#fff' : '#222';
    }

    function initBannerPreviewFromCurrent() {
      if (!banner || !bannerPreview) return;
      const cs = getComputedStyle(banner);
      const bgImg = cs.backgroundImage;
      const bgCol = cs.backgroundColor;
      
      if (bgImg && bgImg !== 'none') {
        pending.type = 'image';
        pending.image = bgImg;
        pending.hex = null;
        bannerPreview.style.backgroundImage = bgImg;
        bannerPreview.style.background = '';
        const txt = bannerPreview.querySelector('span');
        if (txt) txt.style.display = 'none';
      } else {
        pending.type = 'color';
        pending.hex = bgCol && bgCol !== 'rgba(0, 0, 0, 0)' ? bgCol : DEFAULT_SOLID;
        pending.image = null;
        bannerPreview.style.backgroundImage = '';
        bannerPreview.style.background = pending.hex;
      }
      bannerPreview.style.backgroundSize = 'cover';
      bannerPreview.style.backgroundPosition = 'center';
    }

    // --- MODAL CONTROLS ---
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

    // --- GENERATE COLOR GRID ---
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
    const solidColors = ['#222222','#000000','#ffffff','#f4b41a','#ef4444','#22c55e','#06b6d4','#3b82f6','#8b5cf6','#94a3b8','#10b981'];

    if (colorSwatches && colorSwatches.children.length === 0) {
      // 1. Custom Picker Button
      const wrap = document.createElement('div');
      wrap.style.width = 'var(--swatch-size)'; wrap.style.height = 'var(--swatch-size)'; wrap.style.position = 'relative';
      const pickerBtn = document.createElement('button');
      pickerBtn.className = 'color-picker-circle picker-donut'; pickerBtn.title = 'Custom color';
      wrap.appendChild(pickerBtn);
      
      const pop = document.createElement('div');
      pop.className = 'swatch-popover hidden';
      pop.innerHTML = `<input id="inlinePicker" type="color" value="#3b82f6" style="width: 160px; height: 36px; border: 0; background: transparent; padding: 0;"/>`;
      wrap.appendChild(pop);
      colorSwatches.appendChild(wrap);

      const inlinePicker = pop.querySelector('#inlinePicker');
      pickerBtn.addEventListener('click', (e) => { e.stopPropagation(); pop.classList.toggle('hidden'); });
      document.addEventListener('click', (e) => { if (!pop.contains(e.target) && e.target !== pickerBtn) pop.classList.add('hidden'); });
      
      inlinePicker?.addEventListener('input', () => {
        const hex = inlinePicker.value;
        pending.type = 'color'; pending.hex = hex; pending.image = null; pending.gradient = null;
        bannerPreview.style.backgroundImage = ''; bannerPreview.style.background = hex;
      });

      // 2. Solid Colors
      solidColors.slice(0,12).forEach((hex) => {
        const sw = document.createElement('button');
        sw.className = 'color-picker-circle'; sw.style.background = hex;
        sw.addEventListener('click', () => {
          pending.type = 'color'; pending.hex = hex; pending.image = null; pending.gradient = null;
          bannerPreview.style.backgroundImage = ''; bannerPreview.style.background = hex;
          pop.classList.add('hidden');
        });
        colorSwatches.appendChild(sw);
      });

      // 3. Gradients
      gradients.slice(0,12).forEach(g => {
        const btn = document.createElement('button');
        btn.className = 'gradient-circle';
        btn.style.background = `linear-gradient(90deg, ${g.from}, ${g.to})`;
        btn.addEventListener('click', () => {
          pending.type = 'gradient'; pending.gradient = `linear-gradient(to right, ${g.from}, ${g.to})`;
          pending.from = g.from; pending.to = g.to; pending.hex = null; pending.image = null;
          bannerPreview.style.backgroundImage = 'none'; bannerPreview.style.background = pending.gradient;
          pop.classList.add('hidden');
        });
        colorSwatches.appendChild(btn);
      });
    }

    // --- EVENT LISTENERS ---
    editBannerBtn?.addEventListener('click', (e) => { e.stopPropagation(); openEditModal(); });
    closeEditModal?.addEventListener('click', closeEditModalIfOpen);
    document.addEventListener('click', (e) => { if (banner && !banner.contains(e.target) && bannerMenu) bannerMenu.classList.add('hidden'); });

    // UPLOAD & CROP
    uploadIconBtn?.addEventListener('click', () => { bannerImageUpload?.click(); });

    bannerImageUpload?.addEventListener('change', (e) => {
      const file = e.target.files?.[0]; if (!file || !cropImage) return;
      
      // Ensure Cropper is loaded
      if (typeof Cropper === 'undefined') {
          alert('Image editor is still loading or failed to load. Please check your internet connection.');
          return;
      }

      const reader = new FileReader();
      reader.onload = (ev) => {
        cropImage.src = ev.target.result;
        cropModal.classList.remove('hidden'); cropModal.classList.add('flex');
        
        if (cropper) cropper.destroy();
        
        // Initialize Cropper
        cropper = new Cropper(cropImage, { 
            aspectRatio: 21/9, 
            viewMode: 1, 
            autoCropArea: 1, 
            responsive: true, 
            background: false, 
            dragMode: 'move' 
        });
      };
      reader.readAsDataURL(file);
    });

    applyCropBtn?.addEventListener('click', () => {
      if (!cropper) return;
      const canvas = cropper.getCroppedCanvas({ width: 2000, height: 800 });
      const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
      
      // Update pending for both DB and Preview
      pending.type = 'image';
      pending.imageData = dataUrl; // For DB
      pending.image = `url("${dataUrl}")`; // For Preview State
      
      // Update Preview Visuals
      const img = new Image();
      img.onload = () => {
         bannerPreview.style.background = 'none';
         bannerPreview.style.backgroundImage = `url("${dataUrl}")`;
         bannerPreview.style.backgroundSize = 'cover';
         bannerPreview.style.backgroundPosition = 'center';
         const txt = bannerPreview.querySelector('span');
         if (txt) txt.style.display = 'none';
      };
      img.src = dataUrl;

      closeCropModal();
    });

    // --- APPLY TO SERVER ---
    applyBannerBtn?.addEventListener('click', async () => {
       const payload = { id: eventId, type: pending.type };
       if (pending.type === 'image' && pending.imageData) payload.imageData = pending.imageData;
       if (pending.type === 'color') payload.color = pending.hex;
       if (pending.type === 'gradient') { payload.from = pending.from; payload.to = pending.to; }

       const originalText = applyBannerBtn.textContent;
       applyBannerBtn.textContent = "Saving...";
       applyBannerBtn.disabled = true;

       try {
         const resp = await fetch('/DINADRAWING/Backend/events/save_banner.php', {
           method:'POST',
           headers:{'Content-Type':'application/json'},
           body: JSON.stringify(payload)
         });
         const res = await resp.json();

         if (res.success) {
             if (res.banner.imageUrl) {
                 const freshUrl = res.banner.imageUrl + '?t=' + new Date().getTime();
                 banner.style.backgroundImage = `url("${freshUrl}")`;
                 banner.style.backgroundSize = 'cover';
                 banner.style.backgroundPosition = 'center';
                 banner.style.color = '#fff';
             } else if (payload.color) {
                 banner.style.backgroundImage = 'none';
                 banner.style.background = payload.color;
                 setTextColorForHex(payload.color);
             } else if (payload.from && payload.to) {
                 banner.style.backgroundImage = 'none';
                 banner.style.background = `linear-gradient(to right, ${payload.from}, ${payload.to})`;
                 setTextColorForGradient(payload.from, payload.to);
             }
             closeEditModalIfOpen();
         } else {
             alert('Error saving: ' + (res.error || 'Unknown error'));
         }
       } catch(e) {
         console.error(e);
         alert('Network error saving banner');
       } finally {
         applyBannerBtn.textContent = originalText;
         applyBannerBtn.disabled = false;
       }
    });
</script>

<script>
// ==========================================
// 1. POST BOX & STANDARD POSTING LOGIC
// ==========================================
const postInput = document.getElementById('postInput');
const toolbar = document.getElementById('toolbar');
const postActions = document.getElementById('postActions');
const submitPostBtn = document.getElementById('submitPostBtn');
const postImageInput = document.getElementById('postImageInput');
const postImagePreviewContainer = document.getElementById('postImagePreviewContainer');
const postImagePreview = document.getElementById('postImagePreview');
const feedContainer = document.getElementById('feedContainer');

// Toolbar Buttons
const btnBold = document.getElementById('btnBold');
const btnItalic = document.getElementById('btnItalic');
const btnUnderline = document.getElementById('btnUnderline');

// We now target the SPECIFIC button group
const submitButtons = document.getElementById('submitButtons'); 

function expandPostBox() {
  postInput?.classList.add('min-h-[120px]');
  toolbar?.classList.remove('hidden');
  
  // Show Cancel/Post buttons
  submitButtons?.classList.remove('hidden');
}
    
function collapsePostBox() {
  // Only collapse if empty
  if (postInput?.innerText.trim() === '' && postImageInput.files.length === 0) {
      postInput?.classList.remove('min-h-[120px]');
      toolbar?.classList.add('hidden');
      
      // Hide Cancel/Post buttons ONLY
      // The rest of #postActions remains visible!
      submitButtons?.classList.add('hidden');
  }
}

postInput?.addEventListener('focus', expandPostBox);
document.addEventListener('click', (e) => {
  const postBox = document.getElementById('postBox');
  if (postBox && !postBox.contains(e.target)) collapsePostBox();
});

function keepFocus(e){ e.preventDefault(); }

function formatText(cmd){
  postInput?.focus(); 
  document.execCommand(cmd === 'underline' ? 'underline' : cmd, false, null);
  checkToolbarState(); // Check highlighting immediately after clicking
}

// --- HIGHLIGHTING LOGIC ---
function checkToolbarState() {
    if (!btnBold || !btnItalic || !btnUnderline) return;

    // Check Bold
    if (document.queryCommandState('bold')) {
        btnBold.classList.add('text-[#f4b41a]', 'bg-gray-200');
    } else {
        btnBold.classList.remove('text-[#f4b41a]', 'bg-gray-200');
    }

    // Check Italic
    if (document.queryCommandState('italic')) {
        btnItalic.classList.add('text-[#f4b41a]', 'bg-gray-200');
    } else {
        btnItalic.classList.remove('text-[#f4b41a]', 'bg-gray-200');
    }

    // Check Underline
    if (document.queryCommandState('underline')) {
        btnUnderline.classList.add('text-[#f4b41a]', 'bg-gray-200');
    } else {
        btnUnderline.classList.remove('text-[#f4b41a]', 'bg-gray-200');
    }
}

// Listen for interactions to update button states
postInput?.addEventListener('keyup', checkToolbarState);
postInput?.addEventListener('mouseup', checkToolbarState);
postInput?.addEventListener('click', checkToolbarState);

// --- IMAGE UPLOAD LOGIC ---
function triggerPostImageUpload() { postImageInput.click(); }
postImageInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            postImagePreview.src = e.target.result;
            postImagePreviewContainer.classList.remove('hidden');
            expandPostBox();
        };
        reader.readAsDataURL(this.files[0]);
    }
});

function removePostImage() {
    postImageInput.value = ''; 
    postImagePreview.src = ''; 
    postImagePreviewContainer.classList.add('hidden');
}

function cancelPost(){ 
    if (postInput) postInput.innerHTML=''; 
    removePostImage(); 
    collapsePostBox(); 
    checkToolbarState(); // Reset buttons
}

async function submitPost(){ 
    // Use innerHTML to capture bold/italic tags
    const content = postInput.innerHTML.trim(); 
    const imageFile = postImageInput.files[0];
    if (content === '' && !imageFile) return;

    submitPostBtn.disabled = true; submitPostBtn.textContent = 'Posting...';
    const formData = new FormData();
    formData.append('event_id', <?php echo (int)$id; ?>);
    formData.append('content', content);
    if (imageFile) formData.append('image', imageFile);

    try {
        const res = await fetch('/DINADRAWING/Backend/events/create_post.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) { cancelPost(); prependNewPost(data.post); } 
        else { alert('Failed to post: ' + (data.error || 'Unknown error')); }
    } catch (err) { console.error(err); alert('Network error.'); } 
    finally { submitPostBtn.disabled = false; submitPostBtn.textContent = 'Post'; }
}
</script>

<script>
// ==========================================
// 2. POLL MODAL LOGIC (FIXED)
// ==========================================

const pollModal = document.getElementById('pollModal');
const pollQuestionInput = document.getElementById('pollQuestionInput');
const pollOptionsContainer = document.getElementById('pollOptionsContainer');
const pollAddOptionBtn = document.getElementById('pollAddOptionBtn');
const createPollBtn = document.getElementById('btnCreatePoll'); 

// Specific IDs for the toggles
const allowMultipleChk = document.getElementById('pollAllowMultiple');
const isAnonymousChk = document.getElementById('pollIsAnonymous');
const allowUserAddChk = document.getElementById('pollAllowUserAdd');

function openPoll(){ pollModal?.classList.remove('hidden'); }

function closePoll(){ 
    pollModal?.classList.add('hidden'); 
    // Reset Form
    if(pollQuestionInput) pollQuestionInput.value = '';
    
    // Reset Options to just 2
    if(pollOptionsContainer) {
         pollOptionsContainer.innerHTML = `
            <input type="text" placeholder="Add option 1" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none option-input" />
            <input type="text" placeholder="Add option 2" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none option-input" />
         `;
    }
    // Reset Toggles
    if(allowMultipleChk) allowMultipleChk.checked = false;
    if(isAnonymousChk) isAnonymousChk.checked = false;
    if(allowUserAddChk) allowUserAddChk.checked = false;
}

// Add New Option Input
pollAddOptionBtn?.addEventListener('click', () => {
  const count = pollOptionsContainer.children.length + 1;
  const input = document.createElement('input');
  input.type = 'text';
  input.placeholder = `Add option ${count}`;
  input.className = 'w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3b82f6] focus:outline-none option-input';
  pollOptionsContainer?.appendChild(input);
});

// Create Poll Action
createPollBtn?.addEventListener('click', async () => {
    // 1. Get Values
    const question = pollQuestionInput.value.trim();
    
    // Collect all inputs with class .option-input
    const options = Array.from(pollOptionsContainer.querySelectorAll('.option-input'))
                         .map(input => input.value.trim())
                         .filter(val => val !== ''); 

    // 2. Validate
    if (!question) { alert("Please enter a question."); return; }
    if (options.length < 2) { alert("Please add at least 2 options."); return; }

    createPollBtn.disabled = true; createPollBtn.textContent = "Creating...";

    // 3. Construct Payload (Capture toggle states NOW, not before)
    const payload = {
        event_id: <?php echo (int)$id; ?>,
        question: question,
        options: options,
        allow_multiple: allowMultipleChk ? allowMultipleChk.checked : false, 
        is_anonymous: isAnonymousChk ? isAnonymousChk.checked : false,
        allow_user_add: allowUserAddChk ? allowUserAddChk.checked : false
    };

    try {
        const res = await fetch('/DINADRAWING/Backend/events/create_poll.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success) {
            closePoll();
            // Add the new poll to the feed immediately
            if (typeof prependNewPost === 'function') {
                prependNewPost(data.post); 
            }
        } else {
            alert('Failed to create poll: ' + (data.error || 'Unknown error'));
        }
    } catch (err) { console.error(err); alert('Network error.'); }
    finally { createPollBtn.disabled = false; createPollBtn.textContent = "Create Poll"; }
});
</script>


<script>
// ==========================================
// 1. TASK MODAL SYSTEM (Open, Close, Rows, Suggestions)
// ==========================================

// --- ELEMENTS ---
const taskModal = document.getElementById('taskModal');
const taskOptionsContainer = document.getElementById('taskOptionsContainer');
const taskAddOptionBtn = document.getElementById('taskAddOptionBtn');
const btnSaveTask = document.getElementById('btnSaveTask');
const taskTitleInput = taskModal?.querySelector('input[type="text"]');

// Suggestion Box
const suggestionBox = document.createElement('div');
suggestionBox.className = "absolute bg-white border border-gray-200 shadow-lg rounded-lg max-h-40 overflow-y-auto z-[60] hidden w-48";
document.body.appendChild(suggestionBox);
let activeAssignInput = null;

// OPEN MODAL
function openTask() { 
    taskModal?.classList.remove('hidden'); 
    taskModal?.classList.add('flex');
    if(taskOptionsContainer && taskOptionsContainer.children.length === 0) addTaskRow();
}

// CLOSE MODAL
function closeTask() { 
    taskModal?.classList.add('hidden'); 
    taskModal?.classList.remove('flex');
    suggestionBox.classList.add('hidden'); 
    if(taskTitleInput) taskTitleInput.value = '';
    if(taskOptionsContainer) taskOptionsContainer.innerHTML = '';
    
    // Reset Settings
    const ds = document.getElementById('taskDeadlineSelect');
    if(ds) { ds.value = ""; toggleCustomDate(); }
    const aa = document.getElementById('allowTaskUserAdd');
    if(aa) aa.checked = false;
}

// ADD ROW
function addTaskRow() {
    const div = document.createElement('div');
    div.className = 'flex gap-2 relative mb-2 items-center'; 
    div.innerHTML = `
        <input type="text" placeholder="Task description" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#f4b41a] focus:border-[#f4b41a] outline-none task-name">
        <input type="text" placeholder="@Assignee" class="w-1/3 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#f4b41a] focus:border-[#f4b41a] outline-none task-assign" autocomplete="off">
        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-red-500 transition px-1">✕</button>
    `;
    if(taskOptionsContainer) taskOptionsContainer.appendChild(div);

    const assignInput = div.querySelector('.task-assign');
    assignInput.addEventListener('focus', (e) => showSuggestions(e.target));
    assignInput.addEventListener('input', (e) => showSuggestions(e.target));
    assignInput.addEventListener('blur', () => setTimeout(() => suggestionBox.classList.add('hidden'), 200));
}

taskAddOptionBtn?.addEventListener('click', (e) => { e.preventDefault(); addTaskRow(); });

// SUGGESTIONS
function showSuggestions(inputElement) {
    activeAssignInput = inputElement;
    const val = inputElement.value.toLowerCase().replace('@', '');
    const members = (typeof EVENT_MEMBERS !== 'undefined' ? EVENT_MEMBERS : []);
    
    const matches = members.filter(m => (m.name || m.username).toLowerCase().includes(val));
    if (matches.length === 0) { suggestionBox.classList.add('hidden'); return; }

    suggestionBox.innerHTML = matches.map(m => `
        <div class="px-3 py-2 hover:bg-yellow-50 cursor-pointer flex items-center gap-2 text-sm transition" 
             onclick="selectMember('${m.name || m.username}')">
            <img src="${m.profile_picture || 'Assets/Profile Icon/profile.png'}" class="w-6 h-6 rounded-full object-cover border border-gray-200">
            <span class="text-gray-700 font-medium">${m.name || m.username}</span>
        </div>
    `).join('');

    const rect = inputElement.getBoundingClientRect();
    suggestionBox.style.top = (rect.bottom + window.scrollY + 2) + 'px';
    suggestionBox.style.left = (rect.left + window.scrollX) + 'px';
    suggestionBox.style.width = rect.width + 'px';
    suggestionBox.classList.remove('hidden');
}

window.selectMember = function(name) {
    if(activeAssignInput) { activeAssignInput.value = name; suggestionBox.classList.add('hidden'); }
};

// DEADLINE HELPERS
function toggleCustomDate() {
    const s = document.getElementById('taskDeadlineSelect');
    const c = document.getElementById('taskCustomDate');
    if (s && s.value === 'custom') { c?.classList.remove('hidden'); c?.focus(); } else { c?.classList.add('hidden'); }
}

function getCalculatedDeadline() {
    const s = document.getElementById('taskDeadlineSelect');
    if(!s || !s.value) return null;
    const d = new Date();
    if (s.value === 'tomorrow') d.setDate(d.getDate() + 1);
    else if (s.value === 'next_week') d.setDate(d.getDate() + 7);
    else if (s.value === 'custom') {
        const v = document.getElementById('taskCustomDate')?.value;
        return v ? v.replace('T', ' ') + ':00' : null;
    }
    const offset = d.getTimezoneOffset() * 60000;
    return new Date(d.getTime() - offset).toISOString().slice(0, 19).replace('T', ' ');
}


// ==========================================
// 2. SAVE TASK LOGIC (Backend Connection)
// ==========================================
if(btnSaveTask) {
    btnSaveTask.addEventListener('click', async () => {
        const title = taskTitleInput?.value.trim() || 'Assigned Tasks';
        const allowUserAdd = document.getElementById('allowTaskUserAdd')?.checked || false;
        
        const items = [];
        taskOptionsContainer.querySelectorAll('div.flex').forEach(row => {
            const txt = row.querySelector('.task-name').value.trim();
            const assign = row.querySelector('.task-assign').value.trim().replace('@', '');
            if(txt) items.push({ text: txt, assigned: assign });
        });

        if (items.length === 0) { alert("Please add at least one task item."); return; }

        const originalText = btnSaveTask.textContent;
        btnSaveTask.disabled = true; btnSaveTask.textContent = "Creating...";

        try {
            const res = await fetch('/DINADRAWING/Backend/events/create_task.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    event_id: <?php echo (int)$id; ?>,
                    title: title,
                    items: items,
                    allow_user_add: allowUserAdd,
                    deadline: getCalculatedDeadline()
                })
            });
            const data = await res.json();
            
            if (data.success) {
                closeTask();
                if(typeof prependNewPost === 'function') prependNewPost(data.post);
                if(typeof showToast === 'function') showToast("Task list created!");
            } else {
                alert('Failed: ' + (data.error || 'Unknown error'));
            }
        } catch (e) { console.error(e); alert("Network error."); } 
        finally { btnSaveTask.disabled = false; btnSaveTask.textContent = originalText; }
    });
}


// ==========================================
// 3. GENERATE FEED HTML (The Missing Part!)
// ==========================================
function generateTaskHTML(taskData) {
    if (!taskData || !taskData.items) return '';

    // 1. GENERATE LIST ITEMS (Using uniform gap, no individual margins)
    let itemsHTML = taskData.items.map(item => {
        const isDone = (item.is_completed == 1 || item.is_completed === 't' || item.is_completed === true);
        const assignee = item.assigned_to ? item.assigned_to.trim() : '';
        
        const textStyle = isDone ? "text-gray-400 line-through" : "text-gray-800";
        const checkBg = isDone ? "bg-[#f4b41a] border-[#f4b41a]" : "bg-white border-gray-300 hover:border-[#f4b41a]";
        const checkIcon = isDone ? `<svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg>` : ``;

        let badge = '';
        const badgeClass = "ml-2 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide border flex items-center justify-center min-w-[75px] transition";

        if (assignee === '') {
            badge = `<button onclick="claimTask(${item.id}, this)" class="${badgeClass} border-dashed border-gray-400 text-gray-500 hover:border-[#f4b41a] hover:text-[#f4b41a] hover:bg-yellow-50">+ Volunteer</button>`;
        } else if (typeof CURRENT_USER_NAME !== 'undefined' && assignee === CURRENT_USER_NAME) {
            badge = `<button onclick="unclaimTask(${item.id}, this)" class="${badgeClass} border-transparent bg-yellow-100 text-yellow-800 hover:bg-red-100 hover:text-red-600">@${assignee}</button>`;
        } else {
            badge = `<span class="${badgeClass} border-transparent bg-gray-100 text-gray-600 cursor-default">@${assignee}</span>`;
        }

        return `
        <div id="task-item-${item.id}" class="flex items-center justify-between bg-white border border-gray-200 p-2.5 rounded-lg transition hover:shadow-sm group">
            <div class="flex items-center gap-2.5 flex-1 overflow-hidden">
                <div onclick="toggleTaskItem(${item.id})" class="cursor-pointer w-5 h-5 rounded-full border-2 ${checkBg} flex items-center justify-center shadow-sm transition">${checkIcon}</div>
                <span class="${textStyle} font-medium text-sm flex-1 truncate transition">${item.item_text}</span>
            </div>
            <div class="flex items-center">
                ${badge}
                <button onclick="deleteTaskItem(${item.id})" class="ml-1.5 text-gray-300 hover:text-red-500 p-1 rounded opacity-0 group-hover:opacity-100 transition" title="Delete">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
            </div>
        </div>`;
    }).join('');

    // 2. DEADLINE TAG
    let deadlineHTML = '';
    if (taskData.deadline) {
        const d = new Date(taskData.deadline);
        const isPast = new Date() > d;
        const tagColor = isPast ? "bg-red-100 text-red-700 border-red-200" : "bg-orange-100 text-orange-800 border-orange-200";
        const dateText = d.toLocaleDateString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});

        deadlineHTML = `
        <span class="${tagColor} border px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 whitespace-nowrap">
            ${isPast ? '⚠️' : '🕒'} ${dateText}
        </span>`;
    }

    // 3. ADD NEW TASK OPTION (Sized to match task rows and placed in the gap grid)
    let addTaskHTML = '';
    if (taskData.allow_user_add == 1 || taskData.allow_user_add === true || taskData.allow_user_add === 't') {
        addTaskHTML = `
        <div class="relative flex items-center bg-white border border-gray-200 p-1 rounded-lg transition hover:shadow-sm">
            <input type="text" id="new-task-input-${taskData.id}" placeholder="+ Add a new task..." 
                   class="w-full bg-transparent px-3 py-1.5 text-sm focus:outline-none placeholder-gray-400 pr-16"
                   onkeydown="if(event.key === 'Enter') addNewTaskItem(${taskData.id})">
            <button onclick="addNewTaskItem(${taskData.id})" class="absolute right-1 z-10 bg-[#f4b41a] hover:bg-[#e3a918] text-[#222] text-[10px] font-bold px-3 py-1.5 rounded-md transition">ADD</button>
        </div>`;
    }

    // 4. FINAL RETURN (Using flex-col and gap-2 for perfect even spacing)
    return `
    <div class="bg-gray-50 rounded-xl p-3 border border-gray-200 mb-2" id="task-container-${taskData.id}">
        <div class="relative flex items-center justify-center mb-4 min-h-[24px]">
            <h3 class="font-bold text-base text-[#222] text-center">
                ${taskData.title}
            </h3>
            
            <div class="absolute right-0">
                ${deadlineHTML}
            </div>
        </div>

        <div class="flex flex-col gap-2" id="task-list-${taskData.id}">
            ${itemsHTML}
            ${addTaskHTML}
        </div>
    </div>`;
}


// ==========================================
// 4. GLOBAL ACTIONS (Volunteer, Delete, Toggle)
// ==========================================
window.claimTask = async function(itemId, btn) {
    if(event) { event.preventDefault(); event.stopPropagation(); }
    const original = btn.innerHTML; btn.innerHTML = "Saving..."; btn.disabled = true;
    try {
        const res = await fetch('/DINADRAWING/Backend/events/claim_task.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({item_id:itemId}) });
        const data = await res.json();
        if(data.success) { if(typeof showToast==='function') showToast("You volunteered!"); if(typeof loadEventPosts==='function') loadEventPosts(); }
        else { alert(data.error); btn.innerHTML = original; btn.disabled = false; }
    } catch(e){ console.error(e); btn.innerHTML = original; btn.disabled = false; }
};

window.unclaimTask = function(itemId, btn) {
    if(event) { event.preventDefault(); event.stopPropagation(); }
    const run = async () => {
        try {
            const res = await fetch('/DINADRAWING/Backend/events/unclaim_task.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({item_id:itemId}) });
            if((await res.json()).success) { if(typeof showToast==='function') showToast("Unvolunteered."); if(typeof loadEventPosts==='function') loadEventPosts(); }
        } catch(e){ console.error(e); }
    };
    if(typeof showConfirm === 'function') showConfirm("Unclaim task?", run); else if(confirm("Unclaim task?")) run();
};

window.deleteTaskItem = function(itemId) {
    const run = async () => {
        try {
            const res = await fetch('/DINADRAWING/Backend/events/delete_task_item.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({item_id:itemId}) });
            if((await res.json()).success) { document.getElementById(`task-item-${itemId}`)?.remove(); }
        } catch(e){ console.error(e); }
    };
    if(typeof showConfirm === 'function') showConfirm("Delete task?", run); else if(confirm("Delete task?")) run();
};

window.addNewTaskItem = async function(taskId) {
    const input = document.getElementById(`new-task-input-${taskId}`);
    if(!input) return;
    const text = input.value.trim();
    if(!text) { input.classList.add('bg-red-50'); setTimeout(()=>input.classList.remove('bg-red-50'), 500); return; }

    input.disabled = true; const orig = input.placeholder; input.placeholder = "Adding...";
    try {
        const res = await fetch('/DINADRAWING/Backend/events/add_task_item.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({task_id:taskId, text:text}) });
        const data = await res.json();
        if(data.success) { if(typeof loadEventPosts==='function') loadEventPosts(); if(typeof showToast==='function') showToast("Task added!"); }
        else { alert(data.error); input.disabled = false; input.placeholder = orig; }
    } catch(e){ console.error(e); input.disabled = false; input.placeholder = orig; }
};

async function toggleTaskItem(itemId) {
    try {
        await fetch('/DINADRAWING/Backend/events/toggle_task.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({item_id:itemId}) });
        // Optimistic UI Swap
        const container = document.getElementById(`task-item-${itemId}`);
        if(container) {
            const check = container.querySelector('div[onclick]');
            const txt = container.querySelector('span.font-medium');
            const isDone = check.classList.contains('bg-[#f4b41a]');
            
            if(isDone) {
                check.className = "cursor-pointer w-6 h-6 rounded-full border-2 bg-white border-gray-300 hover:border-[#f4b41a] flex items-center justify-center shadow-sm transition";
                check.innerHTML = '';
                txt.className = "text-gray-800 font-medium flex-1 truncate transition";
            } else {
                check.className = "cursor-pointer w-6 h-6 rounded-full border-2 bg-[#f4b41a] border-[#f4b41a] flex items-center justify-center shadow-sm transition";
                check.innerHTML = '<svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg>';
                txt.className = "text-gray-400 line-through font-medium flex-1 truncate transition";
            }
        }
    } catch(e){ console.error(e); }
}
</script>

<script>
// ==========================================
// 4. MAIN FEED RENDERING (With Pin & Delete Menu)
// ==========================================
function prependNewPost(post) {
    let postContentHTML = '';
    
    // Formatting Fix (Keep this logic!)
    const cleanContent = (post.content || '')
        .replace(/&lt;(\/?(b|i|u|strong|em|div|br|span|p))(.*?)&gt;/gi, function(match, tag, attrs) {
            const cleanAttrs = attrs.replace(/&quot;/g, '"').replace(/&amp;/g, '&');
            return `<${tag}${cleanAttrs}>`;
        })
        .replace(/&nbsp;/g, ' ');

    // --- CONTENT GENERATION ---
    if (post.post_type === 'standard') {
        postContentHTML = `
            ${cleanContent ? `<div class="text-sm text-gray-800 mb-3 break-words">${cleanContent}</div>` : ''}
            ${post.image_path ? `<div class="mb-3 rounded-lg overflow-hidden border border-gray-100"><img src="${post.image_path}" alt="Post Image" class="w-full h-auto object-cover max-h-[500px]"></div>` : ''}
        `;
    } 
    else if (post.post_type === 'poll') {
    const pd = post.poll_data;
    const totalVotes = pd ? (pd.total_votes || 0) : 0;
    const optionsHTML = (typeof generatePollOptionsHTML === 'function' && pd) ? generatePollOptionsHTML(pd, post.id) : '';
    if (pd) {
        postContentHTML = `
        <div class="bg-gray-50 rounded-xl p-3 border border-gray-200 mb-2" id="poll-container-${post.id}">
            <div class="relative flex items-center justify-center mb-4 min-h-[24px]">
                <h3 class="text-base font-bold text-[#222] text-center">
                    ${pd.question}
                </h3>
            </div>
          
            <div class="space-y-2" id="poll-options-${post.id}">
                ${optionsHTML}
            </div>

            <div class="flex justify-between items-center mt-4 text-xs text-gray-500 font-medium px-1">
                <span id="poll-stats-${post.id}">${totalVotes} total votes</span>
                <span class="bg-gray-100 border border-gray-200 px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wide">
                    ${pd.is_anonymous ? 'Anonymous' : 'Public'}
                </span>
            </div>
        </div>`;
    }
}
    else if (post.post_type === 'task') {
        const td = post.task_data;
        if (td && typeof generateTaskHTML === 'function') {
            postContentHTML = generateTaskHTML(td);
        } else {
            postContentHTML = `<div class="bg-gray-50 p-3 rounded text-sm text-gray-600">Task: ${td ? td.title : 'Untitled'}</div>`;
        }
    }

    const likeCount = post.like_count || 0;
    const commentCount = post.comment_count || 0;
    const isLiked = post.is_liked ? 'text-[#f4b41a] font-bold' : 'text-gray-500';
    
    // --- PINNED BADGE ---
    const pinnedBadge = post.is_pinned ? 
        `<div class="mb-2 flex items-center gap-1 text-[10px] font-bold text-[#f4b41a] uppercase tracking-wider">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.699-3.184A1 1 0 0118 3v1a1 1 0 01-.447.894L16 5.618V14a2 2 0 01-1.059 1.764l-4 2a2 2 0 01-1.882 0l-4-2A2 2 0 014 14V5.618L2.447 4.894A1 1 0 012 4V3a1 1 0 011.347-.279l1.699 3.184L9 4.323V3a1 1 0 011-1z"/></svg> 
            Pinned Post
         </div>` : '';
    
    // --- ACTION MENU (Only for Owner) ---
    let actionMenuHTML = '';
    if (post.is_owner) {
        actionMenuHTML = `
        <div class="relative">
            <button onclick="togglePostMenu(event, ${post.id})" class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
            </button>
            <div id="post-menu-${post.id}" class="hidden absolute right-0 mt-1 w-32 bg-white rounded-lg shadow-xl border border-gray-100 z-20 overflow-hidden">
                <button onclick="togglePin(${post.id})" class="w-full text-left px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                    ${post.is_pinned ? 'Unpin Post' : 'Pin Post'}
                </button>
                <button onclick="deletePost(${post.id})" class="w-full text-left px-4 py-2 text-xs font-medium text-red-600 hover:bg-red-50 flex items-center gap-2 border-t border-gray-50">
                    Delete Post
                </button>
            </div>
        </div>`;
    }

    const finalHTML = `
    <div class="bg-white p-4 rounded-lg shadow w-full transition-all duration-300 animate-fade-in mb-4" id="post-${post.id}">
      ${pinnedBadge}
      <div class="flex items-start justify-between mb-3">
        <div class="flex items-start gap-3">
            <img src="${post.user.avatar}" alt="${post.user.name}" class="w-10 h-10 rounded-full object-cover shadow-sm" />
            <div>
              <h4 class="font-semibold text-[#222] text-sm">${post.user.name}</h4>
              <p class="text-xs text-gray-500">${post.created_at}</p>
            </div>
        </div>
        ${actionMenuHTML}
      </div>
      
      ${postContentHTML}

      <div class="flex items-center gap-6 pt-2 border-t border-gray-100 text-sm font-medium">
        <button onclick="toggleLike(${post.id}, this)" class="flex items-center gap-1.5 hover:text-[#f4b41a] transition ${isLiked}">
           <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="${post.is_liked ? 'currentColor' : 'none'}" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
           </svg> 
           <span class="like-count">${likeCount > 0 ? likeCount : 'Like'}</span>
        </button>

        <button onclick="toggleComments(${post.id})" class="flex items-center gap-1.5 text-gray-500 hover:text-[#f4b41a] transition">
           <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
           </svg> 
           <span class="comment-count-label">${commentCount > 0 ? commentCount : 'Comment'}</span>
        </button>
      </div>

      <div id="comments-section-${post.id}" class="hidden mt-3 pt-3 border-t border-gray-50">
         <div id="comments-list-${post.id}" class="space-y-3 mb-3 max-h-60 overflow-y-auto"></div>
         <div class="flex items-start gap-2">
            <img src="<?php echo htmlspecialchars($currentUserAvatar); ?>" class="w-8 h-8 rounded-full border border-gray-200">
            <div class="flex-1 relative">
                <textarea id="comment-input-${post.id}" rows="1" placeholder="Write a comment..." class="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#f4b41a] focus:outline-none resize-none"></textarea>
                <button onclick="submitComment(${post.id})" class="absolute right-2 bottom-1.5 text-[#f4b41a] hover:text-black text-xs font-bold uppercase p-1">Post</button>
            </div>
         </div>
      </div>
    </div>`;
    
    document.getElementById('feedContainer').insertAdjacentHTML('afterbegin', finalHTML);
}


// ==========================================
// POLL VOTING LOGIC
// ==========================================
let currentPollVotes = new Map(); // Track which user has voted on which poll

// ==========================================
    // POLL STATE INITIALIZATION
    // ==========================================
    function initPollVotingState() {
        // This would ideally be populated from server when page loads
        // For now, we'll track locally
        currentPollVotes = new Map();
    }

async function votePoll(pollId, optionId, postId) {
    // 1. Prevent rapid double-clicking
    if (window.isVoting) return;
    window.isVoting = true;
    
    // 2. Get poll data from DOM to check settings
    const pollContainer = document.getElementById(`poll-container-${postId}`);
    if (!pollContainer) {
        window.isVoting = false;
        return;
    }
    
    // 3. Check if user has already voted on this poll
    const pollKey = `poll_${pollId}`;
    const userAlreadyVoted = currentPollVotes.has(pollKey);
    
    try {
        // 4. First, get poll details to check settings
        const pollDetailsRes = await fetch(`/DINADRAWING/Backend/events/get_poll.php?poll_id=${pollId}`);
        const pollDetails = await pollDetailsRes.json();
        
        if (!pollDetails.success) {
            console.error("Failed to get poll details");
            window.isVoting = false;
            return;
        }
        
        const poll = pollDetails.poll;
        
        // 5. CHECK: If multiple votes NOT allowed and user already voted
        if (!poll.allow_multiple && userAlreadyVoted) {
            alert("You have already voted in this poll. Multiple votes are not allowed.");
            window.isVoting = false;
            return;
        }
        
        // 6. Send the vote to server
        const res = await fetch('/DINADRAWING/Backend/events/vote_poll.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                poll_id: pollId, 
                option_id: optionId,
                allow_multiple: poll.allow_multiple 
            })
        });
        const data = await res.json();
        
        if (data.success) {
            // Track this vote
            if (!poll.allow_multiple) {
                currentPollVotes.set(pollKey, true);
            }
            
            // Update UI with fresh poll data
            const optionsContainer = document.getElementById(`poll-options-${postId}`);
            const statsContainer = document.getElementById(`poll-stats-${postId}`);
            
            if (optionsContainer && data.poll_data) {
                optionsContainer.innerHTML = generatePollOptionsHTML(data.poll_data, postId);
                if (statsContainer) {
                    statsContainer.textContent = `${data.poll_data.total_votes} total votes`;
                }
            }
            
            // Show success message
            if (typeof showToast === 'function') {
                showToast("Vote submitted!");
            }
        } else {
            console.error("Vote failed:", data.error);
            alert(data.error || "Failed to submit vote");
        }
    } catch(e) { 
        console.error("Vote network error", e);
        alert("Network error. Please try again.");
    } finally { 
        window.isVoting = false; 
    }
}

// ==========================================
// UPDATED: CLEAN COMMENT DESIGN + HOVER TOOLTIP
// ==========================================

async function toggleComments(postId) {
    const section = document.getElementById(`comments-section-${postId}`);
    const list = document.getElementById(`comments-list-${postId}`);
    
    // Toggle Visibility
    section.classList.toggle('hidden');
    
    // Load comments only if opening and empty
    if (!section.classList.contains('hidden') && list.children.length === 0) {
        try {
            // UPDATED LINE: Added "&t=${new Date().getTime()}" to force fresh data
            const res = await fetch(`/DINADRAWING/Backend/events/get_comments.php?post_id=${postId}&t=${new Date().getTime()}`);
            const data = await res.json();
            
            if (data.success && data.comments.length > 0) {
                list.innerHTML = data.comments.map(c => `
                    <div class="flex gap-3 items-start mb-4 animate-fade-in">
                        <img src="${c.user_avatar}" class="w-9 h-9 rounded-full object-cover border border-gray-100 shrink-0">
                        <div class="flex-1">
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-semibold text-sm text-[#222]">${c.user_name}</span>
                                <span class="text-xs text-gray-500 font-medium cursor-help" title="${c.full_date || 'Date unavailable'}">&bull; ${c.created_at}</span>
                            </div>
                            <p class="text-sm text-gray-800 mt-0.5 leading-snug">${c.content}</p>
                        </div>
                    </div>
                `).join('');
            } else {
                list.innerHTML = '<p class="text-xs text-gray-400 italic px-1">No comments yet. Be the first!</p>';
            }
        } catch(e) { console.error(e); }
    }
}

async function submitComment(postId) {
    const input = document.getElementById(`comment-input-${postId}`);
    const content = input.value.trim();
    if (!content) return;

    input.disabled = true;

    try {
        const res = await fetch('/DINADRAWING/Backend/events/add_comment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ post_id: postId, content: content })
        });
        const data = await res.json();

        if (data.success) {
            const c = data.comment;
            const list = document.getElementById(`comments-list-${postId}`);
            
            const emptyMsg = list.querySelector('p.italic');
            if (emptyMsg) emptyMsg.remove();

            const html = `
                <div class="flex gap-3 items-start mb-4 animate-fade-in">
                    <img src="${c.user_avatar}" class="w-9 h-9 rounded-full object-cover border border-gray-100 shrink-0">
                    <div class="flex-1">
                        <div class="flex items-baseline gap-1.5">
                            <span class="font-semibold text-sm text-[#222]">${c.user_name}</span>
                            <span class="text-xs text-gray-500 font-medium cursor-help" title="${c.full_date}">&bull; ${c.created_at}</span>
                        </div>
                        <p class="text-sm text-gray-800 mt-0.5 leading-snug">${c.content}</p>
                    </div>
                </div>`;
            
            list.insertAdjacentHTML('beforeend', html);
            input.value = ''; 
            list.scrollTop = list.scrollHeight;
        }
    } catch(e) { alert("Error posting comment"); }
    finally { input.disabled = false; input.focus(); }
}

// Toggle the Dropdown Menu
function togglePostMenu(e, postId) {
    e.stopPropagation();
    // Close all other menus first
    document.querySelectorAll('[id^="post-menu-"]').forEach(el => el.classList.add('hidden'));
    
    // Toggle this one
    const menu = document.getElementById(`post-menu-${postId}`);
    if (menu) menu.classList.toggle('hidden');
}

// Close menus when clicking anywhere else
document.addEventListener('click', () => {
    document.querySelectorAll('[id^="post-menu-"]').forEach(el => el.classList.add('hidden'));
});

// Logic: DELETE POST
async function deletePost(postId) {
    if(!confirm("Are you sure you want to delete this post?")) return;
    try {
        const res = await fetch('/DINADRAWING/Backend/events/delete_post.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ post_id: postId })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById(`post-${postId}`).remove();
        } else {
            alert("Error: " + data.error);
        }
    } catch (e) { console.error(e); }
}

// Logic: PIN POST
async function togglePin(postId) {
    try {
        const res = await fetch('/DINADRAWING/Backend/events/pin_post.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ post_id: postId })
        });
        const data = await res.json();
        if (data.success) {
            // Reload feed to see changes (easiest way to resort)
            loadEventPosts(); 
        } else {
            alert("Error: " + data.error);
        }
    } catch (e) { console.error(e); }
}
</script>

<script>
    // TAB SWITCHING
    function switchTab(tabName) {
      document.getElementById('feed-section')?.classList.remove('active');
      document.getElementById('budget-section')?.classList.remove('active');
      document.getElementById('settings-section')?.classList.remove('active');

      document.getElementById(`${tabName}-section`)?.classList.add('active');

      ['feed', 'budget', 'settings'].forEach(tab => {
        const btn = document.getElementById(`tab-${tab}`);
        if (!btn) return;
        btn.className = tab === tabName
          ? 'flex-1 bg-[#f4b41a] text-[#222] font-semibold py-2 text-center rounded-lg cursor-pointer'
          : 'flex-1 text-gray-600 font-medium py-2 text-center hover:text-[#222] hover:bg-gray-100 rounded-lg cursor-pointer';
      });

      if (tabName === 'budget' && typeof window.loadBudget === 'function') {
        window.loadBudget();
      }
    }
</script>

<script>
    // ==========================================
    // SETTINGS & DELETE LOGIC
    // ==========================================
    document.addEventListener("DOMContentLoaded", () => {
      initPollVotingState(); // <-- ADD THIS LINE FIRST
      loadEventPosts(); 
    loadMembers(); 
    setInterval(loadMembers, 5000);
        
        // SAVE SETTINGS
        document.getElementById("saveSettingsBtn")?.addEventListener("click", () => {
            // 1. Get Values
            const nameVal = document.getElementById("eventName").value.trim();
            const descVal = document.getElementById("eventDesc").value.trim();
            const locationVal = document.getElementById("eventPlace").value.trim();
            
            // 2. Handle Date & Time
            const dateTimeVal = document.getElementById("eventDate").value; 
            let datePart = null;
            let timePart = null;
            
            if (dateTimeVal) {
                const parts = dateTimeVal.split('T');
                datePart = parts[0];
                if (parts.length > 1) timePart = parts[1];
            }

            // 3. Prepare Payload
            const payload = {
                id: <?php echo (int)$id; ?>,
                name: nameVal,
                description: descVal,
                date: datePart,
                time: timePart,
                location: locationVal 
            };

            // 4. Send Request
            const btn = document.getElementById("saveSettingsBtn");
            const originalText = btn.textContent;
            btn.textContent = "Saving...";
            btn.disabled = true;

            fetch('/DINADRAWING/Backend/events/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('Settings saved successfully!');
                    // Update Banner Text
                    const bannerTitle = document.getElementById('bannerText');
                    if(bannerTitle) bannerTitle.textContent = nameVal;
                } else {
                    alert('Save failed: ' + (res.error || res.message || 'unknown error'));
                }
            })
            .catch(() => alert('Save failed due to network error.'))
            .finally(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            });
        });
        
        // DELETE EVENT
        document.getElementById('deleteEventBtn')?.addEventListener('click', () => {
            if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) return;
            
            // FIX: Updated path to match your backend folder structure
            fetch('/DINADRAWING/Backend/events/delete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: <?php echo (int)$id; ?> })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) window.location.href = 'myplans.php';
                else alert('Delete failed: ' + (res.error || 'unknown'));
            })
            .catch(() => alert('Delete failed due to network error'));
        });

    });

// HELPER: Generates Poll Options + Voter Faces
// ==========================================
// 3. POLL UI & LOGIC (Updated & Fixed)
// ==========================================

// ==========================================
// POLL UI GENERATOR (Venice Style + Face Piles)
// ==========================================
function generatePollOptionsHTML(pd, postId) {
    if (!pd || !pd.options) return '';
    const totalVotes = pd.total_votes || 0;
    
    // Check if user has already voted in this poll
    const pollKey = `poll_${pd.id}`;
    const userAlreadyVoted = currentPollVotes.has(pollKey) || pd.has_voted;
    
    let html = pd.options.map(opt => {
        const votes = opt.vote_count || 0;
        const percentage = totalVotes > 0 ? Math.round((votes / totalVotes) * 100) : 0;
        const isVoted = opt.is_voted;
        
        // Determine if this option should be clickable
        const isClickable = pd.allow_multiple || !userAlreadyVoted;
        
        // Add a class to indicate non-clickable state
        const containerClass = `relative w-full h-11 rounded-full border border-gray-200 bg-white overflow-hidden mb-2 transition-all ${isClickable ? 'cursor-pointer hover:border-[#f4b41a]' : 'cursor-not-allowed opacity-80'}`;
        
        const barColor = isVoted ? 'bg-[#f4b41a]' : 'bg-[#f4b41a]/60';
        
        const checkIcon = isVoted 
            ? `<div class="mr-3 text-gray-900 z-10"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></div>`
            : `<div class="mr-3 w-4 h-4 rounded-full border border-gray-300 ${isClickable ? 'group-hover:border-[#f4b41a]' : ''} transition z-10"></div>`;
        
        // Voters profile image logic...
        let avatarsHTML = '';
        if (opt.voters && opt.voters.length > 0) {
            const maxDisplay = 3;
            const displayVoters = opt.voters.slice(0, maxDisplay);
            const extraCount = votes - displayVoters.length;
            
            avatarsHTML = `<div class="flex -space-x-2 ml-3 items-center z-10 relative">`;
            displayVoters.forEach(pic => {
                avatarsHTML += `<img src="${pic}" class="w-6 h-6 rounded-full border-2 border-white object-cover shadow-sm" alt="Voter">`;
            });
            
            if (extraCount > 0) {
                avatarsHTML += `
                <div class="w-6 h-6 rounded-full border-2 border-white bg-gray-800 text-white flex items-center justify-center text-[8px] font-bold z-20">
                    +${extraCount}
                </div>`;
            }
            avatarsHTML += `</div>`;
        }
        
        return `
        <div class="${containerClass}" ${isClickable ? `onclick="votePoll(${pd.id}, ${opt.id}, ${postId})"` : 'title="You have already voted in this poll"'}>
          <div class="absolute top-0 left-0 h-full ${barColor} transition-all duration-500 ease-out z-0" 
               style="width: ${votes > 0 ? percentage : 0}%; opacity: ${votes > 0 ? 1 : 0};">
          </div>
          <div class="relative w-full h-full flex items-center px-4 z-10">
            ${checkIcon}
            <span class="text-sm font-medium text-gray-800 truncate flex-1">${opt.option_text}</span>
            ${votes > 0 ? `<span class="text-xs font-bold text-gray-700 ml-2">${percentage}%</span>` : ''}
            ${avatarsHTML}
          </div>
          ${!isClickable ? '<div class="absolute inset-0 bg-white/50 z-5"></div>' : ''}
        </div>`;
    }).join('');
    
    // Add warning message if user has voted and multiple votes not allowed
    if (userAlreadyVoted && !pd.allow_multiple) {
        html = `<div class="mb-3 p-2 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800 text-center">
                  You have already voted in this poll
                </div>` + html;
    }
    
    // Add option input...
    if (pd.allow_user_add && (!userAlreadyVoted || pd.allow_multiple)) {
        html += `
        <div class="relative w-full h-10 flex items-center mt-2">
            <input type="text" id="new-opt-${pd.id}" placeholder="+ Add option" 
                   class="w-full h-full rounded-full border border-gray-200 bg-white px-5 text-sm focus:outline-none focus:border-[#f4b41a] transition placeholder-gray-400">
            <button onclick="addPollOption(${pd.id}, ${postId})" 
                    class="absolute right-1 top-1 bottom-1 bg-gray-100 hover:bg-[#f4b41a] text-gray-600 hover:text-black rounded-full px-4 text-[10px] font-bold transition">
                ADD
            </button>
        </div>`;
    }
    
    return html;
}

// 2. VOTING FUNCTION (Connects to our secured Backend)
async function votePoll(pollId, optionId, postId) {
    // Basic frontend lock to prevent double clicking rapidly
    if (window.isVoting) return; 
    window.isVoting = true;

    try {
        const res = await fetch('/DINADRAWING/Backend/events/vote_poll.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ poll_id: pollId, option_id: optionId })
        });
        const data = await res.json();
        
        if (data.success) {
            // Find the container and update JUST that poll
            const optionsContainer = document.getElementById(`poll-options-${postId}`);
            const statsContainer = document.getElementById(`poll-stats-${postId}`);
            
            if (optionsContainer && data.poll_data) {
                optionsContainer.innerHTML = generatePollOptionsHTML(data.poll_data, postId);
                if (statsContainer) {
                    statsContainer.textContent = `${data.poll_data.total_votes} total votes`;
                }
            }
        } else {
            console.error("Vote failed:", data.error);
        }
    } catch(e) { console.error("Vote network error", e); }
    finally { window.isVoting = false; }
}
</script>

<script>
    // ==========================================
    // FEED LOADING LOGIC
    // ==========================================
    async function loadEventPosts() {
        const feedContainer = document.getElementById('feedContainer');
        if (!feedContainer) return;

        feedContainer.innerHTML = '<p class="text-center text-gray-500 py-4">Loading posts...</p>';

        try {
            const res = await fetch(`/DINADRAWING/Backend/events/get_posts.php?event_id=<?php echo (int)$id; ?>`);
            const data = await res.json();

            if (data.success) {
                feedContainer.innerHTML = ''; // Clear loading message
                if (data.posts.length === 0) {
                    feedContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No posts yet. Be the first to post!</p>';
                } else {
                    // Reverse to show oldest first when prepending (so newest ends up on top)
                    data.posts.reverse().forEach(post => {
                        // Check if function exists before calling
                        if (typeof prependNewPost === 'function') {
                            prependNewPost(post);
                        }
                    });
                }
            } else {
                feedContainer.innerHTML = '<p class="text-center text-red-500 py-4">Failed to load posts.</p>';
            }
        } catch (err) {
            console.error('Network error loading posts:', err);
            feedContainer.innerHTML = '<p class="text-center text-red-500 py-4">Network error.</p>';
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        loadEventPosts();
    });
</script>

<script>
    // ==========================================
    // LOAD MEMBERS & NOTIFICATIONS
    // ==========================================
    let lastMemberCount = -1; 

    async function loadMembers() {
        const listContainer = document.getElementById('membersList');
        const countLabel = document.querySelector('#membersCard h3');
        
        if(!listContainer) return;

        try {
            const res = await fetch(`/DINADRAWING/Backend/events/get_members.php?event_id=<?php echo (int)$id; ?>&t=${Date.now()}`);
            const data = await res.json();

            if (data.success && data.members) {
                const currentCount = data.members.length;

                if (lastMemberCount !== -1 && currentCount > lastMemberCount) {
                    showToast("A new member has joined your plan!");
                }
                lastMemberCount = currentCount; 

                if(countLabel) countLabel.textContent = `Members (${currentCount})`;
                listContainer.innerHTML = '';
                
                data.members.slice(0, 9).forEach(m => {
                    const wrapper = document.createElement('div');
                    wrapper.className = "relative inline-block w-8 h-8 cursor-pointer group"; 

                    const img = document.createElement('img');
                    img.src = m.avatar;
                    img.className = `w-8 h-8 rounded-full border-2 object-cover ${m.is_owner ? 'border-[#f4b41a]' : 'border-transparent'}`;
                    img.alt = m.name;
                    wrapper.appendChild(img);

                    if (m.is_owner) {
                        const badge = document.createElement('div');
                        badge.className = "absolute -bottom-1 -right-1 bg-white rounded-full p-[1px] shadow-sm z-20";
                        badge.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-[#f4b41a] fill-[#f4b41a]" viewBox="0 0 24 24"><path d="M12 2L14.4 9.6H22L16 14.4L18.4 22L12 17.6L5.6 22L8 14.4L2 9.6H9.6L12 2Z" stroke="none"/></svg>`; 
                        wrapper.appendChild(badge);
                    }
                    
                    const tooltip = document.createElement('div');
                    tooltip.className = "absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block whitespace-nowrap bg-gray-900 text-white text-[10px] py-1 px-2 rounded z-50 pointer-events-none";
                    tooltip.textContent = m.name + (m.is_owner ? " (Creator)" : "");
                    wrapper.appendChild(tooltip);

                    listContainer.appendChild(wrapper);
                });

                if (currentCount > 9) {
                    const more = document.createElement('span');
                    more.className = "w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500 border border-gray-200";
                    more.textContent = "+" + (currentCount - 9);
                    listContainer.appendChild(more);
                }
            }
        } catch (e) { console.error("Error loading members", e); }
    }

    function showToast(msg) {
        let toast = document.getElementById('toastNotification');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toastNotification';
            toast.className = 'fixed bottom-5 right-5 bg-gray-900 text-white px-4 py-3 rounded-lg shadow-lg transform transition-transform duration-300 translate-y-24 z-[100] flex items-center gap-3';
            toast.innerHTML = `<div class="bg-[#f4b41a] rounded-full p-1"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg></div><span id="toastMessage"></span>`;
            document.body.appendChild(toast);
        }
        
        const txt = document.getElementById('toastMessage');
        if(txt) {
            txt.textContent = msg;
            toast.classList.remove('translate-y-24');
            setTimeout(() => { toast.classList.add('translate-y-24'); }, 4000);
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        loadMembers(); 
        setInterval(loadMembers, 5000);
    });
</script>

<script>
    // ==========================================
    // BUDGET WIZARD, LOGIC & DISPLAY (MERGED)
    // ==========================================
    document.addEventListener("DOMContentLoaded", () => {
      
      // 1. SETUP VARIABLES
      const modal = document.getElementById("budgetModal");
      const openBtn = document.getElementById("openBudgetModal");
      const closeBtn = document.getElementById("closeBudgetModal");
      const editExpBtn = document.getElementById("editExpensesBtn");
      const editConBtn = document.getElementById("editContributionsBtn");
      
      // The single source of truth for budget data
      let budgetData = null;
      let currentStep = 0;
      let memberCounter = 1;

      const steps = [
        { id: "budgetStep1", title: "Add Expenses" },
        { id: "budgetStep2", title: "Set Division" },
        { id: "budgetStep3", title: "Confirm Plan" }
      ];

      // 2. HELPER FUNCTIONS
      function showStep(i){
        steps.forEach((s,idx)=> {
            const el = document.getElementById(s.id);
            if(el) el.classList.toggle("hidden", idx!==i);
        });
        const lbl = document.getElementById("budgetStepLabel");
        const tit = document.getElementById("budgetStepTitle");
        if(lbl) lbl.textContent = `Step ${i+1} of 3`;
        if(tit) tit.textContent = steps[i].title;
      }

      function openModal(stepIndex = 0) {
          modal.classList.remove('hidden'); 
          modal.classList.add('flex'); 
          currentStep = stepIndex; 
          showStep(currentStep); 
      }

      function closeModal() {
          modal.classList.add('hidden'); 
          modal.classList.remove('flex'); 
      }

      // Event Listeners for Modal
      if(openBtn) openBtn.addEventListener('click', () => openModal(0));
      if(closeBtn) closeBtn.addEventListener('click', closeModal);
      if(modal) modal.addEventListener('click', (e) => { if (e.target===modal) closeModal(); });

      // 3. LOGIC: STEP 1 (EXPENSES)
      function updateTotalCost() {
        let total = 0;
        document.querySelectorAll('#expenseTableBody input[type="number"]').forEach(inp => {
            total += parseFloat(inp.value) || 0;
        });
        const display = document.getElementById("totalCost");
        if(display) display.textContent = "₱" + total.toLocaleString(undefined, {minimumFractionDigits: 2});
      }

      function attachExpenseRowEvents(row) {
        const costInput = row.querySelector('.expense-cost');
        const delBtn = row.querySelector('.delete-expense');
        if (costInput) costInput.addEventListener('input', updateTotalCost);
        if (delBtn) {
            delBtn.onclick = () => { row.remove(); updateTotalCost(); };
        }
      }

      // Attach to existing rows
      document.querySelectorAll('#expenseTableBody tr').forEach(row => attachExpenseRowEvents(row));

      document.getElementById("addExpenseRow")?.addEventListener('click', () => {
        const tbody = document.getElementById("expenseTableBody");
        const row = document.createElement("tr");
        row.className = "border-b last:border-0 hover:bg-gray-50 transition";
        row.innerHTML = `
            <td><input type="text" placeholder="Item name" class="w-full bg-transparent border-none p-2 outline-none"></td>
            <td><input type="number" placeholder="0.00" step="0.01" class="w-full bg-transparent border-none p-2 text-right outline-none expense-cost"></td>
            <td class="text-center align-middle">
                <button class="delete-expense text-gray-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50 transition" title="Delete">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
            </td>
        `;
        tbody.appendChild(row);
        attachExpenseRowEvents(row);
      });

      // 4. LOGIC: STEP 2 (MEMBERS)
      function updateDivisionTotal() {
        let sum = 0;
        document.querySelectorAll('.member-amount').forEach(i => sum += parseFloat(i.value) || 0);
        const out = document.getElementById("divisionTotal");
        if (out) out.textContent = `₱${sum.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
      }

      function addMemberRow(member, amount="0.00", divisionType=null) {
        const tbody = document.getElementById("memberTableBody");
        const currentDivision = divisionType || document.querySelector('input[name="division"]:checked')?.value || 'equal';
        const isReadOnly = currentDivision === "equal";
        
        const row = document.createElement("tr");
        row.className = "border-b last:border-0 hover:bg-gray-50 transition";
        row.innerHTML = `
          <td class="py-2 px-3">
            <div class="flex items-center gap-2">
              <img src="${member.avatar || 'Assets/Profile Icon/profile.png'}" class="w-6 h-6 rounded-full border object-cover">
              <div class="flex-1">
                <input type="text" value="${member.name}" class="text-sm font-medium w-full bg-transparent border-none outline-none p-0 member-name" placeholder="Member name">
              </div>
            </div>
          </td>
          <td class="py-2 px-3">
            <input type="number" value="${amount}" class="w-full border rounded px-2 py-1 text-right member-amount ${isReadOnly ? 'bg-transparent border-none' : ''}" placeholder="0.00" step="0.01" min="0" ${isReadOnly ? 'readonly' : ''}>
          </td>
          <td class="py-2 px-3 text-center align-middle">
            <button class="delete-member text-gray-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50 transition" title="Remove member">
               <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
          </td>`;

        tbody.appendChild(row);
        if (!isReadOnly) row.querySelector('.member-amount').addEventListener('input', updateDivisionTotal);
        row.querySelector('.delete-member').addEventListener('click', () => { row.remove(); updateDivisionTotal(); });
      }

      function initMemberList() {
        const tbody = document.getElementById("memberTableBody");
        if(tbody.children.length > 0) return; // Don't wipe if data exists

        const total = parseFloat((document.getElementById("totalCost").textContent || '0').replace(/[₱,]/g,'')) || 0;
        
        // Grab members from sidebar
        const sidebarMembers = document.querySelectorAll('#membersList img');
        let membersToLoad = [];
        if(sidebarMembers.length > 0) {
            sidebarMembers.forEach(img => membersToLoad.push({ name: img.title || "Member", avatar: img.src }));
        } else {
            membersToLoad = [{ name: "You", avatar: "Assets/Profile Icon/profile.png" }]; 
        }

        const splitAmt = (total / membersToLoad.length).toFixed(2);
        membersToLoad.forEach(m => addMemberRow(m, splitAmt, "equal"));
        updateDivisionTotal();
      }

// FIXED: Add Member with Auto-Recalculation
      document.getElementById("addMemberBtn")?.addEventListener('click', () => {
        memberCounter++;
        
        // 1. Check current mode
        const isEqualMode = document.querySelector('input[name="division"][value="equal"]').checked;
        const totalBudget = parseFloat((document.getElementById("totalCost").textContent || '0').replace(/[₱,]/g,'')) || 0;

        // 2. Add the row based on mode
        if (isEqualMode) {
            // Add row as "equal" (Read Only)
            addMemberRow({ name: `Member ${memberCounter}`, avatar: "Assets/Profile Icon/profile.png" }, "0.00", "equal");
            
            // RECALCULATE SPLIT FOR EVERYONE
            const allAmountInputs = document.querySelectorAll('.member-amount');
            const count = allAmountInputs.length;
            if (count > 0) {
                const newSplit = (totalBudget / count).toFixed(2);
                allAmountInputs.forEach(input => {
                    input.value = newSplit;
                });
            }
        } else {
            // Custom Mode: Just add the row normally (Editable)
            addMemberRow({ name: `Member ${memberCounter}`, avatar: "Assets/Profile Icon/profile.png" }, "0.00", "custom");
        }

        // 3. Update the bottom total summary
        updateDivisionTotal();
      });

      // 5. NAVIGATION
      document.getElementById("toStep2")?.addEventListener('click', ()=>{
        currentStep = 1; showStep(currentStep);
        const numeric = parseFloat((document.getElementById("totalCost").textContent||'0').replace(/[₱,]/g,''))||0;
        document.getElementById("totalEstimatedStep2").innerText = numeric.toLocaleString();
        initMemberList();
      });

      document.getElementById("backFromStep2")?.addEventListener('click', ()=>{ currentStep = 0; showStep(currentStep); });
      
      document.getElementById("toStep3")?.addEventListener('click', ()=>{
        document.getElementById("summaryTotal").textContent = document.getElementById("totalCost").textContent;
        document.getElementById("summaryMembers").textContent = document.querySelectorAll('.member-amount').length;
        currentStep = 2; showStep(currentStep);
      });
      
      document.getElementById("backToStep2")?.addEventListener('click', ()=>{ currentStep = 1; showStep(currentStep); });

      // 6. SAVE FUNCTION (FIXED: Shows Budget Immediately)
      document.getElementById("saveBudgetPlan")?.addEventListener('click', async () => {
        const expenses = [];
        document.querySelectorAll('#expenseTableBody tr').forEach(row=>{
          const name = row.querySelector('input[type="text"]')?.value?.trim();
          const est = parseFloat(row.querySelector('input[type="number"]')?.value || "0") || 0;
          if (name || est > 0) expenses.push({ name, estimated: est, actual: 0, paid: false });
        });
        
        const contributions = [];
        document.querySelectorAll('#memberTableBody tr').forEach(row=>{
          const name = row.querySelector('.member-name')?.value || 'Member';
          const amt = parseFloat(row.querySelector('.member-amount')?.value || "0") || 0;
          const avatar = row.querySelector('img')?.src;
          contributions.push({ name, amount: amt, paid: false, avatar });
        });
        
        const payload = {
            event_id: <?php echo (int)$id; ?>,
            expenses: expenses,
            contributions: contributions,
            totalBudget: expenses.reduce((s,e)=>s+e.estimated,0)
        };

        try {
            await fetch('/DINADRAWING/Backend/events/save_budget.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            
            // --- THE FIX STARTS HERE ---
            budgetData = payload; 
            
            // 1. Force the view to switch immediately
            document.getElementById('noBudgetView').classList.add('hidden');
            document.getElementById('budgetView').classList.remove('hidden');

            // 2. Render the data
            renderBudget();       
            closeModal();
            // --- THE FIX ENDS HERE ---

        } catch(e) {
            console.error(e);
            alert("Network error saving budget.");
        }
      });

      // 7. EDIT LOGIC (POPULATE MODAL)
      function populateWizard() {
          if(!budgetData) return;

          // Expenses
          const expTbody = document.getElementById("expenseTableBody");
          if(expTbody) {
              expTbody.innerHTML = ''; 
              (budgetData.expenses || []).forEach(exp => {
                 const row = document.createElement("tr");
                 row.className = "border-b last:border-0 hover:bg-gray-50 transition";
                 row.innerHTML = `
                    <td><input type="text" value="${exp.name}" class="w-full bg-transparent border-none p-2 outline-none"></td>
                    <td><input type="number" value="${parseFloat(exp.estimated).toFixed(2)}" step="0.01" class="w-full bg-transparent border-none p-2 text-right outline-none expense-cost"></td>
                    <td class="text-center align-middle">
                        <button class="delete-expense text-gray-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50 transition">
                          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </td>`;
                 expTbody.appendChild(row);
                 attachExpenseRowEvents(row);
              });
              updateTotalCost();
          }

          // Members
          const memTbody = document.getElementById("memberTableBody");
          if(memTbody) {
              memTbody.innerHTML = '';
              // Force custom mode
              const customRadio = document.querySelector('input[value="custom"]');
              if(customRadio) { customRadio.checked = true; }

              (budgetData.contributions || []).forEach(con => {
                 addMemberRow({ name: con.name, avatar: con.avatar }, parseFloat(con.amount).toFixed(2), "custom");
              });
              updateDivisionTotal();
          }
      }

      if(editExpBtn) editExpBtn.addEventListener('click', () => { populateWizard(); openModal(0); });
      if(editConBtn) editConBtn.addEventListener('click', () => { populateWizard(); openModal(1); });

      // ==========================================
      // 8. DATA LOADING & RENDERING (THE CORE FIX)
      // ==========================================
      
      // Expose to window so tabs can call it
      window.loadBudget = async function() {
          try {
              const res = await fetch(`/DINADRAWING/Backend/events/get_budget.php?event_id=<?php echo (int)$id; ?>`);
              const data = await res.json();

              if (data.success && data.exists) {
                  // This updates the SHARED budgetData variable
                  budgetData = data.budget; 
                  
                  document.getElementById('noBudgetView').classList.add('hidden');
                  document.getElementById('budgetView').classList.remove('hidden');
                  
                  renderBudget(); // Call local render function
              } else {
                  document.getElementById('noBudgetView').classList.remove('hidden');
                  document.getElementById('budgetView').classList.add('hidden');
              }
          } catch (e) {
              console.error("Error loading budget:", e);
          }
      }

      function renderBudget() {
        if (!budgetData) return;

        // 1. Calculate Totals
        const total = parseFloat(budgetData.totalBudget) || 0;
        let collected = 0;
        budgetData.contributions.forEach(m => {
            if (m.paid) collected += parseFloat(m.amount);
        });
        const balance = total - collected;
        const progress = total > 0 ? (collected / total) * 100 : 0;

        // 2. Update Dashboard Cards
        document.getElementById('displayTotalBudget').textContent = "₱" + total.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('displayCollectedAmount').textContent = "₱" + collected.toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('displayBalanceAmount').textContent = "₱" + balance.toLocaleString(undefined, {minimumFractionDigits: 2});
        
        document.getElementById('progressPercentage').textContent = Math.round(progress) + "%";
        document.getElementById('progressBar').style.width = progress + "%";

        // 3. Render Expense View Table
        const expBody = document.getElementById('expenseDisplayBody');
        if(expBody) {
            expBody.innerHTML = '';
            let totalEst = 0;
            let totalAct = 0;

            budgetData.expenses.forEach(exp => {
                const est = parseFloat(exp.estimated) || 0;
                const act = parseFloat(exp.actual) || 0;
                totalEst += est;
                totalAct += act;

                expBody.insertAdjacentHTML('beforeend', `
                    <tr>
                        <td class="py-3 px-4 text-gray-800">${exp.name}</td>
                        <td class="py-3 px-4 text-right text-gray-600">₱${est.toLocaleString()}</td>
                        <td class="py-3 px-4 text-right text-gray-600">₱${act.toLocaleString()}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-medium ${exp.paid ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}">
                                ${exp.paid ? 'Paid' : 'Pending'}
                            </span>
                        </td>
                        <td class="py-3 px-4"></td>
                    </tr>
                `);
            });
            document.getElementById('totalEstimated').textContent = "₱" + totalEst.toLocaleString();
            document.getElementById('totalActual').textContent = "₱" + totalAct.toLocaleString();
        }

        // 4. Render Contribution View Table
        const contBody = document.getElementById('contributionDisplayBody');
        if(contBody) {
            contBody.innerHTML = '';
            budgetData.contributions.forEach((c, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="py-3 px-4 flex items-center gap-3">
                        <img src="${c.avatar || 'Assets/Profile Icon/profile.png'}" class="w-8 h-8 rounded-full object-cover">
                        <span class="font-medium text-gray-800">${c.name}</span>
                    </td>
                    <td class="py-3 px-4 text-right text-gray-600">₱${parseFloat(c.amount).toLocaleString()}</td>
                    <td class="py-3 px-4 text-center">
                        <button class="toggle-paid px-3 py-1 rounded-full text-xs font-semibold transition ${c.paid ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-700 hover:bg-red-200'}">
                            ${c.paid ? 'Paid' : 'Unpaid'}
                        </button>
                    </td>
                `;
                
                // Toggle Status & Auto-Save
                row.querySelector('.toggle-paid').addEventListener('click', async () => {
                    budgetData.contributions[index].paid = !budgetData.contributions[index].paid;
                    renderBudget(); // Re-render locally immediately
                    
                    await fetch('/DINADRAWING/Backend/events/save_budget.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            event_id: <?php echo (int)$id; ?>,
                            expenses: budgetData.expenses,
                            contributions: budgetData.contributions,
                            totalBudget: budgetData.totalBudget
                        })
                    });
                });
                contBody.appendChild(row);
            });
        }
      }

      // Initial Load
      window.loadBudget();
    });
</script>

<script>
// If you have a 'Create Event' modal on this page, this handles it.
// If not, you can delete this block.
document.getElementById('createEventSubmitBtn')?.addEventListener('click', async (e) => {
  e.preventDefault();
  
  const payload = {
    name: document.getElementById('createName').value,
    description: document.getElementById('createDescription').value,
    date: document.getElementById('createDate').value || null,
    time: document.getElementById('createTime').value || null,
    location: document.getElementById('createLocation').value || null
  };

  const res = await fetch('/DINADRAWING/Backend/events/create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  const data = await res.json();
  if (res.ok && data.success && data.id) {
    window.location.href = `/DINADRAWING/plan.php?id=${data.id}`;
  } else {
    alert(`Create failed: ${data.message || 'unknown error'}`);
  }
});
</script>

<script>
    // Open Modal
    function openInvite() {
        const modal = document.getElementById('inviteModal');
        if(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    // Close Modal
    function closeInvite() {
        const modal = document.getElementById('inviteModal');
        if(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // Copy Code (Fixed ID)
    function copyCode() {
        // The ID in your HTML is 'displayInviteCode', not 'modalInviteCode'
        const codeElement = document.getElementById('displayInviteCode'); 
        if(codeElement) {
            const codeText = codeElement.innerText;
            navigator.clipboard.writeText(codeText).then(() => {
                alert('Code copied: ' + codeText);
            });
        }
    }
</script>

<script>
    // Open the Invite Modal
    function openInvite() {
        const modal = document.getElementById('inviteModal');
        if(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } else {
            console.error("Error: inviteModal not found inside HTML");
        }
    }

    // Close the Invite Modal
    function closeInvite() {
        const modal = document.getElementById('inviteModal');
        if(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // Copy Code Function
    function copyModalCode() {
        // Corrected ID to match HTML
        const codeElement = document.getElementById('displayInviteCode');
        
        if (codeElement) {
            const codeText = codeElement.innerText;
            navigator.clipboard.writeText(codeText).then(() => alert(`Code copied: ${codeElement.innerText}`));
        } else {
            console.error("Error: Element 'displayInviteCode' not found.");
        }
    }

    
</script>

</body>
</html>

