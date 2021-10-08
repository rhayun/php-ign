<?php

namespace IGN;

use Buzz\Browser;
use Buzz\Client\Curl;
use Symfony\Component\DomCrawler\Crawler;

class Game
{
    protected $title;
    protected $url;

    /**
     * @var array
     */
    protected $headers = ['User-Agent' => 'firefox'];

    /**
     * @var Crawler
     */
    protected $crawler;

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url)
    {
        $this->title = null;
        $this->url   = $url;
        $this->getCrawler();
    }

    public function getId()
    {
        try {
            $content = file_get_contents($this->url);
            $content = str_replace(array("\n", "\r"), "", $content);
            preg_match('/"object1_id":(\d+),/', $content, $matches);
            return $matches[1] ? $matches[1] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getTitle()
    {
        if (null === $this->title) {
            try {
                $this->title = trim($this->crawler->filterXpath('//h1[@class="contentTitle"]')->text());
            } catch (\Exception $e) {
                return null;
            }
        }

        return $this->title;
    }

    public function getReleaseDate()
    {
        try {
            return $this->crawler->filterXpath("//div[@class='releaseDate']/strong")->text();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getReleaseDateUnixtime()
    {
        try {
            $releaseDate = $this->getReleaseDate();
            return strtotime($releaseDate);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getYear()
    {
        try {
            $unixtime = $this->getReleaseDateUnixtime();
            $year = date("Y", $unixtime);
            return $year == 1970 ? null : $year;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getSummary()
    {
        try {
            $summary = $this->crawler->filterXpath("//div[@id='summary']/div[@class='gameInfo']//p[1]")->text();
            return trim(html_entity_decode($summary));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getFullSummary()
    {
        try {
            $summary = '';
            $this->crawler->filterXpath("//div[@id='summary']/div[@class='gameInfo']//p")->each(function ($node, $i) use (&$summary) {
                $summary .= htmlentities($node->nodeValue);
            });
            return trim(strip_tags(html_entity_decode($summary)));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getSummarySplit()
    {
        try {
            $summary = array();
            $this->crawler->filterXpath("//div[@id='summary']/div[@class='gameInfo']/p/..")->each(function ($node, $i) use (&$summary) {
                $summary[] = trim(html_entity_decode(htmlentities($node->nodeValue)));
            });
            return $summary;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getRating()
    {
        try {
            return trim($this->crawler->filter('.ignRating div.ratingValue')->text());
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getBoxArt()
    {
        try {
            $box_art = $this->crawler->filter('.mainBoxArt img.highlight-boxArt')->extract(array('src'));
            if(!empty($box_art)) {
                $cover = trim($box_art[0]);
                $full_cover = str_replace("_160h", "", $cover);
                $array = get_headers($full_cover);
                $string = $array[0];
                if(strpos($string,"200")) {
                    return $full_cover;
                }
                else {
                    return $cover;
                }
            }
            else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getGenres()
    {
        $genres = array();

        try {
            $this->crawler->filterXpath("//div[@class='gameInfo-list']/div/strong[text()='Genre']/../a[contains(@href, '/games/editors-choice')]")->each(function ($node, $i) use (&$genres) {
                $genres[] = trim(strip_tags($node->nodeValue));
            });
        } catch (\Exception $e) {
        }

        return $genres;
    }

    public function getPublishers()
    {
        $publishers = array();

        try {
            $this->crawler->filterXpath("//div[@class='gameInfo-list']/div/strong[text()='Publisher']/../a")->each(function ($node, $i) use (&$publishers) {
                $publishers[] = trim(strip_tags($node->nodeValue));
            });
        } catch (\Exception $e) {
        }

        return $publishers;
    }

    public function getDevelopers()
    {
        $developers = array();

        try {
            $this->crawler->filterXpath("//div[@class='gameInfo-list']/div/strong[text()='Developer']/../a")->each(function ($node, $i) use (&$developers) {
                $developers[] = trim(strip_tags($node->nodeValue));
            });
        } catch (\Exception $e) {
        }

        return $developers;
    }

    /**
     * @return Crawler
     */
    protected function getCrawler()
    {
        if (null === $this->crawler) {
            $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
            $client = new Curl($psr17Factory);
            $browser = new Browser($client, $psr17Factory);

            $content = $browser->get($this->url, $this->headers)->getBody()->__toString();
            $this->crawler = new Crawler($content);
        }

        return $this->crawler; 
    }
}
