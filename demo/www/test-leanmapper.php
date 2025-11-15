<?php
require __DIR__ . '/../vendor/autoload.php';

$bootstrap = new App\Bootstrap;
$container = $bootstrap->bootWebApplication();

// Somehow the BookRepository is missing...

// $bookRepository = $container->getByType(Model\Repository\BookRepository::class);
// $book = $bookRepository->find(1);

// if ($book) {
//     echo "Book: {$book->name}, Available: " . ($book->available ? 'Yes' : 'No');
// } else {
//     echo "Book not found.";
// }