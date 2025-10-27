<?php


class recipe {
    
    private $connection;
    
    public function __construct($connection) {
        $this->connection = $connection;
    }
    
    public function selectOneRecipe($recipe_id) {
        
        $sql = "select * from gerecht where id = $recipe_id";
        
        $result = mysqli_query($this->connection, $sql);
        while ($row = mysqli_fetch_assoc($result))
            {
            $user = $this->selecteerUser($row['user_id']);
            $kitchen = $this->selectKitchen($row['keuken_id']);
            $type = $this->selectType($row['type_id']);
            $recipe = array_merge([
                'id' => $row['id'],
                'kitchen' => $row['keuken_id'],
                'type' => $row['type_id'],
                'title' => $row['titel'],
                'user id' => $row['user_id'],
                'short description' => $row['korte_omschrijving'],
                'long description' => $row['lange_omschrijving'],
                'date added' => $row['datum_toegevoegd'],
                'image' => $row['afbeelding']
            ], $user, $kitchen, $type);           
        };
        return($recipe); 
    }
    
    public function selectMultipleRecipes($amount) {
        $sql = "select top $amount * from gerecht";
        
        $result = mysqli_query($this->connection, $sql);
        while ($row = mysqli_fetch_assoc($result))
            {
            $user = $this->selecteerUser($row['user_id']);
            $kitchen = $this->selectKitchen($row['keuken_id']);
            $type = $this->selectType($row['type_id']);
            $recipes[] = array_merge([
                'id' => $row['id'],
                'kitchen' => $row['keuken_id'],
                'type' => $row['type_id'],
                'title' => $row['titel'],
                'user id' => $row['user_id'],
                'short description' => $row['korte_omschrijving'],
                'long description' => $row['lange_omschrijving'],
                'date added' => $row['datum_toegevoegd'],
                'image' => $row['afbeelding']
            ], $user, $kitchen, $type);
        };
        return($recipes);
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
    
    
    // Calculate total calories based on ingredients
    // This calorie calculation is a temporary placeholder, change it soon to accurate values!
    private function calcCalories($ingredients) {
        $totalCalories = 0;
        foreach ($ingredients as $ingredient) {
            $totalCalories += $ingredient['calorieen'] * $ingredient['amount'];
        }
        return $totalCalories;
    }
    
    private function calcPrice($recipe_id) {
        $ingredients = $this->selectIngredientsFromRecipe($recipe_id);
        $totalPrice = 0;
        foreach ($ingredients as $ingredient) {
            $totalPrice += $ingredient['prijs'] * $ingredient['amount'];
        }
        return $totalPrice;
        
    }
    
    private function calcRating($recipe_id) {
        $recipeInfo = new recipeinfo($this->connection);
        $recipeRating = $recipeInfo->selectRecipeInfo($recipe_id, 'W');
        $totalRating = array_sum($recipeRating['nummeriekveld'])/count($recipeRating['nummeriekveld']);
    }
    
    private function selectSteps($recipe_id) {
        $recipeInfo = new recipeinfo($this->connection);
        $recipeSteps = $recipeInfo->selectRecipeInfo($recipe_id, 'B');
        return $recipeSteps;
    }
    
    private function selectRemarks($recipe_id) {
        $recipeInfo = new recipeinfo($this->connection);
        $recipeRemarks = $recipeInfo->selectRecipeInfo($recipe_id, 'O');
        return $recipeRemarks;
    }
    
    private function selectKitchen($kitchen_id) {
        $kitchentype = new kitchentype($this->connection);
        $kitchen = $kitchentype->selecteerKitchentype($kitchen_id);
        return($kitchen);
    }
    
    private function selectType($type_id) {
        $recipetype = new kitchentype($this->connection);
        $type = $recipetype->selecteerKitchentype($type_id);
        return($type);
    }
    
    private function determineFavorite($recipe_id, $user_id) {
        $recipeInfo = new recipeinfo($this->connection);
        $favoriteInfo = $recipeInfo->selectRecipeInfo($recipe_id, 'F');
        foreach ($favoriteInfo as $favorite) {
            if ($favorite['user_id'] == $user_id) {
                return true;
                $this->addRecipe(   );
            }
        }
        return false;
    }
    
    // temp function
    
    private function addRecipe($short_description, $long_description, $kitchen_id, $type_id, $user_id, $image) {
        $eenAndereVariable = $title;
    } 
}
