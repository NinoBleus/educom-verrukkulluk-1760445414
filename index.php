<?php
// load twig
require_once("./vendor/autoload.php");
// Twig koppelen:
$loader = new \Twig\Loader\FilesystemLoader("./templates");
/// VOOR DEVELOPMENT:
$twig = new \Twig\Environment($loader, ["debug" => true ]);
$twig->addExtension(new \Twig\Extension\DebugExtension());


//Load libraries
require_once("lib/database.php");
require_once("lib/artikel.php");
require_once("lib/user.php");
require_once("lib/kitchentype.php");
require_once("lib/ingredient.php");
require_once("lib/recipeinfo.php");
require_once("lib/recipe.php");
require_once("lib/boodschappenlijst.php");

/// INIT Libraries
$db = new database();
$art = new artikel($db->getConnection());
$user = new user($db->getConnection());
$ingredient = new ingredient($db->getConnection());
$recipeInfo = new recipeinfo($db->getConnection());
$kitchentype = new kitchentype($db->getConnection());
$gerecht = new recipe($db->getConnection());
$boodschappenlijst = new boodschappenlijst($db->getConnection());

/// Get info from URL
$recipeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'homepage';
$contextExtras = [];

switch($action) {
    
    case "homepage": {
        $data = $gerecht->selectRecipe();
        $perPage = 4;
        $totalRecipes = is_array($data) ? count($data) : 0;
        $totalPages = $totalRecipes > 0 ? (int) ceil($totalRecipes / $perPage) : 1;
        $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        if ($totalRecipes > 0) {
            $currentPage = min($currentPage, $totalPages);
            $offset = ($currentPage - 1) * $perPage;
            $currentRecipes = array_slice($data, $offset, $perPage);
        } else {
            $currentPage = 1;
            $currentRecipes = [];
        }

        $recipesForView = array_map(function($recipeRow) use ($gerecht) {
            $recipeId = $recipeRow['id'] ?? null;
            $rating = null;
            $price = null;
            $calories = null;

            if ($recipeId !== null) {
                $calculatedRating = $gerecht->calcRating($recipeId);
                $rating = is_numeric($calculatedRating) ? round($calculatedRating, 1) : null;

                $calculatedPrice = $gerecht->calcPrice($recipeId);
                $price = is_numeric($calculatedPrice) ? (float) $calculatedPrice : null;

                $calculatedCalories = $gerecht->calcCalories($recipeId);
                $calories = is_numeric($calculatedCalories) ? (int) round($calculatedCalories) : null;
            }

            $title = $recipeRow['title'] ?? ($recipeRow['titel'] ?? 'Onbekend recept');
            $shortDescription = $recipeRow['short description'] ?? ($recipeRow['long description'] ?? '');
            $servings = $recipeRow['personen'] ?? ($recipeRow['aantal_personen'] ?? null);

            return array_merge($recipeRow, [
                'display_title' => $title,
                'display_description' => $shortDescription,
                'servings_total' => $servings,
                'rating' => $rating,
                'price_total' => $price,
                'calories_total' => $calories
            ]);
        }, $currentRecipes);

        $template = 'homepage.html.twig';
        $title = "homepage";
        $contextExtras = [
            "recipes" => $recipesForView,
            "currentPage" => $currentPage,
            "totalPages" => $totalRecipes > 0 ? $totalPages : 0,
            "perPage" => $perPage,
            "totalRecipes" => $totalRecipes,
            "hasPagination" => $totalRecipes > $perPage
        ];
        break;
    }
    
    case "detail": {
        $data = $gerecht->selectRecipe($recipeId);
        $template = 'detail.html.twig';
        $title = "detail pagina";
        break;
    }
}


/// Load template
$template = $twig->load($template);

/// Render template
$context = array_merge(["title" => $title, "data" => $data], $contextExtras);
echo $template->render($context);
