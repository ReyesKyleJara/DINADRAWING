<?php
session_start();

// 1. SECURITY CHECK: If PHP session is missing, redirect to index.
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Settings | DiNaDrawing</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.tailwindcss.com"></script>

  <script>
    (function() {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark-mode');
        document.body.classList.add('dark-mode');
      } else {
        document.documentElement.classList.remove('dark-mode');
        document.body.classList.remove('dark-mode');
      }
      window.addEventListener('storage', function(e) {
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
    body { font-family: 'Poppins', sans-serif; background-color: #fffaf2; color: #222; }
    .soft-shadow { box-shadow: 0 6px 14px rgba(0,0,0,0.06); }
    .thin-btn {
      padding: 4px 12px !important; border-radius: 8px !important; font-weight: 600;
      font-size: 0.95rem; height: 32px; display: inline-flex; align-items: center; justify-content: center;
    }
    .thin-danger { 
      padding: 4px 12px !important; border-radius: 8px !important; font-weight: 600;
      font-size: 0.95rem; height: 32px; display: inline-flex; align-items: center; justify-content: center;
    }
    .appearance-switch {
      position: relative; width: 100px; height: 34px; background: #e5e7eb; padding: 3px;
      border-radius: 9999px; display: flex; align-items: center; justify-content: space-between;
    }
    .appearance-switch .option {
      width: 50px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
      border-radius: 20px; cursor: pointer;
    }
    .appearance-switch .option.left svg { color: #9ca3af; }
    .appearance-switch .option.right svg { color: #9ca3af; }
    .appearance-switch .option.active {
      background: #ffffff; box-shadow: 0 3px 8px rgba(0,0,0,0.08); border: 1px solid rgba(0,0,0,0.04);
    }
    .appearance-switch .option.left.active svg { color: #f59e0b; }
    .appearance-switch .option.right.active svg { color: #374151; }

    /* Validation Styles */
    .req-valid .req-icon { color: #22c55e !important; }
    .req-valid .req-text { color: #16a34a; }
    #strengthLabel.weak { color: #ef4444; }
    #strengthLabel.medium { color: #f59e0b; }
    #strengthLabel.strong { color: #22c55e; }

    /* Dark Mode */
    body.dark-mode { background-color: #1a1a1a !important; color: #e0e0e0 !important; }
    body.dark-mode .bg-white { background-color: #2a2a2a !important; }
    body.dark-mode .bg-\[\#fffaf2\] { background-color: #1a1a1a !important; }
    body.dark-mode .bg-gray-50 { background-color: #2a2a2a !important; }
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, body.dark-mode p, body.dark-mode span, body.dark-mode label { color: #e0e0e0 !important; }
    body.dark-mode .text-gray-600, body.dark-mode .text-gray-700, body.dark-mode .text-gray-500 { color: #a0a0a0 !important; }
    body.dark-mode .text-\[\#222\] { color: #e0e0e0 !important; }
    body.dark-mode .border-gray-200 { border-color: #404040 !important; }
    body.dark-mode .border-gray-300 { border-color: #454545 !important; }
    body.dark-mode input, body.dark-mode textarea, body.dark-mode select {
      background-color: #2a2a2a !important; color: #e0e0e0 !important; border-color: #454545 !important;
    }
    body.dark-mode .appearance-switch { background: #404040 !important; }
    body.dark-mode .appearance-switch .option.active { background: #2a2a2a !important; }
    body.dark-mode #changeProfileModal .bg-white { background-color: #2a2a2a !important; }
    body.dark-mode #passwordRequirements, body.dark-mode #emailValidation { background-color: #333333 !important; border-color: #454545 !important; }
    body.dark-mode .req-text, body.dark-mode .email-format-text, body.dark-mode .email-match-text { color: #a0a0a0 !important; }

    /* Hamburger */
    .hamburger { display: flex; flex-direction: column; gap: 4px; cursor: pointer; padding: 8px; border-radius: 8px; transition: background 0.2s; }
    .hamburger:hover { background: rgba(244, 180, 26, 0.1); }
    .hamburger span { width: 24px; height: 3px; background: #222; border-radius: 2px; transition: all 0.3s; }
    body.dark-mode .hamburger span { background: #e0e0e0; }

    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 45; }
    .sidebar-overlay.active { display: block; }
    #sidebar { transition: transform 0.3s ease; z-index: 50; transform: translateX(-100%); }
    #sidebar.active { transform: translateX(0); }
    @media (min-width: 769px) { #sidebar { transform: translateX(0); } #sidebar:not(.active) { transform: translateX(-100%); } }
    main { transition: margin-left 0.3s ease; }
    @media (min-width: 769px) { main.sidebar-open { margin-left: 16rem; } main:not(.sidebar-open) { margin-left: 0; } }
    .page-header { transition: left 0.3s ease, width 0.3s ease; }
    @media (min-width: 769px) { .page-header.sidebar-open { left: 16rem; width: calc(100% - 16rem); } .page-header:not(.sidebar-open) { left: 0; width: 100%; } }
  </style>
</head>
<body class="flex bg-[#fffaf2]">

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside id="sidebar"
class="fixed top-4 left-0 h-[calc(100vh-1rem)] w-64
       bg-[#f4b41a] rounded-tr-3xl
       p-6 shadow flex flex-col gap-6">

  <div class="flex items-center gap-2">
    <img src="Assets/dinadrawing-logo.png" alt="Logo" class="w-14">
    <h2 class="text-xl font-bold text-[#222]">DiNaDrawing</h2>
  </div>

  <nav>
    <ul class="space-y-5">
      <li><a href="dashboard.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Home</a></li>
      <li><a href="myplans.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">My Plans</a></li>
      <li><a href="help.php" class="block px-4 py-2 rounded-lg font-medium text-[#222] hover:bg-[#222] hover:text-white transition">Help</a></li>
      <li><a href="settings.php" class="block px-4 py-2 rounded-lg font-medium bg-[#222] text-white hover:bg-[#111] transition" aria-current="page">Settings</a></li>
    </ul>
  </nav>
</aside>

<main id="mainContent" class="flex-1 min-h-screen px-12 py-10 pt-24">

  <div class="page-header flex justify-between items-center border-b-2 border-gray-200 pb-4 mb-6 fixed top-0 left-0 w-full bg-[#fffaf2] z-40 px-12 py-10">
    <div class="flex items-center gap-4">
      <button id="hamburgerBtn" class="hamburger"><span></span><span></span><span></span></button>
      <div class="flex flex-col">
        <h1 class="text-3xl font-bold">Settings</h1>
        <span class="text-gray-600 text-sm">Manage your account settings and preferences</span>
      </div>
    </div>

    <div class="flex items-center gap-3">
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
        </div>/Applications/XAMPP/xamppfiles/htdocs/DINADRAWING/Backend/event/event/delete.php
      </div>
    </div>
  </div>

  <div class="w-full mt-12">
    <div class="bg-white rounded-2xl soft-shadow overflow-hidden">
      <div class="p-6 md:p-8 space-y-10">

        <div>
          <h2 class="text-xl font-bold mb-6">Profile</h2>
          <div class="flex flex-col gap-6 px-6">
            <div class="flex items-center gap-6 mt-4">
              <div class="w-20 h-20 md:w-24 md:h-24 rounded-full overflow-hidden border border-black shadow-md">
                <img id="profileImage" src="Assets/Profile Icon/profile.png" alt="avatar" class="w-full h-full object-cover">
              </div>
              <div class="flex flex-col gap-2">
                <div class="flex gap-3">
                  <button id="changeImageBtn" class="bg-[#2a2a2a] text-white px-4 py-2 rounded-xl hover:bg-black">+ Change Profile Photo</button>
                </div>
                <p class="text-xs text-gray-500 mt-1">We support PNGs and JPGs under 300MB</p>
              </div>
            </div>

            <div class="flex-1">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 md:gap-x-4 md:[grid-template-columns:repeat(2,min-content)]">
                <div class="flex flex-col items-start">
                  <label class="text-sm text-gray-700 block mb-1">Name</label>
                  <div class="relative">
                    <input id="nameInput" type="text" placeholder="Your Name" class="w-80 max-w-full border border-gray-300 text-sm rounded-xl px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-[#f4b41a]" />
                  </div>
                </div>

                <div class="flex flex-col items-start">
                  <label class="text-sm text-gray-700 block mb-1">Username</label>
                  <div class="relative">
                    <input id="usernameInput" type="text" placeholder="@username" class="w-80 max-w-full border border-gray-300 text-sm rounded-xl px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-[#f4b41a]" />
                  </div>
                </div>
              </div>

              <div class="mt-4">
                <button id="saveProfileBtn" class="bg-[#222] text-white thin-btn hover:bg-black transition">Save Changes</button>
              </div>
            </div>
          </div>

          <hr class="my-8 border-t-2 border-gray-200" />

          <div>
            <h2 class="text-xl font-bold mb-6">Preferences</h2>
            <div class="flex flex-col gap-8 px-6">
              <div>
                <h3 class="text-lg font-semibold mb-4">Appearance</h3>
                <div class="flex items-center justify-between md:justify-start md:gap-[520px]">
                  <span class="font-medium text-gray-700 dark:text-gray-300">Theme</span>
                  <div id="appearanceSwitch" class="appearance-switch">
                    <div class="option left active" aria-hidden="true">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4"><g fill="currentColor"><circle cx="12" cy="12" r="3" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></g></svg>
                    </div>
                    <div class="option right" aria-hidden="true">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4"><path fill="currentColor" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" /></svg>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <hr class="my-8 border-t-2 border-gray-200" />

          <div>
            <h2 class="text-xl font-bold mb-6">Account & Security</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 md:gap-x-4 md:[grid-template-columns:repeat(2,min-content)]">
              <div class="flex flex-col gap-3 px-6 w-full">
                <div class="grid grid-cols-1 md:grid-cols-2 items-start gap-y-3 md:gap-x-8 mb-2">
                  <div><h3 class="text-lg font-semibold">Change Password</h3></div>
                  <div class="flex justify-start md:justify-start"><h3 class="text-lg font-semibold">Update Email</h3></div>
                </div>

                <div class="flex-1 w-full">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 md:gap-x-8 md:[grid-template-columns:repeat(2,min-content)]">
                    
                    <div class="flex flex-col items-start w-full">
                      <div class="flex flex-col gap-2 w-full">
                        <input type="password" id="oldPass" placeholder="Old Password" class="w-80 max-w-full border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#f4b41a]" />
                        <input type="password" id="newPass" placeholder="New Password" class="w-80 max-w-full border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#f4b41a]" />
                        <input type="password" id="confirmPass" placeholder="Confirm New Password" class="w-80 max-w-full border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#f4b41a]" />
                        <span id="passMatchMsg" class="text-xs text-red-500 hidden">Passwords do not match.</span>
                        <span id="passMatchSuccess" class="text-xs text-green-600 hidden">✓ Passwords match!</span>
                      </div>
                      <button onclick="validatePassword()" class="mt-2 bg-[#222] text-white thin-btn hover:bg-black transition">Save</button>

                      <div id="passwordRequirements" class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs hidden shadow-sm mt-2 w-full">
                        <h4 class="font-semibold mb-2 text-gray-700 text-xs">Requirements:</h4>
                        <div class="space-y-1">
                          <div id="req-lowercase" class="flex items-center gap-2"><span class="req-icon text-red-500 font-bold">✗</span><span class="req-text text-xs">Lowercase</span></div>
                          <div id="req-uppercase" class="flex items-center gap-2"><span class="req-icon text-red-500 font-bold">✗</span><span class="req-text text-xs">Uppercase</span></div>
                          <div id="req-number" class="flex items-center gap-2"><span class="req-icon text-red-500 font-bold">✗</span><span class="req-text text-xs">Number</span></div>
                          <div id="req-special" class="flex items-center gap-2"><span class="req-icon text-red-500 font-bold">✗</span><span class="req-text text-xs">Special char</span></div>
                          <div id="req-length" class="flex items-center gap-2"><span class="req-icon text-red-500 font-bold">✗</span><span class="req-text text-xs">8-12 chars</span></div>
                        </div>
                      </div>
                    </div>

                    <div class="flex flex-col items-start w-full">
                      <div class="flex flex-col gap-2 w-full">
                        <input type="email" id="currentEmail" placeholder="Current Email" value="" class="w-80 max-w-full border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#f4b41a]" />
                        <input type="email" id="newEmail" placeholder="New Email" class="w-80 max-w-full border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#f4b41a]" />
                        <input type="email" id="confirmEmail" placeholder="Confirm New Email" class="w-80 max-w-full border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#f4b41a]" />
                        <span id="emailFormatMsg" class="text-xs text-red-500 hidden">Invalid email</span>
                        <span id="emailFormatSuccess" class="text-xs text-green-600 hidden">✓ Valid email</span>
                        <span id="emailMatchMsg" class="text-xs text-red-500 hidden">Emails do not match</span>
                        <span id="emailMatchSuccess" class="text-xs text-green-600 hidden">✓ Match!</span>
                      </div>
                      <button onclick="validateEmail()" class="mt-2 bg-[#222] text-white thin-btn hover:bg-black transition">Verify</button>

                      <div id="emailValidation" class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs hidden shadow-sm mt-2 w-full">
                        <h4 class="font-semibold mb-2 text-gray-700 text-xs">Validation:</h4>
                        <div class="space-y-1">
                          <div id="email-format-check" class="flex items-center gap-2"><span class="email-format-icon text-red-500 font-bold">✗</span><span class="email-format-text text-xs">Valid email format</span></div>
                          <div id="email-match-check" class="flex items-center gap-2"><span class="email-match-icon text-red-500 font-bold">✗</span><span class="email-match-text text-xs">Emails match</span></div>
                        </div>
                      </div>
                    </div>

                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</main>

<div id="changeProfileModal" class="fixed inset-0 flex items-center justify-center bg-black/40 backdrop-blur-sm z-50 hidden">
  <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl relative">
    <button id="cancelBtn" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
    <h2 class="text-2xl font-bold mb-1">Change Profile Photo</h2>
    <p class="text-sm text-gray-500 mb-4">Choose or upload a new profile photo</p>
    <div class="flex justify-center mb-4">
      <img id="profilePreview" src="Assets/Profile Icon/profile.png" alt="Profile Preview" class="w-32 h-32 rounded-full object-cover border shadow-md" style="border-color: #090404;" />
    </div>
    <div class="flex justify-center gap-4 mb-4">
      <div id="uploadBtn" class="w-8 h-8 rounded-full border flex items-center justify-center cursor-pointer hover:bg-gray-100 transition" style="border-color: #090404;"><img src="Assets/upload.png" alt="Upload" class="w-6 h-6 opacity-70"></div>
      <div id="takePhotoBtn" class="w-8 h-8 rounded-full border flex items-center justify-center cursor-pointer hover:bg-gray-100 transition" style="border-color: #090404;"><img src="Assets/camera.png" alt="Take Photo" class="w-6 h-6 opacity-70"></div>
    </div>
    <input type="file" id="uploadInput" accept="image/*" class="hidden">
    <hr class="my-4" />
    <div class="grid grid-cols-5 gap-3 mb-6">
      <img src="Assets/Profile Icon/Profile.png" class="w-12 h-12 rounded-full cursor-pointer border hover:border-yellow-500" />
      <img src="Assets/Profile Icon/Profile2.png" class="w-12 h-12 rounded-full cursor-pointer border hover:border-yellow-500" />
      <img src="Assets/Profile Icon/Profile3.png" class="w-12 h-12 rounded-full cursor-pointer border hover:border-yellow-500" />
      <img src="Assets/Profile Icon/Profile4.png" class="w-12 h-12 rounded-full cursor-pointer border hover:border-yellow-500" />
      <img src="Assets/Profile Icon/Profile5.png" class="w-12 h-12 rounded-full cursor-pointer border hover:border-yellow-500" />
      <img src="Assets/Profile Icon/Profile6.png" class="w-12 h-12 rounded-full cursor-pointer border hover:border-yellow-500" />
      <img src="Assets/Profile Icon/Profile7.png" class="w-12 h-12 rounded-full cursor-pointer border hover:border-yellow-500" />
      <img src="Assets/Profile Icon/Profile8.png" class="w-12 h-12 rounded-full cursor-pointer border hover:border-yellow-500" />
    </div>
    <div class="flex justify-end gap-3">
      <button class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition" id="cancelBtnFooter">Cancel</button>
      <button class="bg-[#f4b41a] px-4 py-2 rounded-lg text-black hover:bg-[#e0a419] transition" id="saveChangesBtn">Save Changes</button>
    </div>
  </div>
</div>

<div id="logoutModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden z-[70] p-4 flex items-center justify-center">
  <div class="bg-white rounded-2xl shadow-xl relative w-full max-w-md p-6" role="dialog" aria-modal="true" onclick="event.stopPropagation()">
    <h2 class="text-xl font-bold text-[#222] mb-2">Log out?</h2>
    <p class="text-sm text-gray-600 mb-6">Are you sure you want to log out?</p>
    <div class="flex justify-end gap-3">
      <button id="cancelLogoutBtn" class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100 transition">Cancel</button>
      <button id="confirmLogoutBtn" class="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition">Yes</button>
    </div>
  </div>
</div>

<script>
  // UI & NAVIGATION LOGIC
  document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.getElementById('mainContent');
    const pageHeader = document.querySelector('.page-header');
    const isMobile = window.innerWidth < 769;

    if (localStorage.getItem('sidebarOpen') === 'true') {
        sidebar.classList.add('active');
        if (!isMobile) { mainContent.classList.add('sidebar-open'); pageHeader.classList.add('sidebar-open'); }
    }

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
  });

  const profileBtn = document.getElementById('profileBtn');
  const profileDropdown = document.getElementById('profileDropdown');
  profileBtn.addEventListener('click', () => profileDropdown.classList.toggle('hidden'));
  document.addEventListener('click', (e) => {
    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) profileDropdown.classList.add('hidden');
  });

  // THEME SWITCH
  (function(){
    const switchEl = document.getElementById('appearanceSwitch');
    if (!switchEl) return;
    const left = switchEl.querySelector('.option.left');
    const right = switchEl.querySelector('.option.right');
    const isDark = localStorage.getItem('theme') === 'dark';
    
    function setActive(isLight) {
      if (isLight) {
        left.classList.add('active'); right.classList.remove('active');
        document.documentElement.classList.remove('dark-mode'); document.body.classList.remove('dark-mode');
        localStorage.setItem('theme', 'light');
      } else {
        left.classList.remove('active'); right.classList.add('active');
        document.documentElement.classList.add('dark-mode'); document.body.classList.add('dark-mode');
        localStorage.setItem('theme', 'dark');
      }
    }
    if (isDark) setActive(false); else setActive(true);
    left.addEventListener('click', (e) => { e.stopPropagation(); setActive(true); });
    right.addEventListener('click', (e) => { e.stopPropagation(); setActive(false); });
    switchEl.addEventListener('click', () => { setActive(!left.classList.contains('active')); });
  })();
</script>

<script>
  // ==========================================
  //  MODALS & CAMERA LOGIC
  // ==========================================
  const modal = document.getElementById("changeProfileModal");
  const cancelBtn = document.getElementById("cancelBtn");
  const cancelBtnFooter = document.getElementById("cancelBtnFooter");
  const previewImg = document.getElementById("profilePreview");
  const uploadBtn = document.getElementById("uploadBtn");
  const uploadInput = document.getElementById("uploadInput");
  const saveChangesBtn = document.getElementById("saveChangesBtn");
  const avatarImages = document.querySelectorAll("#changeProfileModal .grid img");
  const takePhotoBtn = document.getElementById("takePhotoBtn");
  const mainProfileImg = document.getElementById("profileImage");

  function closeModal(){ modal?.classList.add("hidden"); }
  cancelBtn?.addEventListener("click", closeModal);
  cancelBtnFooter?.addEventListener("click", closeModal);

  let originalSrc = previewImg?.src || '';
  let selectedSrc = originalSrc;

  avatarImages.forEach(avatar => {
    avatar.addEventListener("click", () => {
      selectedSrc = avatar.src;
      if (previewImg) previewImg.src = avatar.src;
    });
  });
  uploadBtn?.addEventListener("click", () => uploadInput?.click());
  uploadInput?.addEventListener("change", (e) => {
    const file = e.target.files[0]; if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => { if (previewImg){ previewImg.src = ev.target.result; selectedSrc = ev.target.result; } };
    reader.readAsDataURL(file);
  });
  
  // OPEN MODAL
  document.getElementById("changeImageBtn")?.addEventListener("click", () => {
    originalSrc = previewImg?.src || '';
    selectedSrc = originalSrc;
    modal?.classList.remove("hidden");
  });
  modal?.addEventListener("click", (e) => {
    if (e.target === modal) { if (previewImg) previewImg.src = originalSrc; closeModal(); }
  });

  // CAMERA
  takePhotoBtn?.addEventListener("click", async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user", width: { ideal: 640 }, height: { ideal: 480 } } });
      const cameraModal = document.createElement("div");
      cameraModal.className = "fixed inset-0 flex items-center justify-center bg-black/40 backdrop-blur-sm z-50";
      cameraModal.innerHTML = `
        <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl relative">
          <button id="closeCamera" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
          <h2 class="text-2xl font-bold mb-4">Take a Photo</h2>
          <div class="relative mb-4"><video id="cameraVideo" autoplay playsinline class="w-full h-auto rounded-lg"></video></div>
          <div class="flex justify-center gap-3"><button id="captureBtn" class="bg-[#f4b41a] px-4 py-2 rounded-lg text-black hover:bg-[#e0a419] transition">Capture</button></div>
        </div>`;
      document.body.appendChild(cameraModal);
      const video = cameraModal.querySelector("#cameraVideo");
      video.srcObject = stream;
      cameraModal.querySelector("#closeCamera").addEventListener("click", () => { stream.getTracks().forEach(t=>t.stop()); cameraModal.remove(); });
      cameraModal.querySelector("#captureBtn").addEventListener("click", () => {
        const canvas = document.createElement("canvas"); canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext("2d").drawImage(video, 0, 0);
        const captured = canvas.toDataURL("image/png");
        if (previewImg){ previewImg.src = captured; selectedSrc = captured; }
        stream.getTracks().forEach(t=>t.stop()); cameraModal.remove();
      });
    } catch (err) { alert("Failed to access camera."); }
  });
</script>

<script>
  // ==========================================
  //  BACKEND INTEGRATION (API CALLS)
  // ==========================================
  
  // 1. UPDATE PROFILE PICTURE
  saveChangesBtn?.addEventListener("click", async () => {
    if (!selectedSrc) { closeModal(); return; }
    const btn = saveChangesBtn;
    btn.textContent = "Uploading..."; btn.disabled = true;

    try {
        const res = await fetch('/DINADRAWING/Backend/settings/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'photo', image: selectedSrc })
        });
        const data = await res.json();
        if (data.success) {
            if (mainProfileImg) mainProfileImg.src = selectedSrc;
            const navProfile = document.getElementById("navProfileImg");
            const dropProfile = document.getElementById("dropdownProfileImg");
            if (navProfile) navProfile.src = selectedSrc;
            if (dropProfile) dropProfile.src = selectedSrc;
            alert(data.message); closeModal();
        } else { alert("Error: " + data.message); }
    } catch (err) { alert("Server error during upload."); } 
    finally { btn.textContent = "Save Changes"; btn.disabled = false; }
  });

  // 2. UPDATE PROFILE INFO
  const nameInput = document.getElementById('nameInput');
  const usernameInput = document.getElementById('usernameInput');
  const saveProfileBtn = document.getElementById('saveProfileBtn');

  saveProfileBtn?.addEventListener('click', async () => {
    const name = nameInput.value;
    const username = usernameInput.value;
    const btn = saveProfileBtn;

    if (!name || !username) { alert('Please fill in both Name and Username.'); return; }
    btn.textContent = "Saving..."; btn.disabled = true;

    try {
      const res = await fetch('/DINADRAWING/Backend/settings/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'profile', name: name, username: username })
      });
      const data = await res.json();
      if (data.success) {
        alert(data.message);
        document.getElementById('navProfileName').textContent = name;
        document.getElementById('dropdownProfileName').textContent = name;
      } else { alert("Error: " + data.message); }
    } catch (err) { alert("Connection error."); } 
    finally { btn.textContent = "Save Changes"; btn.disabled = false; }
  });

  // 3. UPDATE PASSWORD
  async function validatePassword() {
    let oldPass = document.getElementById("oldPass").value;
    let newPass = document.getElementById("newPass").value;
    let confirmPass = document.getElementById("confirmPass").value;
    
    // Simple Validation
    const hasUpperCase = /[A-Z]/.test(newPass);
    const hasLowerCase = /[a-z]/.test(newPass);
    const hasNumber = /[0-9]/.test(newPass);
    const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>\-=+\/]/.test(newPass);
    const isValidLength = newPass.length >= 8 && newPass.length <= 12;

    if (!oldPass) { alert("Enter current password."); return; }
    if (!isValidLength || !hasUpperCase || !hasLowerCase || !hasNumber || !hasSpecialChar) { alert("Password requirements not met."); return; }
    if (newPass !== confirmPass) { alert("Passwords do not match."); return; }
    if (oldPass === newPass) { alert("New password must be different."); return; }

    try {
        const res = await fetch('/DINADRAWING/Backend/settings/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'password', old_password: oldPass, new_password: newPass })
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            document.getElementById("oldPass").value = '';
            document.getElementById("newPass").value = '';
            document.getElementById("confirmPass").value = '';
            document.getElementById("passwordRequirements")?.classList.add('hidden');
        } else { alert("Error: " + data.message); }
    } catch (err) { alert("Connection error."); }
  }

  // 4. UPDATE EMAIL
  async function validateEmail() {
    let newEmail = document.getElementById("newEmail").value;
    let confirmEmail = document.getElementById("confirmEmail").value;
    let currentEmail = document.getElementById("currentEmail").value;
    let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!currentEmail) { alert("Enter current email."); return; }
    if (!emailRegex.test(newEmail)) { alert("Invalid email format."); return; }
    if (newEmail !== confirmEmail) { alert("Emails do not match."); return; }
    if (newEmail === currentEmail) { alert("New email must be different."); return; }

    try {
        const res = await fetch('/DINADRAWING/Backend/settings/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'email', current_email: currentEmail, new_email: newEmail })
        });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            document.getElementById("newEmail").value = '';
            document.getElementById("confirmEmail").value = '';
            document.getElementById("currentEmail").value = newEmail;
            document.getElementById("emailValidation")?.classList.add('hidden');
        } else { alert("Error: " + data.message); }
    } catch (err) { alert("Connection error."); }
  }

  // 5. DATA INJECTION & LOGOUT
  const currentUser = <?php echo json_encode($userData); ?>;
  document.addEventListener('DOMContentLoaded', function() {
    function setUI(user) {
      if (!user) return;
      const ids = ['navProfileName', 'dropdownProfileName'];
      ids.forEach(id => { const el = document.getElementById(id); if(el) el.textContent = user.name || user.username; });
      const navImg = document.getElementById('navProfileImg');
      const ddImg  = document.getElementById('dropdownProfileImg');
      if (navImg && user.photo) navImg.src = user.photo;
      if (ddImg && user.photo) ddImg.src  = user.photo;

      // Fill Forms
      const mainProfileImg = document.getElementById('profileImage');
      const nameIn = document.getElementById('nameInput');
      const userIn = document.getElementById('usernameInput');
      const mailIn = document.getElementById('currentEmail');
      if (mainProfileImg && user.photo) mainProfileImg.src = user.photo;
      if (nameIn) nameIn.value = user.name;
      if (userIn) userIn.value = user.username;
      if (mailIn) mailIn.value = user.email;
    }
    setUI(currentUser);

    const logoutModal = document.getElementById('logoutModal');
    document.getElementById('logoutProfile')?.addEventListener('click', (e) => {
        e.preventDefault(); e.stopPropagation();
        logoutModal.classList.remove('hidden');
        document.getElementById('profileDropdown')?.classList.add('hidden');
    });
    document.getElementById('cancelLogoutBtn')?.addEventListener('click', () => logoutModal.classList.add('hidden'));
    document.getElementById('confirmLogoutBtn')?.addEventListener('click', () => {
        localStorage.removeItem('currentUser'); localStorage.removeItem('authSource');
        window.location.href = '/DINADRAWING/Backend/auth/logout.php';
    });
    logoutModal.addEventListener('click', (e) => { if (e.target === logoutModal) logoutModal.classList.add('hidden'); });
  });

  // REAL-TIME VALIDATION HELPERS (UI ONLY)
  const newPassInput = document.getElementById("newPass");
  const passwordRequirements = document.getElementById("passwordRequirements");
  newPassInput?.addEventListener('input', () => {
    const password = newPassInput.value;
    passwordRequirements?.classList.remove('hidden');
    // ... (Keep your visual validation logic here if desired, or rely on backend check) ...
  });
</script>

</body>
</html>