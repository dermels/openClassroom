<?php

namespace Controller;

use App\Twig\PathExtension;
use Model\Article;
use Model\ArticleRepository;
use Model\CommentaryRepository;
use Model\UserRepository;
use ReflectionException;
use Twig\Environment;
use PDO;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ArticleController
{

    private Environment $twig;
    private ArticleRepository $articleRepository;
    private CommentaryRepository $commentaryRepository;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->articleRepository = new ArticleRepository($db);
        $this->userRepository = new UserRepository($db);
        $this->commentaryRepository = new CommentaryRepository($db);
    }

    /**
     * @throws SyntaxError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function showArticleForm(): string
    {
        $params = [];

        if (isset($_GET['id'])) {
            $article = $this->articleRepository->getArticle($_GET['id']);
            if (!$article) {
                return $this->twig->render('article/articleForm.twig', [
                    "message" => "L'article demandé n'existe pas."
                ]);
            }
            $params['article'] = $article;
        }

        return $this->twig->render('article/articleForm.twig', $params);
    }

    /**
     * @throws SyntaxError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function submitArticleForm(): string
    {
        // enleve uniquement les balises <script> et <style> du contenu
        $id = $_POST['id'] ?? null;
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $_POST['content']);
        $chapo = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', "", $_POST['chapo']);
        $title = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', "", $_POST['title']);

        $article = $this->articleRepository->save(new Article($id, $title, $chapo, $content, $_SESSION['user']['id']));
        if (!$article) {
            return $this->twig->render('article/articleForm.twig', [
                "message" => "Une erreur est survenue lors de la sauvegarde de l'article."
            ]);
        }

        header('Location: '. (MODE === 'dev' ? '/index.php/' : '/') . 'article/show?id=' . $article->getId());
        exit();
    }

    /**
     * @throws SyntaxError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function showArticle($id): string
    {
        $article = $this->articleRepository->getArticle($id);
        $auteur = $this->userRepository->getUser($article->getAuthorId());

        $current_page = $_GET['current_page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;

        $commentaries = $this->commentaryRepository->getCommentaries(null, $current_page, $limit);
        return $this->twig->render('article/article.twig', ['article' => $article, 'auteur' => $auteur, 'commentaries' => $commentaries['data'], 'total' => $commentaries['total'], 'current_page' => $current_page, 'limit' => $limit]);
    }

    /**
     * @throws SyntaxError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function showArticleList(): string
    {
        $current_page = $_GET['current_page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;
        $data = $this->articleRepository->getArticlesList($current_page, $limit) ?? null;

        $data['data'] = array_map(function ($article) {
            $article->setContent(html_entity_decode(strip_tags($article->getContent()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return $article;
        }, $data['data']);

        return $this->twig->render('article/articleList.twig', [
            'articles' => $data['data'],
            'total' => $data['total'],
            'current_page' => $current_page,
            'limit' => $limit
        ]);

    }
}