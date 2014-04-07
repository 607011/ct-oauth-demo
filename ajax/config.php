<?php
/*
    Auxiliary Classes.
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

require_once '../google-api-php-client/Google_Client.php';


class ct_Timer {
    private $t0;
    public function __construct() {
        $this->t0 = microtime(true);
    }
    public function elapsed() {
        return microtime(true) - $this->t0;
    }
}


class ct_OAuthDemo {
    private static $VALID_OAUTH_CLIENT_IDS = array(
        '336758185464-24sv6gmltpr9bt7nbtg38j5fbk7kv4l5.apps.googleusercontent.com',
        '336758185464-bfeuon5mamfh8iffhrvi3q218e4m6e95.apps.googleusercontent.com'
    );

    public static $GOOGLE_OAUTH_CLIENT_ID;
    public static $GOOGLE_OAUTH_CLIENT_SECRET;
    public static $GOOGLE_OAUTH_REDIRECT_URI;
    public static $DB_PATH;
    public static $CACERT_PATH;
    public static $dbh;
    public static $res = array('status' => 'ok');

    private static $timer;
    private static $DB_NAME;
    private static $CACERT_NAME;

    const DB_PERSISTENT = false;

    public static function verifyAccessToken($token, $force = false) {
        $result = isset($_SESSION[$token]) ? $_SESSION[$token] : array();
        $must_validate = $force || !is_array($result) || !isset($result['expires_at']) || time() > $result['expires_at'];
        if ($must_validate) {
            $service_url = 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . filter_var($token, FILTER_SANITIZE_STRING);
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $service_url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CAINFO, self::$CACERT_NAME);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            $curl_response = curl_exec($curl);
            if ($curl_response === false) {
                $info = curl_getinfo($curl);
                curl_close($curl);
                die('<pre>error occured during curl_exec(): ' . var_export($info) . '</pre>');
            }
            curl_close($curl);
            $result = json_decode($curl_response, true);
        }
        if (isset($result['user_id'])
            && isset($result['expires_in']) && $result['expires_in'] > 0
            && isset($result['audience']) && in_array($result['audience'], self::$VALID_OAUTH_CLIENT_IDS)
            && isset($result['issued_to']) && in_array($result['issued_to'], self::$VALID_OAUTH_CLIENT_IDS))
        { // cache result
            $result['expires_at'] = time() + $result['expires_in'];
            $_SESSION[$token] = $result;
            $_SESSION['access_token'] = $token;
            self::$res['status'] = 'ok';
            self::$res['user_id'] = $result['user_id'];
            self::$res['expires_in'] = $result['expires_in'];
        }
        self::$res['revalidated'] = $must_validate;
        return !self::error();
    }
    
    
    public static function verifyIdToken($id_token) {
        $id_token = filter_var($id_token, FILTER_SANITIZE_STRING);
        $client = new Google_Client();
        $client->setClientId(self::$GOOGLE_OAUTH_CLIENT_ID);
        $client->setClientSecret(self::$GOOGLE_OAUTH_CLIENT_SECRET);
        try {
            $client->getAccessToken();
            $ticket = $client->verifyIdToken($id_token);
            self::$res['ticket'] = $ticket;
            self::$res['status'] = 'ok';
        }
        catch (Exception $e) {
            self::$res['status'] = 'error';
            self::$res['error'] = $e->getMessage();
            self::$res['trace'] = $e->getTrace();
            self::$res['id_token'] = $id_token;
        }
        return !self::error();
    }

    public static function error() {
        return self::$res['status'] !== 'ok';
    }
    
    public static function out() {
        header('Content-Type: text/json');
        self::$res['processing_time'] = self::$timer->elapsed();
        echo json_encode(self::$res);
    }

    public static function init() {
        self::$timer = new ct_Timer();
        session_start();
        if ($_SERVER['SERVER_NAME'] === 'localhost' && strpos($_SERVER['REQUEST_URI'], '/ct-oauth-demo') === 0) {
            self::$GOOGLE_OAUTH_CLIENT_ID = '336758185464-24sv6gmltpr9bt7nbtg38j5fbk7kv4l5.apps.googleusercontent.com';
            self::$GOOGLE_OAUTH_CLIENT_SECRET = 'GEHEIM';
            self::$GOOGLE_OAUTH_REDIRECT_URI = 'http://localhost/ct-oauth-demo';
            self::$DB_PATH = 'D:/Developer/xampp';
        }
        else if ($_SERVER['SERVER_NAME'] === 'www.example.com' && strpos($_SERVER['REQUEST_URI'], '/ct-oauth-demo') === 0) {
            self::$GOOGLE_OAUTH_CLIENT_ID = '336758185464-bfeuon5mamfh8iffhrvi3q218e4m6e95.apps.googleusercontent.com';
            self::$GOOGLE_OAUTH_CLIENT_SECRET = 'GEHEIM';
            self::$GOOGLE_OAUTH_REDIRECT_URI = 'http://www.example.com/ct/ct-oauth-demo';
            self::$DB_PATH = '/var/www/sqlite';
        }
        self::$CACERT_PATH = self::$DB_PATH;
        self::$CACERT_NAME = self::$CACERT_PATH . '/cacert.pem';
        self::$DB_NAME = self::$DB_PATH . '/ct-oauth-demo.sqlite';
        self::$dbh = new PDO('sqlite:' . self::$DB_NAME, null, null, array(
             PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_PERSISTENT => self::DB_PERSISTENT
        ));
        if (substr(str_replace("\\", '/', __FILE__), -strlen($_SERVER['PHP_SELF'])) === $_SERVER['PHP_SELF']) {
            self::$res['GoogleOAuthClientId'] = self::$GOOGLE_OAUTH_CLIENT_ID;
            if (!isset($_SESSION['CSRF']))
                $_SESSION['CSRF'] =  base64_encode(sha1(rand() . microtime(), true));
            self::$res['CSRF'] = $_SESSION['CSRF'];
            header('Content-Type: text/json');
            if (self::$GOOGLE_OAUTH_CLIENT_ID === null)
                self::$res['status'] = 'error';
            echo json_encode(self::$res);
        }
    }
}

ct_OAuthDemo::init();


?>