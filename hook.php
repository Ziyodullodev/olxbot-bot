<?php


$config = require_once "config.php";
require_once "autoload.php";
require_once "components/functions.php";
require_once "components/menus.php";
require_once 'lang/i18n.class.php';

$tg = new Telegram(['token' => $config['token']]);
// $tg->set_webhook("https://ziyodev.uz/olx-telegram/src/hook.php");
$updates = $tg->get_webhookUpdates();
// $i18n = new i18n('lang/lang_{LANGUAGE}.ini', 'langcache/', 'uz');
// $i18n->getAppliedLang();
// $i18n->getCachePath();

if (!empty($updates)) {

    if (!empty($updates['message']['chat']['id'])) {
        $tg->set_chatId($updates['message']['chat']['id']);
        $chat_id = $updates['message']['chat']['id'];
        $name = $updates['message']['chat']['first_name'];

    } else {
        $tg->set_chatId($updates['callback_query']['message']['chat']['id']);
        $chat_id = $updates['callback_query']['message']['chat']['id'];
        $name = $updates['callback_query']['message']['chat']['first_name'];

    }

    $db = new Localbase($chat_id);
    $profile = new Profile($db, $tg, $chat_id);
    $giverent = new Giverent($db, $tg, $chat_id);
    $user_profile = $db->user;
    if (!$user_profile) {
        $profile->choice_city($name, true);
        exit();
    }

    $step = $user_profile['step'];
    $lang = 'uz';
    $menus = $db->get_menu($lang);
    foreach ($menus as $menu) {
        $main_menu[] = [$menu['name']];
    }

    if (!empty($updates['message']['text'])) {
        $text = $updates['message']['text'];
        if ($text == $db->get_text('back_button', $lang)) {
            $tg->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('menu_text', $lang));
            if ($step == "add_product_photo") {

                $db->delete_product_and_img($user_profile['back']);
            } else {
                $db->delete_product($user_profile['back']);
            }
            $db->update_user(['step' => 'menu']);
            exit();
        }
        if ($step == "start") {
            $profile->choice_city($name);
            exit();
        } elseif ($step == "add_product") {
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
            if (stripos($text, "+998") !== 0) {
                $tg->send_message($db->get_text("send_phone_number", $lang));
                exit();
            }
            $db->update_user(['phone_number' => $text]);
            $db->update_product(['phone_number' => $text], $user_profile['back']);
            $tg->set_replyKeyboard($main_menu)
                ->send_message($db->get_text("thanks_for_our", $lang));

            // sending moderator message new product
            $product = $db->get_product($user_profile['back']);
            $stmt = $db->db->prepare("SELECT image FROM product_image WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $product, PDO::PARAM_INT);
            $stmt->execute();

            $products = $db->db->query("SELECT 
               pr.title, pr.description, 
               users.name, users.chat_id, users.phone_number,
               cat.title_uz as category_title
               FROM products as pr
               LEFT JOIN users ON users.id = pr.user_id
               LEFT JOIN categories as cat ON cat.id = pr.category_id
               WHERE pr.id = {$product}")->fetch(PDO::FETCH_ASSOC);
            // Initialize an array to store media items
            $mediaItems = [];

            // Fetch all images and create media items
            $i = 0;
            $text = "🛍 <b>" . $products['title'] . "</b>\n\n<i>" . $products['description'] . "</i>\n\n" . "📞 Telefon raqam: <b>" . $this->db->user['phone_number'] . "</b>\n\n" . "👤 Sotuvchi: <b>" . $products['name'] . "</b>\n\n" . "📦 Kategoriya: <b>" . $products['category_title'] . "</b>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Check if the 'image' column is not null
                if (!is_null($row['image'])) {
                    // Create a media item for each image
                    if ($i == 0) {
                        $mediaItems[] = [
                            'type' => 'photo',
                            // Specify the media type as photo
                            'media' => $row['image'],
                            // URL or file ID of the image
                            'caption' => $text,
                            'parse_mode' => 'HTML'
                        ];
                        $i++;
                    } else {
                        $mediaItems[] = [
                            'type' => 'photo',
                            // Specify the media type as photo
                            'media' => $row['image'],
                            // URL or file ID of the image
                        ];
                    }
                }
            }
            $text = "Tepadagi elonni tasdiqlang yoki rad eting";
            $tg->set_inlineKeyboard(
                [
                    [
                        ['text' => "Tasdiqlash", 'callback_data' => "confirm_product-{$product['id']}"],
                        ['text' => "Rad etish", 'callback_data' => "reject_product-{$product['id']}"]
                    ]
                ]
            )
                ->send_message($text, $config['admin_id']);
            $db->update_user(['step' => 'manu']);
            exit();

        }
        elseif ($step == "lang"){
            if ($text == "🇺🇿 O'zbekcha"){
                $profile->change_lang('uz');
                $lang = "uz";
            }elseif ($text == "🇷🇺 Русский"){
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

        }
        if ($text == "/start" and $step != "start") {

            $tg->set_replyKeyboard($main_menu)
                ->send_message($db->get_text('menu_text', $lang));
            exit();
        } elseif ($text == "/location") {
            $profile->choice_city($name);
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
            $product = $db->get_product($data);
            $user_id = $product['user_id'];
            $user_chat_id = $db->get_user_chat_id($user_id);
            $tg->send_message("test", $user_chat_id);

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
        $stmt = $db->db->prepare("SELECT image FROM product_image WHERE product_id = :product_id");
        $stmt->bindParam(':product_id', $product['id'], PDO::PARAM_INT);
        $stmt->execute();
        $products = $db->db->query("SELECT 
               pr.title, pr.description, 
               users.name, users.chat_id, users.phone_number,
               cat.title_uz as category_title
               FROM products as pr
               LEFT JOIN users ON users.id = pr.user_id
               LEFT JOIN categories as cat ON cat.id = pr.category_id
               WHERE pr.id = {$product['id']}")->fetch(PDO::FETCH_ASSOC);
        // // Initialize an array to store media items
        $mediaItems = [];

        // Fetch all images and create media items
        $i = 0;
        $text = "🛍 <b>" . $products['title'] . "</b>\n\n<i>" . $products['description'] . "</i>\n\n" . "📞 Telefon raqam: <b>" . $products['phone_number'] . "</b>\n\n" . "👤 Sotuvchi: <b>" . $products['name'] . "</b>\n\n" . "📦 Kategoriya: <b>" . $products['category_title'] . "</b>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Check if the 'image' column is not null
            if (!is_null($row['image'])) {
                // Create a media item for each image
                if ($i == 0) {
                    $mediaItems[] = [
                        'type' => 'photo',
                        // Specify the media type as photo
                        'media' => $row['image'],
                        // URL or file ID of the image
                        'caption' => $text,
                        'parse_mode' => 'HTML'
                    ];
                    $i++;
                } else {
                    $mediaItems[] = [
                        'type' => 'photo',
                        // Specify the media type as photo
                        'media' => $row['image'],
                        // URL or file ID of the image
                    ];
                }
            }
        }
        if (!empty($mediaItems)) {
            //      // Send the media group
            $tg->send_media_group($mediaItems);
        } else {
            $tg->send_message($text);
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
            $db->create_img($product_id, $photo_url['file_id']);
            $tg->set_replyKeyboard([[$db->get_text('ready_button', $lang)], [$db->get_text('back_button', $lang)]])
                ->send_message($db->get_text('limit_images', $lang));
            exit();
        }

    } else {
        $text = "";
    }
}