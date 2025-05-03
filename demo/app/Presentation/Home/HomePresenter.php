<?php

declare(strict_types=1);

namespace App\Presentation\Home;

use Nette;


final class HomePresenter extends Nette\Application\UI\Presenter
{

    public function __construct(
        private Nette\Database\Explorer $database,
    ) {
    }


    public function renderDefault(): void
    {
        $this->template->posts = $this->database
            ->table('posts')
            ->order('created_at DESC')
            ->limit(5);

        //Reflection example:
        // foreach ($this->template->posts as $post) {
        // echo get_class($post);
        // }
    }
}
