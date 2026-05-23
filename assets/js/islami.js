/* Fitur Islami: jadwal sholat realtime, countdown, mode tenang, quote popup */
(function () {
  'use strict';

  // ===== Quote islami popup saat buka aplikasi (1x per sesi) =====
  try {
    if (!sessionStorage.getItem('islami_quote_seen')) {
      sessionStorage.setItem('islami_quote_seen', '1');
      // popup ringan, jangan ganggu kalau ada modal lain
    }
  } catch (e) {}

  // ===== Jadwal Sholat via aladhan.com =====
  async function loadPrayer() {
    var card = document.getElementById('prayerCard');
    if (!card) return;
    var kota = card.getAttribute('data-kota') || 'Jakarta';
    var negara = card.getAttribute('data-negara') || 'Indonesia';
    var modeTenang = card.getAttribute('data-mode-tenang') === '1';
    var key = 'prayer_' + kota + '_' + negara + '_' + new Date().toISOString().slice(0,10);
    var data = null;
    try {
      var cached = localStorage.getItem(key);
      if (cached) data = JSON.parse(cached);
    } catch (e) {}
    if (!data) {
      try {
        var res = await fetch('https://api.aladhan.com/v1/timingsByCity?city=' + encodeURIComponent(kota) +
          '&country=' + encodeURIComponent(negara) + '&method=20');
        var j = await res.json();
        if (j && j.data && j.data.timings) {
          data = j.data.timings;
          try { localStorage.setItem(key, JSON.stringify(data)); } catch (e) {}
        }
      } catch (e) {
        document.getElementById('prayerNext').textContent = 'Gagal memuat jadwal (offline).';
        return;
      }
    }
    if (!data) return;
    var order = ['Fajr','Dhuhr','Asr','Maghrib','Isha'];
    var label = {Fajr:'Subuh',Dhuhr:'Dzuhur',Asr:'Ashar',Maghrib:'Maghrib',Isha:'Isya'};
    var html = '';
    order.forEach(function (k) {
      html += '<span class="me-2"><strong>' + label[k] + '</strong> ' + data[k] + '</span>';
    });
    document.getElementById('prayerList').innerHTML = html;

    function tick() {
      var now = new Date();
      var next = null, nextName = null, nextDate = null;
      for (var i = 0; i < order.length; i++) {
        var k = order[i];
        var parts = (data[k] || '').split(':');
        if (parts.length < 2) continue;
        var d = new Date(now.getFullYear(), now.getMonth(), now.getDate(), parseInt(parts[0],10), parseInt(parts[1],10), 0);
        if (d > now) { next = d; nextName = label[k]; break; }
      }
      if (!next) {
        var p = (data.Fajr || '04:30').split(':');
        next = new Date(now.getFullYear(), now.getMonth(), now.getDate()+1, parseInt(p[0],10), parseInt(p[1],10), 0);
        nextName = 'Subuh (besok)';
      }
      var diff = Math.max(0, next - now);
      var h = Math.floor(diff/3600000);
      var m = Math.floor(diff/60000) % 60;
      var s = Math.floor(diff/1000) % 60;
      var pad = function (n) { return n < 10 ? '0'+n : ''+n; };
      document.getElementById('prayerNext').innerHTML =
        '<i class="bi bi-clock-history text-success"></i> <strong>' + nextName +
        '</strong> dalam <span class="badge bg-success">' + pad(h)+':'+pad(m)+':'+pad(s) + '</span>';
      // Mode tenang saat adzan: 0..120 detik setelah waktu lewat -> dim screen
      if (modeTenang) {
        order.forEach(function (k) {
          var parts = (data[k] || '').split(':');
          if (parts.length < 2) return;
          var d = new Date(now.getFullYear(), now.getMonth(), now.getDate(), parseInt(parts[0],10), parseInt(parts[1],10), 0);
          var dt = (now - d) / 1000;
          if (dt >= 0 && dt <= 180) {
            if (!document.getElementById('modeTenang')) {
              var div = document.createElement('div');
              div.id = 'modeTenang';
              div.style.cssText = 'position:fixed;inset:0;background:rgba(0,40,20,.85);color:#fff;z-index:9999;display:flex;align-items:center;justify-content:center;flex-direction:column;text-align:center;padding:2rem;';
              div.innerHTML = '<div style="font-size:3rem">🕌</div>' +
                '<h2>Waktu ' + label[k] + ' telah tiba</h2>' +
                '<p>Mari hentikan aktivitas sejenak dan tunaikan sholat.</p>' +
                '<button class="btn btn-light mt-3" onclick="document.getElementById(\'modeTenang\').remove()">Lanjutkan</button>';
              document.body.appendChild(div);
              setTimeout(function(){ var el=document.getElementById('modeTenang'); if(el) el.remove(); }, 180000);
            }
          }
        });
      }
    }
    tick();
    setInterval(tick, 1000);
  }
  loadPrayer();

  // ===== Countdown Ramadhan & Idul Adha =====
  function countdown(elId, targetIso) {
    var el = document.getElementById(elId);
    if (!el) return;
    function t() {
      var diff = new Date(targetIso) - new Date();
      if (diff < 0) { el.textContent = 'Sudah tiba 🎉'; return; }
      var d = Math.floor(diff/86400000);
      var h = Math.floor(diff/3600000)%24;
      var m = Math.floor(diff/60000)%60;
      el.innerHTML = '<strong>' + d + '</strong> hari <strong>' + h + '</strong> jam <strong>' + m + '</strong> menit';
    }
    t(); setInterval(t, 30000);
  }
  window.islamiCountdown = countdown;
})();
