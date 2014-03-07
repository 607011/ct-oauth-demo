/*
    c't OAuth Demo
    Copyright (c) 2014 Oliver Lau <ola@ct.de>, Heise Zeitschriften Verlag

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

Number.prototype.padded = function() {
  return ('0' + this).slice(-2);
};


Number.prototype.toHMS = function () {
  var v = Math.floor(this),
    h = Math.floor(v / 3600),
    m = Math.floor((v - h * 3600) / 60),
    s = v % 60,
    result = m.padded() + ':' + s.padded();
  if (h > 0)
    result = h.padded() + ':' + result;
  return result;
};


var CTOAUTHDEMO = (function () {
  var me = {},
    hTimer = null,
    CSRF = null,
    GoogleOAuthClientId = null;


  function base64urldecode(arg) {
    var s = arg.replace(/-/g, '+').replace(/_/g, '/');
    switch (s.length % 4) {
      case 0: break;
      case 2: s += "=="; break;
      case 3: s += "="; break;
      default: throw new InputException('Ungültiger Base64Url-String!');
    }
    return window.atob(s);
  }


  function parseIdToken(token) {
    var tokens = token.split('.'), i, a = [];
    for (i = 0; i < tokens.length; ++i)
      a.push(base64urldecode(tokens[i]));
    return a;
  }


  function showProgress() {
    $('#loader-icon').css('display', 'block');
    $('#app-container').addClass('gray-out');
  }


  function hideProgress() {
    $('#loader-icon').css('display', 'none');
    $('#app-container').removeClass('gray-out');
  }


  function showLogon() {
    $('#logon').addClass('show').removeClass('hide');
    $('#app').addClass('hide');
    $('#googleSigninButton').css('visibility', 'visible');
  }


  function showApp() {
    $('#app').addClass('box').removeClass('hide');
    $('#logon').addClass('hide').removeClass('show');
    $('#googleSigninButton').css('visibility', 'hidden');

  }


  function countdown(secs) {
    var TIMEOUT_MARGIN = 15,
      TIMEOUT_CAUTION = 60,
      TIMEOUT_DANGER = 30,
      targetTime = Math.floor(new Date(Date.now() + secs * 1000).getTime() / 1000),
      expiry = $('#expires_in');
    if (hTimer !== null)
      clearInterval(hTimer);
    hTimer = setInterval(function () {
      var dt = targetTime - Math.floor(Date.now() / 1000);
      if (dt < TIMEOUT_DANGER)
        expiry.addClass('danger');
      else if (dt < TIMEOUT_CAUTION)
        expiry.removeClass('danger').addClass('caution');
      if (dt < TIMEOUT_MARGIN) {
        clearInterval(hTimer);
        expiry.removeClass('danger').removeClass('caution');
        refreshToken();
      }
      else {
        expiry.text(dt.toHMS());
      }
    }, 1000);
  }


  function checkIdToken() {
    showProgress();
    $.ajax({
      url: 'ajax/verifyidtoken.php',
      type: 'POST',
      accepts: 'json',
      data: {
        id_token: me.oauth.id_token,
        CSRF: CSRF
      }
    }).done(function (data) {
      hideProgress();
      if (typeof data.status === 'string') {
        switch (data.status) {
          case 'ok':
            alert('ID-Token ist gültig.');
            break;
          case 'error':
            alert('FEHLER: ' + data.error + '\nDu wirst jetzt automatisch abgemeldet.');
            disconnectUser();
            break;
        }
      }
    }).error(function (error) {
      hideProgress();
      alert('Fehler bei der Prüfung des Zugriffs-Tokens: ' + error);
    });
  }


  function checkAccessToken() {
    showProgress();
    $.ajax({
      url: 'ajax/verifyaccesstoken.php',
      type: 'POST',
      accepts: 'json',
      data: {
        access_token: me.oauth.access_token,
        force_validation: true,
        CSRF: CSRF
      }
    }).done(function (data) {
      hideProgress();
      if (typeof data.status === 'string') {
        switch (data.status) {
          case 'ok':
            me.oauth.expires_in = data.expires_in;
            $('#expires_in').text(me.oauth.expires_in.toHMS());
            countdown(me.oauth.expires_in);
            alert('Zugriffs-Token ist gültig.');
            break;
          case 'error':
            alert('FEHLER: ' + data.error + '\nDu wirst jetzt automatisch abgemeldet.');
            disconnectUser();
            break;
        }
      }
    }).error(function (error) {
      hideProgress();
      alert('Fehler bei der Prüfung des Zugriffs-Tokens: ' + error);
    });
  }


  function transferUserData() {
    showProgress();
    $.ajax({
      url: 'ajax/setuserdata.php',
      type: 'POST',
      accepts: 'json',
      data: {
        code: me.oauth.code,
        CSRF: CSRF
      }
    }).done(function (data) {
      hideProgress();
      if (typeof data.status === 'string') {
        switch (data.status) {
          case 'ok':
            console.log(data);
            break;
          case 'authfailed':
            console.error(data.error);
            break;
          case 'error':
            alert('FEHLER: ' + data.error);
            break;
        }
      }
    }).error(function (error) {
      hideProgress();
      alert('Übertragungsfehler: ' + error);
    });
  }


  function transferUserProfileData() {
    showProgress();
    $.ajax({
      url: 'ajax/setuserdata.php',
      type: 'POST',
      accepts: 'json',
      data: {
        access_token: me.oauth.access_token,
        user_id: me.id,
        name: me.name,
        avatar: me.avatar,
        CSRF: CSRF
      }
    }).done(function (data) {
      hideProgress();
      if (typeof data.status === 'string') {
        switch (data.status) {
          case 'ok':
            console.log(data);
            break;
          case 'authfailed':
            console.error(data.error);
            break;
          case 'error':
            alert('FEHLER: ' + data.error);
            break;
        }
      }
    }).error(function (error) {
      hideProgress();
      console.error(error);
    });
  }


  function loadProfileCallback(profile) {
    var img = new Image();
    hideProgress();
    console.log(profile);
    me.name = profile.displayName; // z.B. "Oliver Lau"
    me.id = profile.id; // z.B. "100829969894177493033"
    me.avatarUrl = profile.image.url; // "http://...
    me.avatar = null;
    me.gender = profile.gender; // z.B. "male"
    me.plus = profile.isPlusUser; // z.B. true
    $('#displayname').text(me.name);
    img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function () {
      me.avatar = (function imgToDataUrl(img) {
        var canvas = document.createElement('canvas'),
          ctx = canvas.getContext('2d'), dataUrl;
        canvas.width = img.width;
        canvas.height = img.height;
        ctx.drawImage(img, 0, 0);
        dataUrl = canvas.toDataURL('image/jpg');
        $('#avatar').attr('src', dataUrl);
        return dataUrl; 
      })(img);
      transferUserProfileData();
    };
    img.src = me.avatarUrl;
  }


  function getUserInfo() {
    gapi.client.load('plus', 'v1', function loadProfile() {
      gapi.client.plus.people.get({
        'userId': 'me'
      }).execute(loadProfileCallback);
    });
  }


  function googleSigninCallback(authResult) {
    hideProgress();
    if (authResult.status.signed_in) {
      showApp();
      me.oauth = {
        access_token: authResult.access_token,
        id_token: authResult.id_token,
        code: authResult.code,
        client_id: authResult.client_id,
        expires_at: parseInt(authResult.expires_at, 10),
        expires_in: parseInt(authResult.expires_in, 10),
        issued_at: parseInt(authResult.issued_at, 10)
      };
      transferUserData();
      $('#access_token').text(me.oauth.access_token);
      $('#id_token').text(me.oauth.id_token);
      $('#id_token_decoded').text(parseIdToken(me.oauth.id_token).join("\n"));
      $('#issued_at').text(new Date(1000 * me.oauth.issued_at));
      $('#expires_at').text(new Date(1000 * me.oauth.expires_at));
      $('#expires_in').text(me.oauth.expires_in.toHMS());
      countdown(me.oauth.expires_in);
      if (typeof me.name === 'undefined')
        getUserInfo();
    }
    else {
      console.debug('(Automatische) Authentifizierung fehlgeschlagen!');
    }
  }


  function disconnectUser() {
    showProgress();
    $.ajax({
      url: 'https://accounts.google.com/o/oauth2/revoke?token=' + me.oauth.access_token,
      type: 'GET',
      async: false,
      contentType: 'application/json',
      dataType: 'jsonp'
    }).done(function (nullResponse) {
      hideProgress();
      showLogon();
    }).error(function (e) {
      hideProgress();
      alert('Zurückziehen des Tokens fehlgeschlagen: ' + e);
      showLogon();
    });
  }


  function refreshToken() {
    showProgress();
    $.ajax({
      url: 'ajax/refresh.php',
      type: 'POST',
      accepts: 'json',
      data: {
        user_id: me.id,
        CSRF: CSRF
      }
    }).done(function (data) {
      hideProgress();
      if (typeof data.status === 'string') {
        switch (data.status) {
          case 'ok':
            me.oauth.access_token = data.access_token;
            me.oauth.issued_at = data.created;
            me.oauth.expires_in = data.expires_in;
            $('#expires_in').text(data.expires_in.toHMS());
            $('#issued_at').text(new Date(1000 * me.oauth.issued_at));
            $('#access_token').text(data.access_token);
            countdown(me.oauth.expires_in);
            break;
          case 'error':
            alert('FEHLER: ' + data.error);
            break;
        }
      }
    }).error(function (error) {
      hideProgress();
      console.error(error);
    });
  }


  return {
    init: function () {
      $.ajax({
        url: 'ajax/config.php',
        accepts: 'json'
      }).done(function (data) {
        if (data.status && data.status === 'ok') {
          if (data.GoogleOAuthClientId && typeof data.GoogleOAuthClientId === 'string'
              && data.CSRF && typeof data.CSRF === 'string') {
            CSRF = data.CSRF;
            GoogleOAuthClientId = data.GoogleOAuthClientId;
            $('.g-signin').attr('data-clientid', GoogleOAuthClientId);
            $('<script>')
              .attr('type', 'text/javascript')
              .attr('async', true)
              .attr('src', 'https://plus.google.com/js/client:plusone.js?onload=start')
              .appendTo($('head'));
            $('button#check-id-token').click(checkIdToken);
            $('button#check-access-token').click(checkAccessToken);
            $('button#refresh-token').click(refreshToken);
            $('button#revoke-token').click(disconnectUser);
          }
        }
      }).error(function (e) {
        alert('Konfiguration fehlgeschlagen!');
      });
    },
    googleSigninCallback: googleSigninCallback
  };

})();


function googleSigninCallback(authResult) {
  CTOAUTHDEMO.googleSigninCallback(authResult);
}
