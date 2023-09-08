<?php


$config = require_once "config.php";
require_once "autoload.php";
//require_once "components/functions.php";
//require_once "components/menus.php";

$tg = new Telegram(['token' => $config['token']]);
$tg->set_webhook("https://06cc-195-158-3-178.ngrok-free.app/hook.php");
$updates = $tg->get_webhookUpdates();
$photo_url = end($updates['message']['photo']);
$photo_url = $photo_url['file_id'];
$db->update_product_image(['image_url' => $photo_url], $product_id);
$tg->send_message("saqlandi", 848796050);
exit();
