# Capacitor Android Wrapper — KawanKeringat

Folder ini mem-bundle aplikasi Android (APK / AAB) yang membungkus website
PHP Anda di `https://sportapp-rumd.onrender.com`.

> Folder ini **terpisah** dari kode PHP. Anda hanya menjalankan perintah di
> folder ini di **laptop lokal** Anda (bukan di hosting). Sumber PHP tetap
> berjalan di hosting Render seperti biasa.

---

## 0. Prasyarat (sekali saja)

1. **Node.js 20+** — https://nodejs.org
2. **Android Studio** (versi terbaru, Hedgehog/Iguana ke atas)
3. **JDK 17** (otomatis terpasang bersama Android Studio)
4. **Android SDK Platform 34** + **Build-Tools 34.0.0**

Buka Android Studio → SDK Manager → centang “Android 14 (API 34)” + “Android SDK
Command-line Tools (latest)”. Set environment variable `ANDROID_HOME` ke folder
SDK (mis. `~/Library/Android/sdk` atau `C:\Users\<You>\AppData\Local\Android\Sdk`).

---

## 1. Setup awal proyek Android (sekali)

Dari dalam folder `capacitor/`:

```bash
npm install
npx cap add android      # generate folder android/ pertama kali
npx cap sync android
```

Setelah ini akan muncul folder `android/` lengkap (Gradle, AndroidManifest, dll).

---

## 2. Generate icon & splash screen (sekali / tiap rebrand)

Letakkan dua file di `capacitor/resources/`:

- `icon.png`   → minimal **1024 × 1024**, latar penuh (akan dipotong otomatis)
- `splash.png` → minimal **2732 × 2732**, logo di tengah, latar `#0f172a`

Lalu jalankan:

```bash
npx capacitor-assets generate --android
npx cap sync android
```

Ini otomatis mengisi semua ukuran `mipmap-*`, `drawable-*`, dan adaptive icon.

---

## 3. Permission Android (edit sekali)

Buka `android/app/src/main/AndroidManifest.xml` dan **pastikan** ada di dalam
tag `<manifest>` (di atas `<application>`):

```xml
<uses-permission android:name="android.permission.INTERNET"/>
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE"/>
<uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>
<uses-permission android:name="android.permission.VIBRATE"/>
<!-- Untuk fitur masa depan: -->
<uses-permission android:name="android.permission.CAMERA"/>
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION"/>
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION"/>
<uses-permission android:name="android.permission.USE_BIOMETRIC"/>
```

Pastikan juga di `<application ...>`:

```xml
android:usesCleartextTraffic="false"
android:networkSecurityConfig="@xml/network_security_config"
```

---

## 4. Jalankan di emulator / HP

```bash
npx cap run android            # otomatis pilih device
# atau buka di Android Studio:
npx cap open android
```

Tekan tombol ▶ Run di Android Studio. APK debug akan ter-install di device.

---

## 5. Build APK untuk diinstall manual

**Debug APK** (cepat, untuk testing):
```bash
cd android
./gradlew assembleDebug
```
Output: `android/app/build/outputs/apk/debug/app-debug.apk`

**Release APK** (production, butuh signing):
```bash
cd android
./gradlew assembleRelease
```

Untuk release, Anda perlu **keystore**. Generate sekali:

```bash
keytool -genkey -v -keystore hapfam-release.jks \
  -keyalg RSA -keysize 2048 -validity 10000 -alias hapfam
```

Lalu tambahkan di `android/app/build.gradle` (blok `android { ... }`):

```gradle
signingConfigs {
    release {
        storeFile file('../../hapfam-release.jks')
        storePassword System.getenv('HAPFAM_STORE_PW') ?: 'GANTI_PASSWORD'
        keyAlias 'hapfam'
        keyPassword System.getenv('HAPFAM_KEY_PW') ?: 'GANTI_PASSWORD'
    }
}
buildTypes {
    release {
        signingConfig signingConfigs.release
        minifyEnabled true
        shrinkResources true
        proguardFiles getDefaultProguardFile('proguard-android-optimize.txt'), 'proguard-rules.pro'
    }
}
```

Lalu:
```bash
./gradlew assembleRelease
```
Output APK signed: `android/app/build/outputs/apk/release/app-release.apk`

**AAB (untuk Play Store):**
```bash
./gradlew bundleRelease
```
Output: `android/app/build/outputs/bundle/release/app-release.aab`

---

## 6. Setelah update kode PHP / asset

Karena `server.url` mengarah ke hosting, **tidak perlu rebuild APK** untuk
perubahan PHP/CSS/JS biasa. Cukup deploy ke Render seperti biasa.

Anda **perlu rebuild APK** hanya jika:
- Mengubah `capacitor.config.ts` (URL, splash, plugin)
- Menambah/menghapus Capacitor plugin
- Mengubah icon / splash / permission Android

Setelah perubahan tersebut:
```bash
npx cap sync android
cd android && ./gradlew assembleDebug
```

---

## 7. Troubleshooting cepat

| Masalah | Solusi |
| --- | --- |
| Layar putih saat dibuka | Pastikan `server.url` `https://` dan hosting Render online |
| “net::ERR_CLEARTEXT_NOT_PERMITTED” | Hosting wajib HTTPS, jangan pakai `http://` |
| Cookie / session login hilang | Pastikan PHP set cookie dengan `SameSite=None; Secure` |
| Tombol back keluar app langsung | Sudah dihandle `mobile-shell.js` via Capacitor App plugin |
| Notifikasi tidak muncul | Permission `POST_NOTIFICATIONS` di Android 13+ wajib di-request runtime |

---

## 8. Future native upgrades (tinggal `npm install`)

```bash
npm i @capacitor/push-notifications     # Push FCM
npm i @capacitor/geolocation            # GPS (untuk run.php)
npm i @capacitor/camera                 # Upload foto native
npm i @capacitor-community/barcode-scanner  # QR check-in native
npm i @capacitor/preferences            # Offline key/value storage
npm i capacitor-native-biometric        # Login biometrik
npx cap sync android
```
