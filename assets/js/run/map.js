/* ============================================================
 * KK Run · map.js  — Leaflet + rotasi map + auto-follow
 * ============================================================ */
(function(){
  'use strict';

  var map, mapRotWrap, segments = [], curSeg = null;
  var marker = null, accCircle = null;
  var followUser = true, userPanning = false;
  var rotationEnabled = true;
  var currentHeading = 0;

  function makeRunnerIcon(){
    return L.divIcon({
      className: 'kk-runner-icon',
      html: '<div class="kk-runner"></div>',
      iconSize: [26,26], iconAnchor: [13,13]
    });
  }

  function newSegment(){
    var poly = L.polyline([], {
      color:'#fc5200', weight:6, opacity:.95,
      lineCap:'round', lineJoin:'round'
    }).addTo(map);
    var s = { poly: poly, pts: [] };
    segments.push(s); curSeg = s; return s;
  }

  function ensureAccCircle(lat,lng,r){
    if (!accCircle){
      accCircle = L.circle([lat,lng], {
        radius: r || 10, color:'#fc5200',
        weight:1, opacity:.5, fillOpacity:.1
      }).addTo(map);
    } else {
      accCircle.setLatLng([lat,lng]); accCircle.setRadius(r || 10);
    }
  }

  function placeMarker(p, firstFix){
    if (!marker){
      marker = L.marker([p.lat,p.lng], { icon: makeRunnerIcon() }).addTo(map);
    } else {
      marker.setLatLng([p.lat,p.lng]);
    }
    if (followUser){
      if (firstFix){
        map.setView([p.lat,p.lng], Math.max(map.getZoom(), 17), { animate:true });
      } else {
        map.panTo([p.lat,p.lng], { animate:true, duration:0.9, easeLinearity:0.5 });
      }
    }
  }

  function setRotation(headingDeg){
    if (!rotationEnabled || headingDeg == null || isNaN(headingDeg)) {
      currentHeading = 0;
      if (mapRotWrap) mapRotWrap.style.transform = 'rotate(0deg)';
      return;
    }
    // rotate opposite so heading points to top
    currentHeading = headingDeg;
    if (mapRotWrap) mapRotWrap.style.transform = 'rotate(' + (-headingDeg) + 'deg)';
    // Counter-rotate marker so runner icon stays upright
    if (marker){
      var el = marker.getElement && marker.getElement();
      if (el){
        var inner = el.querySelector('.kk-runner');
        if (inner) inner.style.transform = 'rotate(' + headingDeg + 'deg)';
      }
    }
  }

  window.KKMap = {
    init: function(){
      map = L.map('kk-map', {
        zoomControl: false, attributionControl: true,
        tap: true, worldCopyJump: true
      }).setView([-6.2, 106.816666], 14);
      L.tileLayer(window.KK_RUN.mapboxTileUrl, {
        maxZoom: 19, attribution: window.KK_RUN.mapboxAttr
      }).addTo(map);

      // Wrap Leaflet map/tile pane so we can rotate the whole thing
      var container = map.getContainer();
      mapRotWrap = container.querySelector('.leaflet-map-pane');
      if (mapRotWrap) mapRotWrap.classList.add('kk-map-rot');

      newSegment();

      map.on('dragstart', function(){
        userPanning = true; followUser = false;
        var rc = document.getElementById('kk-recenter');
        if (rc) rc.classList.add('show');
      });
      return map;
    },

    invalidate: function(){ if (map) setTimeout(function(){ map.invalidateSize(); }, 60); },

    handleFix: function(p, firstFix){
      ensureAccCircle(p.lat, p.lng, p.acc);
      if (!curSeg) newSegment();
      curSeg.poly.addLatLng([p.lat,p.lng]);
      curSeg.pts.push([p.lat, p.lng]);
      placeMarker(p, !!firstFix);
    },

    breakSegment: function(p){
      newSegment();
      curSeg.poly.addLatLng([p.lat, p.lng]);
      curSeg.pts.push([p.lat, p.lng]);
      placeMarker(p);
    },

    updateMarkerOnly: function(p){
      if (marker) marker.setLatLng([p.lat,p.lng]);
    },

    recenter: function(p){
      followUser = true; userPanning = false;
      var rc = document.getElementById('kk-recenter');
      if (rc) rc.classList.remove('show');
      if (p) map.setView([p.lat, p.lng], Math.max(map.getZoom(), 17), { animate:true });
    },

    setRotationEnabled: function(v){ rotationEnabled = !!v; if (!v) setRotation(0); },
    setHeading: setRotation,

    reset: function(){
      segments.forEach(function(s){ try{ map.removeLayer(s.poly); }catch(e){} });
      segments = []; curSeg = null;
      if (marker){ try{ map.removeLayer(marker); }catch(e){} marker = null; }
      if (accCircle){ try{ map.removeLayer(accCircle); }catch(e){} accCircle = null; }
      newSegment();
      followUser = true; userPanning = false;
      var rc = document.getElementById('kk-recenter');
      if (rc) rc.classList.remove('show');
      setRotation(0);
    },

    getMap: function(){ return map; },
    getSegments: function(){ return segments; }
  };
})();
