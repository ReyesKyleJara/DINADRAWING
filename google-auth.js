import { auth } from "./firebase-config.js";
import { GoogleAuthProvider, signInWithPopup } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";

const provider = new GoogleAuthProvider();

export async function signInWithGoogle() {
  try {
    const result = await signInWithPopup(auth, provider);
    const user = result.user;
    
    // Check kung nakuha ang photo URL
    console.log("Google Photo:", user.photoURL); 

    if (window.handleGoogleBackend) {
      // Ipapasa nito ang photoURL kasama ang ibang data
      window.handleGoogleBackend(user);
    } else {
      console.error("Error: window.handleGoogleBackend is not defined.");
    }

  } catch (err) {
    console.error("Google Auth Error:", err);
  }
}