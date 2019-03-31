<?php

namespace Foolz\FoolFuuka\Controller\Chan;

use Foolz\FoolFrame\Model\DoctrineConnection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Foolz\FoolFuuka\Model\Board;
use Foolz\FoolFuuka\Model\Comment;
use Foolz\FoolFuuka\Model\Media;
use Foolz\FoolFuuka\Plugins\FourchanApi\Model\apiComment;


class FourchanApi extends \Foolz\FoolFuuka\Controller\Chan
{
    /**
     * @var JsonResponse
     */
    protected $response;

    /**
     * @var Comment
     */
    protected $comment;

    /**
     * @var Media
     */
    protected $media_obj;
    protected $media;

    /**
     * @var DoctrineConnection
     */
    protected $dc;

    protected $bbcode;

    /**
     * @var apiComment
     */
    protected $apicomment;

    public function before()
    {
        parent::before();
        $this->comment = new Comment($this->getContext());
        $this->media_obj = new Media($this->getContext());
        $this->media = null;
        $this->dc = $this->getContext()->getService('doctrine');
    }

    public function setResponse()
    {
        $this->response = new JsonResponse();
        $this->response->headers->set('Access-Control-Allow-Origin', '*');
        $this->response->headers->set('Access-Control-Allow-Credentials', 'true');
        $this->response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, POST, PUT, DELETE');
        $this->response->headers->set('Access-Control-Max-Age', '604800');
        $this->response->headers->set('Access-Control-Allow-Headers', 'If-Modified-Since');
    }

    public function radix_api_thread($num = 0)
    {
        $this->setResponse();
        $this->apicomment = new apiComment($this->getContext(), $this->getRequest(), $this->radix);

        if ($num === null) {
            return $this->response->setData(['error' => _i('The "num" parameter is missing.')])->setStatusCode(422);
        }

        if (!ctype_digit((string) $num)) {
            return $this->response->setData(['error' => _i('The value for "num" is invalid.')])->setStatusCode(422);
        }

        $num = intval($num);

        $board = Board::forge($this->getContext())
            ->getThread($num)
            ->setRadix($this->radix)
            ->setOptions(['type' => 'thread']);

        $thread_status = $board->getThreadStatus();
        $last_modified = $thread_status['last_modified'];

        $this->setLastModified($last_modified);
        $thread = [];
        $thread['posts'] = [];
        $c = 0;

        if (!$this->response->isNotModified($this->request)) {
            $bulks = $board->getCommentsUnsorted();
            foreach ($bulks as $bulk) {
                $this->comment->setBulk($bulk);
                if ($bulk->media !== null) {
                    $this->media_obj->setBulk($bulk);
                    $this->media = $this->media_obj;
                } else {
                    $this->media = null;
                }

                $thread['posts'][$c] = $this->apicomment->apify($this->comment, $this->media, $thread_status);

                $c++;
            }

            $this->response->setData($thread);
        }

        return $this->response;
    }

    public function radix_api_post($num = 0)
    {
        $this->setResponse();
        $this->apicomment = new apiComment($this->getContext(), $this->getRequest(), $this->radix);

        if ($num === null) {
            return $this->response->setData(['error' => _i('The "num" parameter is missing.')])->setStatusCode(422);
        }

        if (!ctype_digit((string)$num)) {
            return $this->response->setData(['error' => _i('The value for "num" is invalid.')])->setStatusCode(422);
        }

        $num = intval($num);
        $post = [];

        try {
            $comment = Board::forge($this->getContext())
                ->getPost($num)
                ->setRadix($this->radix)
                ->getComment();

            $this->comment->setBulk($comment);
            if ($comment->media !== null) {
                $this->media_obj->setBulk($comment);
                $this->media = $this->media_obj;
            } else {
                $this->media = null;
            }

            $post['posts'][0] = $this->apicomment->apify($this->comment, $this->media);

            $this->response->setData($post);
        } catch (\Foolz\FoolFuuka\Model\BoardPostNotFoundException $e) {
            return $this->response->setData(['error' => _i('Post not found.')]);
        } catch (\Foolz\FoolFuuka\Model\BoardException $e) {
            return $this->response->setData(['error' => $e->getMessage()])->setStatusCode(500);
        }

        return $this->response;
    }

    public function radix_api_catalog($page = 0)
    {
        $this->setResponse();
        $this->apicomment = new apiComment($this->getContext(), $this->getRequest(), $this->radix);

        if (!ctype_digit((string)$page)) {
            return $this->response->setData(['error' => 'Invalid page number']);
        }

        $page = $page * 10;
        $response = [];
        $max = $page + 10;
        $vispage = 0;
        while ($page < $max) {
            $board = Board::forge($this->getContext())
                ->getLatest()
                ->setRadix($this->radix)
                ->setPage($page + 1)
                ->setOptions([
                    'per_page' => $this->radix->getValue('threads_per_page'),
                    'per_thread' => 5,
                    'order' => 'by_post'
                ]);

            $c = 0;

            foreach ($board->getComments() as $key => $post) {
                $response[$vispage]['threads'][$c] = [];

                if (isset($post['op'])) {
                    $op = $post['op'];
                    $this->comment->setBulk($op);
                    if ($op->media !== null) {
                        $this->media_obj->setBulk($op);
                        $this->media = $this->media_obj;
                    } else {
                        $this->media = null;
                    }
                    $response[$vispage]['threads'][$c] = $this->apicomment->apify($this->comment, $this->media);
                }

                $response[$vispage]['threads'][$c]['last_replies'] = [];
                if (isset($post['posts'])) {
                    foreach ($post['posts'] as $p) {
                        $this->comment->setBulk($p);
                        if ($p->media !== null) {
                            $this->media_obj->setBulk($p);
                            $this->media = $this->media_obj;
                        } else {
                            $this->media = null;
                        }
                        $response[$vispage]['threads'][$c]['last_replies'][] = $this->apicomment->apify($this->comment, $this->media);
                    }
                }

                $thread_status = $this->threadStatus();
                $response[$vispage]['threads'][$c]['replies'] = (int)$thread_status['nreplies'];
                $response[$vispage]['threads'][$c]['images'] = (int)$thread_status['nimages'];
                $response[$vispage]['threads'][$c]['last_modified'] = (int)$thread_status['time_last_modified'];
                $response[$vispage]['threads'][$c]['omitted_posts'] = (int)$post['omitted'];
                $response[$vispage]['threads'][$c]['omitted_images'] = (int)$post['images_omitted'];

                $c++;
            }
            if (!empty($response[$vispage]['threads'])) {
                $response[$vispage]['page'] = $page + 1;
            }
            $page++;
            $vispage++;
        }

        $this->setLastModified(time());

        return $this->response->setData($response);
    }

    public function radix_api_catalog_single($page = 0)
    {
        $this->setResponse();
        $this->apicomment = new apiComment($this->getContext(), $this->getRequest(), $this->radix);

        if (!ctype_digit((string)$page)) {
            return $this->response->setData(['error' => 'Invalid page number']);
        }

        $response = [];
        $vispage = 0;
        $board = Board::forge($this->getContext())
            ->getLatest()
            ->setRadix($this->radix)
            ->setPage($page + 1)
            ->setOptions([
                'per_page' => $this->radix->getValue('threads_per_page'),
                'per_thread' => 5,
                'order' => 'by_post'
            ]);

        $response[$vispage]['pages_total'] = $board->getPages();
        $c = 0;

        foreach ($board->getComments() as $key => $post) {
            $response[$vispage]['threads'][$c] = [];

            if (isset($post['op'])) {
                $op = $post['op'];
                $this->comment->setBulk($op);
                if ($op->media !== null) {
                    $this->media_obj->setBulk($op);
                    $this->media = $this->media_obj;
                } else {
                    $this->media = null;
                }
                $response[$vispage]['threads'][$c] = $this->apicomment->apify($this->comment, $this->media);
            }

            $response[$vispage]['threads'][$c]['last_replies'] = [];
            if (isset($post['posts'])) {
                foreach ($post['posts'] as $p) {
                    $this->comment->setBulk($p);
                    if ($p->media !== null) {
                        $this->media_obj->setBulk($p);
                        $this->media = $this->media_obj;
                    } else {
                        $this->media = null;
                    }
                    $response[$vispage]['threads'][$c]['last_replies'][] = $this->apicomment->apify($this->comment, $this->media);
                }
            }

            $thread_status = $this->threadStatus();
            $response[$vispage]['threads'][$c]['replies'] = (int)$thread_status['nreplies'];
            $response[$vispage]['threads'][$c]['images'] = (int)$thread_status['nimages'];
            $response[$vispage]['threads'][$c]['last_modified'] = (int)$thread_status['time_last_modified'];
            $response[$vispage]['threads'][$c]['omitted_posts'] = (int)$post['omitted'];
            $response[$vispage]['threads'][$c]['omitted_images'] = (int)$post['images_omitted'];

            $c++;
        }
        if (!empty($response[$vispage]['threads'])) {
            $response[$vispage]['page'] = $page + 1;

        }

        $this->setLastModified(time());

        return $this->response->setData($response);
    }


    private function threadStatus()
    {
        return $this->dc->qb()
            ->select('*')
            ->from($this->radix->getTable('_threads'), 't')
            ->where('thread_num = :thread_num')
            ->setParameter(':thread_num', $this->comment->comment->thread_num)
            ->execute()
            ->fetch();
    }

    public function radix_api_threads($page = 0)
    {
        $this->setResponse();

        if (!ctype_digit((string)$page)) {
            return $this->response->setData(['error' => 'Invalid page number']);
        }

        $response = [];
        $page = $page * 10;
        $max = $page + 10;
        $vispage = 0;
        while ($page < $max) {
            $board = Board::forge($this->getContext())
                ->getLatest()
                ->setRadix($this->radix)
                ->setPage($page + 1)
                ->setOptions([
                    'per_page' => $this->radix->getValue('threads_per_page'),
                    'per_thread' => 5,
                    'order' => 'by_post'
                ]);

            $c = 0;

            foreach ($board->getComments() as $key => $post) {
                if (isset($post['op'])) {
                    $this->comment->setBulk($post['op']);
                    $response[$vispage]['threads'][$c]['no'] = (int)$this->comment->comment->num;

                    $thread = $this->dc->qb()
                        ->select('time_last_modified')
                        ->from($this->radix->getTable('_threads'), 't')
                        ->where('thread_num = :thread_num')
                        ->setParameter(':thread_num', $this->comment->comment->thread_num)
                        ->execute()
                        ->fetch();

                    $response[$vispage]['threads'][$c]['last_modified'] = (int)$thread['time_last_modified'];
                    $c++;
                }
            }
            if (!empty($response[$vispage]['threads'])) {
                $response[$vispage]['page'] = $page + 1;
            }
            $page++;
            $vispage++;
        }
        return $this->response->setData($response);
    }

    public function radix_api_archive($page = 0)
    {
        $this->setResponse();

        if (!ctype_digit((string)$page)) {
            return $this->response->setData(['error' => 'Invalid page number']);
        }

        $response = [];

        $board = Board::forge($this->getContext())
            ->getThreads()
            ->setRadix($this->radix)
            ->setPage($page + 1)
            ->setOptions('per_page', 100);

        foreach ($board->getComments() as $key => $post) {
            $this->comment->setBulk($post);
            $response[] = (int)$this->comment->comment->thread_num;
        }

        return $this->response->setData($response);
    }
}