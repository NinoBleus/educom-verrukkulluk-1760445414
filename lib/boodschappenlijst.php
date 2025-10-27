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
    $ingredienten = new ingredient($this->connection);
    $ingredientenLijst = $ingredienten->selecteerIngredientsFromRecipe($gerecht_id);
    foreach($ingredientenLijst as $ingredient) {
        if(!$this->ArtikelOpLijst($ingredient['artikel_id'], $user_id)) {
            $this->voegArtikelToe($ingredient['artikel_id'], $user_id, $amount = 1);
        }
        else {
            $this->updateArtikel($ingredient['artikel_id'], $user_id);
        }
    }
}
    
    private function ArtikelOpLijst($artikel_id, $user_id) {
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
        $sql = "insert into boodschappenlijst (user_id, article_id, amount) values ($user_id, $artikel_id, $amount)";
        mysqli_query($this->connection, $sql);
    }
    
    private function updateArtikel($artikel_id, $user_id) {
        $sql = "update boodschappenlijst set amount = amount + 1 where article_id = $artikel_id and user_id = $user_id";
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
    //End private functions
}
