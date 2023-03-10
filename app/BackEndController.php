<?php
declare(strict_types=1);


namespace App;


use App\Core\Auth;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Scrawler\Arca\Database;
use SimpleValidator\Validator;
use EdSDK\FlmngrServer\FlmngrServer;
use EasySlugger\Slugger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class BackEndController
{
    use Auth;

    private  BackEndView $View;
    private  Database $Model;

    public function __construct(Database $Model, BackEndView $View)
    {
        $this->View = $View;
        $this->Model = $Model;
    }

    public function responseWrapper(string $str):ResponseInterface
    {
        $response = new Response;
        $response->getBody()->write($str);
        return $response;
    }

    public function goUrl(string $url)
    {
        return $response = new RedirectResponse($url);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return $this->showDashboard($request);
    }

    public function setMessage($text, $color = 'success', $position = 'top right' )
    {
        $_SESSION['message'] =
            [
                'color'=>$color,
                'text'=>$text,
                'position'=>$position
            ];
    }
    public function getMessage()
    {
        $message = null;
        if (isset($_SESSION['message'])){
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
        }
        return $message;
    }

    public  function  sendmail($title, $body, $address)
    {
        $mail = new PHPMailer();
        try {
            $mail->isSMTP();
            $mail->CharSet = "UTF-8";
            $mail->SMTPAuth   = true;
            $mail->Debugoutput = function($str, $level) {$GLOBALS['status'][] = $str;};

            // Настройки вашей почты
            $mail->Host       = '0.0.0.0'; // SMTP сервера вашей почты
            $mail->Username   = 'null'; // Логин на почте
            $mail->Password   = 'null'; // Пароль на почте
            $mail->SMTPSecure = 'null';
            $mail->Port       = 1025;
            $mail->setFrom('mail@yandex.ru', 'Администрация сайта '.$_SERVER['HTTP_ORIGIN']); // Адрес самой почты и имя отправителя

            // Получатель письма
            $mail->addAddress($address);


            // Отправка сообщения
            $mail->isHTML(true);
            $mail->Subject = $title;
            $mail->Body = $body;

            // Проверяем отравленность сообщения
            if ($mail->send()) {$result = "success";}
            else {$result = "error";}

            } catch (Exception $e) {
            $result = "error";
            $status = "Сообщение не было отправлено. Причина ошибки: {$mail->ErrorInfo}";
            $this->setMessage($status, 'error');
        }
    }

    /**
     * Эти методы относится к модели - нужно определиться выносить модели в отдельный класс
     * или оставлять в этом...
     */
    public function getUserByEmail(string $email)
    {
        $users = $this->Model->find('users')
            ->where('email = :email')
            ->setParameter('email',$email)
            ->first();
        return $users->toArray();
    }

    public function getAll(string $tablename):array
    {
        $all = $this->Model->get($tablename);
        return $all->toArray();
    }

    public function getById(string $tablename,  $id)
    {
        $all = $this->Model->get($tablename,$id);
        return $all->toArray();
    }


    /**
     * end Model
     **/


    public function UserSignIn(ServerRequestInterface $request): ResponseInterface
    {
        $requestBody = $request->getParsedBody();
        $user = $this->getUserByEmail($requestBody['email']);
        if (empty($user)){
            return $this->responseWrapper('User not found...');
        }else{
            if (password_verify($requestBody['password'],$user['password']))
            {
                //return $this->responseWrapper('Ok');
                $this->signIn($user['username'],$user['id']);
                return $this->goUrl('/admin');
            }else{
                $r = $this->responseWrapper('Неверный пароль');
                dd($r);
            }
        }
//        $requestBody = $request->getParsedBody();
//        $user = $this->getUserByEmail($requestBody['email']);
//        if (empty($user)){
////            return $this->responseWrapper('User not found...');
//            $this->setMessage('Пользователь не найден ...', 'error');
//            return $this->goUrl('/admin');
//        }else{
//            if (password_verify($requestBody['password'],$user['password']))
//            {
//                //return $this->responseWrapper('Ok');
//                $this->signIn($user['username'],$user['id']);
//                $this->setMessage('Привет '.$user['username'].'. Рады снова тебя видеть ;)');
//                $this->sendMail(
//                    'From Admin Panel',
//                    'Привет '.$user['username'].'. Рады снова тебя видеть ;)',
//                    '1@1.ru');
//                return $this->goUrl('/admin');
//            }else{
//                //$r = $this->responseWrapper('Неверный пароль');
//                $this->setMessage('Неверный пароль ...', 'error');
//                return $this->goUrl('/admin');
//                //dd($r);
//            }
//        }
    }

    public function userLogOut(ServerRequestInterface $request): ResponseInterface
    {
//        $this->sendMail(
//            'From Admin Panel',
//            'Пока '.  $_SESSION['username'].'. Возвращайся, я буду скучать ;)',
//            '1@1.ru');
        $this->signOut();
        return $this->goUrl('/admin');
    }

    public function UserSignUp(ServerRequestInterface $request): ResponseInterface
    {
        $requestBody = $request->getParsedBody();
        $rules = [
            'username' => [
                'required',
                'alpha',
                'min_length(5)',
                'max_length(50)'
            ],
            'email' => [
                'required',
                'email'
            ],
            'password' => [
                'required',
                'min_length(5)',
                'max_length(50)',
                'equals(:password_verify)'
            ],
            'password_verify' => [
                'required'
            ]
        ];
        $validation_result = Validator::validate($requestBody, $rules);
        if ($validation_result->isSuccess() == true) {
            $user = $this->getUserByEmail($requestBody['email']);
            if (empty($user)){
                $hash = $this->tokenGenerator();
                $host = $_SERVER['HTTP_ORIGIN'].'/';
                $user = $this->Model->create('users');
                $user->username = $requestBody['username'];
                $user->email = $requestBody['email'];
                $user->password = password_hash($requestBody['password'], PASSWORD_DEFAULT);
                $user->avatar = '/files/files/users/avatarka-standoff.jpg';
                $user->admin = 0;
                $user->verification_token = $hash;
                $user->confirmed = 0;
                $user->save();
                $this->setMessage('Для завершения регистрации вам отправлено письмо. Подтвердите вашу почту перейдя по ссылке в письме');
                $link = $host.'confirm/'.$requestBody['email'].'/'.$hash;
                $this->sendMail(
                    'Завершение регистрации',
                    'Для завершения регистрации на ресурсе '.$host.' подтвердите вашу почту перейдя по <a href="'.$link.'" >ссылке</a>.' ,
                    $requestBody['email']);
                return $this->goUrl('/signin');
            } else {
                $this->setMessage('Email is used ;(', 'error');
                return $this->goUrl('/signup');
            }
        }else{
            $this->setMessage($validation_result->getErrors(), 'error');
            return $this->goUrl('/signup');
        }
    }

//    public function showForgotPasswordForm(ServerRequestInterface $request): ResponseInterface
//    {
//        $message = $this->getMessage();
//        $html = $this->View->ShowForgotPasswordForm($message);
//        return $this->responseWrapper($html);
//    }

//    public function setNewUserPassword(string $email)
//    {
//        $user = $this->Model->find('users')
//            ->where('email = :email')
//            ->setParameter('email',$email)
//            ->first();
//        $new_password = $this->passwordGenerator();
//        $user->password = password_hash($new_password, PASSWORD_DEFAULT);
//        $user->save();
//        return $new_password;
//    }

//    public function hashGenerator(string $email):string
//    {
//        return $hash = password_hash($email.time(), PASSWORD_DEFAULT);
//    }
//
//    public function tokenGenerator():string
//    {
//        $token = openssl_random_pseudo_bytes(16);
//        return $token = bin2hex($token);
//    }
//
//    public function passwordGenerator():string
//    {
//        $password_length = rand(8, 16);
//        $chars = '#qazxswedcvfrtgbnhyujmkiolp@*1234567890!$QAZXSWEDCVFRTGBNHYUJMKIOLP';
//        $size = strlen($chars) - 1;
//        $password = '';
//        while($password_length--) {
//            $password .= $chars[random_int(0, $size)];
//        }
//        return $password;
//    }

//    public function forgotUserPassword(ServerRequestInterface $request): ResponseInterface
//    {
//        $requestBody = $request->getParsedBody();
//        $host = $_SERVER['HTTP_ORIGIN'].'/';
//        $user = $this->getUserByEmail($requestBody['email']);
//        if (empty($user)){
//            $this->setMessage('Пользователь не найден ...', 'error');
//            return $this->goUrl('/admin');
//        }else{
//            $hash = $this->tokenGenerator();
//            $link = $host.'password-reset/'.$requestBody['email'].'/'.$hash;
//            $password_resets = $this->Model->create('password_resets');
//            $password_resets->email = $requestBody['email'];
//            $password_resets->token = $hash;
//            $password_resets->expiration_date = time()+10800;
//            $password_resets->confirmed = 0;
//            $password_resets->save();
//
//            $this->setMessage('Ссылка для востоновления пароля отправлена на указанный Email');
//            $this->sendMail(
//                'Востановление пароля',
//                'Востановление пароля для  '.$user['email'].'  <a href="'.$link.'" >link</a>' ,
//                $user['email']);
//            return $this->goUrl('/admin');
//
//        }
//        return dd($requestBody['email']);
//    }

//    public function confirmEmail(ServerRequestInterface $request, array $arg): ResponseInterface
//    {
//        $user = $this->Model->find('users' )
//            ->where('verification_token = :token')
//            ->setParameter('token',$arg['token'])
//            ->first();
//        if (empty($user->getProperties())){
//            $this->setMessage('Что-то пошло не так', 'error');
//            return $this->goUrl('/');
//        }else{
//            if ($user->email == $arg['email'] and $user->confirmed == 0){
//                $user->confirmed = time();
//                $user->save();
//                $this->setMessage('Регистрация закончена. Войдите под своими учетными данными.');
//                return $this->goUrl('/admin');
//            };
//        };
//    }
//
//    public function passswordReset(ServerRequestInterface $request, array $arg): ResponseInterface
//    {
//        $password_reset = $this->Model->find('password_resets' )
//            ->where('token = :token')
//            ->setParameter('token',$arg['token'])
//            ->first();
//        if (empty($password_reset->getProperties())){
//            $this->setMessage('нет такого токена', 'error');
//            return $this->goUrl('/admin');
//        }else{
//            if (
//                $password_reset->email == $arg['email'] and
//                $password_reset->confirmed == 0
//            ){
//                if ($password_reset->expiration_date < time()) {
//                    $this->setMessage('токен прасрочен', 'error');
//                    return $this->goUrl('/admin');
//                }else {
//                    $password_reset->confirmed = time();
//                    $password_reset->save();
//                    $new_password = $this->setNewUserPassword($arg['email']);
//                    $this->setMessage('Пароль сброшен успешно. Новый пароль отправлен вам на email');
//                    $this->sendMail(
//                        'Новый пароль',
//                        'Ваш новый пароль -> '.$new_password,
//                        $arg['email']);
//                    return $this->goUrl('/admin');
//                }
//            }elseif($password_reset->email != $arg['email']){
//                $this->setMessage('токен не совпадает с  email', 'error');
//                return $this->goUrl('/admin');
//            }elseif ($password_reset->confirmed != 0){
//                $this->setMessage('этот токен уже использовался', 'error');
//                return $this->goUrl('/admin');
//            }
//        };
//    }


    public function showDashboard(ServerRequestInterface $request): ResponseInterface
    {
        $categories_count = $this->Model->get('categories')->count();
        $articles_count = $this->Model->get('articles')->count();
        $tags_count = $this->Model->get('tags')->count();
        $message = $this->getMessage();
        $html = $this->View->index($categories_count, $articles_count, $tags_count, $message);
        return $this->responseWrapper($html);
    }

    public function showSignInForm(ServerRequestInterface $request): ResponseInterface
    {
        $message = $this->getMessage();
        $html =$this->View->showSignInForm($message);
        return $this->responseWrapper($html);
    }

    public function showSignUpForm(ServerRequestInterface $request): ResponseInterface
    {
        $html = $this->View->showSignUpForm();
        return $this->responseWrapper($html);
    }

    public function showUsersList(ServerRequestInterface $request): ResponseInterface
    {
        $users = $this->getAll('users');
        $columns = ['username','email'];
        $html = $this->View->showUserList($users);
        return $this->responseWrapper($html);
    }

    public function showArticlesList(ServerRequestInterface $request): ResponseInterface
    {
        //$articles = $this->getAll('articles');
        $articles = $this->Model->find('articles')
            ->where('deleted_at = 0')
            ->get();
        $categories = $this->getAll('categories');
        $message = $this->getMessage();
        $html = $this->View->showArticlesList($articles, $categories, $message);
        return $this->responseWrapper($html);
    }

    public function showDeleteArticlesList(ServerRequestInterface $request): ResponseInterface
    {

        $articles = $this->Model->find('articles')
            ->where('deleted_at != 0')
            ->get();
        $categories = $this->getAll('categories');
        $message = $this->getMessage();
        $html = $this->View->showDeleteArticlesList($articles, $categories, $message);
        return $this->responseWrapper($html);
    }

    public function showAddArticleForm(ServerRequestInterface $request): ResponseInterface
    {   $article = [];
        $tags = $this->getAll('tags');
        $categories = $this->getAll('categories');
        $target = 'article-add';
        $html = $this->View->showAddArticleForm($article, $categories, $target, $tags );
        return $this->responseWrapper($html);
    }

    public function showUpdateArticleForm(ServerRequestInterface $request, array $arg): ResponseInterface
    {
        $article = $this->getById('articles', $arg['id']);

        $selected_tag = $this->Model->find('article_tag' )
            ->select('tag_id')
            ->where('article_id = :id')
            ->setParameter('id',$arg['id'])
            ->get()
            ->toArray();
        $tags = $this->getAll('tags');
        $categories = $this->getAll('categories');
        $target = 'article-update/'.$arg['id'];
        $html = $this->View->showAddArticleForm($article, $categories, $target, $tags, $selected_tag );
        return $this->responseWrapper($html);

    }

    public function showUpdateCategoriesForm(ServerRequestInterface $request, array $arg): ResponseInterface
    {
        $categories = $this->getById('categories', $arg['id']);
        $categories = $this->getAll('categories');
        $target = 'categories-update/'.$arg['id'];
        $html = $this->View->showAddCategoriesForm( $categories, $target );
        return $this->responseWrapper($html);
    }


    public function saveArticle(array $requestBody,  $id)
    {
        if ($id <> null){
            $article = $this->Model->get('articles',$id);
        }else{
            $article = $this->Model->create('articles');
            $id=time();
        }
        $article->title = $requestBody['title'];
        $article->slug = $id.'-'.Slugger::slugify($requestBody['title']);
        $article->intro_image = $requestBody['intro_image'];
        $article->intro_text = $requestBody['intro_text'];
        $article->category_id = $requestBody['category'];
        $article->user_id = $_SESSION['user_id'];
        $article->content = $requestBody['content'];
        $date = date('Y-m-d H:i:s', time());
        $article->created_at = $date;
        $article->deleted_at = 0;
        $article->favorites = 0;
        //dd($requestBody);
        $article->save();

        if (isset($requestBody['tags'])) {
            $qb = $this->Model->connection
                ->createQueryBuilder()
                ->delete('article_tag', 'at')
                ->where('at.article_id = :id')
                ->setParameter('id', $article->id)
                ->executeQuery();

            foreach ($requestBody['tags'] as $tag) {
                $article_tag = $this->Model->create('article_tag');
                $article_tag->article_id = $article->id;
                $article_tag->tag_id = $tag;
                $article_tag->save();
            }

        }
    }


    public function showCategoriesList(ServerRequestInterface $request): ResponseInterface
    {
        $categories = $this->getAll('categories');
        $message = $this->getMessage();
      //  $user = $this->getCurentUser();
        $html = $this->View->showCategoriesList($categories, $message );
        return $this->responseWrapper($html);
    }

    public function showCategoriesForm(ServerRequestInterface $request): ResponseInterface
    {
        $categories = $this->getAll('categories');
        $target = 'categories-add';
        $html = $this->View-> showAddCategoriesForm($categories, $target );
        return $this->responseWrapper($html);
    }

    public function saveCategories(array $requestBody,  $id)
    {
        if ($id <> null){
            $categories = $this->Model->get('categories',$id);
        }else{
            $categories = $this->Model->create('categories');
        }
       $categories->title = $requestBody['title'];
        $categories->slug = Slugger::slugify($requestBody['title']);
        $categories->image = $requestBody['image'];
        $categories->description = $requestBody['description'];

        $categories->save();
    }

    public function insertArticle(ServerRequestInterface $request): ResponseInterface
    {
        $requestBody = $request->getParsedBody();
        $this->saveArticle($requestBody,$id = null);
        $this->setMessage('Статья добавлена успешно ;)');
        return $this->goUrl('/admin/articles');
    }

    public function updateArticle(ServerRequestInterface $request, array $arg): ResponseInterface
    {
        $requestBody = $request->getParsedBody();
        $this->saveArticle($requestBody, $arg['id']);
        $this->setMessage('Изменения выполнены успешно');
        return $this->goUrl('/admin/articles');
    }

    public function updateDeleteArticle(ServerRequestInterface $request, array $arg): ResponseInterface
    {
        $article = $this->Model->get('articles',$arg['id']);
        $this->setMessage('XAXAAAAXXAXAAXAXA');
        $article->deleted_at=0;
        $article->save();
        return $this->goUrl('/admin/deletearticleslist');
    }
    public function insertCategories(ServerRequestInterface $request): ResponseInterface
    {
        $requestBody = $request->getParsedBody();
        $this->saveCategories($requestBody,$id = null);
        return $this->goUrl('/admin/categories');
    }

    public function updateCategories(ServerRequestInterface $request, array $arg): ResponseInterface
    {
        $requestBody = $request->getParsedBody();
        $this->saveCategories($requestBody, $arg['id']);
        return $this->goUrl('/admin/categories');
    }

    public function saveTag(array $requestBody,  $id)
    {
        if ($id <> null){
            $tags = $this->Model->get('tags',$id);
        }else{
            $tags = $this->Model->create('tags');
        }
        $tags->title = $requestBody['title'];
        $tags->save();
    }

    public function showTagsList(ServerRequestInterface $request): ResponseInterface
    {
        $tags = $this->getAll('tags');
        $message = $this->getMessage();
        $html = $this->View->showTagsList($tags, $message);
        return $this->responseWrapper($html);
    }

    public function showAddTagsForm(ServerRequestInterface $request): ResponseInterface
    {   $tags = [];
        $target = 'tags-add';
        $html = $this->View->showAddTagsForm($tags, $target );
        return $this->responseWrapper($html);
    }

    public function insertTags(ServerRequestInterface $request): ResponseInterface
    {
        $requestBody = $request->getParsedBody();
        $this->saveTag($requestBody, $id = null);
        $this->setMessage('Тэг добавлен успешно ;)');
        return $this->goUrl('/admin/tags');
   }

    public function updateTags(ServerRequestInterface $request, array $arg): ResponseInterface
    {
        $requestBody = $request->getParsedBody();
        $this->saveTag($requestBody, $arg['id']);
        $this->setMessage('Изменения выполнены успешно');
        return $this->goUrl('/admin/tags');
    }

    public function showUpdateTags(ServerRequestInterface $request, array $arg): ResponseInterface
    {
        $tags = $this->getById('tags', $arg['id']);
        $tags = $this->getAll('tags');
        $target = 'tags-update/'.$arg['id'];
        $html = $this->View->showAddTagsForm( $tags, $target );
        return $this->responseWrapper($html);
    }

    public function deleteArticle(ServerRequestInterface $request, array $arg): ResponseInterface
    {
        $article = $this->Model->get('articles', $arg['id']);
        $article ->deleted_at = time();
        $article->save();
        $this->setMessage('Удаление выполнены успешно');
        return $this->goUrl('/admin/articles');
    }

    public function deleteCategories(ServerRequestInterface $request, array $arg): ResponseInterface
    {
       $categories = $this->Model->get('categories', $arg['id']);
       $categories->delete();
       $this->setMessage('Удаление выполнены успешно');
       return $this->goUrl('/admin/categories');
    }

    public function deleteTags(ServerRequestInterface $request, array $arg): ResponseInterface
    {
        $tags = $this->Model->get('tags', $arg['id']);
        $tags->delete();
        $this->setMessage('Удаление выполнены успешно');
        return $this->goUrl('/admin/tags');
    }
}