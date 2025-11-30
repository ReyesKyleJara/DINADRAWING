// firebase-config.js
import { initializeApp } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
import { getAuth, GoogleAuthProvider, signInWithPopup, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";

// Firebase config
const firebaseConfig = {
  apiKey: "AIzaSyAPDts35PYNaxXklqolOcobv1pLSc-m7ms",
  authDomain: "dinadrawing-12ab4.firebaseapp.com",
  projectId: "dinadrawing-12ab4",
  storageBucket: "dinadrawing-12ab4.firebasestorage.app",
  messagingSenderId: "140429427589",
  appId: "1:140429427589:web:cb9e22c90e9ad27e607aec",
  measurementId: "G-NC3ECPLG3Z"
};

// Initialize Firebase
export const app = initializeApp(firebaseConfig);
export const auth = getAuth(app);
export const provider = new GoogleAuthProvider();

// Function to login user with Google
export function loginWithGoogle() {
  signInWithPopup(auth, provider)
    .then((result) => {
      const user = result.user;
      console.log("Logged in as:", user.displayName, user.uid);
      sendUserToBackend(user);
    })
    .catch((error) => {
      console.error("Login error:", error);
    });
}

// Listen for auth state changes (auto login detection)
onAuthStateChanged(auth, (user) => {
  if (user) {
    console.log("User is logged in:", user.displayName, user.uid);
    sendUserToBackend(user);
  } else {
    console.log("No user logged in.");
  }
});

// Send user data to your backend
function sendUserToBackend(user) {
  fetch("http://localhost/DINADRAWING/Backend/api/auth.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      firebase_uid: user.uid,
      name: user.displayName,
      email: user.email
    })
  })
  .then(res => res.json())
  .then(data => console.log("Backend response:", data))
  .catch(err => console.error("Backend error:", err));
}
