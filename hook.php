<?php


$config = require_once "config.php";
require_once "autoload.php";
require_once "components/functions.php";
require_once "components/menus.php";
require_once 'lang/i18n.class.php';

$tg = new Telegram(['token' => $config['token']]);
$tg->set_webhook("https://b010-37-110-215-15.ngrok-free.app/hook.php");
$updates = $tg->get_webhookUpdates();
$i18n = new i18n('lang/lang_{LANGUAGE}.ini', 'langcache/', 'uz');
$i18n->getAppliedLang();
$i18n->getCachePath();

if (!empty($updates)) {

     if (!empty($updates['message']['chat']['id'])) {
          $tg->set_chatId($updates['message']['chat']['id']);
          $chat_id = $updates['message']['chat']['id'];
     } else {
          $tg->set_chatId($updates['callback_query']['message']['chat']['id']);
          $chat_id = $updates['callback_query']['message']['chat']['id'];
     } 
     // $tg->send_message("Salom kerakli bo'limni tanlang:");
     // exit();

     $localbase = new Localbase($chat_id);
     $user_profile = $localbase->get_user();
     if ($user_profile) {
          $step = $user_profile['step'];
          $lang = 'uz';
     } else {
          $step = "start";
          $name = $updates['message']['chat']['first_name'];
          $i = 0;
          $c = 2;
          foreach ($viloyatlar as $key => $value) {
               $keys[floor($i/$c)][$i%$c]= ['text' => $key,'callback_data' => $value];
               $i++;
          }
          $tg->set_inlineKeyboard($keys)
          ->send_message("Assalomu alaykum, $name\ntuda suda karochi\n\nQaysi viloyatdansiz?");
          $localbase->create_user($name, $step, $chat_id);
     }

     if (!empty($updates['message']['text'])) {
          $text = $updates['message']['text'];
          if ($step == "start")
          {
               $name = $updates['message']['chat']['first_name'];
               $i = 0;
               $c = 2;
               foreach ($viloyatlar as $key => $value) {
                    $keys[floor($i/$c)][$i%$c]= ['text' => $key,'callback_data' => $value];
                    $i++;
               }
               $tg->set_inlineKeyboard($keys)
               ->send_message("Assalomu alaykum, $name\ntuda suda karochi\n\nQaysi viloyatdansiz?");
               $localbase->create_user($name, $step, $chat_id);
          }
          if ($text == "/start" and $step != "start") {
               // $msg = L::start;
               $tg->set_replyKeyboard($main_menu)
               ->send_message("Salom kerakli bo'limni tanlang:");
          }
          elseif ($text == "/location") {
               $i = 0;
               $c = 2;
               foreach ($viloyatlar as $viloyat => $value) {
                    $keys[floor($i/$c)][$i%$c]= ['text' => $viloyat,'callback_data' => $viloyat];
                    $i++;
               }
               $tg
               ->set_inlineKeyboard($keys)
               ->send_message("Qaysi viloyatdansiz?");
               $localbase->update_user(['step' => 'start']);
              
          }
          elseif ($text == "/menu") {
               $tg
               ->set_replyKeyboard($main_menu)
               ->send_message("kerakli bo'limni tanlang:");
          }
          elseif ($text == "/delete") {
               $localbase->deleteuser();
               $tg->send_message("o'chirildi");
          }
          elseif ($text == "Arendaga berish") {
               $i = 0;
               $c = 2;
               foreach ($kategorylar as $kategory => $value) {
                    $keys[floor($i/$c)][$i%$c]= ['text' => $value,'callback_data' => $kategory];
                    $i++;
               }
               $tg
               ->set_inlineKeyboard($keys)
               ->send_message("Qaysi kategoriyadagi maxsulotni arendaga bermoqchisiz?");
          }
          elseif ($text == "Arendaga olish") {
               $tg->send_message("Arendaga olish bo'limi");
          }
          elseif ($text == "Profilim") {
               $phone_number = ($user_profile['phone_number'] == "non") ? "Telefon raqamizni kiritmadingiz" : $user_profile['phone_number'];
               $msg = "Ismingiz: " . $user_profile['name'] . "\n" . "Viloyatingiz: " . $user_profile['location'] . "\n" . "Til: " . $user_profile['lang'] . "\n" . "Telefon raqamingiz: " . $phone_number; 
               $tg
               ->set_inlineKeyboard([
                    [
                    ['text' => "Telefon raqamni kiritish",'callback_data' => "phone_number"]
                    ],
                    // [
                    // ['text' => "Tilni o'zgartirish",'callback_data' => "lang"]
                    // ],
                    [
                    ['text' => "Viloyatni o'zgartirish",'callback_data' => "location"]
                    ],
                    [
                    ['text' => "Mening buyurtmalarim",'callback_data' => "my_orders"]
                    ],
                    [
                    ['text' => "Ortga",'callback_data' => "back"]
                    ]
               ])
               ->send_message($msg);
          }
          

     } elseif (!empty($updates['callback_query']['data'])) {
          $data = $updates['callback_query']['data'];
          if ($step == "start") {
               $viloyatlar = $viloyatlar["{$data}"];
               $shaxarlar = $shaxarlar[$viloyatlar];
               $i = 0;
               $c = 2;
               $localbase->update_user(['step' => 'shahar', 'location' => "{$data}"]);
               foreach ($shaxarlar as $key => $value) {
                    $keys[floor($i/$c)][$i%$c]= ['text' => "{$key}",'callback_data' =>"{$key}"];
                    $i++;
               }
               $tg->set_inlineKeyboard($keys)
               ->edit_message("Shahar yoki tumanni tanlang:");
          }elseif ($step == "shahar") {
               # viloyat capital
               $data = ucwords($user_profile['location']) ." , ". "{$data}";
               $localbase->update_user(['step' => 'menu', 'location' => "{$data}"]);
               $tg->delete_message();
               $tg
               ->set_replyKeyboard($main_menu)
               ->send_message("Salom kerakli bo'limni tanlang:");
          }
          elseif ($data == "phone_number") {
               $tg->delete_message()
               ->request('sendmessage', [
                    'chat_id' => $chat_id,
                    'text' => "Telefon raqamingizni kiriting:",
                    'reply_markup' => json_encode([
                         'resize_keyboard' => true,
                         'keyboard' => [
                              [
                                   ['text' => "Telefon raqamni yuborish",'request_contact' => true]
                              ],
                              [
                                   ['text' => "Ortga"]
                              ]
                         ]
                    ])
               ]);
               $localbase->update_user(['step' => 'phone_number']);
          }
          elseif ($data == "location"){
               $i = 0;
               $c = 2;
               foreach ($viloyatlar as $viloyat => $value) {
                    $keys[floor($i/$c)][$i%$c]= ['text' => $viloyat,'callback_data' => $viloyat];
                    $i++;
               }
               $tg
               ->set_inlineKeyboard($keys)
               ->edit_message("Qaysi viloyatga o'zgartirmoqchisiz?"); 
               $localbase->update_user(['step' => 'start']);
          }
          elseif ($data == "my_orders") {
               $tg->send_message("Mening buyurtmalarim bo'limi");
          }
          elseif ($data == "back") {
               $tg->delete_message()
               ->set_replyKeyboard($main_menu)
               ->send_message("Salom kerakli bo'limni tanlang:");
          }
     
     } elseif (!empty($updates['message']['contact'])) {
          $phone_number = (stripos($updates['message']['contact']['phone_number'], "+") !== 0) ? "+" . $updates['message']['contact']['phone_number'] : $updates['message']['contact']['phone_number'];
          $localbase->update_user(['phone_number' => $phone_number]);
          $tg->set_replyKeyboard($main_menu)
          ->send_message("Salom kerakli bo'limni tanlang:");
     
     } else {
          $text = "";
     }
}
