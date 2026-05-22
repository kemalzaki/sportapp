// SportApp v3 — Firebase Cloud Messaging client.
// Isi konfigurasi Firebase project Anda di bawah lalu jalankan ulang.
window.FCM_CONFIG = {
  apiKey: "REPLACE_ME",
  authDomain: "REPLACE_ME.firebaseapp.com",
  projectId: "REPLACE_ME",
  messagingSenderId: "REPLACE_ME",
  appId: "REPLACE_ME"
};
window.FCM_VAPID_KEY = "REPLACE_ME_PUBLIC_VAPID_KEY";

(async function initFCM() {
  if (!('serviceWorker' in navigator) || !('Notification' in window)) return;
  // register PWA SW dulu
  try { await navigator.serviceWorker.register('/service-worker.js'); } catch(e){}
  if (window.FCM_CONFIG.apiKey === 'REPLACE_ME') return; // belum dikonfigurasi
  try {
    const [{ initializeApp }, { getMessaging, getToken, onMessage }] = await Promise.all([
      import('https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js'),
      import('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging.js'),
    ]);
    const app = initializeApp(window.FCM_CONFIG);
    const messaging = getMessaging(app);
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') return;
    const token = await getToken(messaging, { vapidKey: window.FCM_VAPID_KEY });
    if (token) {
      fetch('/api_register_fcm.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'token=' + encodeURIComponent(token)
      });
    }
    onMessage(messaging, p => {
      try { new Notification(p.notification?.title || 'Notifikasi', { body: p.notification?.body || '' }); } catch(e){}
    });
  } catch (e) { console.warn('FCM init failed', e); }
})();
