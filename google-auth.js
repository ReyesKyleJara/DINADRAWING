import { auth } from "./firebase-config.js";
import {
  GoogleAuthProvider,
  signInWithPopup,
  setPersistence,
  inMemoryPersistence,
  signOut
} from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";

const provider = new GoogleAuthProvider();

/* Disable One Tap / auto account */
provider.setCustomParameters({
  prompt: "select_account"
});

export async function signInWithGoogle() {
  try {
    /* 🔥 FORCE CLEAR ANY EXISTING FIREBASE SESSION */
    if (auth.currentUser) {
      await signOut(auth);
    }

    /* 🔥 NO SESSION RESTORE */
    await setPersistence(auth, inMemoryPersistence);

    const result = await signInWithPopup(auth, provider);
    const user = result.user;

    console.log("Google Photo:", user.photoURL);

    if (window.handleGoogleBackend) {
      window.handleGoogleBackend(user);
    }
  } catch (err) {
    console.error("Google Auth Error:", err);
  }
}
