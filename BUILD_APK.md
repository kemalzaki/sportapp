# Build APK — Quick Reference

Detail lengkap ada di `capacitor/README.md`. Ini cuma cheatsheet.

## Setup sekali

```bash
cd capacitor
npm install
npx cap add android
# Letakkan capacitor/resources/icon.png (1024x1024) dan splash.png (2732x2732)
npx capacitor-assets generate --android
npx cap sync android
```

## Build APK debug (untuk testing pribadi)

```bash
cd capacitor/android
./gradlew assembleDebug
# → app/build/outputs/apk/debug/app-debug.apk
```

## Build APK release (signed, untuk distribusi)

```bash
# 1. Generate keystore sekali
keytool -genkey -v -keystore capacitor/hapfam-release.jks \
  -keyalg RSA -keysize 2048 -validity 10000 -alias hapfam

# 2. Konfigurasi signing di android/app/build.gradle (lihat capacitor/README.md §5)

# 3. Build
cd capacitor/android
./gradlew assembleRelease
# → app/build/outputs/apk/release/app-release.apk
```

## Build AAB (Play Store)

```bash
cd capacitor/android
./gradlew bundleRelease
# → app/build/outputs/bundle/release/app-release.aab
```

## Buka di Android Studio

```bash
cd capacitor
npx cap open android
# Klik ▶ Run, pilih emulator / device USB
```

## Sinkronisasi setelah update kode

- **Update konten PHP/CSS/JS:** TIDAK perlu rebuild APK. Deploy ke Render saja.
- **Update `capacitor.config.ts`, plugin, icon, splash, permission:**
  ```bash
  cd capacitor && npx cap sync android
  cd android && ./gradlew assembleDebug
  ```

## Package & URL

- **Package ID:** `com.happyfamily.hapfamsportapp`
- **App name:** `KawanKeringat`
- **Server URL:** `https://sportapp-rumd.onrender.com`
