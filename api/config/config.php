<?php

declare(strict_types=1);

define("DEBUG", getenv('DEBUG'));

define("MYSQL_HOST", getenv('MYSQL_HOST'));
define("MYSQL_PORT", (int)getenv('MYSQL_PORT'));
define("MYSQL_USER", getenv('MYSQL_USER'));
define("MYSQL_PASSWORD", getenv('MYSQL_PASSWORD'));
define("MYSQL_DATABASE", getenv('MYSQL_DATABASE'));

define("TOKEN_KEY", getenv('TOKEN_KEY'));
define("TOKEN_ISSUER", getenv('TOKEN_ISSUER'));
define("PASSWORD_PEPPER", getenv('PASSWORD_PEPPER'));

define("APP_URL", getenv('APP_URL'));
define("API_URL", getenv('API_URL'));

define("APP_EMAIL_URL", getenv('APP_EMAIL_URL'));

define("SMTP_HOST", getenv('SMTP_HOST'));
define("SMTP_USER", getenv('SMTP_USER'));
define("SMTP_PASSWORD", getenv('SMTP_PASSWORD'));

define("AWS_ACCESS_KEY", getenv('AWS_ACCESS_KEY'));
define("AWS_SECRET_KEY", getenv('AWS_SECRET_KEY'));
define("AWS_REGION", getenv('AWS_REGION'));
define("AWS_BUCKET", getenv('AWS_BUCKET'));