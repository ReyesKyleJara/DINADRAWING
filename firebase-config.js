// firebase-config.js
import { initializeApp } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";

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
