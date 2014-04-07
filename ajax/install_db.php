<?php
/*
    Prepare SQLite database.
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

require 'config.php';

if (ct_OAuthDemo::$dbh) {
    ct_OAuthDemo::$dbh->exec('CREATE TABLE IF NOT EXISTS `users` (' .
        ' `userid` TEXT PRIMARY KEY,' .
        ' `name` TEXT,' .
        ' `avatar` TEXT,' .
        ' `refresh_token` TEXT' .
        ')');

    ct_OAuthDemo::$dbh->exec('CREATE UNIQUE INDEX IF NOT EXISTS `userid_uniq` ON `users` (`userid`)');
    ct_OAuthDemo::$dbh->exec('CREATE INDEX IF NOT EXISTS `name` ON `users` (`name`)');
    echo "Table 'users' created.<br/>\n";
}

?>