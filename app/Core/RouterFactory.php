<?php

declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
    use Nette\StaticClass;

    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        # sing part
        $signRouter = new RouteList('Sign');
        $signRouter->addRoute('login', 'Auth:in');
        $signRouter->addRoute('out', 'Auth:out');
        $signRouter->addRoute('register', 'Register:new');
        $signRouter->addRoute('privacy', 'Policy:privacy');
        $signRouter->addRoute('terms', 'Policy:terms');
        $signRouter->addRoute('password/request', 'Password:lost');
        $signRouter->addRoute('password/reset/<token>', 'Password:reset');
        $signRouter->addRoute('password/change', 'Password:password');
        $signRouter->addRoute('google/login', 'Auth:googleLogin');
        $signRouter->addRoute('google/callback', 'Auth:googleCallback');
        $router[] = $signRouter;

        # front part
        $frontRouter = new RouteList('Front');

        $frontRouter->addRoute('about', 'Home:about');

        $frontRouter->addRoute('calendar', 'Calendar:default');
        $frontRouter->addRoute('calendar/bohosluzby', 'Calendar:bohosluzby');
        $frontRouter->addRoute('calendar/detail/<id \d+>', 'Calendar:detail');

        $frontRouter->addRoute('news', 'News:default');
        $frontRouter->addRoute('news/bartik', 'News:bartik');
        $frontRouter->addRoute('news/vestnik', 'News:vestnik');
        $frontRouter->addRoute('news/detail/<id \d+>', 'News:detail');

        $frontRouter->addRoute('mista', 'Place:default');
        $frontRouter->addRoute('katedrala', 'Place:katedrala');
        $frontRouter->addRoute('katedrala/oltar', 'Place:katedralaoltar');
        $frontRouter->addRoute('katedrala/prohlidky', 'Place:katedralaprohlidky');
        $frontRouter->addRoute('katedrala/vez', 'Place:katedralavez');
        $frontRouter->addRoute('katedrala/zvony', 'Place:katedralazvony');
        $frontRouter->addRoute('npm', 'Place:npm');
        $frontRouter->addRoute('npm/oltar', 'Place:npmoltar');
        $frontRouter->addRoute('npm/zvony', 'Place:npmzvony');
        $frontRouter->addRoute('zvon/<bellId \d+>', 'Place:zvon');
        $frontRouter->addRoute('fara', 'Place:fara');
        $frontRouter->addRoute('fara/knihovna', 'Place:faraknihovna');

        $frontRouter->addRoute('ministranti', 'Ministrans:default');

        $router[] = $frontRouter;

        # admin part
        $adminRouter = new RouteList('Admin');

        $router[] = $adminRouter;

        # dashboard part
        $dashboardRouter = new RouteList('Dashboard');
        $dashboardRouter->addRoute('ticket', 'Ticket:newticket');
        $router[] = $dashboardRouter;


        # export part
        $exportRouter = new RouteList('Export');
        $exportRouter->addRoute('sitemap.xml', 'Sitemap:sitemap');
        $exportRouter->addRoute('sitemap_index.xml', 'Sitemap:sitemapIndex');
        $exportRouter->addRoute('uni.json', 'Sitemap:uniData');
        $router[] = $exportRouter;

        $router->addRoute('<presenter>/<action>[/<id>]', 'Front:Home:default');

        return $router;
    }

}