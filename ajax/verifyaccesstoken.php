<?php
/*
    Verify Google OAuth access token.
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

require_once 'config.php';

if (!isset($_REQUEST['CSRF']) && $_REQUEST['CSRF'] !== $_SESSION['CSRF']) {
  ct_OAuthDemo::$res['status'] = 'error';
  ct_OAuthDemo::$res['error'] = 'CSRF-Token falsch';
  goto end;
}

$force_validation = isset($_REQUEST['force_validation']) && in_array($_REQUEST['force_validation'], array('1', 'true', 'yes'));

if (isset($_REQUEST['access_token'])) {
    ct_OAuthDemo::verifyAccessToken($_REQUEST['access_token'], $force_validation);
}
else {
    ct_OAuthDemo::$res['status'] = 'authfailed';
    ct_OAuthDemo::$res['error'] = 'Ungueltige Authentifizierungsdaten: OAuth-ID-Token fehlt oder ist falsch.';
}

end:
ct_OAuthDemo::out();
?>

