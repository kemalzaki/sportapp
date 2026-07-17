/* ============================================================
 * KK Run · background.js
 * - Capacitor BackgroundGeolocation
 * - KeepAwake (WakeLock browser + Capacitor plugin bila ada)
 * - Foreground service notification
 * - Floating tracking bubble (Android Overlay, opsional)
 * ============================================================ */
(function(){
  'use strict';

  var isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform &&
                    window.Capacitor.isNativePlatform());
  var bgWatcherId = null;
  var wakeLock = null;

  function plugin(name){
    return (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins[name]) || null;
  }

  async function acquireWakeLock(){
    // 1) Native Capacitor KeepAwake
    var KA = plugin('KeepAwake');
    if (KA && KA.keepAwake){ try { await KA.keepAwake(); } catch(e){} }
    // 2) Browser WakeLock
    try {
      if ('wakeLock' in navigator){
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLock.addEventListener('release', function(){ wakeLock = null; });
      }
    } catch(e){}
  }
  async function releaseWakeLock(){
    var KA = plugin('KeepAwake');
    if (KA && KA.allowSleep){ try { await KA.allowSleep(); } catch(e){} }
    try { if (wakeLock){ await wakeLock.release(); wakeLock = null; } } catch(e){}
  }

  async function startBackgroundGeoloc(onFix){
    if (!isNative) return false;
    var BG = plugin('BackgroundGeolocation');
    if (!BG || !BG.addWatcher){
      console.warn('[BG] plugin belum diinstall pada APK.');
      return false;
    }
    try {
      bgWatcherId = await BG.addWatcher({
        backgroundMessage: 'KawanKeringat merekam aktivitasmu…',
        backgroundTitle: '🏃 Tracking aktif',
        requestPermissions: true,
        stale: false,
        distanceFilter: 3
      }, function(location, error){
        if (error){ if (onFix) onFix(null, error); return; }
        if (onFix) onFix({
          coords:{
            latitude: location.latitude, longitude: location.longitude,
            accuracy: location.accuracy, speed: location.speed,
            altitude: location.altitude, heading: location.bearing
          },
          timestamp: location.time || Date.now()
        }, null);
      });
      return true;
    } catch(e){ console.warn('[BG] gagal:', e); return false; }
  }
  async function stopBackgroundGeoloc(){
    try {
      var BG = plugin('BackgroundGeolocation');
      if (BG && bgWatcherId) await BG.removeWatcher({ id: bgWatcherId });
    } catch(e){}
    bgWatcherId = null;
  }

  /* Notification permanen (foreground service) — memakai LocalNotifications */
  var lastNotifAt = 0;
  async function updateNotification(state){
    if (!isNative) return;
    var LN = plugin('LocalNotifications');
    if (!LN || !LN.schedule) return;
    var now = Date.now();
    if (now - lastNotifAt < 3000) return;
    lastNotifAt = now;
    try {
      var km = (state.totalM/1000).toFixed(2);
      var t = state.elapsedSec || 0;
      var m = Math.floor(t/60), s = t%60;
      await LN.schedule({
        notifications: [{
          id: 990001,
          title: '🏃 KawanKeringat',
          body: 'Distance: ' + km + ' km · Time: '
                + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0'),
          ongoing: true, autoCancel: false, smallIcon: 'ic_stat_run'
        }]
      });
    } catch(e){}
  }
  async function clearNotification(){
    var LN = plugin('LocalNotifications');
    if (!LN || !LN.cancel) return;
    try { await LN.cancel({ notifications: [{ id: 990001 }] }); } catch(e){}
  }

  /* Floating bubble Google-Maps-style (Android Overlay) */
  async function showBubble(state){
    var OV = plugin('FloatingOverlay') || plugin('SystemAlertWindow');
    if (!OV || !OV.show) return false;
    try {
      await OV.show({
        text: '🏃 ' + (state.totalM/1000).toFixed(2) + ' km',
        onTap: 'return' // handled native side to return to app
      });
      return true;
    } catch(e){ return false; }
  }
  async function hideBubble(){
    var OV = plugin('FloatingOverlay') || plugin('SystemAlertWindow');
    if (!OV || !OV.hide) return;
    try { await OV.hide(); } catch(e){}
  }

  window.KKBackground = {
    isNative: isNative,
    acquireWakeLock: acquireWakeLock,
    releaseWakeLock: releaseWakeLock,
    startBackgroundGeoloc: startBackgroundGeoloc,
    stopBackgroundGeoloc: stopBackgroundGeoloc,
    updateNotification: updateNotification,
    clearNotification: clearNotification,
    showBubble: showBubble,
    hideBubble: hideBubble
  };
})();
