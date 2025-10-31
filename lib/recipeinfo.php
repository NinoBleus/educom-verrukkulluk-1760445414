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
            $userId = (int) $user_id;
            $recipeId = (int) $recipe_id;

            $checkSql = "select id from gerecht_info where user_id = $userId and gerecht_id = $recipeId and record_type = 'F' limit 1";
            $checkResult = mysqli_query($this->connection, $checkSql);
            if ($checkResult instanceof mysqli_result) {
                $exists = mysqli_fetch_assoc($checkResult);
                mysqli_free_result($checkResult);
                if ($exists) {
                    return true;
                }
            }

            $sql = "insert into gerecht_info (user_id, gerecht_id, record_type) values ($userId, $recipeId, 'F')";
            $result = mysqli_query($this->connection, $sql);

            return $result === true;
        }
        
        public function removeFavoriteRecipe($user_id, $recipe_id){
            $userId = (int) $user_id;
            $recipeId = (int) $recipe_id;
            
            $sql = "delete from gerecht_info where user_id = $userId and gerecht_id = $recipeId and record_type = 'F'";
            
            $result = mysqli_query($this->connection, $sql);
            
            return $result === true;
            
        }

        public function selectFavoritesForUser($user_id){
            $userId = (int) $user_id;

            $sql = "select gerecht_id from gerecht_info where user_id = $userId and record_type = 'F' order by datum desc, id desc";
            $result = mysqli_query($this->connection, $sql);

            $favorites = [];
            if ($result instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    if (isset($row['gerecht_id'])) {
                        $favorites[] = (int) $row['gerecht_id'];
                    }
                }
                mysqli_free_result($result);
            }

            return $favorites;
        }
    }
    
