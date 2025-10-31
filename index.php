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

$defaultUserId = 0;

/// Get info from URL
$recipeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$requestedRecipeId = null;
if (isset($_GET['recipe_id']) && $_GET['recipe_id'] !== '' && ctype_digit((string) $_GET['recipe_id'])) {
    $requestedRecipeId = (int) $_GET['recipe_id'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['recipe_id']) && $_POST['recipe_id'] !== '' && ctype_digit((string) $_POST['recipe_id'])) {
        $requestedRecipeId = (int) $_POST['recipe_id'];
    }
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    }
}
if ($recipeId === 0 && $requestedRecipeId !== null) {
    $recipeId = $requestedRecipeId;
}
$action = isset($_GET['action']) ? $_GET['action'] : 'homepage';
$action = isset($_POST['action']) ? $_POST['action'] : $action;
$contextExtras = [];
$currentSearchQuery = '';

function prepareRecipesForView(array $recipes, recipe $gerecht, $userId = null) {
    $prepared = [];

    foreach ($recipes as $recipeRow) {
        if (!is_array($recipeRow)) {
            continue;
        }

        if (!isset($recipeRow['id']) && isset($recipeRow['recipe_id'])) {
            $recipeRow['id'] = $recipeRow['recipe_id'];
        }

        $recipeId = $recipeRow['id'] ?? null;

        $rating = null;
        $price = null;
        $calories = null;
        $isFavorite = null;

        if ($recipeId !== null) {
            $calculatedRating = $gerecht->calcRating($recipeId);
            $rating = is_numeric($calculatedRating) ? round($calculatedRating, 1) : null;

            $calculatedPrice = $gerecht->calcPrice($recipeId);
            $price = is_numeric($calculatedPrice) ? (float) $calculatedPrice : null;

            $calculatedCalories = $gerecht->calcCalories($recipeId);
            $calories = is_numeric($calculatedCalories) ? (int) round($calculatedCalories) : null;

            if ($userId !== null) {
                $isFavorite = $gerecht->determineFavorite($recipeId, (int) $userId);
            }
        }

        $title = $recipeRow['title'] ?? ($recipeRow['titel'] ?? 'Onbekend recept');
        $shortDescription = $recipeRow['short description'] ?? ($recipeRow['long description'] ?? ($recipeRow['korte_omschrijving'] ?? ($recipeRow['lange_omschrijving'] ?? '')));
        $servingsRaw = $recipeRow['personen'] ?? ($recipeRow['aantal_personen'] ?? null);
        $servingsCount = (is_numeric($servingsRaw) && (int) $servingsRaw > 0) ? (int) $servingsRaw : 4;
        $caloriesPerServing = ($calories !== null && $servingsCount > 0)
            ? (int) round($calories / $servingsCount)
            : null;

        $favoriteFlag = null;
        if ($isFavorite !== null) {
            $favoriteFlag = (bool) $isFavorite;
        }

        $prepared[] = array_merge($recipeRow, [
            'display_title' => $title,
            'display_description' => $shortDescription,
            'servings_total' => $servingsCount,
            'rating' => $rating,
            'price_total' => $price,
            'calories_total' => $calories,
            'calories_per_serving' => $caloriesPerServing,
            'is_favorite' => $favoriteFlag
        ]);
    }

    return $prepared;
}

switch($action) {
    
    case "homepage": {
        $connection = $db->getConnection();
        $idResult = mysqli_query($connection, "SELECT id FROM gerecht ORDER BY id ASC");
        $allRecipeIds = [];
        if ($idResult) {
            while ($row = mysqli_fetch_assoc($idResult)) {
                if (isset($row['id'])) {
                    $allRecipeIds[] = (int) $row['id'];
                }
            }
            mysqli_free_result($idResult);
        }

        $perPage = 4;
        $totalRecipes = count($allRecipeIds);
        $totalPages = $totalRecipes > 0 ? (int) ceil($totalRecipes / $perPage) : 1;
        $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        if ($totalRecipes > 0) {
            $currentPage = min($currentPage, $totalPages);
            $offset = ($currentPage - 1) * $perPage;
            $pageRecipeIds = array_slice($allRecipeIds, $offset, $perPage);
        } else {
            $currentPage = 1;
            $pageRecipeIds = [];
        }

        $currentRecipes = [];
        foreach ($pageRecipeIds as $id) {
            $recipeData = $gerecht->selectRecipe($id);
            if (is_array($recipeData)) {
                $recipeData['id'] = $id;
                $currentRecipes[] = $recipeData;
            }
        }

        $recipesForView = prepareRecipesForView($currentRecipes, $gerecht, $defaultUserId);
        $data = $currentRecipes;

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
    
    case "search": {
        $template = 'search.html.twig';
        $title = "zoekresultaten";
        $query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $currentSearchQuery = $query;

        $allRecipes = $gerecht->selectRecipe();
        $matchedRecipes = [];

        if (is_array($allRecipes)) {
            if ($query === '') {
                $matchedRecipes = $allRecipes;
            } else {
                foreach ($allRecipes as $recipeRow) {
                    if (!is_array($recipeRow)) {
                        continue;
                    }

                    $candidateTitle = $recipeRow['title'] ?? ($recipeRow['titel'] ?? '');
                    if ($candidateTitle === '') {
                        continue;
                    }

                    if (stripos($candidateTitle, $query) !== false) {
                        if (!isset($recipeRow['id']) && isset($recipeRow['recipe_id'])) {
                            $recipeRow['id'] = $recipeRow['recipe_id'];
                        }
                        $matchedRecipes[] = $recipeRow;
                    }
                }
            }
        }

        $recipesForView = prepareRecipesForView($matchedRecipes, $gerecht, $defaultUserId);
        $data = $matchedRecipes;

        $contextExtras = [
            'recipes' => $recipesForView,
            'query' => $query,
            'hasQuery' => $query !== '',
            'resultCount' => count($recipesForView),
            'searchQuery' => $query,
            'isShowingAllRecipes' => $query === ''
        ];
        break;
    }

    case "search_suggestions": {
        $query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $limitRaw = isset($_GET['limit']) ? $_GET['limit'] : null;
        $limit = (is_numeric($limitRaw) && (int) $limitRaw > 0) ? (int) $limitRaw : 10;

        $suggestions = [];
        if ($query !== '') {
            $allRecipes = $gerecht->selectRecipe();
            if (is_array($allRecipes)) {
                foreach ($allRecipes as $recipeRow) {
                    if (!is_array($recipeRow)) {
                        continue;
                    }

                    $candidateTitle = $recipeRow['title'] ?? ($recipeRow['titel'] ?? '');
                    if ($candidateTitle === '') {
                        continue;
                    }

                    if (stripos($candidateTitle, $query) === false) {
                        continue;
                    }

                    $recipeIdSuggestion = $recipeRow['id'] ?? ($recipeRow['recipe_id'] ?? null);
                    if ($recipeIdSuggestion === null) {
                        continue;
                    }

                    $suggestions[] = [
                        'id' => (int) $recipeIdSuggestion,
                        'title' => $candidateTitle
                    ];

                    if (count($suggestions) >= $limit) {
                        break;
                    }
                }
            }
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
        exit;
    }

    case "favorites": {
        $template = 'favorites.html.twig';
        $title = "mijn favorieten";
        $userId = $defaultUserId;

        $favoriteRecipeIds = $recipeInfo->selectFavoritesForUser($userId);
        $favoriteRecipes = [];

        if (is_array($favoriteRecipeIds)) {
            foreach ($favoriteRecipeIds as $favoriteRecipeId) {
                if (!is_numeric($favoriteRecipeId)) {
                    continue;
                }
                $favoriteRecipeId = (int) $favoriteRecipeId;
                if ($favoriteRecipeId < 0) {
                    continue;
                }

                $recipeData = $gerecht->selectRecipe($favoriteRecipeId);
                if (is_array($recipeData)) {
                    $recipeData['id'] = $favoriteRecipeId;
                    $favoriteRecipes[] = $recipeData;
                }
            }
        }

        $recipesForView = prepareRecipesForView($favoriteRecipes, $gerecht, $userId);
        $data = $favoriteRecipes;

        $contextExtras = [
            'recipes' => $recipesForView,
            'favoritesCount' => count($recipesForView),
            'hasFavorites' => !empty($recipesForView)
        ];
        break;
    }

    case "toggle_favorite": {
        $userId = $defaultUserId;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(405);
            }
            echo json_encode([
                'success' => false,
                'message' => 'Alleen POST aanvragen zijn toegestaan.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rawRecipeId = $_POST['recipe_id'] ?? null;
        $favoriteParam = $_POST['favorite'] ?? null;

        if ($rawRecipeId === null || !ctype_digit((string) $rawRecipeId)) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
            }
            echo json_encode([
                'success' => false,
                'message' => 'Ongeldig recept opgegeven.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $recipeIdToToggle = (int) $rawRecipeId;
        if ($recipeIdToToggle < 0) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
            }
            echo json_encode([
                'success' => false,
                'message' => 'Recept-ID moet positief zijn.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $shouldFavorite = in_array(strtolower((string) $favoriteParam), ['1', 'true', 'yes', 'on'], true);

        $existingRecipe = $gerecht->selectRecipe($recipeIdToToggle);
        if (!is_array($existingRecipe) || empty($existingRecipe)) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(404);
            }
            echo json_encode([
                'success' => false,
                'message' => 'Recept niet gevonden.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $dbResult = $shouldFavorite
            ? $recipeInfo->addFavoriteRecipe($userId, $recipeIdToToggle)
            : $recipeInfo->removeFavoriteRecipe($userId, $recipeIdToToggle);

        $isFavorite = $gerecht->determineFavorite($recipeIdToToggle, $userId);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success' => (bool) $dbResult,
            'isFavorite' => (bool) $isFavorite
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    case "detail": {
        $data = $gerecht->selectRecipe($recipeId);
        $template = 'detail.html.twig';
        $title = "detail pagina";
        $recipeDetail = null;
        $recipeIngredients = [];
        $recipeSteps = [];
        $recipeComments = [];
        $ratingNotice = null;

        if (is_array($data) && !empty($data)) {
            $actualRecipeId = $recipeId;

            $calculatedRating = $gerecht->calcRating($actualRecipeId);
            $ratingValue = is_numeric($calculatedRating) ? round($calculatedRating, 1) : null;
            $ratingNotice = is_numeric($calculatedRating) ? null : $calculatedRating;

            $calculatedPrice = $gerecht->calcPrice($actualRecipeId);
            $priceValue = is_numeric($calculatedPrice) ? (float) $calculatedPrice : null;

            $calculatedCalories = $gerecht->calcCalories($actualRecipeId);
            $caloriesValue = is_numeric($calculatedCalories) ? (int) round($calculatedCalories) : null;

            $isFavorite = $gerecht->determineFavorite($actualRecipeId, $defaultUserId);

            $imageFile = $data['image'] ?? '';
            $resolvedImage = $imageFile;
            if (empty($resolvedImage)) {
                $resolvedImage = 'assets/img/logo-v2.png';
            } elseif (strpos($resolvedImage, 'http') === 0) {
                // leave as-is
            } elseif (strpos($resolvedImage, 'assets/') === 0) {
                // already relative to assets
            } else {
                $resolvedImage = 'assets/img/' . ltrim($resolvedImage, '/');
            }

            $servingsValue = $data['servings_total'] ?? ($data['personen'] ?? ($data['aantal_personen'] ?? 4));
            $servingsCount = (is_numeric($servingsValue) && (int) $servingsValue > 0)
                ? (int) $servingsValue
                : 4;
            $caloriesPerServing = ($caloriesValue !== null && $servingsCount > 0)
                ? (int) round($caloriesValue / $servingsCount)
                : null;

            $kitchenLabel = null;
            if (array_key_exists('kitchen', $data) && $data['kitchen'] !== null && $data['kitchen'] !== '') {
                $kitchenData = $gerecht->selectKitchen($data['kitchen']);
                $kitchenLabel = $kitchenData['omschrijving'] ?? ($kitchenData['naam'] ?? ($kitchenData['title'] ?? null));
            }

            $typeLabel = null;
            if (array_key_exists('type', $data) && $data['type'] !== null && $data['type'] !== '') {
                $typeData = $gerecht->selectType($data['type']);
                $typeLabel = $typeData['omschrijving'] ?? ($typeData['naam'] ?? ($typeData['title'] ?? null));
            }

            $recipeDetail = array_merge($data, [
                'id' => $actualRecipeId,
                'display_title' => $data['title'] ?? ($data['titel'] ?? 'Onbekend recept'),
                'display_short_description' => $data['short description'] ?? ($data['korte_omschrijving'] ?? ''),
                'display_long_description' => $data['long description'] ?? ($data['lange_omschrijving'] ?? ''),
                'image_resolved' => $resolvedImage,
                'rating_value' => $ratingValue,
                'rating_notice' => $ratingNotice,
                'price_total' => $priceValue,
                'calories_total' => $caloriesValue,
                'calories_per_serving' => $caloriesPerServing,
                'servings_total' => $servingsCount,
                'kitchen_label' => $kitchenLabel,
                'type_label' => $typeLabel,
                'is_favorite' => (bool) $isFavorite
            ]);
            $data = $recipeDetail;
        }

        $rawIngredients = [];
        $rawSteps = [];
        $rawComments = [];
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (!is_int($key) || !is_array($value)) {
                    continue;
                }
                $recordType = $value['record_type'] ?? null;
                if ($recordType === 'B') {
                    $rawSteps[] = $value;
                    continue;
                }
                if ($recordType === 'O') {
                    $rawComments[] = $value;
                    continue;
                }
                if (array_key_exists('artikel_id', $value) || array_key_exists('amount', $value)) {
                    $rawIngredients[] = $value;
                }
            }
        }

        $recipeIdForRelations = $recipeDetail['id'] ?? $recipeId;

        if (empty($rawIngredients) && $recipeIdForRelations !== null) {
            $rawIngredients = $ingredient->selecteerIngredientsFromRecipe($recipeIdForRelations);
        }
        if (empty($rawSteps) && $recipeIdForRelations !== null) {
            $rawSteps = $gerecht->selectSteps($recipeIdForRelations);
        }
        if (empty($rawComments) && $recipeIdForRelations !== null) {
            $rawComments = $gerecht->selectRemarks($recipeIdForRelations);
        }

        if (is_array($rawIngredients)) {
            foreach ($rawIngredients as $ingredientRow) {
                if (!is_array($ingredientRow)) {
                    continue;
                }

                $ingredientName = $ingredientRow['naam'] ?? ($ingredientRow['name'] ?? ($ingredientRow['titel'] ?? 'Onbekend ingredient'));
                $ingredientDescription = $ingredientRow['korte_omschrijving'] ?? ($ingredientRow['omschrijving'] ?? null);
                $amountRaw = $ingredientRow['amount'] ?? null;
                $unitRaw = $ingredientRow['eenheid'] ?? ($ingredientRow['unit'] ?? ($ingredientRow['verpakking'] ?? ($ingredientRow['verpakking_eenheid'] ?? null)));

                $amountDisplay = null;
                if ($amountRaw !== null && $amountRaw !== '') {
                    if (is_numeric($amountRaw)) {
                        $amountNumeric = (float) $amountRaw;
                        $amountDisplay = fmod($amountNumeric, 1) === 0.0
                            ? (string) (int) $amountNumeric
                            : rtrim(rtrim(number_format($amountNumeric, 2, ',', ''), '0'), ',');
                    } else {
                        $amountDisplay = (string) $amountRaw;
                    }
                }

                $combinedAmount = null;
                if ($amountDisplay !== null && $unitRaw) {
                    $combinedAmount = trim($amountDisplay . ' ' . $unitRaw);
                } elseif ($amountDisplay !== null) {
                    $combinedAmount = $amountDisplay;
                } elseif ($unitRaw) {
                    $combinedAmount = (string) $unitRaw;
                }

                $ingredientImage = $ingredientRow['afbeelding'] ?? ($ingredientRow['image'] ?? ($ingredientRow['img'] ?? null));
                if (!empty($ingredientImage)) {
                    if (strpos($ingredientImage, 'http') === 0) {
                        $resolvedIngredientImage = $ingredientImage;
                    } elseif (strpos($ingredientImage, 'assets/') === 0 || strpos($ingredientImage, 'uploads/') === 0) {
                        $resolvedIngredientImage = $ingredientImage;
                    } else {
                        $resolvedIngredientImage = 'assets/img/' . ltrim($ingredientImage, '/');
                    }
                } else {
                    $resolvedIngredientImage = 'assets/img/logo-v2.png';
                }

                $recipeIngredients[] = [
                    'id' => $ingredientRow['id'] ?? null,
                    'name' => $ingredientName,
                    'description' => $ingredientDescription,
                    'amount_display' => $combinedAmount,
                    'amount' => $amountDisplay,
                    'unit' => $unitRaw,
                    'image' => $resolvedIngredientImage,
                    'article' => [
                        'brand' => $ingredientRow['merk'] ?? ($ingredientRow['brand'] ?? null),
                        'category' => $ingredientRow['categorie'] ?? ($ingredientRow['type'] ?? null)
                    ]
                ];
            }
        }

        if (is_array($rawSteps)) {
            foreach ($rawSteps as $stepRow) {
                if (!is_array($stepRow)) {
                    continue;
                }
                $stepOrder = $stepRow['nummeriekveld'] ?? null;
                $recipeSteps[] = [
                    'order' => is_numeric($stepOrder) ? (int) $stepOrder : null,
                    'body' => $stepRow['tekstveld'] ?? '',
                    'created_at' => $stepRow['create_at'] ?? null
                ];
            }
        }
        if (!empty($recipeSteps)) {
            usort($recipeSteps, function ($a, $b) {
                return ($a['order'] ?? PHP_INT_MAX) <=> ($b['order'] ?? PHP_INT_MAX);
            });
        }

        if (is_array($rawComments)) {
            foreach ($rawComments as $commentRow) {
                if (!is_array($commentRow)) {
                    continue;
                }
                $firstName = $commentRow['voornaam'] ?? ($commentRow['firstname'] ?? null);
                $lastName = $commentRow['achternaam'] ?? ($commentRow['lastname'] ?? null);
                $displayName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
                if ($displayName === '') {
                    $displayName = $commentRow['naam'] ?? ($commentRow['username'] ?? ($commentRow['user_name'] ?? 'Anonieme kok'));
                }

                $recipeComments[] = [
                    'author' => $displayName,
                    'message' => $commentRow['tekstveld'] ?? '',
                    'created_at' => $commentRow['create_at'] ?? null,
                    'user_id' => $commentRow['user_id'] ?? null
                ];
            }
        }

        $contextExtras = [
            'recipeDetail' => $recipeDetail,
            'recipeIngredients' => $recipeIngredients,
            'recipeSteps' => $recipeSteps,
            'recipeComments' => $recipeComments
        ];
        break;
    }

    case "shoppinglist": {
        $template = 'shoppinglist.html.twig';
        $title = "boodschappenlijst";
        $userId = 0;

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($isPost && isset($_POST['remove_article_id'])) {
            $articleToRemove = (int) $_POST['remove_article_id'];
            if ($articleToRemove >= 0) {
                $boodschappenlijst->verwijderArtikel($articleToRemove, $userId);
                if (!headers_sent()) {
                    $redirectUrl = '?action=shoppinglist&removed=' . $articleToRemove;
                    header("Location: $redirectUrl");
                    exit;
                }
            }
        }

        $recipeIdToAdd = null;
        $addedRecipeName = null;
        if ($isPost && isset($_POST['recipe_id']) && $_POST['recipe_id'] !== '') {
            $rawRecipeId = $_POST['recipe_id'];
            if (ctype_digit((string) $rawRecipeId)) {
                $recipeIdToAdd = (int) $rawRecipeId;
            }
        } elseif (!$isPost && isset($_GET['recipe_id'])) {
            $rawRecipeId = $_GET['recipe_id'];
            if (ctype_digit((string) $rawRecipeId)) {
                $recipeIdToAdd = (int) $rawRecipeId;
            }
        } elseif ($requestedRecipeId !== null) {
            $recipeIdToAdd = $requestedRecipeId;
        } elseif (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
            $recipeIdToAdd = (int) $_GET['id'];
        }

        if ($recipeIdToAdd !== null) {
            $selectedRecipe = $gerecht->selectRecipe($recipeIdToAdd);
            if (is_array($selectedRecipe)) {
                $addedRecipeName = $selectedRecipe['title'] ?? ($selectedRecipe['titel'] ?? ($selectedRecipe['display_title'] ?? null));
            }

            $boodschappenlijst->boodschappenToevoegen($recipeIdToAdd, $userId);

            if (!headers_sent()) {
                $redirectParts = ['action=shoppinglist', 'added=' . $recipeIdToAdd];
                if (!empty($addedRecipeName)) {
                    $redirectParts[] = 'addedName=' . rawurlencode($addedRecipeName);
                }
                $targetUrl = '?' . implode('&', $redirectParts);
                header("Location: $targetUrl");
                exit;
            }
        }

        $rawShoppingItems = $boodschappenlijst->selecteerBoodschappenLijst($userId);
        $shoppingItems = [];
        $grandTotalCents = 0;

        if (is_array($rawShoppingItems)) {
            foreach ($rawShoppingItems as $itemRow) {
                if (!is_array($itemRow)) {
                    continue;
                }

                $amountValue = isset($itemRow['amount']) ? (int) $itemRow['amount'] : 0;
                if ($amountValue <= 0) {
                    $amountValue = 1;
                }

                $articleName = $itemRow['naam'] ?? ($itemRow['name'] ?? ($itemRow['titel'] ?? 'Onbekend artikel'));
                $articleDescription = $itemRow['omschrijving'] ?? ($itemRow['korte_omschrijving'] ?? ($itemRow['beschrijving'] ?? null));

                $priceRaw = $itemRow['prijs'] ?? ($itemRow['price'] ?? null);
                $unitPriceCents = 0;
                if (is_numeric($priceRaw)) {
                    $priceFloat = (float) $priceRaw;
                    $unitPriceCents = (fmod($priceFloat, 1.0) === 0.0)
                        ? (int) $priceFloat
                        : (int) round($priceFloat * 100);
                }

                $lineTotalCents = $unitPriceCents * $amountValue;
                $grandTotalCents += $lineTotalCents;

                $articleImage = $itemRow['afbeelding'] ?? ($itemRow['image'] ?? ($itemRow['img'] ?? null));
                if (!empty($articleImage)) {
                    if (strpos($articleImage, 'http') === 0) {
                        $resolvedArticleImage = $articleImage;
                    } elseif (strpos($articleImage, 'assets/') === 0 || strpos($articleImage, 'uploads/') === 0) {
                        $resolvedArticleImage = $articleImage;
                    } else {
                        $resolvedArticleImage = 'assets/img/' . ltrim($articleImage, '/');
                    }
                } else {
                    $resolvedArticleImage = 'assets/img/logo-v2.png';
                }

                $packaging = $itemRow['verpakking'] ?? ($itemRow['verpakking_eenheid'] ?? ($itemRow['unit'] ?? null));

                $shoppingItems[] = [
                    'id' => $itemRow['id'] ?? null,
                    'article_id' => $itemRow['article_id'] ?? null,
                    'name' => $articleName,
                    'description' => $articleDescription,
                    'amount' => $amountValue,
                    'image' => $resolvedArticleImage,
                    'unit_price_cents' => $unitPriceCents,
                    'unit_price_display' => number_format($unitPriceCents / 100, 2, ',', '.'),
                    'line_total_cents' => $lineTotalCents,
                    'line_total_display' => number_format($lineTotalCents / 100, 2, ',', '.'),
                    'packaging' => $packaging
                ];
            }
        }

        $addedRecipeName = isset($_GET['addedName']) ? rawurldecode((string) $_GET['addedName']) : null;
        $addedRecipeId = isset($_GET['added']) ? (int) $_GET['added'] : null;
        $removedItemId = isset($_GET['removed']) ? (int) $_GET['removed'] : null;

        $contextExtras = [
            'shoppingItems' => $shoppingItems,
            'shoppingCount' => count($shoppingItems),
            'shoppingTotalCents' => $grandTotalCents,
            'shoppingTotalDisplay' => number_format($grandTotalCents / 100, 2, ',', '.'),
            'addedRecipeName' => $addedRecipeName,
            'addedRecipeId' => $addedRecipeId,
            'removedItemId' => $removedItemId,
            'hasShoppingItems' => !empty($shoppingItems)
        ];
        $data = $shoppingItems;
        break;
    }
}


/// Load template
$template = $twig->load($template);

/// Render template
$contextExtras = is_array($contextExtras) ? $contextExtras : [];
if (!array_key_exists('searchQuery', $contextExtras)) {
    $contextExtras['searchQuery'] = $currentSearchQuery;
}
$contextExtras['favoriteUserId'] = $contextExtras['favoriteUserId'] ?? $defaultUserId;
$context = array_merge(["title" => $title, "data" => $data], $contextExtras);
echo $template->render($context);
