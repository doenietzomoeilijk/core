<?php

namespace Bolt\Controller\Async;

use Bolt\Configuration\Config;
use Bolt\Version;
use Bolt\Common\Exception\ParseException;
use Bolt\Common\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fetching the news
 *
 * @author Bob den Otter <bobdenotter@gmail.com>
 */
class News
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * News. Film at 11.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @Route("/async/news")
     */
    public function dashboardNews(Request $request)
    {
        $news = $this->getNews($request->getHost());

        // One 'alert' and one 'info' max. Regular info-items can be disabled,
        // but Alerts can't.
        $context = [
            'alert' => empty($news['alert']) ? null : $news['alert'],
            'news' => empty($news['news']) ? null : $news['news'],
            'information' => empty($news['information']) ? null : $news['information'],
            'error' => empty($news['error']) ? null : $news['error'],
            'disable' => false, // $this->getOption('general/backend/news/disable'),
        ];

        $response = new JsonResponse($context, 200);

        return $response;
    }


    /**
     * Get the news from Bolt HQ (with caching).
     *
     * @param string $hostname
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getNews($hostname)
    {
//        // Cached for two hours.
//        $news = $this->app['cache']->fetch('dashboardnews');
//        if ($news !== false) {
//            $this->app['logger.system']->info('Using cached data', ['event' => 'news']);
//
//            return $news;
//        }

        // If not cached, get fresh news.
        $news = $this->fetchNews($hostname);

//        $this->app['cache']->save('dashboardnews', $news, 7200);

        return $news;
    }

    /**
     * Get the news from Bolt HQ.
     *
     * @param string $hostname
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function fetchNews($hostname)
    {
//        $source = $this->getOption('general/branding/news_source', 'https://news.bolt.cm/');
        $source = 'https://news.bolt.cm/';
        $options = $this->fetchNewsOptions($hostname);

//        $this->app['logger.system']->info('Fetching from remote server: ' . $source, ['event' => 'news']);

        try {
            $client = new Client(['base_uri' => $source]);
            $fetchedNewsData = $client->request('GET', '/', $options)->getBody();
        } catch (RequestException $e) {
            $this->app['logger.system']->error(
                'Error occurred during newsfeed fetch',
                ['event' => 'exception', 'exception' => $e]
            );

            return [
                'error' => [
                    'type' => 'error',
                    'title' => 'Unable to fetch news!',
                    'teaser' => "<p>Unable to connect to $source</p>",
                ],
            ];
        }

        try {
            $fetchedNewsItems = Json::parse($fetchedNewsData);
        } catch (ParseException $e) {
            // Just move on, a user-friendly notice is returned below.
            $fetchedNewsItems = [];
        }

//        $newsVariable = $this->getOption('general/branding/news_variable');
//        if ($newsVariable && array_key_exists($newsVariable, $fetchedNewsItems)) {
//            $fetchedNewsItems = $fetchedNewsItems[$newsVariable];
//        }

        $news = [];

        // Iterate over the items, pick the first news-item that
        // applies and the first alert we need to show
        foreach ($fetchedNewsItems as $item) {
            $type = isset($item->type) ? $item->type : 'information';
            if (!isset($news[$type])
                && (empty($item->target_version) || Bolt\Version::compare($item->target_version, '>'))
            ) {
                $news[$type] = $item;
            }
        }

        if ($news) {
            return $news;
        }
//        $this->app['logger.system']->error('Invalid JSON feed returned', ['event' => 'news']);

        return [
            'error' => [
                'type' => 'error',
                'title' => 'Unable to fetch news!',
                'teaser' => "<p>Invalid JSON feed returned by $source</p>",
            ],
        ];
    }

    /**
     * Get the guzzle options.
     *
     * @param string $hostname
     *
     * @return array
     */
    private function fetchNewsOptions($hostname)
    {
//        $driver = $this->app['db']->getDatabasePlatform()->getName();

        $options = [
            'query' => [
                'v' => Version::VERSION,
                'p' => PHP_VERSION,
                'db' => 'none', // $driver,
                'name' => $hostname,
            ],
            'connect_timeout' => 5,
            'timeout' => 10,
        ];

//        if ($this->getOption('general/httpProxy')) {
//            $options['proxy'] = sprintf(
//                '%s:%s@%s',
//                $this->getOption('general/httpProxy/user'),
//                $this->getOption('general/httpProxy/password'),
//                $this->getOption('general/httpProxy/host')
//            );
//        }

        return $options;
    }


}