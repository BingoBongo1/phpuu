<?php
declare(strict_types=1);


namespace App;


class BackEndView
{
    public $twig;

    public function __construct($twig)
    {
        $this->twig = $twig;
    }

    public function index($categories_count, $articles_count, $tags_count, $message)
    {
        return $this->twig->render('index.twig',
            [
                'categories_count'=>$categories_count,
                'articles_count'=>$articles_count,
                'tags_count'=>$tags_count,
                'message' => $message

            ]);
    }

    public function showSignInForm($message)
    {
        return $this->twig->render('signin.twig',['message' => $message]);
    }

    public function showSignUpForm()
    {
        return $this->twig->render('signup.twig');
    }

    public function showForgotPasswordForm($message)
    {
        return $this->twig->render('forgot-password.twig',['message' => $message]);
    }

    public function showUserList($users)
    {
        return $this->twig->render('userlist.twig',['users' => $users]);
    }

    public function showArticlesList($articles, $categories, $message)
    {
        return $this->twig->render(
        'articleslist.twig',

            ['articles' => $articles,
                'categories'=>$categories,
                'message' => $message

            ]);
    }

    public function showDeleteArticlesList($articles, $categories, $message)
    {
        return $this->twig->render('deletearticleslist.twig',['articles' => $articles, 'categories'=>$categories,'message' => $message
        ]);
    }

    public function showAddArticleForm($article, $categories, $target, $tags, $selected_tag = [])
    {
        return $this->twig->render(
            'add-article.twig',
            [
                'article' => $article,
                'categories'=>$categories,
                'target'=> $target,
                'tags' => $tags,
                'selected_tag' => $selected_tag
            ]);
    }

    public function showCategoriesList($categories, $message)
    {
        return $this->twig->render('categorieslist.twig',
            [
                'categories' => $categories,
                'message' => $message

            ]);
    }


    public function showAddCategoriesForm($categories, $target)
    {
        return $this->twig->render('add-categories.twig',
            [
                'categories'=>$categories,
                'target'=> $target,

            ]);
    }

    public function showAddTagsForm($tags, $target)
    {
        return $this->twig->render('add-tag.twig',
            [
                'tags'=>$tags,
                'target'=> $target
            ]);
    }

    public function showTagsList($tags, $message)
    {
        return $this->twig->render('tagslist.twig',['tags' => $tags, 'message' => $message]);
    }

}