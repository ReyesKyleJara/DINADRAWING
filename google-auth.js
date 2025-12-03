/*
import { signInWithPopup } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";
import { auth, provider } from "./firebase-config.js";

export async function signInWithGoogle() {
  try {
    const result = await signInWithPopup(auth, provider);
    const user = result.user;
    console.log("✅ Logged in as:", user.displayName);
    
    window.location.href = "dashboard.html";
    
  } catch (error) {
    console.error("❌ Login Error:", error);
    alert("Login failed. Please try again.");
  }
}
*/

import { auth } from "./firebase-config.js";
import { GoogleAuthProvider, signInWithPopup } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-auth.js";

const provider = new GoogleAuthProvider();

document.addEventListener("DOMContentLoaded", () => {
  const googleBtn = document.getElementById("googleLogin");
  if (googleBtn) {
    googleBtn.addEventListener("click", signInWithGoogle);
  }
});

function signInWithGoogle() {
  signInWithPopup(auth, provider)
    .then((result) => {
      const user = result.user;
      console.log("Google Login Success:", user);

      // SEND USER DATA TO BACKEND (auth.php)
      fetch("/DINADRAWING/Backend/api/auth.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          firebase_uid: user.uid,
          name: user.displayName,
          email: user.email
        })
      })
        .then((res) => res.json())
        .then((data) => {
          console.log("Backend Response:", data);

          // redirect only if backend accepts
          if (data.success) {
            window.location.href = "dashboard.html";
          } else {
            alert("Backend error: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Backend Fetch Error:", error);
        });
    })
    .catch((error) => {
      console.error("Google Login Error:", error);
      alert(error.message);
    });
}

