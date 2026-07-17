/* ============================================================
 * KK Run · voice.js  — Text-to-Speech feedback
 * Menggunakan Web Speech API (juga tersedia di WebView Android
 * pada Capacitor). Untuk bahasa Indonesia (id-ID).
 * ============================================================ */
(function(){
  'use strict';
  var enabled = true;
  var lastKmSpoken = 0;

  function fmtPaceWord(secPerKm){
    if (!isFinite(secPerKm) || secPerKm <= 0) return 'belum stabil';
    var m = Math.floor(secPerKm/60), s = Math.floor(secPerKm%60);
    return m + ' menit ' + s + ' detik';
  }

  function speak(text){
    if (!enabled) return;
    if (!('speechSynthesis' in window)) return;
    try {
      var u = new SpeechSynthesisUtterance(text);
      u.lang = 'id-ID';
      u.rate = 1.0; u.pitch = 1.0; u.volume = 1.0;
      window.speechSynthesis.cancel();
      window.speechSynthesis.speak(u);
    } catch(e){}
  }

  window.KKVoice = {
    setEnabled: function(v){ enabled = !!v; },
    isEnabled: function(){ return enabled; },
    reset: function(){ lastKmSpoken = 0; },

    /* Panggil di setiap update UI. `intervalM` = 500 / 1000 / 0 (off) */
    onDistance: function(totalM, avgPaceSec, intervalM){
      if (!intervalM) return;
      var step = intervalM / 1000; // km per step
      var kmNow = totalM / 1000;
      var mark = Math.floor(kmNow / step) * step;
      if (mark > lastKmSpoken && mark >= step){
        lastKmSpoken = mark;
        var msg = 'Jarak ' + mark.toFixed(mark % 1 ? 1 : 0) + ' kilometer. ' +
                  'Pace rata-rata ' + fmtPaceWord(avgPaceSec) + '. ' +
                  'Pertahankan ritme.';
        speak(msg);
      }
    },

    say: function(text){ speak(text); }
  };
})();
