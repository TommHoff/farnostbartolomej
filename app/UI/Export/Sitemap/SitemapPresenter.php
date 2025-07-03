<?php

declare(strict_types=1);

namespace App\UI\Export\Sitemap;

use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Utils\DateTime;
use App\Model\Develepment\UniRepository;
use App\Model\News\NewsRepository;
use App\Model\Calendar\CalendarRepository;

final class SitemapPresenter extends Presenter
{

    public function __construct(
        private readonly UniRepository    $uniRepository,
        private readonly IRequest         $httpRequest,
        private readonly IResponse        $httpResponse,
        private readonly NewsRepository   $newsRepository,
        private readonly CalendarRepository $calendarRepository,

    ) {
    }

    public function renderSitemapIndex(): void
    {
        $baseUrl = $this->httpRequest->getUrl()->getBaseUrl();
        $lastModDate = date('Y-m-d');

        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
            <sitemap>
                <loc>{$baseUrl}sitemap.xml</loc>
                <lastmod>{$lastModDate}</lastmod>
            </sitemap>
        </sitemapindex>
        XML;

        $this->httpResponse->setContentType('application/xml');
        $this->sendResponse(new TextResponse($xml));
    }

    public function renderSitemap(): void
    {
        $baseUrl = $this->httpRequest->getUrl()->getBaseUrl();

        $staticContentDate = '2025-04-01';
        $policyDate = '2025-04-01';

        $uniDate = $staticContentDate; // Default fallback value
        try {
            $repoData = $this->uniRepository->getUniRev();
            if ($repoData && isset($repoData->release_date)) {
                $releaseDate = DateTime::from($repoData->release_date);
                $uniDate = $releaseDate->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // If an error occurs, $uniDate remains the $staticContentDate fallback
        }

        // Fetch latest news date
        $newsDate = $staticContentDate; // default fallback
        try {
            $lastNews = $this->newsRepository->getLastNews()->fetch();
            if ($lastNews && isset($lastNews->published_at)) {
                $newsDate = DateTime::from($lastNews->published_at)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // If fetch fails, fallback stays
        }

        // Fetch latest calendar date, fallback to static date
        $calendarDate = $staticContentDate;
        try {
            $lastCalendar = $this->calendarRepository->getLastEventDate()->fetch();
            if ($lastCalendar && isset($lastCalendar->start_date)) {
                $calendarDate = \Nette\Utils\DateTime::from($lastCalendar->start_date)->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            // Fallback stays on error
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $addUrl = function (string $locPath, string $lastmod, string $changefreq, float $priority) use ($baseUrl): string {
            $fullLoc = rtrim($baseUrl, '/') . '/' . ltrim($locPath, '/');
            if ($locPath === '') {
                $fullLoc = rtrim($baseUrl, '/');
                if ($fullLoc === $this->httpRequest->getUrl()->getScheme() . '://' . $this->httpRequest->getUrl()->getAuthority()) {
                    $fullLoc .= '/';
                } elseif ($fullLoc === '') {
                    $fullLoc = '/';
                }
            }
            return sprintf(
                "   <url>\n      <loc>%s</loc>\n      <lastmod>%s</lastmod>\n      <changefreq>%s</changefreq>\n      <priority>%.1f</priority>\n   </url>\n",
                htmlspecialchars($fullLoc, ENT_XML1),
                $lastmod,
                $changefreq,
                $priority
            );
        };

        $xml .= $addUrl('', $uniDate, 'monthly', 0.6);

        $xml .= $addUrl('calendar', $calendarDate, 'weekly', 0.7);
        $xml .= $addUrl('calendar/bohosluzby', $calendarDate, 'weekly', 0.8);

        $xml .= $addUrl('news', $newsDate, 'weekly', 0.7);
        $xml .= $addUrl('news/bartik', $newsDate, 'monthly', 0.5);
        $xml .= $addUrl('news/vestnik', $newsDate, 'monthly', 0.2);

        $xml .= $addUrl('privacy', $policyDate, 'yearly', 0.1);
        $xml .= $addUrl('terms', $policyDate, 'yearly', 0.1);

        $xml .= $addUrl('about',        $staticContentDate, 'yearly', 0.2);

        $xml .= $addUrl('mista',        $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('katedrala',    $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('katedrala/oltar',       $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('katedrala/prohlidky',   $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('katedrala/vez',         $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('katedrala/zvony',       $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('npm',                   $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('npm/oltar',             $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('npm/zvony',             $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('fara',                  $staticContentDate, 'yearly', 0.2);
        $xml .= $addUrl('fara/knihovna',         $staticContentDate, 'yearly', 0.2);

        $xml .= '</urlset>' . "\n";

        $this->httpResponse->setContentType('application/xml');
        $this->sendResponse(new TextResponse($xml));
    }


}