import { signInWithGoogle } from "./google-auth.js";

document.addEventListener("DOMContentLoaded", () => {
  // 1. Google Login Button (Login Modal)
  const googleBtn = document.getElementById("googleLogin");
  if (googleBtn) {
    googleBtn.addEventListener("click", (e) => {
      e.preventDefault();
      signInWithGoogle();
    });
    console.log("✅ Google login button listener attached");
  }

  // 2. Google Login Button (Signup Modal)
  const googleBtnSignup = document.getElementById("googleLoginSignup");
  if (googleBtnSignup) {
    googleBtnSignup.addEventListener("click", (e) => {
      e.preventDefault();
      signInWithGoogle();
    });
    console.log("✅ Google signup button listener attached");
  }
});