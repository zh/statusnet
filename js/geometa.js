// A shim to implement the W3C Geolocation API Specification using Gears
if (typeof navigator.geolocation == "undefined" || navigator.geolocation.shim ) (function(){

// -- BEGIN GEARS_INIT
(function() {
  // We are already defined. Hooray!
  if (window.google && google.gears) {
    return;
  }

  var factory = null;

  // Firefox
  if (typeof GearsFactory != 'undefined') {
    factory = new GearsFactory();
  } else {
    // IE
    try {
      factory = new ActiveXObject('Gears.Factory');
      // privateSetGlobalObject is only required and supported on WinCE.
      if (factory.getBuildInfo().indexOf('ie_mobile') != -1) {
        factory.privateSetGlobalObject(this);
      }
    } catch (e) {
      // Safari
      if ((typeof navigator.mimeTypes != 'undefined')
           && navigator.mimeTypes["application/x-googlegears"]) {
        factory = document.createElement("object");
        factory.style.display = "none";
        factory.width = 0;
        factory.height = 0;
        factory.type = "application/x-googlegears";
        document.documentElement.appendChild(factory);
      }
    }
  }

  // *Do not* define any objects if Gears is not installed. This mimics the
  // behavior of Gears defining the objects in the future.
  if (!factory) {
    return;
  }

  // Now set up the objects, being careful not to overwrite anything.
  //
  // Note: In Internet Explorer for Windows Mobile, you can't add properties to
  // the window object. However, global objects are automatically added as
  // properties of the window object in all browsers.
  if (!window.google) {
    google = {};
  }

  if (!google.gears) {
    google.gears = {factory: factory};
  }
})();
// -- END GEARS_INIT

var GearsGeoLocation = (function() {
    // -- PRIVATE
    var geo = google.gears.factory.create('beta.geolocation');
    
    var wrapSuccess = function(callback, self) { // wrap it for lastPosition love
        return function(position) {
            callback(position);
            self.lastPosition = position;
        }
    }
    
    // -- PUBLIC
    return {
        shim: true,
        
        type: "Gears",
        
        lastPosition: null,
        
        getCurrentPosition: function(successCallback, errorCallback, options) {
            var self = this;
            var sc = wrapSuccess(successCallback, self);
            geo.getCurrentPosition(sc, errorCallback, options);
        },
        
        watchPosition: function(successCallback, errorCallback, options) {
            geo.watchPosition(successCallback, errorCallback, options);
        },
        
        clearWatch: function(watchId) {
            geo.clearWatch(watchId);
        },
        
        getPermission: function(siteName, imageUrl, extraMessage) {
            geo.getPermission(siteName, imageUrl, extraMessage);
        }

    };
});

// If you have Gears installed use that
if (window.google && google.gears) {
    navigator.geolocation = GearsGeoLocation();
}

})();
