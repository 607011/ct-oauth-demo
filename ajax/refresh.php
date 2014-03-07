<?php
/*
    Exchange refresh token for a new access token.
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

define('USE_CURL', false);

if (!isset($_REQUEST['CSRF']) && $_REQUEST['CSRF'] !== $_SESSION['CSRF']) {
  ct_OAuthDemo::$res['status'] = 'error';
  ct_OAuthDemo::$res['error'] = 'CSRF-Token falsch';
  goto end;
}


if (ct_OAuthDemo::$dbh && isset($_REQUEST['user_id'])) {
    $user_id = $_REQUEST['user_id'];
    // http://stackoverflow.com/questions/19015451/how-to-get-the-refresh-token-with-google-oauth2-javascript-library
    // http://www.jensbits.com/2012/01/09/google-api-offline-access-using-oauth-2-0-refresh-token/

    $sth = ct_OAuthDemo::$dbh->prepare("SELECT `refresh_token` FROM `users` WHERE `userid` = ?");
    $sth->execute(array($user_id));
    $row = $sth->fetch();
    $sth->closeCursor();
    if ($row) {
        if (USE_CURL) {
            $params = array(
                'client_secret' => ct_OAuthDemo::$GOOGLE_OAUTH_CLIENT_SECRET,
                'grant_type' => 'refresh_token',
                'refresh_token' => $row['refresh_token'],
                'client_id' => ct_OAuthDemo::$GOOGLE_OAUTH_CLIENT_ID,
            );
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
            curl_setopt($curl, CURLOPT_VERBOSE, TRUE);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
            $curl_response = curl_exec($curl);
            if ($curl_response === false) {
                $info = curl_getinfo($curl);
                curl_close($curl);
                die('<pre>error occured during curl_exec(): ' . var_export($info) . '</pre>');
            }
            curl_close($curl);
            $result = json_decode($curl_response, true);
            ct_OAuthDemo::$res = $result;
            if (isset(ct_OAuthDemo::$res['error'])) {
                ct_OAuthDemo::$res['status'] = 'error';
                ct_OAuthDemo::$res['error'] = $res['error'];
            }
            else {
                ct_OAuthDemo::$res['status'] = 'ok';
            }
        }
        else {
            $client = new Google_Client();
            $client->setClientId(ct_OAuthDemo::$GOOGLE_OAUTH_CLIENT_ID);
            $client->setClientSecret(ct_OAuthDemo::$GOOGLE_OAUTH_CLIENT_SECRET);
            try {
                $client->refreshToken($row['refresh_token']);
            }
            catch (Exception $e) {
                ct_OAuthDemo::$res['status'] = 'error';
                ct_OAuthDemo::$res['error'] = $e->getMessage();
                ct_OAuthDemo::$res['trace'] = $e->getTrace();
                goto end;
            }
            ct_OAuthDemo::$res = json_decode($client->getAccessToken(), true);
            ct_OAuthDemo::$res['status'] = 'ok';
        }
    }
    else {
        ct_OAuthDemo::$res['status'] = 'error';
        ct_OAuthDemo::$res['error'] = "Unbekannter User '$user_id'.";
    }
}
else {
    ct_OAuthDemo::$res['status'] = 'error';
    ct_OAuthDemo::$res['error'] = 'Parameter `user_id` fehlt.';
}

end:
ct_OAuthDemo::out();
?>
