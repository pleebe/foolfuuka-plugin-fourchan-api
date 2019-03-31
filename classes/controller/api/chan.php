<?php

namespace Foolz\FoolFuuka\Controller\Api;

use Foolz\FoolFuuka\Model\Comment;
use Foolz\FoolFuuka\Model\Media;
use Foolz\FoolFuuka\Model\RadixCollection;
use Foolz\FoolFuuka\Model\Search;
use Foolz\Inet\Inet;
use Foolz\FoolFuuka\Plugins\FourchanApi\Model\apiComment;

class FourchanApi extends \Foolz\FoolFuuka\Controller\Api\Chan
{
    /**
     * @var RadixCollection
     */
    protected $radix_coll;
    /**
     * @var Comment
     */
    protected $comment;
    /**
     * @var Media
     */
    protected $media_obj;
    protected $media;

    public function before()
    {
        parent::before();
        $this->comment = new Comment($this->getContext());
        $this->media_obj = new Media($this->getContext());
        $this->media = null;
        $this->radix_coll = $this->context->getService('foolfuuka.radix_collection');
    }

    public function get_boards()
    {
        $response = [];
        $response['boards'] = [];
        $response['troll_flags'] = [
            "AC" => "Anarcho-Capitalist",
            "AN" => "Anarchist",
            "BL" => "Black Nationalist",
            "CF" => "Confederate",
            "CM" => "Communist",
            "CT" => "Catalonia",
            "DM" => "Democrat",
            "EU" => "European",
            "FC" => "Fascist",
            "GN" => "Gadsden",
            "GY" => "Gay",
            "JH" => "Jihadi",
            "KN" => "Kekistani",
            "MF" => "Muslim",
            "NB" => "National Bolshevik",
            "NZ" => "Nazi",
            "PC" => "Hippie",
            "PR" => "Pirate",
            "RE" => "Republican",
            "TM" => "Templar",
            "TR" => "Tree Hugger",
            "UN" => "United Nations",
            "WP" => "White Supremacist"
        ];

        foreach ($this->radix_coll->getAll() as $radix) {
            $response['boards'][] = [
                'board' => $radix->shortname,
                'title' => $radix->name,
                'ws_board' => ($radix->getValue('is_nsfw') ? 0 : 1),
                'per_page' => (int)$radix->getValue('threads_per_page'),
                'pages' => 10,
                'max_filesize' => (int)$radix->getValue('max_image_size_kilobytes'),
                'max_webm_filesize' => (int)$radix->getValue('max_image_size_kilobytes'),
                'max_comment_chars' => (int)$radix->getValue('max_comment_characters_allowed'),
                'max_webm_duration' => 120,
                'bump_limit' => (int)$radix->getValue('max_posts_count'),
                'image_limit' => (int)$radix->getValue('max_images_count'),
                'cooldowns' => [
                    'threads' => (int)$radix->getValue('cooldown_new_thread'),
                    'replies' => (int)$radix->getValue('cooldown_new_comment'),
                    'images' => (int)$radix->getValue('min_image_repost_time')
                ],
                'meta_description' => '/' . $radix->shortname . '/ - ' . $radix->name,
                'is_archived' => 1,
                'user_ids' => ($radix->getValue('enable_poster_hash') ? 1 : 0)
            ];
        }

        return $this->response->setData($response);
    }

    public function get_api_search()
    {
        // check all allowed search modifiers and apply only these
        $modifiers = [
            'boards', 'tnum', 'subject', 'text', 'username', 'tripcode', 'email', 'filename', 'capcode', 'uid', 'country',
            'image', 'deleted', 'ghost', 'type', 'filter', 'start', 'end', 'results', 'order', 'page',
            'since4pass', 'width', 'height'
        ];

        if ($this->getAuth()->hasAccess('comment.see_ip')) {
            $modifiers[] = 'poster_ip';
        }

        $search = [];

        foreach ($modifiers as $modifier) {
            $search[$modifier] = $this->getQuery($modifier, null);
        }

        foreach ($search as $key => $value) {
            if (in_array($key, $modifiers) && $value !== null) {
                if (trim($value) !== '') {
                    $search[$key] = rawurldecode(trim($value));
                } else {
                    $search[$key] = null;
                }
            }
        }

        if ($search['boards'] !== null) {
            $search['boards'] = explode('.', $search['boards']);
        }

        if ($search['image'] !== null) {
            $search['image'] = base64_encode(Media::urlsafe_b64decode($search['image']));
        }

        if ($this->getAuth()->hasAccess('comment.see_ip') && $search['poster_ip'] !== null) {
            if (!filter_var($search['poster_ip'], FILTER_VALIDATE_IP)) {
                return $this->response->setData(['error' => _i('The poster IP you inserted is not a valid IP address.')]);
            }

            $search['poster_ip'] = Inet::ptod($search['poster_ip']);
        }

        if ($search['tnum'] !== null && !is_numeric($search['tnum'])) {
            return $this->response->setData(['error' => _i('Thread number you inserted is not a valid number.')]);
        }

        if ($search['height'] !== null && !is_numeric($search['height'])) {
            return $this->response->setData(['error' => _i('Image height you inserted is not a valid number.')]);
        }

        if ($search['width'] !== null && !is_numeric($search['width'])) {
            return $this->response->setData(['error' => _i('Image width you inserted is not a valid number.')]);
        }

        if ($search['since4pass'] !== null && !is_numeric($search['since4pass'])) {
            return $this->response->setData(['error' => _i('Since4pass you inserted is not a valid number.')]);
        }

        $posts = [];

        try {
            $board = Search::forge($this->getContext())
                ->getSearch($search)
                ->setRadix($this->radix)
                ->setPage($search['page'] ? $search['page'] : 1);

            foreach ($board->getCommentsUnsorted() as $post) {
                $this->comment->setBulk($post);
                if ($post->media !== null) {
                    $this->media_obj->setBulk($post);
                    $this->media = $this->media_obj;
                } else {
                    $this->media = null;
                }
                $posts['posts'][] = (new apiComment($this->getContext(), $this->getRequest(), $this->comment->radix))->apify($this->comment, $this->media);
            }
        } catch (\Foolz\FoolFuuka\Model\SearchException $e) {
            return $this->response->setData(['error' => $e->getMessage()]);
        } catch (\Foolz\FoolFuuka\Model\BoardException $e) {
            return $this->response->setData(['error' => $e->getMessage()])->setStatusCode(500);
        }

        return $this->response->setData($posts);
    }
}