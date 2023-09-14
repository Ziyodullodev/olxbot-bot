<?php


$config = require_once "config.php";
require_once "autoload.php";
require_once "components/functions.php";
require_once "components/menus.php";

$tg = new Telegram(['token' => $config['token']]);
$updates = $tg->get_webhookUpdates();

if (!empty($updates)) {

    if (!empty($updates['message']['chat']['id'])) {
        $chatData = $updates['message']['chat'];
    } elseif (!empty($updates['callback_query']['message']['chat']['id'])) {
        $chatData = $updates['callback_query']['message']['chat'];
    } else {
        $tg->send_message("Xatolik yuz berdi", "848796050");
        exit();
    }
    
    $tg->set_chatId($chatData['id']);
    $chat_id = $chatData['id'];
    $name = $chatData['first_name'];
    if ($chat_id == $config['arxiv_channel_id']) {
        $tg->send_message("Kanaldan xatolik", "848796050");
        exit();
    }
    $db = new Localbase($chat_id, $config['dbHost'], $config['dbName'], $config['dbUser'], $config['dbPassword']);
    $profile = new Profile($db, $tg, $chat_id);
    $giverent = new Giverent($db, $tg, $chat_id);
    $user_profile = $db->user;
    if (!$user_profile) {
        $profile->choice_city($name, true);
        exit();
    }

    $step = $user_profile['step'];
    $lang = $user_profile['lang'];
    $menus = $db->get_menu($lang);
    foreach ($menus as $menu) {
        $main_menu[] = [$menu['name']];
    }

    if (!empty($updates['message']['text'])) {
        $text = $updates['message']['text'];
        if ($text == $db->get_text('back_button', $lang)) {
            $tg->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('menu_text', $lang));
            if ($step == "add_product_photo" or $step == "add_product_info" or $step == "add_product") {
                $db->delete_product_and_img($user_profile['back']);
                $db->delete_product($user_profile['back']);
            }
            $db->update_user(['step' => 'menu']);
            exit();
        }
        if ($step == "start") {
            $profile->choice_city($name);
            exit();
        } elseif ($step == "add_product" and $text != $db->get_text('back_button', $lang)) {
            $tg->set_replyKeyboard([[$db->get_text('back_button', $lang)]])
                ->send_message($db->get_text('send_product_info', $lang));
            $action = json_decode($user_profile['back']);
            $category_id = end($action);
            $id = $db->create_product($category_id, $text);
            $db->update_user(['step' => 'add_product_info', 'page' => 'add', 'back' => $id]);
            exit();
        } elseif ($step == "add_product_info") {
            $product_id = $user_profile['back'];
            $db->update_product(['description' => $text], $product_id);
            $tg->set_replyKeyboard([[$db->get_text('back_button', $lang)]])->send_message($db->get_text("send_product_photo", $lang));
            $db->update_user(['step' => 'add_product_photo']);
            exit();
        } elseif ($step == "phone_number") {
            if (stripos($text, "+998") === 0 and strlen($text) == 13) {
                if (preg_match("/^[0-9]{12}$/", substr($text, 1, 12))) {
                    $db->update_user(['phone_number' => $text]);
                    $db->update_product(['phone_number' => $text], $user_profile['back']);
                    $tg
                        ->set_replyKeyboard($main_menu)
                        ->send_message($db->get_text("thanks_for_our", $lang));
                    // sending moderator message new product
                    $product = $db->get_product($user_profile['back']);
                    $product_photos = send_product_photos($db, $product['id']);
                    if (!empty($product_photos[0])) {
                        //      // Send the media group
                        $tg->send_media_group($product_photos[0]);
                    } else {
                        $tg->send_message($product_photos[1]);
                    }
                    $text = "⬆️ Tepadagi elonni tasdiqlang yoki rad eting";
                    $tg->set_inlineKeyboard(
                        [
                            [
                                ['text' => "✅ Tasdiqlash", 'callback_data' => "confirm_product-{$product['id']}"],
                                ['text' => "❌ Rad etish", 'callback_data' => "reject_product-{$product['id']}"]
                            ]
                        ]
                    )
                    ->send_message($text, $config['admin_id']);
                    $db->update_user(['step' => 'menu']);
                    exit();
                }
            }
            $tg->send_message($db->get_text("send_phone_number", $lang));
            exit();
        } elseif ($step == "lang") {
            if ($text == "🇺🇿 O'zbekcha") {
                $profile->change_lang('uz');
                $lang = "uz";
            } elseif ($text == "🇷🇺 Русский") {
                $profile->change_lang('ru');
                $lang = "ru";
            }

            $menus = $db->get_menu($lang);
            $main_menu = [];
            foreach ($menus as $menu) {
                $main_menu[] = [$menu['name']];
            }
            $tg->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('change_lang_success', $lang));
            exit();

        } elseif ($step == "search_product") {
            $tg->set_replyKeyboard($main_menu)
                ->send_message("Bu funksiya hali ishlamayapti");
            $db->update_user(['step' => 'menu']);
        //    $giverent->search_product($text);
            exit();
        }

        if ($text == "/start" and $step != "start") {

            $tg->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('menu_text', $lang));
            exit();
        } elseif ($text == "/menu") {
            $tg
                ->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('menu_text', $lang));
        }
        $get_command = $db->get_command_by_menu_name($text, $lang);
        if ($get_command) {
            if ($get_command['command'] == "sale") {
                $giverent->show_category(0, true);
                $db->update_user(['step' => "show_category", 'back' => json_encode(['menu', 0]), 'page' => 0, 'command' => 'sale']);
            } elseif ($get_command['command'] == "me") {
                $profile->show_profile();
            } elseif ($get_command['command'] == "get") {
                $giverent->show_category(0, true);
                $db->update_user(['step' => "show_category", 'back' => json_encode(['menu', 0]), 'page' => 0, 'command' => 'get']);
            } elseif ($get_command['command'] == "ready_button") {
                if ($step == "add_product_photo") {
                    $tg->request('sendmessage', [
                        'chat_id' => $chat_id,
                        'text' => $db->get_text("send_phone_number", $lang),
                        'reply_markup' => json_encode([
                            'resize_keyboard' => true,
                            'keyboard' => [
                                [
                                    ['text' => $db->get_text('send_number_button', $lang), 'request_contact' => true]
                                ],
                                [
                                    ['text' => $db->get_text('back_button', $lang)]
                                ]
                            ]
                        ])
                    ]);
                    $db->update_user(['step' => 'phone_number']);
                    exit();
                }
            } elseif ($get_command['command'] == "change_lang") {
                $profile->lang_keyboard();
            } elseif ($get_command['command'] == "change_location") {
                $tg->set_replyKeyboard([], true)
                    ->send_message(".")->delete_message($updates['message']['message_id'] + 1);
                $profile->choice_city($name, false, true);
            } elseif ($get_command['command'] == "edit_profile") {
                $tg->send_message("profileni tahrirlash");
            } elseif ($get_command['command'] = "search_product") {
                $tg->set_replyKeyboard([[$db->get_text('back_button', $lang)]])
                ->send_message($db->get_text('search_text', $lang));
                $db->update_user(['step' => 'search_product']);
            }
        } else {
            $tg->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('menu_text', $lang));
        }

    } elseif (!empty($updates['callback_query']['data'])) {
        $data = $updates['callback_query']['data'];
        if (stripos($data, "confirm_product") !== false) {
            $product_id = explode("-", $data)[1];
            $product_user = $db->get_product_user_chat_id($product_id);
            $db->update_product(['active' => 1], $product_id);
            $tg->delete_message();
            $tg->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('your_order_success', $lang), $product_user)
                ->send_message("{$product_id}-id dagi elon tasdiqlandi:", $chat_id);
            $db->update_user(['step' => 'menu']);
            exit();
        } elseif (stripos($data, "reject_product") !== false) {
            $product_id = explode("-", $data)[1];
            $product_user = $db->get_product_user_chat_id($product_id);
            $db->update_product(['active' => 0], $product_id);
            $tg->delete_message();
            $tg->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('your_order_filed', $lang), $product_user)
                ->send_message("{$product_id}-id dagi elon rad etildi:", $chat_id);
            $db->update_user(['step' => 'menu']);

            exit();
        }
        if ($step == "start") {
            $profile->choice_region($data);
            exit();
        } elseif ($step == "show_category") {
            $action = json_decode($user_profile['back']);
            if ($data == 'back') {
                array_pop($action);
                $data = end($action);
                if ($data == "menu") {
                    $tg->delete_message()
                        ->set_replyKeyboard($main_menu)
                        ->send_message($db->get_text('menu_text', $lang));
                    exit();
                }
                if ($data == 0) {
                    $giverent->show_category();
                } else {
                    $giverent->show_category(0, false, $data);
                }
                $db->update_user(['back' => json_encode($action), 'page' => 0]);
                exit();
            } elseif ($data == 'next') {
                $total = $user_profile['total_count'];
                $page = $user_profile['page'] + 4;
                if ($page >= $total) {
                    $tg->send_answer($db->get_text('next_page_not_found', $lang));
                } else {
                    $id = end($action);
                    if ($id == 0) {
                        $giverent->show_category($page);
                    } else {
                        $giverent->show_category($page, false, $id);
                    }
                    $db->update_user(['page' => $page]);
                }
                exit();
            } elseif ($data == 'prev') {
                $page = $user_profile['page'];
                if ($page <= 0) {
                    $tg->send_answer($db->get_text('prev_page_not_found', $lang));
                } else {
                    $id = end($action);
                    $page -= 4;
                    if ($id == 0) {
                        $giverent->show_category($page);
                    } else {
                        $giverent->show_category($page, false, $id);
                    }
                    $db->update_user(['page' => $page]);
                }
                exit();
            } elseif ($data == 'pagination_category') {
                $text = $db->get_text('total_category', $lang);
                $text = str_replace("{count}", $user_profile['total_count'], $text);
                $tg->send_answer($text);
                exit();
            }
            array_push($action, $data);
            $giverent->show_category(0, false, $data);
            $db->update_user(['back' => json_encode($action), 'page' => 0]);
        } elseif ($step == "show_product") {
            $action = json_decode($user_profile['back']);
            if ($data == 'back') {
                array_pop($action);
                $data = end($action);
                $giverent->show_product($data, 0, 'show', 'backk');
                $db->update_user(['back' => json_encode($action), 'page' => 0, 'step' => 'show_product']);
                exit();
            } elseif ($data == 'backk') {
                array_pop($action);
                $data = end($action);
                $giverent->show_category(0, false, $data);
                $db->update_user(['back' => json_encode($action), 'page' => 0, 'step' => 'show_category']);
                exit();
            } elseif ($data == 'next') {
                $total = $user_profile['total_count'];
                $page = $user_profile['page'] + 4;
                if ($page >= $total) {
                    $tg->send_answer($db->get_text('next_page_not_found', $lang));
                } else {
                    $id = end($action);
                    $giverent->show_product($id, $page);
                    $db->update_user(['page' => $page]);
                }
                exit();
            } elseif ($data == 'prev') {
                $page = $user_profile['page'];
                if ($page <= 0) {
                    $tg->send_answer($db->get_text('prev_page_not_found', $lang));
                } else {
                    $id = end($action);
                    $page -= 4;
                    $giverent->show_product($id, $page);
                    $db->update_user(['page' => $page]);
                }
                exit();
            } elseif ($data == 'pagination_category') {
                $text = $db->get_text('total_category', $lang);
                $text = str_replace("{count}", $user_profile['total_count'], $text);
                $tg->send_answer($text);
                exit();
            }
            array_push($action, $data);
            $giverent->show_product($data, 0, 'product');
            $db->update_user(['back' => json_encode($action), 'page' => 0]);
        } elseif ($step == "choice_region") {
            # viloyat capital
            $profile->choice_region_redirect_menu($data);

        } elseif ($data == "phone_number") {
            $tg->delete_message()
                ->request('sendmessage', [
                    'chat_id' => $chat_id,
                    'text' => $db->get_text("send_phone_number", $lang),
                    'reply_markup' => json_encode([
                        'resize_keyboard' => true,
                        'keyboard' => [
                            [
                                ['text' => $db->get_text('send_number_button', $lang), 'request_contact' => true]
                            ],
                            [
                                ['text' => $db->get_text('back_button', $lang)]
                            ]
                        ]
                    ])
                ]);
            $localbase->update_user(['step' => 'phone_number']);
        } elseif ($data == "location") {
            $i = 0;
            $c = 2;
            foreach ($viloyatlar as $viloyat => $value) {
                $keys[floor($i / $c)][$i % $c] = ['text' => $viloyat, 'callback_data' => $viloyat];
                $i++;
            }
            $tg
                ->set_inlineKeyboard($keys)
                ->edit_message($db->get_text('choice_region', $lang));
            $localbase->update_user(['step' => 'start']);
        } elseif ($data == "my_orders") {
            $tg->send_message($db->get_text('my_orders', $lang));
        } elseif ($data == "back") {
            $tg->delete_message()
                ->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('menu_text', $lang));
                $db->update_user(['step' => 'menu']);
        }

    } elseif (!empty($updates['message']['contact'])) {
        if ($step != "phone_number") {
            $tg
                ->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('menu_text', $lang));
            exit();
        }
        $phone_number = (stripos($updates['message']['contact']['phone_number'], "+") !== 0) ? "+" . $updates['message']['contact']['phone_number'] : $updates['message']['contact']['phone_number'];
        $db->update_user(['phone_number' => $phone_number]);
        $db->update_product(['phone_number' => $phone_number], $user_profile['back']);
        $tg
            ->set_replyKeyboard($main_menu)
            ->send_message($db->get_text("thanks_for_our", $lang));
        // sending moderator message new product
        $product = $db->get_product($user_profile['back']);
        $product_photos = send_product_photos($db, $product['id']);
        if (!empty($product_photos[0])) {
            //      // Send the media group
            $tg->send_media_group($product_photos[0]);
        } else {
            $tg->send_message($product_photos[1]);
        }
        $text = "⬆️ Tepadagi elonni tasdiqlang yoki rad eting";
        $tg->set_inlineKeyboard(
            [
                [
                    ['text' => "✅ Tasdiqlash", 'callback_data' => "confirm_product-{$product['id']}"],
                    ['text' => "❌ Rad etish", 'callback_data' => "reject_product-{$product['id']}"]
                ]
            ]
        )
            ->send_message($text, $config['admin_id']);
        $db->update_user(['step' => 'menu']);
        exit();
    } elseif (!empty($updates['message']['photo'])) {
        if ($step == "add_product_photo") {
            $product_id = $user_profile['back'];
            $count_img = $db->get_count_img($product_id);
            if ($count_img >= 3) {
                $tg->request('sendmessage', [
                    'chat_id' => $chat_id,
                    'text' => $db->get_text("send_phone_number", $lang),
                    'reply_markup' => json_encode([
                        'resize_keyboard' => true,
                        'keyboard' => [
                            [
                                ['text' => $db->get_text('send_number_button', $lang), 'request_contact' => true]
                            ],
                            [
                                ['text' => $db->get_text('back_button', $lang)]
                            ]
                        ]
                    ])
                ]);
                $db->update_user(['step' => 'phone_number']);
                exit();
            }
            $photo_url = end($updates['message']['photo']);
            $channel_message_id = getUserConfig('message_id');
            $img_link = $config['channel_username'] . "/" . $channel_message_id;
            $id = $db->create_img($product_id,$img_link, $photo_url['file_id']);
            $tg->send_photo($photo_url['file_id'], "{$id}", $config['arxiv_channel_id']);
            setUserConfig('message_id', $channel_message_id + 1);
            $tg->set_replyKeyboard([[$db->get_text('ready_button', $lang)], [$db->get_text('back_button', $lang)]])
                ->send_message($db->get_text('limit_images', $lang));
            exit();
        }
    } else {
        $text = "";
    }
} else {
    //$tg->set_webhook("https://f836-95-214-211-70.ngrok-free.app/hook.php");
    echo "set webhook success";
    die();
}