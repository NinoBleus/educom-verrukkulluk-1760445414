<?php


class recipeinfo {
    
    private $connection;
    
    public function __construct($connection) {
        $this->connection = $connection;
    }
    
    public function selectRecipeInfo($recipe_id, $record_type) {
        
        $sql = "select * from gerecht_info where gerecht_id = $recipe_id AND record_type = '$record_type'";
        $recipeInfo = [];

        $result = mysqli_query($this->connection, $sql);
        while ($row = mysqli_fetch_assoc($result))
            {
            if ($row['record_type'] == 'F' || $row['record_type'] == 'O')
                {
                $user = $this->selecteerUser($row['user_id']);
                $recipeInfo[] = array_merge(
                    [
                        'id'=>$row['id'],
                        'record_type'=>$row['record_type'],
                        'recipe_id'=>$row['gerecht_id'],
                        'user_id'=>$row['user_id'],
                        'create_at'=>$row['datum'],
                        'nummeriekveld'=>$row['nummeriekveld'],
                        'tekstveld'=>$row['tekstveld']
                    ],  $user);
                } else {
                    $recipeInfo[] = [
                        'id'=>$row['id'],
                        'record_type'=>$row['record_type'],
                        'recipe_id'=>$row['gerecht_id'],
                        'user_id'=>$row['user_id'],
                        'create_at'=>$row['datum'],
                        'nummeriekveld'=>$row['nummeriekveld'],
                        'tekstveld'=>$row['tekstveld']
                    ]; 
                }
                
            };
            return($recipeInfo); 
        }
        
        private function selecteerUser($user_id){
            $usr = new user($this->connection);
            $user = $usr->selecteerUser($user_id);
            return($user);
        }
        
        public function addFavoriteRecipe($user_id, $recipe_id){
            
            $sql = "insert into gerecht_info (user_id, gerecht_id, record_type) values ($user_id, $recipe_id, 'F')";
            
            $result = mysqli_query($this->connection, $sql);
            $recipeInfo = mysqli_fetch_array($result, MYSQLI_ASSOC);
            
            return $recipeInfo;
            
        }
        
        public function removeFavoriteRecipe($user_id, $recipe_id){
            
            $sql = "delete from gerecht_info where user_id = $user_id and gerecht_id = $recipe_id and record_type = 'F'";
            
            $result = mysqli_query($this->connection, $sql);
            
            return $result;
            
        }
    }
    