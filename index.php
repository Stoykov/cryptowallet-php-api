<?php
include "config.php";
include "lib/jsonRPCClient.php";
include "lib/Crypto.php";

$crypt = new Crypto_API($integrity_check, $settings, $server);

if ($crypt->open_connection())
{
	echo "connected<br />";
}
else
{
	echo "not connected<br />";
}

//echo $crypt->generate_new_address('AKRQM');
echo $crypt->get_balance('AKRQM');