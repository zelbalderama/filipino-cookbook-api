<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Reads JSON request bodies and handles Slim routes/errors
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Connect the API to the MySQL database
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=filipino_cookbook_api;charset=utf8mb4',
        'root',
        ''
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed.'
    ]);

    exit;
}

// Reusable function for returning data as JSON with a status code
function jsonResponse($response, $data, $status = 200)
{
    $response->getBody()->write(
        json_encode($data, JSON_PRETTY_PRINT)
    );

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

// Gets food details together with its category, origin, and ingredients
function getFoods($pdo, $where = '', $params = [])
{
    $sql = "
        SELECT
            f.food_id,
            f.food_name,
            c.category_name,
            o.origin_name,
            f.instructions,
            GROUP_CONCAT(
                i.ingredient_name
                ORDER BY i.ingredient_name
                SEPARATOR '|'
            ) AS ingredient_list
        FROM foods f
        JOIN categories c
            ON f.category_id = c.category_id
        JOIN origins o
            ON f.origin_id = o.origin_id
        LEFT JOIN food_ingredients fi
            ON f.food_id = fi.food_id
        LEFT JOIN ingredients i
            ON fi.ingredient_id = i.ingredient_id
        $where
        GROUP BY
            f.food_id,
            f.food_name,
            c.category_name,
            o.origin_name,
            f.instructions
        ORDER BY f.food_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $foods = $stmt->fetchAll();

    // Converts the combined ingredient text into a JSON array
    foreach ($foods as &$food) {
        $food['food_id'] = (int) $food['food_id'];

        if ($food['ingredient_list']) {
            $food['ingredients'] = explode('|', $food['ingredient_list']);
        } else {
            $food['ingredients'] = [];
        }

        unset($food['ingredient_list']);
    }

    return $foods;
}

// Public route: shows the welcome message without requiring a token
$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'message' => 'Welcome to the Secured Filipino Cookbook API',
        'note' => 'Use a valid Bearer token to access /api endpoints.'
    ]);
});

// Middleware: checks the Authorization header before allowing access
$responseFactory = $app->getResponseFactory();

$tokenMiddleware = function (
    Request $request,
    RequestHandler $handler
) use ($responseFactory) {
    $token = $request->getHeaderLine('Authorization');
    $validToken = 'Bearer dmmmsu-cookbook-token-2026';

    if ($token !== $validToken) {
        $response = $responseFactory->createResponse();

        return jsonResponse($response, [
            'status' => 'error',
            'message' => 'Unauthorized access. Valid API token is required.'
        ], 401);
    }

    return $handler->handle($request);
};

// All routes inside /api require the valid Bearer token
$app->group('/api', function (RouteCollectorProxy $group) use ($pdo) {

    // Returns all food records with complete details
    $group->get('/foods', function (
        Request $request,
        Response $response
    ) use ($pdo) {
        $foods = getFoods($pdo);

        return jsonResponse($response, $foods);
    });

    // Searches foods using part or all of the food name
    $group->get('/foods/search/{name}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($pdo) {
        $foods = getFoods(
            $pdo,
            'WHERE f.food_name LIKE ?',
            ['%' . $args['name'] . '%']
        );

        return jsonResponse($response, $foods);
    });

    // Returns one food record using its numeric ID
    $group->get('/foods/{id:[0-9]+}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($pdo) {
        $foods = getFoods(
            $pdo,
            'WHERE f.food_id = ?',
            [(int) $args['id']]
        );

        if (count($foods) === 0) {
            return jsonResponse($response, [
                'status' => 'error',
                'message' => 'Food not found'
            ], 404);
        }

        return jsonResponse($response, $foods[0]);
    });

    // Returns the list of available food categories
    $group->get('/categories', function (
        Request $request,
        Response $response
    ) use ($pdo) {
        $stmt = $pdo->query(
            'SELECT category_id, category_name
             FROM categories
             ORDER BY category_name'
        );

        return jsonResponse($response, $stmt->fetchAll());
    });

    // Returns the list of available ingredients
    $group->get('/ingredients', function (
        Request $request,
        Response $response
    ) use ($pdo) {
        $stmt = $pdo->query(
            'SELECT ingredient_id, ingredient_name
             FROM ingredients
             ORDER BY ingredient_name'
        );

        return jsonResponse($response, $stmt->fetchAll());
    });

    // Adds a food record and connects it to its ingredients
    $group->post('/foods', function (
        Request $request,
        Response $response
    ) use ($pdo) {
        $data = $request->getParsedBody();

        // Makes sure all required fields were included in the JSON body
        if (
            !isset($data['food_name']) ||
            !isset($data['category_id']) ||
            !isset($data['origin_id']) ||
            !isset($data['instructions']) ||
            !isset($data['ingredient_ids'])
        ) {
            return jsonResponse($response, [
                'status' => 'error',
                'message' => 'Please complete all required fields.'
            ], 400);
        }

        // ingredient_ids should be written as an array, such as [10, 15, 22]
        if (!is_array($data['ingredient_ids'])) {
            return jsonResponse($response, [
                'status' => 'error',
                'message' => 'ingredient_ids must be an array.'
            ], 400);
        }

        try {
            $pdo->beginTransaction();

            // Gets the next food ID because food_id is not AUTO_INCREMENT
            $stmt = $pdo->query(
                'SELECT COALESCE(MAX(food_id), 0) + 1 AS next_id FROM foods'
            );

            $foodId = $stmt->fetch()['next_id'];

            // Saves the main food information in the foods table
            $stmt = $pdo->prepare(
                'INSERT INTO foods
                (food_id, food_name, category_id, origin_id, instructions)
                VALUES (?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $foodId,
                $data['food_name'],
                $data['category_id'],
                $data['origin_id'],
                $data['instructions']
            ]);

            // Saves each food and ingredient connection in food_ingredients
            $ingredientStmt = $pdo->prepare(
                'INSERT INTO food_ingredients
                (food_id, ingredient_id)
                VALUES (?, ?)'
            );

            foreach ($data['ingredient_ids'] as $ingredientId) {
                $ingredientStmt->execute([
                    $foodId,
                    $ingredientId
                ]);
            }

            // Keeps all inserts only when every query succeeds
            $pdo->commit();

            return jsonResponse($response, [
                'status' => 'success',
                'message' => 'Food added successfully.'
            ], 201);

        } catch (PDOException $e) {
            // Cancels the inserts when one of the database queries fails
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return jsonResponse($response, [
                'status' => 'error',
                'message' => 'Food could not be added.'
            ], 500);
        }
    });

})->add($tokenMiddleware);

$app->run();