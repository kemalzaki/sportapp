/* KawanKeringat — Sound Effects (Revisi 4 Jun 2026)
 * Efek suara ringan berbasis WebAudio. Tidak butuh file mp3.
 *
 * Update 4 Jun 2026:
 *  - Suara klik kini AKTIF di SEMUA halaman, untuk SEMUA elemen interaktif
 *    (<button>, <a>, [role=button], input type=submit/button/reset, .btn, .nav-link,
 *     .gt-chip, .gj-item, .list-group-item-action, .dropdown-item).
 *  - Submit form -> SFX.success(); error response -> SFX.error().
 *  - Bisa dimatikan: SFX.mute() / unmute(). Status disimpan di localStorage.
 */
(function(global){
  var Ctx = global.AudioContext || global.webkitAudioContext;
  if (!Ctx) {
    global.SFX = { tap:function(){}, success:function(){}, error:function(){}, toggle:function(){}, notify:function(){}, mute:function(){}, unmute:function(){}, isMuted:function(){return true;} };
    return;
  }
  var ac = null;
  function ctx(){
    if (!ac){ try{ ac = new Ctx(); }catch(e){ return null; } }
    if (ac.state === 'suspended') { try{ ac.resume(); }catch(e){} }
    return ac;
  }
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
    g.gain.exponentialRampToValueAtTime(gainVal||0.10, t+0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, t+dur);
    o.start(t); o.stop(t+dur+0.02);
  }
  var SFX = {
    tap:     function(){ tone(880, 0.05, 'triangle', 0.06); },
    toggle:  function(){ tone(660, 0.05, 'square',   0.06); },
    notify:  function(){ tone(740, 0.10, 'sine',     0.10); setTimeout(function(){ tone(988, 0.10, 'sine', 0.10); }, 90); },
    success: function(){ tone(660, 0.08, 'sine',     0.10); setTimeout(function(){ tone(880, 0.10, 'sine', 0.12); }, 80); setTimeout(function(){ tone(1175,0.14,'sine',0.12); }, 180); },
    error:   function(){ tone(220, 0.18, 'sawtooth', 0.10); setTimeout(function(){ tone(165, 0.22, 'sawtooth', 0.10); }, 140); },
    mute:    function(){ try { localStorage.setItem('hfSfxMuted','1'); } catch(e){} },
    unmute:  function(){ try { localStorage.removeItem('hfSfxMuted'); } catch(e){} },
    isMuted: muted
  };
  global.SFX = SFX;

  // ===== KLIK global di SEMUA halaman =====
  // Hindari spam: throttle 60ms agar event ganda (touchstart+click) tidak dobel-bunyi.
  var lastT = 0;
  var INTERACTIVE = 'a, button, [role=button], input[type=submit], input[type=button], input[type=reset], .btn, .nav-link, .dropdown-item, .list-group-item-action, .gt-chip, .gt-burger, .gt-bell, .gt-avatar, .gj-item, .gj-fab, summary, label[for], .form-check-label';

  document.addEventListener('click', function(e){
    var now = Date.now(); if (now - lastT < 60) return;
    var el = e.target.closest('[data-sfx]') || e.target.closest(INTERACTIVE);
    if (!el) return;
    if (el.hasAttribute('data-sfx-off') || el.closest('[data-sfx-off]')) return;
    var kind = (el.getAttribute && el.getAttribute('data-sfx')) || 'tap';
    if (typeof SFX[kind] !== 'function') kind = 'tap';
    SFX[kind]();
    lastT = now;
  }, true);

  // Submit form -> sukses
  document.addEventListener('submit', function(e){
    var f = e.target; if (!f || f.tagName !== 'FORM') return;
    if (f.hasAttribute('data-sfx-off')) return;
    SFX.success();
  }, true);

  // Toggle (checkbox/switch/radio)
  document.addEventListener('change', function(e){
    var el = e.target;
    if (!el || !el.matches) return;
    if (el.matches('input[type=checkbox], input[type=radio], .form-check-input, select')) {
      SFX.toggle();
    }
  }, true);

  // Jika halaman dirender dengan flash alert error/danger -> bunyi error
  document.addEventListener('DOMContentLoaded', function(){
    try {
      if (document.querySelector('.alert-danger, .alert-error, .lg-alert, .is-invalid')) {
        setTimeout(function(){ SFX.error(); }, 250);
      } else if (document.querySelector('.alert-success')) {
        setTimeout(function(){ SFX.success(); }, 250);
      }
      var b = document.querySelector('.gt-badge-dot, .badge.bg-danger');
      if (b && /\d/.test(b.textContent||'')) {
        setTimeout(function(){ SFX.notify(); }, 1200);
      }
    } catch(e){}
  });
})(window);
