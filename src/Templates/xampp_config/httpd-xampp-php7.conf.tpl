#
# XAMPP settings
#

<IfModule env_module>
    SetEnv MIBDIRS "{{xamppDir}}/php/extras/mibs"
    SetEnv MYSQL_HOME "\\xampp\\mysql\\bin"
    SetEnv OPENSSL_CONF "{{xamppDir}}/apache/bin/openssl.cnf"
    SetEnv PHP_PEAR_SYSCONF_DIR "\\xampp\\php"
    SetEnv PHPRC "\\xampp\\php"
    SetEnv TMP "\\xampp\\tmp"
</IfModule>

#
# PHP-Module setup
#
LoadFile "{{xamppDir}}/php/php7ts.dll"
LoadFile "{{xamppDir}}/php/libpq.dll"
LoadModule php7_module "{{xamppDir}}/php/php7apache2_4.dll"

<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
<FilesMatch "\.phps$">
    SetHandler application/x-httpd-php-source
</FilesMatch>

#
# PHP-CGI setup
#
#<FilesMatch "\.php$">
#    SetHandler application/x-httpd-php-cgi
#</FilesMatch>
#<IfModule actions_module>
#    Action application/x-httpd-php-cgi "/php-cgi/php-cgi.exe"
#</IfModule>


<IfModule php7_module>
    PHPINIDir "{{xamppDir}}/php"
</IfModule>

<IfModule mime_module>
    AddType text/html .php .phps
</IfModule>

ScriptAlias /php-cgi/ "{{xamppDir}}/php/"
<Directory "{{xamppDir}}/php">
    AllowOverride None
    Options None
    Require all denied
    <Files "php-cgi.exe">
          Require all granted
    </Files>
</Directory>

<Directory "{{xamppDir}}/cgi-bin">
    <FilesMatch "\.php$">
        SetHandler cgi-script
    </FilesMatch>
    <FilesMatch "\.phps$">
        SetHandler None
    </FilesMatch>
</Directory>

<Directory "{{xamppDir}}/htdocs/xampp">
    <IfModule php7_module>
    	<Files "status.php">
    		php_admin_flag safe_mode off
    	</Files>
    </IfModule>
    AllowOverride AuthConfig
</Directory>

<IfModule alias_module>
    Alias /licenses "{{xamppDir}}/licenses/"
    <Directory "{{xamppDir}}/licenses">
        Options +Indexes
        <IfModule autoindex_color_module>
            DirectoryIndexTextColor  "#000000"
            DirectoryIndexBGColor "#f8e8a0"
            DirectoryIndexLinkColor "#bb3902"
            DirectoryIndexVLinkColor "#bb3902"
            DirectoryIndexALinkColor "#bb3902"
        </IfModule>
        Require local
        ErrorDocument 403 /error/XAMPP_FORBIDDEN.html.var
   </Directory>

    Alias /phpmyadmin "{{xamppDir}}/phpMyAdmin/"
    <Directory "{{xamppDir}}/phpMyAdmin">
        AllowOverride AuthConfig
        Require local
        ErrorDocument 403 /error/XAMPP_FORBIDDEN.html.var
    </Directory>

    Alias /webalizer "{{xamppDir}}/webalizer/"
    <Directory "{{xamppDir}}/webalizer">
        <IfModule php7_module>
    		<Files "webalizer.php">
    			php_admin_flag safe_mode off
    		</Files>
        </IfModule>
        AllowOverride AuthConfig
        Require local
        ErrorDocument 403 /error/XAMPP_FORBIDDEN.html.var
    </Directory>
</IfModule>
