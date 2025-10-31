<?php

require_once("lib/artikel.php");

class ingredient {
    
    private $connection;
    
    public function __construct($connection) {
        $this->connection = $connection;
    }
    
    public function selecteerIngredientsFromRecipe($recipe_id) {
        
        $recipe_id = (int) $recipe_id;
        $sql = "select * from ingredient where gerecht_id = $recipe_id";
        
        $result = mysqli_query($this->connection, $sql);
        
        $ingredients = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $artikelId = isset($row['artikel_id']) ? (int) $row['artikel_id'] : null;
            $article = $artikelId !== null ? $this->selecteerIngredientArticle($artikelId) : [];
            $ingredients[] = array_merge(
                [
                    'id' => $row['id'],
                    'gerecht_id' => $row['gerecht_id'],
                    'artikel_id' => $artikelId,
                    'amount' => $row['aantal']
                ],
                $article);
            }
            
            return $ingredients;
            
        }
        
        
        private function selecteerIngredientArticle($artikel_id){
            $art = new artikel($this->connection);
            $ingredientsArticles = $art->selecteerArtikel($artikel_id);
            return($ingredientsArticles);
        }
    }
    
