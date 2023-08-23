<?php

class Localbase
{
    private $db;
    public $chat_id;
    function __construct($chat_id)
    {
        $this->chat_id = $chat_id;
        $this->connect();
    }

    function connect()
    {
        try {
            $dbPath = 'db/users.db';
            $pdo = new PDO('sqlite:' . $dbPath);
            // return "success";
        } catch (PDOException $e) {
            echo "Error connecting to database: " . $e->getMessage();
            die();
        }
        // return 'ok';
        $this->db = $pdo;
    }

    function get_user()
    {
        return $this->db->query("SELECT * FROM users WHERE chat_id = '{$this->chat_id}'")->fetch(PDO::FETCH_ASSOC);
    }

    public function create_user($name, $step, $chatId, $lang='uz', $location="non", )
    {
        // Prepare the SQL statement
        $sql = "INSERT INTO users (name, step, lang, chat_id, location,phone_number, created_at)
                VALUES (:name, :step, :lang, :chatId, :location, :phone_number, datetime('now'))"; 
        $stmt = $this->db->prepare($sql);
        $phone_number = "non";
        // Bind the parameters
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':step', $step);
        $stmt->bindParam(':lang', $lang);
        $stmt->bindParam(':chatId', $chatId);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':phone_number',$phone_number);
        
        // Execute the statement
        $stmt->execute();
    }

    function update_user($data)
     {
          $placeholders = [];
          foreach ($data as $key => $value) {
               // Escape and sanitize the value
               $escapedValue = $this->db->quote($value);
               $placeholders[] = "$key = $escapedValue";
          }

          $updateFields = implode(', ', $placeholders);

          $sql = "UPDATE users SET $updateFields WHERE chat_id = :chatId";

          $stmt = $this->db->prepare($sql);
          $stmt->bindValue(':chatId', $this->chat_id);

          return $stmt->execute();
     }

    public function deleteuser(){
        $this->db->query("DELETE FROM users WHERE chat_id = '{$this->chat_id}'");
        return "okey";
    }
}

// $localbase = new Localbase();