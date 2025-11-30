import { signInWithGoogle } from "google-auth.js";

document.addEventListener("DOMContentLoaded", () => {
  // FIX: CHANGE FROM .google-btn to #googleLogin
  const googleBtn = document.getElementById("googleLogin");
  if (googleBtn) {
    googleBtn.addEventListener("click", signInWithGoogle);
    console.log("✅ Google login button event listener added");
  } else {
    console.error("❌ Google login button not found!");
  }
});

// HANDLE MODAL OPEN/CLOSE
document.getElementById("loginBtn").addEventListener("click", () => {
  document.getElementById("loginModal").classList.remove("hidden");
});

document.getElementById("closeModal").addEventListener("click", () => {
  document.getElementById("loginModal").classList.add("hidden");
});

// FAQ ACCORDION TOGGLE
document.querySelectorAll(".faq-toggle").forEach((btn) => {
  btn.addEventListener("click", () => {
    const answer = btn.nextElementSibling;
    answer.classList.toggle("hidden");
  });
});