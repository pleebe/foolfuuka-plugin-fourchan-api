<?php

use Doctrine\DBAL\Schema\Schema;
use Foolz\FoolFrame\Model\Autoloader;
use Foolz\FoolFrame\Model\Context;
use Foolz\FoolFrame\Model\Preferences;
use Foolz\FoolFrame\Model\DoctrineConnection;
use Foolz\FoolFrame\Model\Plugins;
use Foolz\FoolFrame\Model\Uri;
use Foolz\FoolFuuka\Model\RadixCollection;
use Foolz\Plugin\Event;
use Symfony\Component\Routing\Route;

class Fourchan_api
{
    public function run()
    {
        Event::forge('Foolz\Plugin\Plugin::execute#foolz/foolfuuka-plugin-fourchan-api')
            ->setCall(function ($plugin) {
                /** @var Context $context */
                $context = $plugin->getParam('context');
                /** @var Autoloader $autoloader */
                $autoloader = $context->getService('autoloader');
                $autoloader->addClassMap([
                    'Foolz\FoolFuuka\Controller\Chan\FourchanApi' => __DIR__ . '/classes/controller/chan.php',
                    'Foolz\FoolFuuka\Controller\Api\FourchanApi' => __DIR__ . '/classes/controller/api/chan.php',
                    'Foolz\FoolFuuka\Plugins\FourchanApi\Model\apiComment' => __DIR__ . '/classes/model/ApiComment.php',
                ]);
                $context->getContainer()
                    ->register('foolfuuka-plugin.apicomment', 'Foolz\FoolFuuka\Plugins\FourchanApi\Model\apiComment')
                    ->addArgument($context);

                Event::forge('Foolz\FoolFrame\Model\Context::handleWeb#obj.routing')
                    ->setCall(function ($result) use ($context) {
                        $routes = $result->getObject();
                        $radix_collection = $context->getService('foolfuuka.radix_collection');
                        foreach ($radix_collection->getAll() as $radix) {
                            $routes->getRouteCollection()->add(
                                'foolfuuka.plugin.fourchanapi.chan.radix.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/thread/{_suffix}.json',
                                [
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\FourchanApi::api_thread',
                                    '_default_suffix' => '',
                                    '_suffix' => '',
                                    'radix_shortname' => $radix->shortname
                                ], [
                                    '_suffix' => '.*'
                                ]
                            ));
                            $routes->getRouteCollection()->add(
                                'foolfuuka.plugin.fourchanapi.chan.radix.post.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/post/{_suffix}.json',
                                [
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\FourchanApi::api_post',
                                    '_default_suffix' => '',
                                    '_suffix' => '',
                                    'radix_shortname' => $radix->shortname
                                ], [
                                    '_suffix' => '.*'
                                ]
                            ));
                            $routes->getRouteCollection()->add(
                                'foolfuuka.plugin.fourchanapi.chan.catalog.radix.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/catalog.json',
                                [
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\FourchanApi::api_catalog',
                                    '_default_suffix' => '',
                                    '_suffix' => '',
                                    'radix_shortname' => $radix->shortname
                                ], [
                                    '_suffix' => '.*'
                                ]
                            ));
                            $routes->getRouteCollection()->add(
                                'foolfuuka.plugin.fourchanapi.chan.catalog.radix.page.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/catalog-{_suffix}.json',
                                [
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\FourchanApi::api_catalog',
                                    '_default_suffix' => '',
                                    '_suffix' => '',
                                    'radix_shortname' => $radix->shortname
                                ], [
                                    '_suffix' => '.*'
                                ]
                            ));
                            $routes->getRouteCollection()->add(
                                'foolfuuka.plugin.fourchanapi.chan.catalog.radix.single.page.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/single-catalog-{_suffix}.json',
                                [
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\FourchanApi::api_catalog_single',
                                    '_default_suffix' => '',
                                    '_suffix' => '',
                                    'radix_shortname' => $radix->shortname
                                ], [
                                    '_suffix' => '.*'
                                ]
                            ));
                            $routes->getRouteCollection()->add(
                                'foolfuuka.plugin.fourchanapi.chan.threads.radix.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/threads.json',
                                [
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\FourchanApi::api_threads',
                                    '_default_suffix' => '',
                                    '_suffix' => '',
                                    'radix_shortname' => $radix->shortname
                                ], [
                                    '_suffix' => '.*'
                                ]
                            ));
                            $routes->getRouteCollection()->add(
                                'foolfuuka.plugin.fourchanapi.chan.threads.radix.page.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/threads-{_suffix}.json',
                                [
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\FourchanApi::api_threads',
                                    '_default_suffix' => '',
                                    '_suffix' => '',
                                    'radix_shortname' => $radix->shortname
                                ], [
                                    '_suffix' => '.*'
                                ]
                            ));
                            $routes->getRouteCollection()->add(
                                'foolfuuka.plugin.fourchanapi.chan.archive.radix.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/archive.json',
                                [
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\FourchanApi::api_archive',
                                    '_default_suffix' => '',
                                    '_suffix' => '',
                                    'radix_shortname' => $radix->shortname
                                ], [
                                    '_suffix' => '.*'
                                ]
                            ));
                            $routes->getRouteCollection()->add(
                                'foolfuuka.plugin.fourchanapi.chan.archive.radix.page.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/archive-{_suffix}.json',
                                [
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\FourchanApi::api_archive',
                                    '_default_suffix' => '',
                                    '_suffix' => '',
                                    'radix_shortname' => $radix->shortname
                                ], [
                                    '_suffix' => '.*'
                                ]
                            ));
                        }
                        $routes->getRouteCollection()->add(
                            'foolfuuka.plugin.fourchanapi.chan', new Route(
                            '/boards.json',
                            [
                                '_controller' => '\Foolz\FoolFuuka\Controller\Api\FourchanApi::boards',
                            ]
                        ));
                        $routes->getRouteCollection()->add(
                            'foolfuuka.plugin.fourchanapi.chan.search', new Route(
                            '/search.json',
                            [
                                '_controller' => '\Foolz\FoolFuuka\Controller\Api\FourchanApi::api_search',
                            ]
                        ));
                    });
            });
    }
}


(new Fourchan_api())->run();
