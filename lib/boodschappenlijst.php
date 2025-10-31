<?php

class boodschappenlijst {
    
    private $connection;

    //Constructor
    public function __construct($connection) {
        $this->connection = $connection;
    }
    //End constructor

    //Public functions
    public function selecteerBoodschappenLijst($user_id) {
        $user_id = (int) $user_id;
        $sql = "select * from boodschappenlijst where user_id = $user_id";
        
        $result = mysqli_query($this->connection, $sql);
        $boodschappenlijst = [];

        while ($row = mysqli_fetch_assoc($result))
            {
            $user = $this->selecteerUser($row['user_id']);
            $artikel = $this->selecteerArtikel($row['article_id']);    
            $boodschappenlijst[] = array_merge(
                [
                    'id' => $row['id'],
                    'user_id' => $row['user_id'],
                    'article_id' => $row['article_id'],
                    'amount' => $row['amount']
                ],
                $user,
                $artikel
            );           
        };
        return($boodschappenlijst);
    }
    
    public function boodschappenToevoegen($gerecht_id, $user_id) {
        $gerecht_id = (int) $gerecht_id;
        $user_id = (int) $user_id;

        $ingredienten = new ingredient($this->connection);
        $ingredientenLijst = $ingredienten->selecteerIngredientsFromRecipe($gerecht_id);
        foreach($ingredientenLijst as $ingredient) {
            if (!isset($ingredient['artikel_id'])) {
                continue;
            }

            $artikelId = (int) $ingredient['artikel_id'];
            $requiredAmount = $this->bepaalIngredientHoeveelheid($ingredient);

            if (!$this->ArtikelOpLijst($artikelId, $user_id)) {
                $this->voegArtikelToe($artikelId, $user_id, $requiredAmount);
            } else {
                $this->updateArtikel($artikelId, $user_id, $requiredAmount);
            }
        }
    }

    public function verwijderArtikel($artikelId, $user_id) {
        $artikelId = (int) $artikelId;
        $user_id = (int) $user_id;

        if ($artikelId < 0) {
            return false;
        }

        $sql = "delete from boodschappenlijst where article_id = $artikelId and user_id = $user_id";
        $result = mysqli_query($this->connection, $sql);
        return $result !== false;
    }
    
    private function ArtikelOpLijst($artikel_id, $user_id) {
        $artikel_id = (int) $artikel_id;
        $user_id = (int) $user_id;

        $boodschappen = $this->selecteerBoodschappenLijst($user_id);
        if (!is_array($boodschappen) || empty($boodschappen)) {
            return false;
        }
        foreach($boodschappen as $item) {
            if($item['article_id'] == $artikel_id) {
                return(true);
            }
        }
        return(false);
    }
    //End public functions

    //Private functions
    private function voegArtikelToe($artikel_id, $user_id, $amount) {
        $artikel_id = (int) $artikel_id;
        $user_id = (int) $user_id;
        $amount = (int) max(1, $amount);

        $sql = "insert into boodschappenlijst (user_id, article_id, amount) values ($user_id, $artikel_id, $amount)";
        mysqli_query($this->connection, $sql);
    }
    
    private function updateArtikel($artikel_id, $user_id, $amount) {
        $artikel_id = (int) $artikel_id;
        $user_id = (int) $user_id;
        $amount = (int) max(1, $amount);

        $sql = "update boodschappenlijst set amount = amount + $amount where article_id = $artikel_id and user_id = $user_id";
        mysqli_query($this->connection, $sql);
    }
    
    private function selecteerUser($user_id){
        $usr = new user($this->connection);
        $user = $usr->selecteerUser($user_id);
        return($user);
    }
    
    private function selecteerArtikel($artikel_id){
        $art = new artikel($this->connection);
        $artikel = $art->selecteerArtikel($artikel_id);
        return($artikel);
    }

    private function bepaalIngredientHoeveelheid($ingredient) {
        if (!is_array($ingredient)) {
            return 1;
        }

        $rawAmount = $ingredient['amount'] ?? ($ingredient['aantal'] ?? 1);

        if (is_numeric($rawAmount)) {
            $numericAmount = (float) $rawAmount;
            if ($numericAmount <= 0) {
                return 1;
            }
            return (int) max(1, ceil($numericAmount));
        }

        return 1;
    }
    //End private functions
}
