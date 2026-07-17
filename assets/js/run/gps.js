/* ============================================================
 * KK Run · gps.js  — Geolocation + heading + filter GPS noise
 * ============================================================ */
(function(){
  'use strict';

  var watchId = null;
  var onFix = null;
  var lastAcceptedAt = 0;
  var curSpeed = 0;

  function haversine(a,b){
    var R=6371000, toRad=Math.PI/180;
    var dLat=(b.lat-a.lat)*toRad, dLng=(b.lng-a.lng)*toRad;
    var s=Math.sin(dLat/2)*Math.sin(dLat/2)
        + Math.cos(a.lat*toRad)*Math.cos(b.lat*toRad)
        * Math.sin(dLng/2)*Math.sin(dLng/2);
    return 2*R*Math.asin(Math.sqrt(s));
  }
  function bearing(a,b){
    var toRad=Math.PI/180, toDeg=180/Math.PI;
    var lat1=a.lat*toRad, lat2=b.lat*toRad;
    var dLng=(b.lng-a.lng)*toRad;
    var y=Math.sin(dLng)*Math.cos(lat2);
    var x=Math.cos(lat1)*Math.sin(lat2)-Math.sin(lat1)*Math.cos(lat2)*Math.cos(dLng);
    var br=Math.atan2(y,x)*toDeg;
    return (br+360)%360;
  }
  function adaptiveMinInterval(){
    var kmh = curSpeed*3.6;
    if (kmh < 1) return 5000;
    if (kmh < 6) return 2000;
    return 1000;
  }

  window.KKGps = {
    haversine: haversine,
    bearing: bearing,
    setSpeedRef: function(s){ curSpeed = s; },
    adaptiveMinInterval: adaptiveMinInterval,

    start: function(cb){
      onFix = cb;
      if (!navigator.geolocation) return false;
      if (watchId !== null) return true;
      watchId = navigator.geolocation.watchPosition(function(pos){
        if (onFix) onFix(pos, null);
      }, function(err){
        if (onFix) onFix(null, err);
      }, { enableHighAccuracy:true, maximumAge:0, timeout:12000 });
      return true;
    },

    stop: function(){
      if (watchId !== null){
        navigator.geolocation.clearWatch(watchId); watchId = null;
      }
    },

    /* Device orientation → compass heading fallback bila GPS heading tidak ada */
    _headingCb: null,
    startCompass: function(cb){
      this._headingCb = cb;
      var handler = function(ev){
        var hd = null;
        if (typeof ev.webkitCompassHeading === 'number') hd = ev.webkitCompassHeading;
        else if (typeof ev.alpha === 'number') hd = (360 - ev.alpha) % 360;
        if (hd != null && cb) cb(hd);
      };
      var attach = function(){
        window.addEventListener('deviceorientationabsolute', handler, true);
        window.addEventListener('deviceorientation', handler, true);
      };
      if (typeof DeviceOrientationEvent !== 'undefined' &&
          typeof DeviceOrientationEvent.requestPermission === 'function'){
        DeviceOrientationEvent.requestPermission().then(function(st){
          if (st === 'granted') attach();
        }).catch(function(){});
      } else {
        attach();
      }
    }
  };
})();
