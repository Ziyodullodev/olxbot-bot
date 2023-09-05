<?php

class Database
{
     public $db;
     public $user_id;
     private $db_lang = 'mysql';
     private $db_host = 'localhost';
     private $dbname = 'my-health';
     private $db_username = 'my-health';
     private $db_password = 'cU7cJ1fF4a';
     function __construct($chat_id)
     {
          $this->user_id = $chat_id;
          $this->connect();
     }
     function connect()
     {
          try {
               // $dbPath = 'db/db.sqlite3';
               $pdo = new PDO($this->db_lang . ":host=" . $this->db_host . ";dbname=" . $this->dbname . ";charset=utf8mb4", $this->db_username, $this->db_password);
          } catch (PDOException $e) {
               echo "Error connecting to database: " . $e->getMessage();
               die();
          }
          // echo "successfully connected";
          $this->db = $pdo;
     }
     function get_user()
     {
          return $this->db->query("SELECT * FROM users WHERE chat_id = '{$this->user_id}'")->fetch(PDO::FETCH_ASSOC);
     }
     function get_text($command, $lang = 'uz')
     {
          return $this->db->query("SELECT `text_{$lang}` as msg FROM `texts` WHERE command = '{$command}'")->fetch(PDO::FETCH_ASSOC)['msg'];
     }
     function get_buttons($lang = 'uz')
     {
          return $this->db->query("SELECT `text_{$lang}` as text FROM `texts` WHERE type = 'menu'")->fetchAll(PDO::FETCH_ASSOC);
     }
     function get_command_by_text($text, $lang = 'uz')
     {
          $query = "SELECT command FROM `texts` WHERE text_{$lang} = :text";

          $stmt = $this->db->prepare($query);
          $stmt->bindValue(':text', $text);
          $stmt->execute();

          $result = $stmt->fetch(PDO::FETCH_ASSOC);

          return $result ? $result['command'] : null;
     }

     function get_order_with_user($order_id)
     {
          $query = "
        SELECT o.id, u.chat_id, u.name as user_name, u.lang
        FROM `orders` AS o
        LEFT JOIN `users` AS u ON o.user_id = u.id
        WHERE o.id = :order_id
    ";

          $stmt = $this->db->prepare($query);
          $stmt->bindParam(':order_id', $order_id);
          $stmt->execute();

          return $stmt->fetch(PDO::FETCH_ASSOC);
     }

     function get_product_by_id($id)
     {
          return $this->db->query("SELECT * FROM `products` WHERE id = '{$id}'")->fetch(PDO::FETCH_ASSOC);
     }
     function get_product($id)
     {
          if ($id < 0) {
               $data = $this->db->query("SELECT * FROM `products` WHERE active = 1 ORDER BY `id` DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
               $count = $this->db->query("SELECT COUNT(id) as count FROM `products` WHERE active = 1")->fetch(PDO::FETCH_ASSOC)['count'];
               return [$data, $count - 1];
          }
          $data = $this->db->query("SELECT * FROM `products` WHERE active = 1 LIMIT 1 OFFSET " . $id)->fetch(PDO::FETCH_ASSOC);
          if (!$data) {
               // SELECT * FROM `products` WHERE active = 1 ORDER BY `id` DESC LIMIT 1
               $data = $this->db->query("SELECT * FROM `products` WHERE active = 1 ORDER BY `id` ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
               return [$data, 0];
          }
          return [$data, $id];
     }
     function update_user($condition)
     {
          $this->db->query("UPDATE users SET {$condition} WHERE chat_id=" . $this->user_id);
     }
     function create_user($name, $lang, $step)
     {
          // SQL query to insert the user
          $sql = "INSERT INTO `my-health`.`users` (`id`, `chat_id`, `name`, `phone_number`, `lang`, `step`,`created_at`)
            VALUES (NULL, :chat_id, :full_name, :phone, :lang, :step, CURRENT_TIMESTAMP);";

          // Prepare the statement
          $stmt = $this->db->prepare($sql);

          // Bind the parameters
          $stmt->bindValue(':full_name', $name);
          $stmt->bindValue(':chat_id', $this->user_id);
          $stmt->bindValue(':phone', "non"); // Assuming you're using chat_id as the phone number
          $stmt->bindValue(':lang', $lang);
          $stmt->bindValue(':step', $step);

          // Execute the query
          $stmt->execute();
     }

     function insertOrder($user_id, $product_id, $amount, $location, $status)
     {
          $sql = "INSERT INTO orders (user_id, product_id, amount, location, status, deleted, create_at) 
            VALUES (:user_id, :product_id, :amount, :location, :status, '0', CURRENT_TIMESTAMP)";

          $stmt = $this->db->prepare($sql);
          $stmt->bindParam(':user_id', $user_id);
          $stmt->bindParam(':product_id', $product_id);
          $stmt->bindParam(':amount', $amount);
          $stmt->bindParam(':location', $location);
          $stmt->bindParam(':status', $status);

          if ($stmt->execute()) {
               return $this->db->lastInsertId();
          } else {
               return false;
          }
     }


     function updateOrderStatus($order_id, $new_status)
     {
          $sql = "UPDATE orders 
                  SET status = :new_status 
                  WHERE id = :order_id";

          $stmt = $this->db->prepare($sql);
          $stmt->bindParam(':order_id', $order_id);
          $stmt->bindParam(':new_status', $new_status);

          return $stmt->execute();
     }

     function updateOrderDeleted($order_id)
     {
          // First, retrieve the current deleted status
          $current_deleted = $this->getOrderDeletedStatus($order_id);

          // Toggle the deleted status (0 to 1 or 1 to 0)
          $new_deleted = $current_deleted ? 0 : 1;

          // Update the deleted status in the database
          $sql = "UPDATE orders 
            SET deleted = :deleted
            WHERE id = :order_id";

          $stmt = $this->db->prepare($sql);
          $stmt->bindParam(':order_id', $order_id);
          $stmt->bindParam(':deleted', $new_deleted);

          return $stmt->execute();
     }

     function getOrderDeletedStatus($order_id)
     {
          $sql = "SELECT deleted FROM orders WHERE id = :order_id";
          $stmt = $this->db->prepare($sql);
          $stmt->bindParam(':order_id', $order_id);
          $stmt->execute();
          return $stmt->fetchColumn();
     }
}
