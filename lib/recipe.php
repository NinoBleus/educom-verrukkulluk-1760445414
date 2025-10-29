<?php


class recipe {
    
    private $connection;
    
    public function __construct($connection) {
        $this->connection = $connection;
    }
    
    public function selectRecipe($recipe_id = null) {
        
        if ($recipe_id === null) {
            $sql = "select * from gerecht";
        } else {
            $sql = "select * from gerecht where id = $recipe_id";
        }
        
        $result = mysqli_query($this->connection, $sql);
        $recipes = []; 
        while ($row = mysqli_fetch_assoc($result))
            {
            $user = $this->selecteerUser($row['user_id']);
            $kitchen = $this->selectKitchen($row['keuken_id']);
            $type = $this->selectType($row['type_id']);
            $article = $this->selectIngredientsFromRecipe($row['id']);
            $steps = $this->selectSteps($row['id']);
            $recipeRemarks = $this->selectRemarks($row['id']);

            $recipeDetails = array_merge([
                'recipe_id' => $row['id'],
                'kitchen' => $row['keuken_id'],
                'type' => $row['type_id'],
                'title' => $row['titel'],
                'user id' => $row['user_id'],
                'short description' => $row['korte_omschrijving'],
                'long description' => $row['lange_omschrijving'],
                'date added' => $row['datum_toegevoegd'],
                'image' => $row['afbeelding']
            ], $user, $kitchen, $type, $article, $steps, $recipeRemarks);

            $recipeDetails['id'] = $row['id'];
            $recipeDetails['recipe_id'] = $row['id'];

            $recipes[] = $recipeDetails;
        };
        
        return ($recipe_id === null) ? $recipes : $recipes[0]; 
    }
    
    private function selecteerUser($user_id){
        $usr = new user($this->connection);
        $user = $usr->selecteerUser($user_id);
        return($user);
    }
    
    private function selectIngredientsFromRecipe($recipe_id) {
        $ing = new ingredient($this->connection);
        $ingredients = $ing->selecteerIngredientsFromRecipe($recipe_id);
        return($ingredients);
    }
    
    public function calcCalories($recipe_id) {
        $ingredients = $this->selectIngredientsFromRecipe($recipe_id);
        $totalCalories = 0;
        
        foreach ($ingredients as $item) {
            if (is_array($item)) {
                if (isset($item['calorie_per_100']) && isset($item['verpakking_gewicht'])) {
                    $calories = ($item['calorie_per_100'] / 100) * $item['verpakking_gewicht'];
                    $totalCalories += $calories;
                }
            }
        }
        
        return $totalCalories;
    }
    
    
    public function calcPrice($recipe_id) {
        $ingredients = $this->selectIngredientsFromRecipe($recipe_id);
        $totalPrice = 0;
        foreach ($ingredients as $ingredient) {
            $totalPrice += $ingredient['prijs'] * $ingredient['amount'];
        }
        return $totalPrice;
        
    }
    
    public function calcRating($recipe_id) {
        $recipeInfo = new recipeinfo($this->connection);
        $recipeRating = $recipeInfo->selectRecipeInfo($recipe_id, 'W');
        
        $ratings = array_column($recipeRating, 'nummeriekveld');
        
        if (empty($ratings)) {
            return "Wie deelt door nul, is een snul!";
        }
        
        $totalRating = array_sum($ratings) / count($ratings);
        return $totalRating;
    }
    
    public function selectSteps($recipe_id) {
        $recipeInfo = new recipeinfo($this->connection);
        $recipeSteps = $recipeInfo->selectRecipeInfo($recipe_id, 'B');
        return $recipeSteps;
    }
    
    public function selectRemarks($recipe_id) {
        $recipeInfo = new recipeinfo($this->connection);
        $recipeRemarks = $recipeInfo->selectRecipeInfo($recipe_id, 'O');
        return $recipeRemarks;
    }
    
    public function selectKitchen($kitchen_id) {
        $kitchentype = new kitchentype($this->connection);
        $kitchen = $kitchentype->selecteerKitchentype($kitchen_id);
        return($kitchen);
    }
    
    public function selectType($type_id) {
        $recipetype = new kitchentype($this->connection);
        $type = $recipetype->selecteerKitchentype($type_id);
        return($type);
    }
    
    public function determineFavorite($recipe_id, $user_id) {
        $recipeInfo = new recipeinfo($this->connection);
        $favoriteInfo = $recipeInfo->selectRecipeInfo($recipe_id, 'F');
        foreach ($favoriteInfo as $favorite) {
            if ($favorite['user_id'] == $user_id) {
                return true;
            }
        }
        return false;
    }
}
