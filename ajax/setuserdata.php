<?php
/*
    Insert or update user.
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

$user_id = isset($_REQUEST['user_id']) ? filter_var($_REQUEST['user_id'], FILTER_SANITIZE_STRING) : null;

$code = isset($_REQUEST['code']) ? filter_var($_REQUEST['code'], FILTER_SANITIZE_STRING) : null;
$avatar = isset($_REQUEST['avatar']) ? filter_var($_REQUEST['avatar'], FILTER_SANITIZE_STRING) : null;
$name = isset($_REQUEST['name']) ? filter_var($_REQUEST['name'], FILTER_SANITIZE_STRING) : null;
$access_token = isset($_REQUEST['access_token']) ? filter_var($_REQUEST['access_token'], FILTER_SANITIZE_STRING) : null;
$refresh_token = null;

if (!isset($_REQUEST['CSRF']) && $_REQUEST['CSRF'] !== $_SESSION['CSRF']) {
  header('HTTP/1.1 409 Conflict', true, 409);
  exit;
//  ct_OAuthDemo::$res['status'] = 'error';
//  ct_OAuthDemo::$res['error'] = 'CSRF-Token falsch';
//  goto end;
}

if (is_string($code)) { // exchange code for access and (possibly) refresh token
    $client = new Google_Client();
    $client->setClientId(ct_OAuthDemo::$GOOGLE_OAUTH_CLIENT_ID);
    $client->setClientSecret(ct_OAuthDemo::$GOOGLE_OAUTH_CLIENT_SECRET);
    $client->setRedirectUri('postmessage');
    try {
        $client->authenticate($code);
    }
    catch (Exception $e) {
        ct_OAuthDemo::$res['status'] = 'error';
        ct_OAuthDemo::$res['error'] = $e->getMessage();
        ct_OAuthDemo::$res['trace'] = $e->getTrace();
        goto end;
    }
    ct_OAuthDemo::$res = json_decode($client->getAccessToken(), true);
    $id_token = $client->verifyIdToken()->getAttributes();
    $user_id = $id_token['payload']['sub'];
    $access_token = ct_OAuthDemo::$res['access_token'];
    $refresh_token = isset(ct_OAuthDemo::$res['refresh_token']) ? ct_OAuthDemo::$res['refresh_token'] : null;
    $_SESSION['access_token'] = $access_token;
    $_SESSION['user_id'] = $user_id;
}

if ($user_id === null) {
    ct_OAuthDemo::$res['status'] = 'error';
    ct_OAuthDemo::$res['error'] = 'User-Id fehlt oder kann ermittelt werden';
    goto end;
}

if ($access_token === null) {
    ct_OAuthDemo::$res['status'] = 'error';
    ct_OAuthDemo::$res['error'] = 'Zugriffs-Token fehlt oder kann ermittelt werden';
    goto end;
}

if (!ct_OAuthDemo::verifyAccessToken($access_token)) {
    ct_OAuthDemo::$res['status'] = 'error';
    ct_OAuthDemo::$res['error'] = 'Zugriffs-Token nicht gueltig: ' . $access_token;
    goto end;
}

if (ct_OAuthDemo::$dbh) {
    $sth = ct_OAuthDemo::$dbh->prepare("SELECT `avatar`, `name` FROM `users` WHERE `userid` = ?");
    $sth->execute(array($user_id));
    $row = $sth->fetch();
    $sth->closeCursor();
    ct_OAuthDemo::$res['processing_info'] = array();
    if (!$row) {
        ct_OAuthDemo::$dbh->exec("INSERT INTO `users` (`userid`) VALUES ('$user_id')");
        ct_OAuthDemo::$res['id'] = ct_OAuthDemo::$dbh->lastInsertId();
        $sth->execute(array($user_id));
        $row = $sth->fetch();
        $sth->closeCursor();
        ct_OAuthDemo::$res['processing_info'][] = 'User hinzugefuegt';
    }
    if ($refresh_token) {
        ct_OAuthDemo::$dbh->exec("UPDATE `users` SET `refresh_token` = '$refresh_token' WHERE `userid` = '$user_id'");
        ct_OAuthDemo::$res['processing_info'][] = 'Refresh-Token aktualisiert.';
        ct_OAuthDemo::$res['refresh_token'] = '***SECRET***';
    }
    if ($avatar && $avatar !== $row['avatar']) {
        ct_OAuthDemo::$dbh->exec("UPDATE `users` SET `avatar` = '$avatar' WHERE `userid` = '$user_id'");
        ct_OAuthDemo::$res['processing_info'][] = 'Avatar aktualisiert.';
    }
    if ($name && $name !== $row['name']) {
        ct_OAuthDemo::$dbh->exec("UPDATE `users` SET `name` = '$name' WHERE `userid` = '$user_id'");
        ct_OAuthDemo::$res['processing_info'][] = 'Name aktualisiert.';
    }
}
ct_OAuthDemo::$res['status'] = 'ok';

end:
ct_OAuthDemo::out();
?>