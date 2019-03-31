<?php

namespace Foolz\FoolFuuka\Plugins\FourchanApi\Model;

use Foolz\FoolFrame\Model\Model;
use Foolz\FoolFuuka\Model\RadixCollection;
use Foolz\FoolFuuka\Model\Board;
use Foolz\FoolFrame\Model\Config;
use Foolz\FoolFuuka\Model\Comment;
use Foolz\FoolFuuka\Model\Media;


class apiComment extends Model
{
    protected $radix_coll;
    protected $bbcode;
    protected $request;
    protected $radix;
    protected $config;

    public function __construct(\Foolz\FoolFrame\Model\Context $context, \Symfony\Component\HttpFoundation\Request $request, $radix)
    {
        parent::__construct($context);

        $this->radix_coll = $context->getService('foolfuuka.radix_collection');
        $this->config = $context->getService('config');

        $this->request = $request;
        $this->radix = $radix;
    }

    private function lightCommentProcess($comment)
    {
        $comment = htmlentities($comment, ENT_COMPAT, 'UTF-8', false);
        $comment = $this->processBBCode($comment);
        $comment = preg_replace('/(\r?\n|^)(&gt;.*?)(?=$|\r?\n)/i', '$1<span class="quote">$2</span>$3', $comment);
        $comment = preg_replace_callback('/(&gt;&gt;(\d+(?:,\d+)?))/i',
            [$this, 'processLink'], $comment);
        $comment = preg_replace_callback('/(&gt;&gt;&gt;(\/(\w+)\/([\w-]+(?:,\d+)?)?(\/?)))/i',
            [$this, 'processCrossboardLink'], $comment);
        $comment = nl2br(trim($comment));
        return $comment;
    }

    private function processBBCode($comment)
    {
        if ($this->bbcode === null) {
            $parser = new \JBBCode\Parser();
            $definitions = array();

            $builder = new \Foolz\FoolFuuka\Model\BBCode\Code();
            array_push($definitions, $builder);

            $builder = new \JBBCode\CodeDefinitionBuilder('spoiler', '<s>{param}</s>');
            array_push($definitions, $builder->build());

            $builder = new \JBBCode\CodeDefinitionBuilder('banned', '<b style="color:red">{param}</b>');
            array_push($definitions, $builder->build());

            $builder = new \JBBCode\CodeDefinitionBuilder('b', '<b>{param}</b>');
            array_push($definitions, $builder->build());

            $builder = new \JBBCode\CodeDefinitionBuilder('fortune', '<span class="fortune" style="color:{color}">{param}</span>');
            $builder->setUseOption(true);
            array_push($definitions, $builder->build());

            $builder = new \JBBCode\CodeDefinitionBuilder('shiftjis', '<span class="sjis">{param}</span>');
            array_push($definitions, $builder->build());

            foreach ($definitions as $definition) {
                $parser->addCodeDefinition($definition);
            }

            $this->bbcode = $parser;
        }

        // work around for dealing with quotes in BBCode tags
        $comment = str_replace('&quot;', '"', $comment);
        $comment = $this->bbcode->parse($comment)->getAsBBCode();
        $comment = str_replace('"', '&quot;', $comment);

        return $this->bbcode->parse($comment)->getAsHTML();
    }

    private function processLink($matches)
    {
        $num = $matches[2];
        try {
            if ($this->radix !== null) {
                $comment = Board::forge($this->getContext())
                    ->getPost()
                    ->setOptions('num', $num)
                    ->setRadix($this->radix)
                    ->getComment();

                return '<a class="quotelink" href="/' . $this->radix->shortname . '/thread/' . $comment->comment->thread_num . '#p' . $num . '">&gt;&gt;' . $num . '</a>';
            } else {
                return '<span class="deadlink">&gt;&gt;' . $num . '</span>';
            }
        } catch (\Foolz\FoolFuuka\Model\BoardMalformedInputException $e) {
            return '<span class="deadlink">&gt;&gt;' . $num . '</span>';
        } catch (\Foolz\FoolFuuka\Model\BoardPostNotFoundException $e) {
            return '<span class="deadlink">&gt;&gt;' . $num . '</span>';
        }
    }

    private function processCrossboardLink($matches)
    {
        $shortname = $matches[3];
        $num = $matches[4];
        $link = $matches[2];
        try {
            $board = $this->radix_coll->getByShortname($shortname);
            if (!$board) {
                return '<span class="deadlink">&gt;&gt;&gt;' . $link . '</span>';
            }

            $comment = Board::forge($this->getContext())
                ->getPost()
                ->setOptions('num', $num)
                ->setRadix($board)
                ->getComment();

            return '<a class="quotelink" href="/' . $board->shortname . '/thread/' . $comment->comment->thread_num . '#p' . $num . '">&gt;&gt;&gt;' . $link . '</a>';
        } catch (\Foolz\FoolFuuka\Model\BoardMalformedInputException $e) {
            return '<span class="deadlink">&gt;&gt;&gt;' . $link . '</span>';
        } catch (\Foolz\FoolFuuka\Model\BoardPostNotFoundException $e) {
            return '<span class="deadlink">&gt;&gt;&gt;' . $link . '</span>';
        }
    }

    public function apify($comment, $media, $thread_status = null)
    {
        $post = [
            'no' => (int)$comment->comment->num,
            'subnum' => (int)$comment->comment->subnum,
            'now' => $comment->getFourchanDate(),
            'name' => $comment->comment->name,
            'com' => $this->lightCommentProcess($comment->comment->comment),
            'time' => $comment->getOriginalTimestamp(),
        ];

        if ($comment->comment->title) {
            $post['sub'] = $comment->comment->title;
        }
        if ($comment->comment->trip) {
            $post['trip'] = $comment->comment->trip;
        }
        if ($comment->comment->poster_hash) {
            $post['id'] = $comment->comment->poster_hash;
        }
        if ($comment->comment->email) {
            $post['email'] = $comment->comment->email;
        }

        switch ($comment->comment->capcode) {
            case 'M':
                $post['capcode'] = 'mod';
                break;
            case 'A':
                $post['capcode'] = 'admin';
                break;
            case 'D':
                $post['capcode'] = 'developer';
                break;
            case 'V':
                $post['capcode'] = 'verified';
                break;
            case 'F':
                $post['capcode'] = 'founder';
                break;
            case 'G':
                $post['capcode'] = 'manager';
                break;
            case 'N':
            default:
                break;
        }

        if ($comment->getExtraData('since4pass') !== null) {
            $post['since4pass'] = (int)$comment->getExtraData('since4pass');
        }

        if ($comment->comment->op == 1) {
            if ($thread_status !== null) {
                $post['replies'] = (int)$thread_status['nreplies'];
                $post['images'] = (int)$thread_status['nimages'];
            }
            $post['sticky'] = (int)$comment->comment->sticky;
            $post['closed'] = (int)$comment->comment->locked;
            $post['resto'] = 0;
            if ($comment->getExtraData('uniqueIps') !== null) {
                $post['unique_ips'] = (int)$comment->getExtraData('uniqueIps');
            }
        } else {
            $post['resto'] = (int)$comment->comment->thread_num;
        }

        if ($comment->comment->poster_hash !== null) {
            $post['id'] = $comment->getPosterHashProcessed();
        }

        if ($comment->comment->poster_country !== null) {
            $post['country'] = $comment->comment->poster_country;
            $post['country_name'] = $comment->getPosterCountryNameProcessed();
        }

        if ($comment->getExtraData('troll_country') !== null) {
            $post['troll_country'] = $comment->getExtraData('troll_country');
            $post['country_name'] = $this->config->get('foolz/foolfuuka', 'pol2_codes', 'codes.'.strtoupper($post['troll_country']));
        }

        if ($media !== null) {
            $parts = explode('.', $media->getMediaFilenameProcessed());
            $post['filename'] = implode('.', array_slice($parts, 0, -1));
            $post['ext'] = '.' . end($parts);
            $post['w'] = (int)$media->media->media_w;
            $post['h'] = (int)$media->media->media_h;
            $post['tn_w'] = (int)$media->media->preview_w;
            $post['tn_h'] = (int)$media->media->preview_h;
            $post['md5'] = $media->media->media_hash;
            $post['fsize'] = (int)$media->media->media_size;
            $post['media_link'] = $media->getMediaLink($this->request);
            $post['thumb_link'] = $media->getThumbLink($this->request);
            if ($media->media->spoiler) {
                $post['spoiler'] = ($media->media->spoiler ? 1 : 0);
            }
            $post['tim'] = (int)explode('.', $media->media->media_orig)[0];
            if ($comment->getExtraData('Tag') !== null) {
                $post['tag'] = $comment->getExtraData('Tag');
            }
        }

        return $post;
    }
}