<?php
namespace App\Presentation\Post;

use Nette;
use Nette\Application\UI\Form;

final class PostPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private Nette\Database\Explorer $database,
    ) {
    }

    public function renderShow(int $id): void
    {
        $post = $this->database
            ->table('posts')
            ->get($id);

        if (!$post) {
            $this->error('Post not found');
        }

        $this->template->post = $post;
        $this->template->comments = $post->related('comments')->order('created_at');
    }


    // This can be referenced from a template via: {control commentForm}
    protected function createComponentCommentForm(): Form
    {
        $form = new Form;

        $form->addText('name', 'Your name:')
            ->setRequired();

        $form->addEmail('email', 'Email:');

        $form->addTextArea('content', 'Comment:')
            ->setRequired();

        $form->addSubmit('send', 'Publish comment');

        // The (...) syntax is new with PHP 8.1, instead of:
        // $form->onSuccess[] = [$this, 'commentFormSucceeded'];
        $form->onSuccess[] = $this->commentFormSucceeded(...);

        return $form;
    }

    private function commentFormSucceeded(\stdClass $data): void
    {
        $id = $this->getParameter('id');

        $this->database->table('comments')->insert([
            'post_id' => $id,
            'name' => $data->name,
            'email' => $data->email,
            'content' => $data->content,
        ]);

        $this->flashMessage('Thank you for your comment', 'success');

        // redirects to the current page
        $this->redirect('this');
    }
}