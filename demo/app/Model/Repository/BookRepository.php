<?php
namespace Model\Repository;

use LeanMapper\Repository;
use Model\Entity\Book;

class BookRepository extends Repository
{
    public function find($id): ?Book
    {
        $row = $this->createFluent()->where('id = %i', $id)->fetch();
        return $row ? $this->createEntity($row) : null;
    }

    public function findAll(): array
    {
        return $this->createEntities(
            $this->createFluent()->fetchAll()
        );
    }
}