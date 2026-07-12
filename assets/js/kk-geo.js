/* =========================================================================
 * KawanKeringat — Unified Geolocation Helper (KKGeo) v1
 *
 * Satu API untuk streaming titik GPS pada mode Web maupun APK (Capacitor).
 *
 *   KKGeo.isNative       -> true bila berjalan sebagai APK Capacitor
 *   KKGeo.start(onPos, onErr, opts) -> mulai streaming (return Promise<boolean>)
 *   KKGeo.stop()         -> hentikan streaming (return Promise)
 *   KKGeo.once(opts)     -> ambil satu titik (Promise<Position>)
 *
 * Semua callback menerima objek shape `Position` standar Web:
 *   { coords:{latitude,longitude,accuracy,speed,heading,altitude}, timestamp }
 *
 * Prioritas backend saat native:
 *   1. @capacitor-community/background-geolocation  (foreground service)
 *   2. @capacitor/geolocation                        (foreground only)
 *   3. navigator.geolocation                         (fallback terakhir)
 *
 * Additive — tidak mengubah API lama, tidak menghapus fitur existing.
 * ======================================================================= */
(function (global) {
  'use strict';

  var Cap        = global.Capacitor || null;
  var isNative   = !!(Cap && Cap.isNativePlatform && Cap.isNativePlatform());
  var Plugins    = (Cap && Cap.Plugins) || {};
  var BG         = Plugins.BackgroundGeolocation || null;
  var Geo        = Plugins.Geolocation || null;
  var LN         = Plugins.LocalNotifications || null;

  var bgId       = null;   // id watcher plugin background-geolocation
  var capWatchId = null;   // id watcher @capacitor/geolocation
  var webWatchId = null;   // id watchPosition standard
  var running    = false;
  var currentSrc = 'none'; // 'bg' | 'cap' | 'web' | 'none'

  function normalize(loc, ts) {
    return {
      coords: {
        latitude:  loc.latitude  != null ? loc.latitude  : loc.coords && loc.coords.latitude,
        longitude: loc.longitude != null ? loc.longitude : loc.coords && loc.coords.longitude,
        accuracy:  loc.accuracy  != null ? loc.accuracy  : loc.coords && loc.coords.accuracy,
        speed:     loc.speed     != null ? loc.speed     : loc.coords && loc.coords.speed,
        heading:   loc.bearing   != null ? loc.bearing   : (loc.heading != null ? loc.heading : (loc.coords && loc.coords.heading)),
        altitude:  loc.altitude  != null ? loc.altitude  : loc.coords && loc.coords.altitude
      },
      timestamp: ts || loc.time || (loc.timestamp) || Date.now()
    };
  }

  function startWeb(onPos, onErr, opts) {
    if (!('geolocation' in navigator)) {
      onErr && onErr(new Error('Browser tidak mendukung GPS.'));
      return false;
    }
    webWatchId = navigator.geolocation.watchPosition(
      function (p) { onPos && onPos(normalize(p, p.timestamp)); },
      function (e) { onErr && onErr(e); },
      Object.assign({ enableHighAccuracy: true, maximumAge: 2000, timeout: 15000 }, opts || {})
    );
    currentSrc = 'web';
    return true;
  }

  async function startBg(onPos, onErr, opts) {
    try {
      bgId = await BG.addWatcher({
        backgroundMessage: (opts && opts.backgroundMessage) || 'KawanKeringat sedang merekam GPS…',
        backgroundTitle:   (opts && opts.backgroundTitle)   || '🏃 Tracking aktif',
        requestPermissions: true,
        stale: false,
        distanceFilter: (opts && opts.distanceFilter != null) ? opts.distanceFilter : 3
      }, function (location, error) {
        if (error) { onErr && onErr(error); return; }
        onPos && onPos(normalize(location));
      });
      currentSrc = 'bg';
      return true;
    } catch (e) { console.warn('[KKGeo] BG gagal:', e); return false; }
  }

  async function startCapGeo(onPos, onErr, opts) {
    try {
      // Minta izin dulu supaya tidak silent-fail di Android 12+
      if (Geo.requestPermissions) { try { await Geo.requestPermissions(); } catch(_){} }
      capWatchId = await Geo.watchPosition(
        Object.assign({ enableHighAccuracy: true, timeout: 15000, maximumAge: 2000 }, opts || {}),
        function (pos, err) {
          if (err) { onErr && onErr(err); return; }
          onPos && onPos(normalize(pos, pos && pos.timestamp));
        }
      );
      currentSrc = 'cap';
      return true;
    } catch (e) { console.warn('[KKGeo] Capacitor Geolocation gagal:', e); return false; }
  }

  async function start(onPos, onErr, opts) {
    if (running) return true;
    running = true;
    if (isNative && BG) {
      if (await startBg(onPos, onErr, opts)) return true;
    }
    if (isNative && Geo) {
      if (await startCapGeo(onPos, onErr, opts)) return true;
    }
    var ok = startWeb(onPos, onErr, opts);
    if (!ok) running = false;
    return ok;
  }

  async function stop() {
    try {
      if (currentSrc === 'bg' && BG && bgId) { await BG.removeWatcher({ id: bgId }); }
      else if (currentSrc === 'cap' && Geo && capWatchId != null) { await Geo.clearWatch({ id: capWatchId }); }
      else if (currentSrc === 'web' && webWatchId != null) { navigator.geolocation.clearWatch(webWatchId); }
    } catch (e) { /* ignore */ }
    bgId = null; capWatchId = null; webWatchId = null;
    running = false; currentSrc = 'none';
  }

  function once(opts) {
    return new Promise(function (resolve, reject) {
      var options = Object.assign({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }, opts || {});
      if (isNative && Geo && Geo.getCurrentPosition) {
        Geo.getCurrentPosition(options)
          .then(function (p) { resolve(normalize(p, p && p.timestamp)); })
          .catch(reject);
        return;
      }
      if (!('geolocation' in navigator)) { reject(new Error('Browser tidak mendukung GPS.')); return; }
      navigator.geolocation.getCurrentPosition(
        function (p) { resolve(normalize(p, p.timestamp)); },
        reject, options
      );
    });
  }

  // Helper opsional — notifikasi native saat sesi tracking dimulai/berhenti.
  async function notify(title, body) {
    if (!isNative || !LN) return;
    try {
      await LN.schedule({ notifications: [{ id: Date.now() % 2147483647, title: title, body: body, schedule: { at: new Date(Date.now()+200) } }] });
    } catch (_) {}
  }

  global.KKGeo = {
    isNative: isNative,
    get running() { return running; },
    get source()  { return currentSrc; },
    start: start, stop: stop, once: once, notify: notify
  };
})(window);
