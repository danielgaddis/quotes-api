<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = preg_replace('#^/index\.php#', '', $path);
$path = rtrim($path, '/');

if ($path === '') {
    $path = '/';
}

$queryParams = $_GET;

try {
    switch ($path) {
        case '/quotes':
            handleQuotes($method, $queryParams, $pdo);
            break;

        case '/authors':
            handleAuthors($method, $queryParams, $pdo);
            break;

        case '/categories':
            handleCategories($method, $queryParams, $pdo);
            break;

        default:
            respond([
                'message' => 'Route Not Found'
            ], 404);
    }
} catch (Throwable $e) {
    respond([
        'message' => 'Server Error',
        'error' => $e->getMessage()
    ], 500);
}

function handleQuotes(string $method, array $queryParams, PDO $pdo): void
{
    if ($method === 'GET') 
    {
        $sql = '
            SELECT 
                q.id,
                q.quote,
                a.author,
                c.category
            FROM quotes q
            JOIN authors a ON q.author_id = a.id
            JOIN categories c ON q.category_id = c.id
        ';
        $params = [];
        $conditions = [];

        if (isset($queryParams['id'])) {
            $conditions[] = 'q.id = ?';
            $params[] = $queryParams['id'];
        }
        if (isset($queryParams['author_id'])) {
            $conditions[] = 'q.author_id = ?';
            $params[] = $queryParams['author_id'];
        }
        if (isset($queryParams['category_id'])) {
            $conditions[] = 'q.category_id = ?';
            $params[] = $queryParams['category_id'];
        }

        if ($conditions) 
        {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($quotes)) {
            respond(['message' => 'No Quotes Found'], 404);
        }

        if (isset($queryParams['id'])) {
            respond($quotes[0]);
        }

        respond($quotes);
    }

    if ($method === 'POST') {
        $input = getInputData();

        if (
            !isset($input['quote']) ||
            !isset($input['author_id']) ||
            !isset($input['category_id']) ||
            trim((string)$input['quote']) === ''
        ) {
            respond(['message' => 'Missing Required Parameters'], 400);
        }

        $authorCheck = $pdo->prepare('SELECT id FROM authors WHERE id = ?');
        $authorCheck->execute([$input['author_id']]);
        if (!$authorCheck->fetch()) {
            respond(['message' => 'author_id Not Found'], 404);
        }

        $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $categoryCheck->execute([$input['category_id']]);
        if (!$categoryCheck->fetch()) {
            respond(['message' => 'category_id Not Found'], 404);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO quotes (quote, author_id, category_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $input['quote'],
            $input['author_id'],
            $input['category_id']
        ]);

        $id = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('
            SELECT id, quote, author_id, category_id
            FROM quotes
            WHERE id = ?
        ');
        $stmt->execute([$id]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);

        respond($quote, 201);
    }

    if ($method === 'PUT') {
        $input = getInputData();

        if (
            !isset($input['id']) ||
            !isset($input['quote']) ||
            !isset($input['author_id']) ||
            !isset($input['category_id']) ||
            trim((string)$input['quote']) === ''
        ) {
            respond(['message' => 'Missing Required Parameters'], 400);
        }

        $quoteCheck = $pdo->prepare('SELECT id FROM quotes WHERE id = ?');
        $quoteCheck->execute([$input['id']]);
        if (!$quoteCheck->fetch()) {
            respond(['message' => 'No Quotes Found'], 404);
        }

        $authorCheck = $pdo->prepare('SELECT id FROM authors WHERE id = ?');
        $authorCheck->execute([$input['author_id']]);
        if (!$authorCheck->fetch()) {
            respond(['message' => 'author_id Not Found'], 404);
        }

        $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $categoryCheck->execute([$input['category_id']]);
        if (!$categoryCheck->fetch()) {
            respond(['message' => 'category_id Not Found'], 404);
        }

        $stmt = $pdo->prepare(
            'UPDATE quotes SET quote = ?, author_id = ?, category_id = ? WHERE id = ?'
        );
        $stmt->execute([
            $input['quote'],
            $input['author_id'],
            $input['category_id'],
            $input['id']
        ]);

        $stmt = $pdo->prepare('
            SELECT id, quote, author_id, category_id
            FROM quotes
            WHERE id = ?
        ');
        $stmt->execute([$input['id']]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);

        respond($quote);
    }

    if ($method === 'DELETE') 
    {
        $input = getInputData();

        if (!isset($input['id'])) {
            respond(['message' => 'Missing Required Parameters'], 400);
        }

        $stmt = $pdo->prepare('SELECT id FROM quotes WHERE id = ?');
        $stmt->execute([$input['id']]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quote) {
            respond(['message' => 'No Quotes Found'], 404);
        }

        $stmt = $pdo->prepare('DELETE FROM quotes WHERE id = ?');
        $stmt->execute([$input['id']]);

        respond(['id' => (int)$input['id']]);
    }
}

function handleAuthors(string $method, array $queryParams, PDO $pdo): void
{
    if ($method === 'GET') 
    {
        $sql = 'SELECT id, author FROM authors';
        $params = [];

        if (isset($queryParams['id'])) {
            $sql .= ' WHERE id = ?';
            $params[] = $queryParams['id'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($authors)) {
            respond(['message' => 'author_id Not Found'], 404);
        }

        if (isset($queryParams['id'])) {
            respond($authors[0]);
        }

        respond($authors);
    }

    if ($method === 'POST') 
    {
        $input = getInputData();

        if (
            !isset($input['author']) ||
            trim((string)$input['author']) === ''
        ) {
            respond(['message' => 'Missing Required Parameters'], 400);
        }

        $stmt = $pdo->prepare('INSERT INTO authors (author) VALUES (?)');
        $stmt->execute([$input['author']]);

        $id = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT id, author FROM authors WHERE id = ?');
        $stmt->execute([$id]);
        $author = $stmt->fetch(PDO::FETCH_ASSOC);

        respond($author, 201);
    }

    if ($method === 'PUT') 
    {
        $input = getInputData();

        if (!isset($input['id'])) {
            respond(['message' => 'author_id Not Found'], 404);
        }

        if (
            !isset($input['author']) ||
            trim((string)$input['author']) === ''
        ) {
            respond(['message' => 'Missing Required Parameters'], 400);
        }

        $stmt = $pdo->prepare('SELECT id FROM authors WHERE id = ?');
        $stmt->execute([$input['id']]);
        $existingAuthor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingAuthor) {
            respond(['message' => 'author_id Not Found'], 404);
        }

        $stmt = $pdo->prepare('UPDATE authors SET author = ? WHERE id = ?');
        $stmt->execute([
            $input['author'],
            $input['id']
        ]);

        $stmt = $pdo->prepare('SELECT id, author FROM authors WHERE id = ?');
        $stmt->execute([$input['id']]);
        $author = $stmt->fetch(PDO::FETCH_ASSOC);

        respond($author);
    }

    if ($method === 'DELETE') 
    {
        $input = getInputData();

        if (!isset($input['id'])) {
            respond(['message' => 'author_id Not Found'], 404);
        }

        $stmt = $pdo->prepare('SELECT id FROM authors WHERE id = ?');
        $stmt->execute([$input['id']]);
        $author = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$author) {
            respond(['message' => 'author_id Not Found'], 404);
        }

        $stmt = $pdo->prepare('DELETE FROM authors WHERE id = ?');
        $stmt->execute([$input['id']]);

        respond(['id' => (int)$input['id']]);
    }
}

function handleCategories(string $method, array $queryParams, PDO $pdo): void
{
    if ($method === 'GET') 
    {
        $sql = 'SELECT id, category FROM categories';
        $params = [];

        if (isset($queryParams['id'])) {
            $sql .= ' WHERE id = ?';
            $params[] = $queryParams['id'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($categories)) {
            respond(['message' => 'category_id Not Found'], 404);
        }

        if (isset($queryParams['id'])) {
            respond($categories[0]);
        }

        respond($categories);
    }

    if ($method === 'POST') 
    {
        $input = getInputData();

        if (
            !isset($input['category']) ||
            trim((string)$input['category']) === ''
        ) {
            respond(['message' => 'Missing Required Parameters'], 400);
        }

        $stmt = $pdo->prepare('INSERT INTO categories (category) VALUES (?)');
        $stmt->execute([$input['category']]);

        $id = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT id, category FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        respond($category, 201);
    }

    if ($method === 'PUT') 
    {
        $input = getInputData();

        if (!isset($input['id'])) {
            respond(['message' => 'category_id Not Found'], 404);
        }

        if (
            !isset($input['category']) ||
            trim((string)$input['category']) === ''
        ) {
            respond(['message' => 'Missing Required Parameters'], 400);
        }

        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $stmt->execute([$input['id']]);
        $existingCategory = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingCategory) {
            respond(['message' => 'category_id Not Found'], 404);
        }

        $stmt = $pdo->prepare('UPDATE categories SET category = ? WHERE id = ?');
        $stmt->execute([
            $input['category'],
            $input['id']
        ]);

        $stmt = $pdo->prepare('SELECT id, category FROM categories WHERE id = ?');
        $stmt->execute([$input['id']]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        respond($category);
    }

    if ($method === 'DELETE') 
    {
        $input = getInputData();

        if (!isset($input['id'])) {
            respond(['message' => 'category_id Not Found'], 404);
        }

        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $stmt->execute([$input['id']]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            respond(['message' => 'category_id Not Found'], 404);
        }

        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$input['id']]);

        respond(['id' => (int)$input['id']]);
    }
}

function getInputData(): array
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        $input = $_POST;
    }

    if (!is_array($input)) {
        $input = [];
    }

    return $input;
}

function respond(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}
