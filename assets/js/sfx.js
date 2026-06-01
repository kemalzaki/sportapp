/* HapFam SportApp — Sound Effects (Revisi 1 Jun 2026)
 * Efek suara ringan berbasis WebAudio. Tidak butuh file mp3, jadi
 * pasti jalan di lokal (PHP + PostgreSQL) tanpa aset eksternal.
 *
 * Pemakaian:
 *   SFX.tap()      -> klik tombol
 *   SFX.success()  -> sukses (submit form, simpan, dll)
 *   SFX.error()    -> error / gagal
 *   SFX.toggle()   -> switch
 *   SFX.notify()   -> notifikasi
 *
 * Auto-hook: setiap <form> akan otomatis memutar SFX.success() ketika
 * di-submit; tombol dengan [data-sfx="success|error|tap"] juga otomatis.
 * Bisa dimatikan per-user via localStorage: SFX.mute()/unmute().
 */
(function(global){
  var Ctx = global.AudioContext || global.webkitAudioContext;
  if (!Ctx) { global.SFX = { tap:function(){}, success:function(){}, error:function(){}, toggle:function(){}, notify:function(){}, mute:function(){}, unmute:function(){}, isMuted:function(){return true;} }; return; }
  var ac = null;
  function ctx(){ if (!ac){ try{ ac = new Ctx(); }catch(e){ return null; } } if (ac.state === 'suspended') { try{ ac.resume(); }catch(e){} } return ac; }
  function muted(){ try { return localStorage.getItem('hfSfxMuted') === '1'; } catch(e) { return false; } }
  function tone(freq, dur, type, gainVal){
    if (muted()) return;
    var a = ctx(); if (!a) return;
    var o = a.createOscillator();
    var g = a.createGain();
    o.type = type || 'sine';
    o.frequency.value = freq;
    g.gain.value = 0.0001;
    o.connect(g); g.connect(a.destination);
    var t = a.currentTime;
    g.gain.exponentialRampToValueAtTime(gainVal||0.12, t+0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, t+dur);
    o.start(t); o.stop(t+dur+0.02);
  }
  var SFX = {
    tap:     function(){ tone(880, 0.06, 'triangle', 0.08); },
    toggle:  function(){ tone(660, 0.05, 'square',   0.06); },
    notify:  function(){ tone(740, 0.10, 'sine',     0.10); setTimeout(function(){ tone(988, 0.10, 'sine', 0.10); }, 90); },
    success: function(){ tone(660, 0.08, 'sine',     0.10); setTimeout(function(){ tone(880, 0.10, 'sine', 0.12); }, 80); setTimeout(function(){ tone(1175,0.14,'sine',0.12); }, 180); },
    error:   function(){ tone(220, 0.18, 'sawtooth', 0.10); setTimeout(function(){ tone(165, 0.22, 'sawtooth', 0.10); }, 140); },
    mute:    function(){ try { localStorage.setItem('hfSfxMuted','1'); } catch(e){} },
    unmute:  function(){ try { localStorage.removeItem('hfSfxMuted'); } catch(e){} },
    isMuted: muted
  };
  global.SFX = SFX;

  // Auto-hook submit semua form
  document.addEventListener('submit', function(e){
    var f = e.target; if (!f || f.tagName !== 'FORM') return;
    if (f.hasAttribute('data-sfx-off')) return;
    SFX.success();
  }, true);

  // Auto-hook tombol/anchor dengan atribut data-sfx
  document.addEventListener('click', function(e){
    var el = e.target.closest('[data-sfx]'); if (!el) return;
    var kind = el.getAttribute('data-sfx') || 'tap';
    if (typeof SFX[kind] === 'function') SFX[kind]();
  }, true);

  // Notifikasi badge unread → notify (sekali per load)
  document.addEventListener('DOMContentLoaded', function(){
    try {
      var b = document.querySelector('.badge.bg-danger');
      if (b && /^\d+$/.test((b.textContent||'').trim())) {
        // delay biar user fokus dulu
        setTimeout(function(){ SFX.notify(); }, 1200);
      }
    } catch(e){}
  });
})(window);
