import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Capacitor config — HapFam SportApp
 *
 * Strategi:
 *  - WebDir tetap "www" (minimal shell offline / redirector). Konten utama
 *    dimuat dari hosting PHP via `server.url`.
 *  - allowNavigation memastikan link internal PHP tetap dibuka di-app
 *    (bukan browser eksternal).
 *  - androidScheme "https" agar cookie session PHP & service-worker
 *    berperilaku identik dengan PWA di browser (sama-sama https origin).
 */
const config: CapacitorConfig = {
  appId: 'com.happyfamily.hapfamsportapp',
  appName: 'Happy Family SportApp',
  webDir: 'www',
  bundledWebRuntime: false,

  server: {
    url: 'https://sportapp-rumd.onrender.com',
    androidScheme: 'https',
    cleartext: false,
    allowNavigation: [
      'sportapp-rumd.onrender.com',
      '*.onrender.com',
      '*.googleapis.com',
      '*.gstatic.com',
      '*.imagekit.io',
      '*.firebaseio.com',
      '*.firebase.com'
    ]
  },

  android: {
    allowMixedContent: false,
    captureInput: true,
    webContentsDebuggingEnabled: false,
    backgroundColor: '#0f172a'
  },

  plugins: {
    SplashScreen: {
      launchShowDuration: 1200,
      launchAutoHide: true,
      backgroundColor: '#0f172a',
      androidSplashResourceName: 'splash',
      androidScaleType: 'CENTER_CROP',
      showSpinner: true,
      androidSpinnerStyle: 'large',
      spinnerColor: '#0ea5e9',
      splashFullScreen: true,
      splashImmersive: true
    },
    StatusBar: {
      style: 'LIGHT',
      backgroundColor: '#0f172a',
      overlaysWebView: true
    },
    Keyboard: {
      resize: 'native',
      resizeOnFullScreen: true
    }
  }
};

export default config;
